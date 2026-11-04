<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerProductResource;
use App\Models\OptionsGroup;
use App\Models\Product;
use App\Models\ProductSizeTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ManagerProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'is_available' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['variants', 'optionGroups.optionGroup.options'])
            ->when(isset($validated['category_id']), fn (Builder $query) => $query->where('category_id', $validated['category_id']))
            ->when(isset($validated['is_available']), fn (Builder $query) => $query->where('is_available', $validated['is_available']))
            ->when(isset($validated['search']), function (Builder $query) use ($validated): void {
                $search = $validated['search'];
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return ManagerProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedProduct($request, creating: true);
        $image = $validated['image'] ?? null;
        $newImagePath = $image instanceof UploadedFile ? $this->storeProductImage($image) : null;

        try {
            $product = DB::transaction(function () use ($validated, $newImagePath): Product {
                $productData = Arr::except($validated, [
                    'sizes',
                    'size_templates',
                    'size_template_ids',
                    'default_size_template_id',
                    'option_groups',
                    'option_group_ids',
                    'image',
                ]);
                if ($newImagePath !== null) {
                    $productData['image_url'] = $newImagePath;
                }

                $product = Product::create($productData);
                if (array_key_exists('size_templates', $validated)) {
                    $this->createSelectedSizeTemplates($product, $validated['size_templates']);
                } elseif (array_key_exists('size_template_ids', $validated)) {
                    $this->createSizeTemplates(
                        $product,
                        $validated['size_template_ids'],
                        $validated['default_size_template_id'] ?? null,
                    );
                } else {
                    $this->createSizes($product, $validated['sizes'] ?? []);
                }

                if (array_key_exists('option_group_ids', $validated)) {
                    $this->attachOptionGroups($product, $validated['option_group_ids']);
                } else {
                    $this->createOptionGroups($product, $validated['option_groups'] ?? []);
                }

                return $this->loadProduct($product);
            });
        } catch (Throwable $exception) {
            $this->cleanupNewImageAfterFailure($newImagePath, $exception);

            throw $exception;
        }

        return (new ManagerProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ManagerProductResource
    {
        return new ManagerProductResource($this->loadProduct($product));
    }

    public function update(Request $request, Product $product): ManagerProductResource
    {
        $validated = $this->validatedProduct($request, creating: false);
        $image = $validated['image'] ?? null;
        $newImagePath = $image instanceof UploadedFile ? $this->storeProductImage($image) : null;
        $previousImagePath = $product->image_url;
        $imageUrlWasUpdated = isset($validated['image_url']);

        try {
            $product = DB::transaction(function () use ($product, $validated, $newImagePath): Product {
                $productData = Arr::except($validated, [
                    'sizes',
                    'size_templates',
                    'size_template_ids',
                    'default_size_template_id',
                    'option_groups',
                    'option_group_ids',
                    'image',
                ]);
                if (($productData['image_url'] ?? null) === null) {
                    unset($productData['image_url']);
                }
                if ($newImagePath !== null) {
                    $productData['image_url'] = $newImagePath;
                }
                $product->update($productData);

                if (array_key_exists('sizes', $validated)
                    || array_key_exists('size_templates', $validated)
                    || array_key_exists('size_template_ids', $validated)) {
                    $product->variants()->delete();

                    if (array_key_exists('size_templates', $validated)) {
                        $this->createSelectedSizeTemplates($product, $validated['size_templates']);
                    } elseif (array_key_exists('size_template_ids', $validated)) {
                        $this->createSizeTemplates(
                            $product,
                            $validated['size_template_ids'],
                            $validated['default_size_template_id'] ?? null,
                        );
                    } else {
                        $this->createSizes($product, $validated['sizes']);
                    }
                }

                if (array_key_exists('option_group_ids', $validated)) {
                    $this->attachOptionGroups($product, $validated['option_group_ids']);
                } elseif (array_key_exists('option_groups', $validated)) {
                    $this->replaceOptionGroups($product, $validated['option_groups']);
                }

                return $this->loadProduct($product->refresh());
            });
        } catch (Throwable $exception) {
            $this->cleanupNewImageAfterFailure($newImagePath, $exception);

            throw $exception;
        }

        if ($newImagePath !== null || $imageUrlWasUpdated) {
            $this->deleteManagedProductImage($previousImagePath);
        }

        return new ManagerProductResource($product);
    }

    public function uploadImage(Request $request, Product $product): ManagerProductResource
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $newImagePath = $this->storeProductImage($validated['image']);
        $previousImagePath = $product->image_url;

        try {
            $product->update(['image_url' => $newImagePath]);
        } catch (Throwable $exception) {
            $this->cleanupNewImageAfterFailure($newImagePath, $exception);

            throw $exception;
        }

        $this->deleteManagedProductImage($previousImagePath);

        return new ManagerProductResource($this->loadProduct($product->refresh()));
    }

    public function deleteImage(Product $product): JsonResponse
    {
        $previousImagePath = $product->image_url;
        $product->update(['image_url' => null]);
        $this->deleteManagedProductImage($previousImagePath);

        return response()->json([
            'data' => [
                'id' => (string) $product->id,
                'image_url' => null,
            ],
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $imagePath = $product->image_url;

        DB::transaction(function () use ($product): void {
            $groupIds = $product->optionGroups()->pluck('option_group_id');
            DB::table('order_items')
                ->where('product_id', $product->id)
                ->update(['product_id' => null, 'variant_id' => null]);
            DB::table('offer_items')->where('product_id', $product->id)->delete();
            $product->optionGroups()->delete();
            $product->variants()->delete();
            $this->deleteUnusedOptionGroups($groupIds->all());
            $product->delete();
        });

        $this->deleteManagedProductImage($imagePath);

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $rules = [
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'name_ar' => [$required, 'string', 'max:150'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description_ar' => ['sometimes', 'nullable', 'string'],
            'description_en' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'url', 'starts_with:http://,https://', 'max:255'],
            'image' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'base_price' => [$required, 'numeric', 'min:0', 'max:999999.99'],
            'has_discount' => ['sometimes', 'boolean'],
            'discount_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discounted_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_best_seller' => ['sometimes', 'boolean'],
            'badge_text_ar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'sizes' => ['sometimes', 'array', 'max:5'],
            'sizes.*.size_key' => ['required', 'string', Rule::in(['small', 'medium', 'large', 'jumbo', 'family']), 'distinct'],
            'sizes.*.name_ar' => ['required', 'string', 'max:50'],
            'sizes.*.name_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sizes.*.piece_count' => ['required', 'integer', 'min:1'],
            'sizes.*.portion_description_ar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sizes.*.portion_description_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sizes.*.price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sizes.*.is_default' => ['sometimes', 'boolean'],
            'size_templates' => ['sometimes', 'array', 'max:5'],
            'size_templates.*.template_id' => ['required', 'integer', 'distinct', 'exists:product_size_templates,id'],
            'size_templates.*.price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'size_templates.*.is_default' => ['sometimes', 'boolean'],
            'size_template_ids' => ['sometimes', 'array', 'max:5'],
            'size_template_ids.*' => ['integer', 'distinct', 'exists:product_size_templates,id'],
            'default_size_template_id' => ['sometimes', 'nullable', 'integer', 'exists:product_size_templates,id'],
            'option_groups' => ['sometimes', 'array', 'max:20'],
            'option_groups.*.name_ar' => ['required', 'string', 'max:100'],
            'option_groups.*.name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'option_groups.*.type' => ['required', Rule::in(['single', 'multiple'])],
            'option_groups.*.option_category' => ['required', Rule::in(['flavor', 'add_on'])],
            'option_groups.*.is_required' => ['sometimes', 'boolean'],
            'option_groups.*.options' => ['required', 'array', 'min:1', 'max:50'],
            'option_groups.*.options.*.name_ar' => ['required', 'string', 'max:100'],
            'option_groups.*.options.*.name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'option_groups.*.options.*.description_ar' => ['sometimes', 'nullable', 'string'],
            'option_groups.*.options.*.description_en' => ['sometimes', 'nullable', 'string'],
            'option_groups.*.options.*.price' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'option_groups.*.options.*.is_available' => ['sometimes', 'boolean'],
            'option_group_ids' => ['sometimes', 'array', 'max:20'],
            'option_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('options_groups', 'id')->where('is_reusable', true),
            ],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $input = $request->all();
            $providedSizeModes = collect(['sizes', 'size_templates', 'size_template_ids'])
                ->filter(fn (string $field): bool => array_key_exists($field, $input));
            if ($providedSizeModes->count() > 1) {
                $validator->errors()->add('sizes', 'Provide only one of sizes, size_templates, or size_template_ids.');
            }

            if (array_key_exists('option_groups', $input) && array_key_exists('option_group_ids', $input)) {
                $validator->errors()->add('option_groups', 'Provide option_groups or option_group_ids, not both.');
            }

            if (($input['default_size_template_id'] ?? null) !== null
                && array_key_exists('size_templates', $input)) {
                $validator->errors()->add('default_size_template_id', 'Use is_default on size_templates instead.');
            }

            if (($input['default_size_template_id'] ?? null) !== null
                && ! in_array($input['default_size_template_id'], $input['size_template_ids'] ?? [], false)) {
                $validator->errors()->add('default_size_template_id', 'The default size must be included in size_template_ids.');
            }

            if (array_key_exists('size_templates', $input)) {
                $templateIds = collect($input['size_templates'])
                    ->pluck('template_id')
                    ->all();
                $sizeKeys = ProductSizeTemplate::query()
                    ->whereIn('id', $templateIds)
                    ->pluck('size_key');

                if ($sizeKeys->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('size_templates', 'Selected size templates must have different size keys.');
                }

                $defaultSizeCount = collect($input['size_templates'])
                    ->filter(fn (array $size): bool => (bool) ($size['is_default'] ?? false))
                    ->count();

                if ($defaultSizeCount > 1) {
                    $validator->errors()->add('size_templates', 'Only one selected size may be marked as default.');
                }
            }

            if (array_key_exists('size_template_ids', $input)) {
                $sizeKeys = ProductSizeTemplate::query()
                    ->whereIn('id', $input['size_template_ids'])
                    ->pluck('size_key');

                if ($sizeKeys->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('size_template_ids', 'Selected size templates must have different size keys.');
                }
            }

            if ($request->hasFile('image') && $request->exists('image_url')) {
                $validator->errors()->add('image', 'Provide either an image upload or image_url, not both.');
            }

            $defaultSizes = collect($request->input('sizes', []))
                ->filter(fn (array $size): bool => (bool) ($size['is_default'] ?? false))
                ->count();

            if ($defaultSizes > 1) {
                $validator->errors()->add('sizes', 'Only one size can be marked as the default.');
            }
        });

        return $validator->validate();
    }

    private function storeProductImage(UploadedFile $image): string
    {
        $path = $image->store('products', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The product image could not be stored.');
        }

        return $path;
    }

    private function cleanupNewImageAfterFailure(?string $path, Throwable $exception): void
    {
        if ($path === null) {
            return;
        }

        try {
            $this->deleteManagedProductImage($path);
        } catch (RuntimeException $cleanupException) {
            Log::error('Failed to clean up a product image after a database failure.', [
                'path' => $path,
                'exception' => $cleanupException,
                'original_exception' => $exception,
            ]);
        }
    }

    private function deleteManagedProductImage(?string $path): void
    {
        if (! $path || ! Str::startsWith($path, 'products/')) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('The product image could not be deleted from storage.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sizes
     */
    private function createSizes(Product $product, array $sizes): void
    {
        foreach ($sizes as $size) {
            $product->variants()->create($size);
        }
    }

    /**
     * @param  list<int|string>  $templateIds
     */
    private function createSizeTemplates(Product $product, array $templateIds, int|string|null $defaultTemplateId): void
    {
        $templates = ProductSizeTemplate::query()
            ->whereIn('id', $templateIds)
            ->get()
            ->keyBy(fn (ProductSizeTemplate $template): string => (string) $template->id);

        foreach ($templateIds as $templateId) {
            $template = $templates->get((string) $templateId);
            if ($template === null) {
                continue;
            }

            $product->variants()->create([
                'size_template_id' => $template->id,
                'size_key' => $template->size_key,
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'piece_count' => $template->piece_count,
                'portion_description_ar' => $template->portion_description_ar,
                'portion_description_en' => $template->portion_description_en,
                'price' => $template->price,
                'is_default' => (string) $template->id === (string) $defaultTemplateId,
            ]);
        }
    }

    /**
     * @param  list<array{template_id: int|string, price: int|float|string, is_default?: bool}>  $selectedSizes
     */
    private function createSelectedSizeTemplates(Product $product, array $selectedSizes): void
    {
        $templates = ProductSizeTemplate::query()
            ->whereIn('id', collect($selectedSizes)->pluck('template_id'))
            ->get()
            ->keyBy(fn (ProductSizeTemplate $template): string => (string) $template->id);

        foreach ($selectedSizes as $selectedSize) {
            $template = $templates->get((string) $selectedSize['template_id']);
            if ($template === null) {
                continue;
            }

            $product->variants()->create([
                'size_template_id' => $template->id,
                'size_key' => $template->size_key,
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'piece_count' => $template->piece_count,
                'portion_description_ar' => $template->portion_description_ar,
                'portion_description_en' => $template->portion_description_en,
                'price' => $selectedSize['price'],
                'is_default' => (bool) ($selectedSize['is_default'] ?? false),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function createOptionGroups(Product $product, array $groups): void
    {
        foreach ($groups as $groupData) {
            $options = $groupData['options'];
            $group = OptionsGroup::create([
                ...Arr::except($groupData, ['options']),
                'is_reusable' => false,
            ]);
            $group->options()->createMany($options);
            $product->optionGroups()->create(['option_group_id' => $group->id]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function replaceOptionGroups(Product $product, array $groups): void
    {
        $oldGroupIds = $product->optionGroups()->pluck('option_group_id')->all();
        $product->optionGroups()->delete();
        $this->deleteUnusedOptionGroups($oldGroupIds);
        $this->createOptionGroups($product, $groups);
    }

    /**
     * @param  list<int|string>  $groupIds
     */
    private function attachOptionGroups(Product $product, array $groupIds): void
    {
        $oldGroupIds = $product->optionGroups()->pluck('option_group_id')->all();
        $product->optionGroups()->delete();
        $this->deleteUnusedOptionGroups($oldGroupIds);

        foreach ($groupIds as $groupId) {
            $product->optionGroups()->create(['option_group_id' => $groupId]);
        }
    }

    /**
     * @param  list<int|string>  $groupIds
     */
    private function deleteUnusedOptionGroups(array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        OptionsGroup::query()
            ->whereIn('id', $groupIds)
            ->where('is_reusable', false)
            ->whereDoesntHave('productRelations')
            ->delete();
    }

    private function loadProduct(Product $product): Product
    {
        return $product->load([
            'variants' => fn ($query) => $query->orderBy('id'),
            'optionGroups.optionGroup.options' => fn ($query) => $query->orderBy('id'),
        ]);
    }
}
