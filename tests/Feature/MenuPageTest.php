<?php

use App\Models\Category;
use App\Models\OptionsGroup;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionRelation;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows available products grouped under active menu categories', function () {
    $category = Category::create([
        'name_ar' => 'المعجنات',
        'name_en' => 'Pastries',
        'slug' => 'menu-page-pastries',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'مطبق',
        'name_en' => 'Mutabbaq',
        'description_ar' => 'مطبق طازج',
        'base_price' => 25,
        'is_available' => true,
        'sort_order' => 1,
    ]);
    ProductVariant::create([
        'product_id' => $product->id,
        'size_key' => 'small',
        'name_ar' => 'صغير',
        'piece_count' => 1,
        'price' => 18,
        'is_default' => true,
    ]);
    $optionGroup = OptionsGroup::create([
        'name_ar' => 'الإضافات',
        'name_en' => 'Add-ons',
        'type' => 'multiple',
        'option_category' => 'add_on',
        'is_required' => false,
    ]);
    ProductOptionRelation::create([
        'product_id' => $product->id,
        'option_group_id' => $optionGroup->id,
    ]);
    ProductOption::create([
        'group_id' => $optionGroup->id,
        'name_ar' => 'جبنة إضافية',
        'description_ar' => 'إضافة جبنة',
        'price' => 4,
        'is_available' => true,
    ]);

    $unavailableCategory = Category::create([
        'name_ar' => 'مخفي',
        'slug' => 'menu-page-hidden',
        'sort_order' => 2,
        'is_active' => false,
    ]);
    Product::create([
        'category_id' => $unavailableCategory->id,
        'name_ar' => 'طبق غير منشور',
        'base_price' => 10,
        'is_available' => true,
    ]);
    Product::create([
        'category_id' => $category->id,
        'name_ar' => 'طبق غير متوفر',
        'base_price' => 10,
        'is_available' => false,
    ]);

    $this->get('/menu')
        ->assertOk()
        ->assertSee('المعجنات')
        ->assertSee('مطبق')
        ->assertSee('18.00')
        ->assertSee('product-details-modal')
        ->assertSee('data-product-details-target="product-details-'.$product->id.'"', false)
        ->assertSee('الأحجام والأسعار')
        ->assertSee('الخيارات والإضافات')
        ->assertSee('جبنة إضافية')
        ->assertSee('+4.00 ر.س')
        ->assertDontSee('طبق غير منشور')
        ->assertDontSee('طبق غير متوفر');
});

it('shows an empty state when no active category has available products', function () {
    $this->get('/menu')
        ->assertOk()
        ->assertSee('القائمة غير متاحة حاليًا');
});
