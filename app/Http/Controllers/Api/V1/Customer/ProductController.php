<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\CustomerApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'featured' => ['sometimes', 'boolean'],
            'best_seller' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $products = Product::query()
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->with(['variants', 'optionGroups.optionGroup.options'])
            ->when(isset($validated['category_id']), fn (Builder $query) => $query->where('category_id', $validated['category_id']))
            ->when(isset($validated['featured']), fn (Builder $query) => $query->where('is_featured', $validated['featured']))
            ->when(isset($validated['best_seller']), fn (Builder $query) => $query->where('is_best_seller', $validated['best_seller']))
            ->when(isset($validated['search']), function (Builder $query) use ($validated): void {
                $search = $validated['search'];
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('description_ar', 'like', "%{$search}%")
                        ->orWhere('description_en', 'like', "%{$search}%");
                });
            })
            ->orderBy(Category::query()
                ->select('sort_order')
                ->whereColumn('categories.id', 'products.category_id'))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        if (! $product->is_available || ! $product->category()->where('is_active', true)->exists()) {
            throw new CustomerApiException(
                'The selected product is unavailable.',
                'PRODUCT_UNAVAILABLE',
                404,
                ['product' => ['The selected product is unavailable.']],
            );
        }

        $product->load(['variants', 'optionGroups.optionGroup.options']);

        return new ProductResource($product);
    }
}
