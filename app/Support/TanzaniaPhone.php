<?php

namespace App\Support;

final class TanzaniaPhone
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '+255'.$digits;
        }

        return $digits === '' ? '' : '+'.$digits;
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^\+255[67]\d{8}$/', self::normalize($value));
    }
}
