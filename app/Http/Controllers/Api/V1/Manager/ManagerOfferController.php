<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerOfferDetailResource;
use App\Http\Resources\ManagerOfferListResource;
use App\Models\Offer;
use App\Models\OfferCouponConfig;
use App\Models\OfferItem;
use App\Models\OfferRedemptionLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class ManagerOfferController extends Controller
{
    /**
     * KPI summary for the offers dashboard.
     */
    public function stats(): JsonResponse
    {
        $now = Carbon::now();
        $today = Carbon::today('Asia/Riyadh');

        $activeCount = Offer::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->where('is_ongoing', true)
                    ->orWhere('ends_at', '>=', $now);
            })
            ->count();

        $scheduledCount = Offer::query()
            ->where('is_active', true)
            ->where('starts_at', '>', $now)
            ->count();

        $expiredCount = Offer::query()
            ->where(function ($q) use ($now) {
                $q->where('is_active', false)
                    ->orWhere(function ($sub) use ($now) {
                        $sub->where('is_ongoing', false)
                            ->where('ends_at', '<', $now);
                    });
            })
            ->count();

        $totalCount = Offer::query()->count();

        $totalDiscountToday = OfferRedemptionLog::query()
            ->whereDate('created_at', $today)
            ->sum('final_discount_given');

        return response()->json([
            'data' => [
                'active_offers_count' => $activeCount,
                'scheduled_offers_count' => $scheduledCount,
                'expired_offers_count' => $expiredCount,
                'total_offers_count' => $totalCount,
                'total_discounts_saved_today' => number_format((float) $totalDiscountToday, 2, '.', ''),
                'currency' => 'SAR',
                'unified_rules_enabled' => true,
            ],
        ]);
    }

    /**
     * List offers with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $now = Carbon::now();

        $query = Offer::query()
            ->with(['couponConfig', 'items.product', 'category.products']);

        // Status filter
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all') {
            match ($status) {
                'active' => $query
                    ->where('is_active', true)
                    ->where('starts_at', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->where('is_ongoing', true)
                            ->orWhere('ends_at', '>=', $now);
                    }),
                'scheduled' => $query
                    ->where('is_active', true)
                    ->where('starts_at', '>', $now),
                'expired' => $query
                    ->where(function ($q) use ($now) {
                        $q->where('is_active', false)
                            ->orWhere(function ($sub) use ($now) {
                                $sub->where('is_ongoing', false)
                                    ->where('ends_at', '<', $now);
                            });
                    }),
                default => null,
            };
        }

        // Type filter
        $type = (string) $request->query('type', 'all');
        if ($type !== 'all' && in_array($type, ['coupon', 'auto_discount'], true)) {
            $query->where('type', $type);
        }

        // Search
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('couponConfig', fn ($c) => $c->where('code', 'like', "%{$search}%"));
            });
        }

        $paginated = $query->orderByDesc('id')->paginate($perPage);

        return ManagerOfferListResource::collection($paginated);
    }

    /**
     * Show a single offer's full details.
     */
    public function show(int $id): JsonResponse
    {
        $offer = Offer::withTrashed()->with(['couponConfig', 'items'])->findOrFail($id);

        return response()->json([
            'data' => new ManagerOfferDetailResource($offer),
        ]);
    }

    /**
     * Create a new offer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());
        $imagePath = isset($validated['image']) ? $this->storeOfferImage($validated['image']) : null;

        try {
            $offer = DB::transaction(function () use ($validated, $imagePath): Offer {
                /** @var Offer $offer */
                $offer = Offer::create([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'image_path' => $imagePath,
                    'type' => $validated['type'],
                    'discount_scope' => $validated['discount_scope'],
                    'category_id' => $validated['category_id'] ?? null,
                    'discount_percentage' => $validated['discount_percentage'],
                    'max_discount_cap' => $validated['max_discount_cap'],
                    'min_order_amount' => $validated['min_order_amount'] ?? 0,
                    'is_active' => $validated['is_active'] ?? true,
                    'is_ongoing' => $validated['is_ongoing'] ?? true,
                    'starts_at' => $validated['starts_at'],
                    'ends_at' => $validated['ends_at'] ?? null,
                ]);

                // Create coupon config if type = coupon
                if ($validated['type'] === 'coupon' && isset($validated['coupon'])) {
                    OfferCouponConfig::create([
                        'offer_id' => $offer->id,
                        'code' => strtoupper((string) $validated['coupon']['code']),
                        'usage_limit_per_user' => $validated['coupon']['usage_limit_per_user'] ?? 'once',
                        'total_usage_limit' => $validated['coupon']['total_usage_limit'] ?? null,
                        'total_used_count' => 0,
                    ]);
                }

                // Sync offer items for specific_products scope
                if ($validated['discount_scope'] === 'specific_products' && ! empty($validated['product_ids'])) {
                    $this->syncOfferItems($offer, (array) $validated['product_ids']);
                }

                return $offer;
            });
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }

        $offer->load('couponConfig');
        $statusKey = $offer->getStatusKey();

        return response()->json([
            'data' => [
                'id' => (int) $offer->id,
                'title' => $offer->title,
                'image_url' => $offer->image_path ? Storage::disk('public')->url($offer->image_path) : null,
                'type' => $offer->type,
                'code' => $offer->couponConfig?->code,
                'discount_percentage' => (float) $offer->discount_percentage,
                'max_discount_cap' => number_format((float) $offer->max_discount_cap, 2, '.', ''),
                'status' => $statusKey,
                'status_label' => $offer->getStatusLabel(),
            ],
            'message' => 'تم إنشاء ونشر العرض الترويجي بنجاح',
        ], 201);
    }

    /**
     * Update an existing offer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $offer = Offer::with(['couponConfig', 'items'])->findOrFail($id);

        $rules = $this->validationRules($offer->id);

        // Make all top-level fields optional on update
        foreach ($rules as $key => $ruleSet) {
            $rules[$key] = array_map(
                fn ($r) => $r === 'required' ? 'sometimes' : $r,
                (array) $ruleSet,
            );
        }

        // Coupon code uniqueness must exclude current config
        if ($offer->couponConfig) {
            $rules['coupon.code'] = array_map(
                fn ($r) => $r instanceof Unique
                    ? Rule::unique('offer_coupon_configs', 'code')->ignore($offer->couponConfig->id)
                    : $r,
                (array) ($rules['coupon.code'] ?? []),
            );
        }

        $validated = $request->validate($rules);
        $newImagePath = isset($validated['image']) ? $this->storeOfferImage($validated['image']) : null;
        $previousImagePath = $offer->image_path;

        try {
            DB::transaction(function () use ($offer, $validated, $newImagePath): void {
                $offerFields = array_intersect_key($validated, array_flip([
                    'title', 'description', 'discount_scope', 'category_id',
                    'discount_percentage', 'max_discount_cap', 'min_order_amount',
                    'is_active', 'is_ongoing', 'starts_at', 'ends_at',
                ]));
                if ($newImagePath !== null) {
                    $offerFields['image_path'] = $newImagePath;
                }
                if (($validated['remove_image'] ?? false) === true) {
                    $offerFields['image_path'] = null;
                }

                if (! empty($offerFields)) {
                    $offer->update($offerFields);
                }

                // Update coupon config if provided
                if (isset($validated['coupon']) && $offer->couponConfig) {
                    $couponFields = [];
                    if (isset($validated['coupon']['code'])) {
                        $couponFields['code'] = strtoupper((string) $validated['coupon']['code']);
                    }
                    if (isset($validated['coupon']['usage_limit_per_user'])) {
                        $couponFields['usage_limit_per_user'] = $validated['coupon']['usage_limit_per_user'];
                    }
                    if (array_key_exists('total_usage_limit', $validated['coupon'] ?? [])) {
                        $couponFields['total_usage_limit'] = $validated['coupon']['total_usage_limit'];
                    }
                    if (! empty($couponFields)) {
                        $offer->couponConfig->update($couponFields);
                    }
                }

                // Re-sync offer items if product_ids provided
                if (isset($validated['product_ids'])) {
                    $this->syncOfferItems($offer, (array) $validated['product_ids']);
                }
            });
        } catch (\Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }
        if (($newImagePath !== null || ($validated['remove_image'] ?? false) === true) && $previousImagePath) {
            Storage::disk('public')->delete($previousImagePath);
        }

        $offer->load(['couponConfig', 'items']);

        return response()->json([
            'data' => new ManagerOfferDetailResource($offer),
            'message' => 'تم تحديث العرض الترويجي بنجاح',
        ]);
    }

    /**
     * Toggle the active status of an offer.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $offer->update(['is_active' => $validated['is_active']]);

        $statusKey = $offer->getStatusKey();

        $messageKey = $validated['is_active']
            ? 'تم تفعيل العرض الترويجي بنجاح'
            : 'تم تعطيل العرض الترويجي بنجاح';

        $statusLabel = $validated['is_active']
            ? $offer->getStatusLabel()
            : 'معطّل مؤقتاً';

        return response()->json([
            'data' => [
                'id' => (int) $offer->id,
                'is_active' => (bool) $offer->is_active,
                'status' => $statusKey,
                'status_label' => $statusLabel,
            ],
            'message' => $messageKey,
        ]);
    }

    /**
     * Delete or archive an offer.
     */
    public function destroy(int $id): JsonResponse
    {
        $offer = Offer::withTrashed()->findOrFail($id);

        $hasLogs = OfferRedemptionLog::where('offer_id', $offer->id)->exists();

        if ($hasLogs) {
            $offer->delete(); // Soft delete

            return response()->json([
                'message' => 'تم أرشفة العرض الترويجي لأنه يحتوي على سجلات استخدام',
            ]);
        }

        // Hard delete — remove related records manually to avoid FK constraint issues
        DB::transaction(function () use ($offer): void {
            $offer->items()->delete();
            $offer->couponConfig()?->delete();
            $offer->forceDelete();
        });

        return response()->json([
            'message' => 'تم حذف العرض الترويجي بنجاح',
        ]);
    }

    /**
     * Build validation rules for store/update.
     *
     * @return array<string, mixed>
     */
    private function validationRules(?int $excludeOfferId = null): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'type' => ['required', 'string', Rule::in(['coupon', 'auto_discount'])],
            'discount_scope' => ['required', 'string', Rule::in(['cart', 'specific_products', 'category'])],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'max_discount_cap' => ['required', 'numeric', 'gt:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_ongoing' => ['sometimes', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at', Rule::requiredIf(fn () => ! (bool) request()->input('is_ongoing', true))],
            'coupon' => ['sometimes', 'array'],
            'coupon.code' => [
                Rule::requiredIf(fn () => request()->input('type') === 'coupon'),
                'nullable',
                'string',
                'alpha_num',
                'min:3',
                'max:32',
                Rule::unique('offer_coupon_configs', 'code'),
            ],
            'coupon.usage_limit_per_user' => [
                Rule::requiredIf(fn () => request()->input('type') === 'coupon'),
                'nullable',
                'string',
                Rule::in(['once', 'twice', 'three_times', 'unlimited']),
            ],
            'coupon.total_usage_limit' => ['nullable', 'integer', 'min:1'],
            'product_ids' => [
                Rule::requiredIf(fn () => request()->input('discount_scope') === 'specific_products'),
                'nullable',
                'array',
            ],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_id' => [
                Rule::requiredIf(fn () => request()->input('discount_scope') === 'category'),
                'nullable',
                'integer',
                'exists:categories,id',
            ],
        ];
    }

    private function storeOfferImage(UploadedFile $image): string
    {
        $path = $image->store('offers', 'public');
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('The offer image could not be stored.');
        }

        return $path;
    }

    /**
     * Sync offer_items pivot for an offer with specific_products scope.
     *
     * @param  int[]  $productIds
     */
    private function syncOfferItems(Offer $offer, array $productIds): void
    {
        $offer->items()->delete();

        $items = array_map(
            fn (int $productId): array => [
                'offer_id' => $offer->id,
                'product_id' => $productId,
                'created_at' => now(),
            ],
            $productIds,
        );

        OfferItem::insert($items);
    }
}
