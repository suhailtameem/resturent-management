<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    protected $table = 'product_options';

    protected $fillable = [
        'group_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'price',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionsGroup::class, 'group_id');
    }
}
