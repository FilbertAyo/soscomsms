<?php

namespace App\Services\Gateway;

final class UnicodeHex
{
    public static function encode(string $text): string
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');

        return strtoupper(bin2hex($utf16));
    }
}
