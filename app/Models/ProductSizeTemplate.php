<?php

namespace App\Models;

use Database\Factories\ProductSizeTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSizeTemplate extends Model
{
    /** @use HasFactory<ProductSizeTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'size_key',
        'name_ar',
        'name_en',
        'piece_count',
        'portion_description_ar',
        'portion_description_en',
        'price',
    ];

    protected $casts = [
        'piece_count' => 'integer',
        'price' => 'decimal:2',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'size_template_id');
    }
}
