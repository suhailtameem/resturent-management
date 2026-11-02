<?php

namespace App\Http\Resources;

use App\Models\CustomerAddress;
use App\Services\ManagerCustomerMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerCustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metrics = $this->getAttribute('manager_metrics') ?? [];
        $isVip = (bool) $this->is_vip;
        $isNew = $this->created_at?->greaterThanOrEqualTo(now()->subDays(30)) ?? false;
        $isRecentlyActive = (bool) ($metrics['is_recently_active'] ?? false);
        $segments = [];
        if ($isVip) {
            $segments[] = 'vip';
        }
        if (($metrics['completed_orders_count'] ?? 0) >= ManagerCustomerMetrics::FREQUENT_ORDER_THRESHOLD) {
            $segments[] = 'frequent';
        }
        if ($isNew) {
            $segments[] = 'new';
        }
        if (! $isRecentlyActive) {
            $segments[] = 'inactive';
        }

        /** @var Collection<int, CustomerAddress>|null $addresses */
        $addresses = $this->resource->relationLoaded('addresses') ? $this->addresses : null;
        $primaryAddress = $addresses?->first();

        return [
            'id' => (int) $this->id,
            'full_name' => $this->full_name ?: $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'is_vip' => $isVip,
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'is_phone_verified' => $this->phone_verified_at !== null,
            'manager_verified_at' => $this->manager_verified_at?->toIso8601String(),
            'manager_verified_by_staff_user_id' => $this->manager_verified_by_staff_user_id,
            'is_manager_verified' => $this->manager_verified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'segments' => $segments,
            'primary_address' => $primaryAddress ? [
                'city' => $primaryAddress->city_ar,
                'district' => $primaryAddress->district_ar,
                'street_details' => $primaryAddress->street_details_ar,
            ] : null,
            'addresses' => $this->whenLoaded('addresses', fn (): array => $addresses
                ->map(fn (CustomerAddress $address): array => [
                    'id' => (int) $address->id,
                    'title_ar' => $address->title_ar,
                    'title_en' => $address->title_en,
                    'city_ar' => $address->city_ar,
                    'city_en' => $address->city_en,
                    'district_ar' => $address->district_ar,
                    'district_en' => $address->district_en,
                    'street_details_ar' => $address->street_details_ar,
                    'street_details_en' => $address->street_details_en,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                    'notes' => $address->notes,
                    'is_default' => (bool) $address->is_default,
                ])
                ->all()),
            'total_orders_count' => (int) ($metrics['completed_orders_count'] ?? 0),
            'total_spent_amount' => $metrics['completed_spend'] ?? '0.00',
            'currency' => 'SAR',
            'notification_device_count' => (int) ($this->notification_device_count ?? 0),
            'favorite_item' => $metrics['favorite_item'] ?? null,
            'last_order_at' => ! empty($metrics['last_order_at'])
                ? CarbonImmutable::parse($metrics['last_order_at'])->toIso8601String()
                : null,
        ];
    }
}
