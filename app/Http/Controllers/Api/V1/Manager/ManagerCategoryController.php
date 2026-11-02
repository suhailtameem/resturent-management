<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ManagerCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ManagerCategoryResource::collection(
            Category::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate($validated['per_page'] ?? 20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('categories', 'slug')],
            'icon_code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return (new ManagerCategoryResource(Category::create($validated)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): ManagerCategoryResource
    {
        return new ManagerCategoryResource($category);
    }

    public function update(Request $request, Category $category): ManagerCategoryResource
    {
        $validated = $request->validate([
            'name_ar' => ['sometimes', 'required', 'string', 'max:100'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('categories', 'slug')->ignore($category->id)],
            'icon_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return new ManagerCategoryResource($category->refresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'This category cannot be deleted while it contains products.',
                'code' => 'CATEGORY_HAS_PRODUCTS',
                'errors' => (object) [],
            ], 409);
        }

        $category->delete();

        return response()->json([], 204);
    }
}
