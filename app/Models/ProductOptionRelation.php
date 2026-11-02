<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionRelation extends Model
{
    protected $table = 'product_option_relations';

    protected $fillable = [
        'product_id',
        'option_group_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionsGroup::class, 'option_group_id');
    }
}
