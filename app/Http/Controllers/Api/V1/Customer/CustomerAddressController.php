<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CustomerAddressResource::collection(
            $request->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request): CustomerAddressResource
    {
        $validated = $request->validate([
            'title_ar' => ['required', 'string', 'max:50'],
            'title_en' => ['nullable', 'string', 'max:50'],
            'city_ar' => ['required', 'string', 'max:50'],
            'city_en' => ['nullable', 'string', 'max:50'],
            'district_ar' => ['required', 'string', 'max:100'],
            'district_en' => ['nullable', 'string', 'max:100'],
            'street_details_ar' => ['nullable', 'string', 'max:255'],
            'street_details_en' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = DB::transaction(function () use ($request, $validated): CustomerAddress {
            if (($validated['is_default'] ?? false) || ! $request->user()->addresses()->exists()) {
                $request->user()->addresses()->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            return $request->user()->addresses()->create($validated);
        });

        return new CustomerAddressResource($address);
    }

    public function update(Request $request, CustomerAddress $address): CustomerAddressResource
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'title_ar' => ['sometimes', 'required', 'string', 'max:50'],
            'title_en' => ['nullable', 'string', 'max:50'],
            'city_ar' => ['sometimes', 'required', 'string', 'max:50'],
            'city_en' => ['nullable', 'string', 'max:50'],
            'district_ar' => ['sometimes', 'required', 'string', 'max:100'],
            'district_en' => ['nullable', 'string', 'max:100'],
            'street_details_ar' => ['nullable', 'string', 'max:255'],
            'street_details_en' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated, $address): void {
            if ($validated['is_default'] ?? false) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->fill($validated)->save();
        });

        return new CustomerAddressResource($address->refresh());
    }

    public function destroy(Request $request, CustomerAddress $address): Response
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $address->delete();

        return response()->noContent();
    }
}
