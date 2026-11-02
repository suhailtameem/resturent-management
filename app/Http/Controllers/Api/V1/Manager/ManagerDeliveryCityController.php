<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerDeliveryCityResource;
use App\Models\DeliveryCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ManagerDeliveryCityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ManagerDeliveryCityResource::collection(
            DeliveryCity::query()
                ->with(['neighborhoods' => fn ($query) => $query->orderBy('name')->orderBy('id')])
                ->orderBy('name')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100', 'unique:delivery_cities,name'],
            'service_status' => ['sometimes', Rule::in(['active', 'coming_soon', 'preorder_only'])],
        ]);

        $city = DeliveryCity::create([
            'name' => $validated['name'],
            'service_status' => $validated['service_status'] ?? 'active',
        ]);

        return (new ManagerDeliveryCityResource($city->load('neighborhoods')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, DeliveryCity $city): ManagerDeliveryCityResource
    {
        $validated = $request->validate([
            'service_status' => ['required', Rule::in(['active', 'coming_soon', 'preorder_only'])],
        ]);
        $city->update($validated);

        return new ManagerDeliveryCityResource($city->load('neighborhoods'));
    }
}
