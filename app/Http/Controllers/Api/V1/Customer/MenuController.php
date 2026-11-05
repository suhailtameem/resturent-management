<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->with([
                'products' => function ($query) {
                    $query->where('is_available', true)
                        ->with('variants')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'variants', 'optionGroups.optionGroup.options']);

        return response()->json([
            'data' => $product,
        ]);
    }
}
