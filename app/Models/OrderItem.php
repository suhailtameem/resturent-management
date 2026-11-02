<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name_ar_snapshot',
        'product_name_en_snapshot',
        'size_name_ar_snapshot',
        'size_name_en_snapshot',
        'piece_count_snapshot',
        'unit_price',
        'quantity',
        'line_total',
        'special_instructions',
        'image_url_snapshot',
    ];

    protected $casts = [
        'piece_count_snapshot' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
