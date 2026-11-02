<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductImageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class MenuPageController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $query) => $query->where('is_available', true))
            ->with(['products' => function (HasMany $query): void {
                $query->where('is_available', true)
                    ->with(['variants', 'optionGroups.optionGroup.options'])
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $categories->each(function (Category $category): void {
            $category->products->each(function (Product $product): void {
                $product->setAttribute('menu_image_url', ProductImageUrl::fromStoredValue($product->image_url));

                $product->variants->each(function ($variant) use ($product): void {
                    $variant->setAttribute('menu_price', (float) ($product->has_discount
                        ? round((float) $variant->price * (100 - min(100, max(0, (float) $product->discount_percentage))) / 100, 2)
                        : $variant->price));
                });

                $sizePrices = $product->variants->pluck('menu_price');

                $startingPrice = $sizePrices->isNotEmpty()
                    ? $sizePrices->min()
                    : ($product->has_discount && (float) $product->discounted_price > 0
                        ? min((float) $product->base_price, (float) $product->discounted_price)
                        : (float) $product->base_price);

                $product->setAttribute('menu_starting_price', $startingPrice);
            });
        });

        return view('menu.index', compact('categories'));
    }
}
