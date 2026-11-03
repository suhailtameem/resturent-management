<?php

namespace App\Http\Resources;

use App\Models\StoreSettings;
use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = StoreSettings::current();

        $deliveryAddress = null;
        if ($this->delivery_mode === 'delivery' && $this->relationLoaded('deliveryAddress') && $this->deliveryAddress) {
            $address = $this->deliveryAddress;
            $deliveryAddress = [
                'id' => (string) $address->id,
                'title_ar' => $address->title_ar,
                'title_en' => $address->title_en,
                'city_ar' => $address->city_ar,
                'city_en' => $address->city_en,
                'district_ar' => $address->district_ar,
                'district_en' => $address->district_en,
                'street_details' => $address->street_details_ar,
                'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
                'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
                'notes' => $address->notes,
            ];
        }

        return [
            'id' => (string) $this->id,
            'order_number' => $this->order_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'status' => $this->order_status,
            'status_label_ar' => $this->statusLabelAr(),
            'status_label_en' => $this->statusLabelEn(),
            'can_cancel' => $this->canBeCancelled(),
            'delivery_mode' => $this->delivery_mode,
            'delivery_mode_label_ar' => $this->delivery_mode === 'delivery' ? 'توصيل' : 'استلام من الفرع',
            'delivery_mode_label_en' => $this->delivery_mode === 'delivery' ? 'Home Delivery' : 'Store Pickup',
            'payment_method' => $this->payment_method,
            'payment_method_label_ar' => $this->payment_method === 'cod' ? 'الدفع عند الاستلام' : 'دفع إلكتروني',
            'payment_method_label_en' => $this->payment_method === 'cod' ? 'Cash on Delivery' : 'Online Payment',
            'payment_status' => $this->payment_status,
            'payment_status_label_ar' => match ($this->payment_status) {
                'paid' => 'تم الدفع',
                'failed' => 'فشل الدفع',
                'refunded' => 'مسترجع',
                default => 'معلق',
            },
            'payment_status_label_en' => match ($this->payment_status) {
                'paid' => 'Paid',
                'failed' => 'Failed',
                'refunded' => 'Refunded',
                default => 'Pending',
            },
            'delivery_address' => $deliveryAddress,
            'pickup_store_details' => $this->delivery_mode === 'pickup' ? [
                'store_name_ar' => $settings->store_name_ar,
                'store_name_en' => $settings->store_name_en,
                'address' => $settings->address,
                'support_phone' => $settings->support_phone,
            ] : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => (string) $item->id,
                'product_id' => (string) $item->product_id,
                'product_name_ar' => $item->product_name_ar_snapshot,
                'product_name_en' => $item->product_name_en_snapshot,
                'image_url' => ProductImageUrl::fromStoredValue($item->image_url_snapshot),
                'size_name_ar' => $item->size_name_ar_snapshot,
                'size_name_en' => $item->size_name_en_snapshot,
                'piece_count' => $item->piece_count_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'special_instructions' => $item->special_instructions,
                'options' => $item->options->map(fn ($option) => [
                    'id' => $option->option_id === null ? null : (string) $option->option_id,
                    'name_ar' => $option->option_name_ar_snapshot,
                    'name_en' => $option->option_name_en_snapshot,
                    'price' => (float) $option->option_price_snapshot,
                ])->values(),
            ])->values()),
            'subtotal' => (float) $this->subtotal_amount,
            'product_discount' => (float) $this->product_discount_amount,
            'coupon_discount' => (float) $this->coupon_discount_amount,
            'coupon_code' => $this->coupon?->code
                ?? ($this->relationLoaded('couponUsages') ? $this->couponUsages->first()?->couponConfig?->code : null),
            'delivery_fee' => (float) $this->delivery_fee,
            'tax_amount' => (float) $this->vat_tax_amount,
            'total' => (float) $this->total_final_amount,
            'currency' => 'SAR',
            'currency_label_ar' => $settings->currency_label_ar,
            'currency_label_en' => $settings->currency_label_en,
            'special_notes' => $this->chef_special_notes,
            'estimated_prep_time_minutes' => $this->estimated_prep_time_minutes,
            'status_timeline' => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->map(fn ($log) => [
                'status' => $log->to_status,
                'status_label_ar' => $log->statusLabelAr(),
                'status_label_en' => $log->statusLabelEn(),
                'notes' => $log->notes,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
