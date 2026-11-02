<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'image_url',
        'base_price',
        'has_discount',
        'discount_percentage',
        'discounted_price',
        'is_available',
        'is_featured',
        'is_best_seller',
        'badge_text_ar',
        'sort_order',
    ];

    protected $casts = [
        'has_discount' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_best_seller' => 'boolean',
        'sort_order' => 'integer',
        'base_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discounted_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionRelation::class, 'product_id');
    }
}
