<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreCurrencySettingsRequest;
use App\Http\Requests\UpdateStoreProfileRequest;
use App\Http\Resources\ManagerStoreSettingsResource;
use App\Models\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ManagerStoreSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return (new ManagerStoreSettingsResource(StoreSettings::current()))
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateStoreProfileRequest $request): ManagerStoreSettingsResource
    {
        $settings = StoreSettings::current();
        $validated = $request->validated();
        $paymentMethods = $validated['payment_methods'] ?? [];
        $cashOnDeliveryEnabled = $paymentMethods['cash_on_delivery_enabled']
            ?? $settings->cash_on_delivery_enabled;
        $onlineEnabled = $paymentMethods['online_enabled'] ?? $settings->online_enabled;

        if (! $cashOnDeliveryEnabled && ! $onlineEnabled) {
            throw ValidationException::withMessages([
                'payment_methods' => ['At least one payment method must remain enabled.'],
            ]);
        }

        $attributes = collect($validated)->except(['payment_methods', 'currency_code'])->all();
        foreach ($paymentMethods as $key => $value) {
            $attributes[$key] = $value;
        }

        $settings->update($attributes);

        return new ManagerStoreSettingsResource($settings->fresh());
    }

    public function showCurrency(): JsonResponse
    {
        return response()->json([
            'data' => StoreSettings::current()->currencyData(),
        ]);
    }

    public function updateCurrency(UpdateStoreCurrencySettingsRequest $request): JsonResponse
    {
        $settings = StoreSettings::current();
        $settings->update($request->validated());

        return response()->json([
            'data' => $settings->refresh()->currencyData(),
        ]);
    }
}
