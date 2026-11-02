<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerProductSizeResource;
use App\Models\ProductSizeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagerProductSizeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ManagerProductSizeResource::collection(
            ProductSizeTemplate::query()
                ->withCount('variants')
                ->orderBy('size_key')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $size = ProductSizeTemplate::create($request->validate($this->rules()));

        return (new ManagerProductSizeResource($size))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductSizeTemplate $size): ManagerProductSizeResource
    {
        return new ManagerProductSizeResource($size->loadCount('variants'));
    }

    public function update(Request $request, ProductSizeTemplate $size): ManagerProductSizeResource
    {
        $validated = $request->validate($this->rules(updating: true));
        $sizeAttributes = [
            'size_key',
            'name_ar',
            'name_en',
            'piece_count',
            'portion_description_ar',
            'portion_description_en',
        ];

        DB::transaction(function () use ($size, $validated, $sizeAttributes): void {
            $size->update($validated);
            $sharedDetails = collect($validated)->only($sizeAttributes)->all();

            if ($sharedDetails !== []) {
                $size->variants()->update($sharedDetails);
            }
        });

        return new ManagerProductSizeResource($size->refresh()->loadCount('variants'));
    }

    public function destroy(ProductSizeTemplate $size): JsonResponse
    {
        if ($size->variants()->exists()) {
            return response()->json([
                'message' => 'This reusable size cannot be deleted while products use it.',
                'code' => 'SIZE_TEMPLATE_IN_USE',
                'errors' => (object) [],
            ], 409);
        }

        $size->delete();

        return response()->json([], 204);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'size_key' => [$required, 'string', Rule::in(['small', 'medium', 'large', 'jumbo', 'family'])],
            'name_ar' => [$required, 'string', 'max:50'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            'piece_count' => [$required, 'integer', 'min:1'],
            'portion_description_ar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'portion_description_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price' => [$required, 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
