<?php

use App\Models\Category;
use App\Models\OptionsGroup;
use App\Models\Product;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createManagerForReusableCatalogTests(): StaffUser
{
    return StaffUser::create([
        'username' => 'reusable-catalog-manager',
        'email' => 'reusable-catalog-manager@example.test',
        'password' => 'password123',
        'role' => 'super_admin',
        'full_name' => 'Reusable Catalog Manager',
        'phone' => '+966500000077',
        'is_active' => true,
    ]);
}

function createCategoryForReusableCatalogTests(): Category
{
    return Category::create([
        'name_ar' => 'وجبات المكتبة',
        'name_en' => 'Library Meals',
        'slug' => 'reusable-catalog-meals',
        'sort_order' => 1,
        'is_active' => true,
    ]);
}

it('requires manager authentication to manage reusable product catalogs', function () {
    $this->getJson('/api/v1/manager/product-sizes')->assertUnauthorized();
    $this->getJson('/api/v1/manager/option-groups')->assertUnauthorized();
});

it('rejects selecting reusable size templates with duplicate size keys', function () {
    $manager = createManagerForReusableCatalogTests();
    $category = createCategoryForReusableCatalogTests();
    $templateIds = [];

    foreach ([20, 25] as $price) {
        $templateIds[] = $this->actingAs($manager, 'staff_api')
            ->postJson('/api/v1/manager/product-sizes', [
                'size_key' => 'large',
                'name_ar' => 'كبير',
                'piece_count' => 8,
                'price' => $price,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            'category_id' => $category->id,
            'name_ar' => 'منتج بأحجام مكررة',
            'base_price' => 20,
            'size_template_ids' => $templateIds,
            'option_groups' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size_template_ids']);

    $this->assertDatabaseCount('products', 0);
});

it('requires a product-specific price for each selected size template', function () {
    $manager = createManagerForReusableCatalogTests();
    $category = createCategoryForReusableCatalogTests();
    $sizeTemplate = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/product-sizes', [
            'size_key' => 'medium',
            'name_ar' => 'متوسط',
            'piece_count' => 4,
            'price' => 30,
        ])
        ->assertCreated();

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            'category_id' => $category->id,
            'name_ar' => 'منتج دون سعر الحجم',
            'base_price' => 20,
            'size_templates' => [
                ['template_id' => $sizeTemplate->json('data.id')],
            ],
            'option_groups' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size_templates.0.price']);

    $this->assertDatabaseCount('products', 0);
});

it('reuses shared size templates across products and propagates catalog edits', function () {
    $manager = createManagerForReusableCatalogTests();
    $category = createCategoryForReusableCatalogTests();
    $sizeResponse = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/product-sizes', [
            'size_key' => 'large',
            'name_ar' => 'كبير',
            'name_en' => 'Large',
            'piece_count' => 8,
            'portion_description_ar' => 'تكفي شخصين',
            'price' => 42,
        ])
        ->assertCreated()
        ->assertJsonPath('data.price', 42);
    $sizeTemplateId = $sizeResponse->json('data.id');
    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/product-sizes')
        ->assertOk()
        ->assertJsonPath('data.0.id', $sizeTemplateId);
    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/product-sizes/'.$sizeTemplateId)
        ->assertOk()
        ->assertJsonPath('data.name_ar', 'كبير');

    foreach ([['بيتزا أولى', 42], ['بيتزا ثانية', 37]] as [$name, $productSizePrice]) {
        $this->actingAs($manager, 'staff_api')
            ->postJson('/api/v1/manager/products', [
                'category_id' => $category->id,
                'name_ar' => $name,
                'base_price' => 25,
                'size_templates' => [[
                    'template_id' => $sizeTemplateId,
                    'price' => $productSizePrice,
                    'is_default' => true,
                ]],
                'option_groups' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sizes.0.template_id', $sizeTemplateId)
            ->assertJsonPath('data.sizes.0.is_default', true)
            ->assertJsonPath('data.sizes.0.price', $productSizePrice);
    }

    $firstProduct = Product::query()->where('name_ar', 'بيتزا أولى')->firstOrFail();
    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/products/'.$firstProduct->id, [
            'size_templates' => [[
                'template_id' => $sizeTemplateId,
                'price' => 39,
                'is_default' => true,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.sizes.0.template_id', $sizeTemplateId)
        ->assertJsonPath('data.sizes.0.price', 39);

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/product-sizes/'.$sizeTemplateId, [
            'name_ar' => 'كبير جدًا',
            'price' => 45,
        ])
        ->assertOk()
        ->assertJsonPath('data.products_count', 2);
    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/product-sizes/'.$sizeTemplateId)
        ->assertConflict()
        ->assertJsonPath('code', 'SIZE_TEMPLATE_IN_USE');

    $products = Product::query()->orderBy('id')->get();
    foreach ($products as $product) {
        $expectedPrice = $product->name_ar === 'بيتزا أولى' ? 39 : 37;

        $this->actingAs($manager, 'staff_api')
            ->getJson('/api/v1/manager/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.sizes.0.name_ar', 'كبير جدًا')
            ->assertJsonPath('data.sizes.0.price', $expectedPrice)
            ->assertJsonPath('data.sizes.0.template_id', $sizeTemplateId);
    }
});

it('reuses shared option groups across products and protects them while attached', function () {
    $manager = createManagerForReusableCatalogTests();
    $category = createCategoryForReusableCatalogTests();
    $optionGroupResponse = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/option-groups', [
            'name_ar' => 'النكهة',
            'name_en' => 'Flavor',
            'type' => 'single',
            'option_category' => 'flavor',
            'is_required' => true,
            'options' => [
                ['name_ar' => 'عادي', 'name_en' => 'Regular', 'price' => 0],
                ['name_ar' => 'حار', 'name_en' => 'Spicy', 'price' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_reusable', true);
    $groupId = $optionGroupResponse->json('data.id');
    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/option-groups')
        ->assertOk()
        ->assertJsonPath('data.0.id', $groupId);
    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/option-groups/'.$groupId)
        ->assertOk()
        ->assertJsonPath('data.options.1.name_ar', 'حار');

    $createdProducts = [];
    foreach (['منتج أول', 'منتج ثان'] as $name) {
        $createdProducts[] = $this->actingAs($manager, 'staff_api')
            ->postJson('/api/v1/manager/products', [
                'category_id' => $category->id,
                'name_ar' => $name,
                'base_price' => 20,
                'sizes' => [],
                'option_group_ids' => [$groupId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.option_groups.0.id', $groupId)
            ->assertJsonPath('data.option_groups.0.options.1.price', 2);
    }

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/products/'.$createdProducts[0]->json('data.id'), [
            'option_group_ids' => [$groupId],
        ])
        ->assertOk()
        ->assertJsonPath('data.option_groups.0.id', $groupId)
        ->assertJsonPath('data.option_groups.0.is_reusable', true);

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/option-groups/'.$groupId, [
            'name_ar' => 'اختيار النكهة',
            'options' => [
                ['name_ar' => 'حار جدًا', 'price' => 3],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.products_count', 2)
        ->assertJsonPath('data.options.0.price', 3);

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/option-groups/'.$groupId)
        ->assertConflict()
        ->assertJsonPath('code', 'OPTION_GROUP_IN_USE');

    foreach ($createdProducts as $createdProduct) {
        $this->actingAs($manager, 'staff_api')
            ->getJson('/api/v1/manager/products/'.$createdProduct->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.option_groups.0.name_ar', 'اختيار النكهة')
            ->assertJsonPath('data.option_groups.0.options.0.price', 3);
    }

    $this->assertModelExists(OptionsGroup::findOrFail($groupId));
    $this->assertDatabaseCount('product_option_relations', 2);
});

it('deletes reusable sizes and option groups when no products use them', function () {
    $manager = createManagerForReusableCatalogTests();
    $size = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/product-sizes', [
            'size_key' => 'small',
            'name_ar' => 'صغير',
            'piece_count' => 2,
            'price' => 12,
        ])
        ->assertCreated();
    $group = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/option-groups', [
            'name_ar' => 'إضافات',
            'type' => 'multiple',
            'option_category' => 'add_on',
            'options' => [
                ['name_ar' => 'جبنة', 'price' => 4],
            ],
        ])
        ->assertCreated();

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/product-sizes/'.$size->json('data.id'))
        ->assertNoContent();
    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/option-groups/'.$group->json('data.id'))
        ->assertNoContent();

    $this->assertDatabaseMissing('product_size_templates', ['id' => $size->json('data.id')]);
    $this->assertDatabaseMissing('options_groups', ['id' => $group->json('data.id')]);
});

it('rejects attaching product-specific option groups as reusable catalog choices', function () {
    $manager = createManagerForReusableCatalogTests();
    $category = createCategoryForReusableCatalogTests();
    $product = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            'category_id' => $category->id,
            'name_ar' => 'منتج بخيارات خاصة',
            'base_price' => 20,
            'sizes' => [],
            'option_groups' => [
                [
                    'name_ar' => 'خيار خاص',
                    'type' => 'single',
                    'option_category' => 'flavor',
                    'options' => [['name_ar' => 'عادي']],
                ],
            ],
        ])
        ->assertCreated();
    $privateGroupId = $product->json('data.option_groups.0.id');

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            'category_id' => $category->id,
            'name_ar' => 'منتج ثان',
            'base_price' => 20,
            'sizes' => [],
            'option_group_ids' => [$privateGroupId],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_group_ids.0']);
});
