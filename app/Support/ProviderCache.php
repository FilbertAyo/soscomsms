<?php

namespace App\Support;

final class ProviderCache
{
    public const CURRENT_TTL_SECONDS = 60;

    public static function currentKey(string $driver): string
    {
        return 'provider:current:'.$driver;
    }
}
