<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerDeliveryNeighborhoodResource;
use App\Models\DeliveryCity;
use App\Models\DeliveryNeighborhood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerDeliveryNeighborhoodController extends Controller
{
    public function store(Request $request, DeliveryCity $city): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:100',
                Rule::unique('delivery_neighborhoods', 'name')->where('delivery_city_id', $city->id),
            ],
            'delivery_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'minimum_order_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);
        $neighborhood = $city->neighborhoods()->create($validated);

        return (new ManagerDeliveryNeighborhoodResource($neighborhood))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, DeliveryNeighborhood $neighborhood): ManagerDeliveryNeighborhoodResource
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:100',
                Rule::unique('delivery_neighborhoods', 'name')
                    ->where('delivery_city_id', $neighborhood->delivery_city_id)
                    ->ignore($neighborhood->id),
            ],
            'delivery_fee' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'minimum_order_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);
        if ($validated === []) {
            throw ValidationException::withMessages([
                'neighborhood' => ['Provide at least one neighborhood field to update.'],
            ]);
        }

        $neighborhood->update($validated);

        return new ManagerDeliveryNeighborhoodResource($neighborhood->refresh());
    }
}
