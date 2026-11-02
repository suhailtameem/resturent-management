<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageUrl
{
    public static function fromStoredValue(?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}
