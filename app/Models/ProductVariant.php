<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'size_template_id',
        'size_key',
        'name_ar',
        'name_en',
        'piece_count',
        'portion_description_ar',
        'portion_description_en',
        'price',
        'is_default',
    ];

    protected $casts = [
        'piece_count' => 'integer',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sizeTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductSizeTemplate::class, 'size_template_id');
    }
}
