<?php

namespace App\Services\Gateway;

final class ProviderPhone
{
    public static function digits(string $e164): string
    {
        return preg_replace('/\D+/', '', $e164) ?? '';
    }
}
