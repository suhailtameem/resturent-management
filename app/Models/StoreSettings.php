<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSettings extends Model
{
    protected $fillable = [
        'store_name_ar',
        'store_name_en',
        'address',
        'support_phone',
        'cash_on_delivery_enabled',
        'online_enabled',
        'auto_accept_prepaid_orders',
        'currency_label_ar',
        'currency_label_en',
    ];

    protected $casts = [
        'cash_on_delivery_enabled' => 'boolean',
        'online_enabled' => 'boolean',
        'auto_accept_prepaid_orders' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], static::defaultAttributes());
    }

    /**
     * @return array<string, bool|string>
     */
    public static function defaultAttributes(): array
    {
        return [
            'store_name_ar' => 'اسم المتجر',
            'store_name_en' => 'Store Name',
            'cash_on_delivery_enabled' => true,
            'online_enabled' => true,
            'auto_accept_prepaid_orders' => false,
            'currency_label_ar' => 'ر.س',
            'currency_label_en' => 'SAR',
        ];
    }

    /**
     * @return array{currency_code: string, currency_label_ar: string, currency_label_en: string}
     */
    public function currencyData(): array
    {
        return [
            'currency_code' => 'SAR',
            'currency_label_ar' => $this->currency_label_ar,
            'currency_label_en' => $this->currency_label_en,
        ];
    }
}
