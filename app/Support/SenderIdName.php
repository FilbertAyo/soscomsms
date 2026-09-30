<?php

namespace App\Support;

final class SenderIdName
{
    public static function normalize(string $raw): string
    {
        $trimmed = trim($raw);

        return preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
    }

    public static function key(string $raw): string
    {
        return mb_strtolower(self::normalize($raw));
    }

    public static function isValid(string $value): bool
    {
        $name = self::normalize($value);
        $max = (int) config('sms.sender_id_max_length', 13);
        $length = mb_strlen($name);

        if ($length < 3 || $length > $max) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9]+$/', $name);
    }

    public static function isBlocked(string $value): bool
    {
        $name = self::key($value);
        $blocked = array_map(
            self::key(...),
            config('sms.sender_id_blocklist', []),
        );

        return in_array($name, $blocked, true);
    }

    public static function invalidMessage(): string
    {
        $max = (int) config('sms.sender_id_max_length', 13);

        return "Enter a Sender ID of 3 to {$max} letters or numbers, with no spaces.";
    }
}
