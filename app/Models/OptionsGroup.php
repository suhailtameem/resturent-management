<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionsGroup extends Model
{
    protected $table = 'options_groups';

    protected $fillable = [
        'name_ar',
        'name_en',
        'type',
        'option_category',
        'is_required',
        'is_reusable',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_reusable' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'group_id');
    }

    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductOptionRelation::class, 'option_group_id');
    }
}
