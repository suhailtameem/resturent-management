<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCity;
use App\Models\StoreOperation;
use App\Models\StoreSettings;
use Illuminate\Http\JsonResponse;

class CustomerStoreSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = StoreSettings::current();
        $operation = StoreOperation::current()->refreshExpiredPause();
        $cities = DeliveryCity::query()
            ->with(['neighborhoods' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (DeliveryCity $city): array => [
                'id' => (string) $city->id,
                'name' => $city->name,
                'service_status' => $city->service_status,
                'neighborhoods' => $city->neighborhoods->map(fn ($neighborhood): array => [
                    'id' => (string) $neighborhood->id,
                    'name' => $neighborhood->name,
                    'delivery_fee' => (float) $neighborhood->delivery_fee,
                    'minimum_order_amount' => (float) $neighborhood->minimum_order_amount,
                    'is_active' => $neighborhood->is_active,
                ])->values(),
            ]);

        return response()->json([
            'data' => [
                'store_name_ar' => $settings->store_name_ar,
                'store_name_en' => $settings->store_name_en,
                'address' => $settings->address,
                'support_phone' => $settings->support_phone,
                ...$settings->currencyData(),
                'payment_methods' => [
                    'cash_on_delivery_enabled' => $settings->cash_on_delivery_enabled,
                    'online_enabled' => $settings->online_enabled,
                ],
                'auto_accept_prepaid_orders' => $settings->auto_accept_prepaid_orders,
                'is_open' => $operation->is_open,
                'paused_until' => $operation->paused_until?->copy()->utc()->toIso8601String(),
                'estimated_prep_time_minutes' => $operation->estimatedPrepTime(),
                'delivery_cities' => $cities,
            ],
        ]);
    }
}
