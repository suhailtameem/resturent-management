<?php

namespace App\Support;

use App\Models\User;

class SaudiPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+966'.$digits;
    }

    /** @return array<int, string> */
    public static function equivalentFormats(string $phone): array
    {
        $canonical = self::normalize($phone);
        $national = substr($canonical, 4);

        return array_values(array_unique([
            $canonical,
            substr($canonical, 1),
            '00'.substr($canonical, 1),
            '0'.$national,
            $national,
        ]));
    }

    public static function isAlreadyUsed(string $phone, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->whereIn('phone', self::equivalentFormats($phone))
            ->when($ignoreUserId !== null, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }
}
