<?php

namespace App\Services\Gateway;

final class MobishastraBalanceParser
{
    public const TANZANIA_DIAL = '255';

    public static function parse(string $raw, string $preferredDial = self::TANZANIA_DIAL): ?int
    {
        $text = self::normalize($raw);
        if ($text === '') {
            return null;
        }

        $dial = preg_replace('/\D+/', '', $preferredDial) ?: self::TANZANIA_DIAL;
        $fromCountry = self::balanceAfterDial($text, $dial)
            ?? self::balanceAfterCountryName($text, 'tanzania');

        if ($fromCountry !== null) {
            return $fromCountry;
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*(?:sms|credits)?$/i', $text) === 1) {
            return self::toCredits($text);
        }

        return null;
    }

    private static function normalize(string $raw): string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function balanceAfterDial(string $text, string $dial): ?int
    {
        $quoted = preg_quote($dial, '/');
        $patterns = [
            '/\(\s*\+?'.$quoted.'\s*\)\s*[=:]\s*([\d,]+(?:\.\d+)?)/i',
            '/\+'.$quoted.'\s*[=:]\s*([\d,]+(?:\.\d+)?)/i',
            '/(?<![0-9])'.$quoted.'\s*[=:]\s*([\d,]+(?:\.\d+)?)/i',
            '/(?<![0-9])'.$quoted.'\s*[,|]\s*([\d,]+(?:\.\d+)?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return self::toCredits($matches[1]);
            }
        }

        return null;
    }

    private static function balanceAfterCountryName(string $text, string $name): ?int
    {
        $quoted = preg_quote($name, '/');
        if (preg_match('/'.$quoted.'\b[^\n=:]{0,40}[=:]\s*([\d,]+(?:\.\d+)?)/i', $text, $matches) !== 1) {
            return null;
        }

        return self::toCredits($matches[1]);
    }

    private static function toCredits(string $value): int
    {
        return (int) floor((float) str_replace(',', '', $value));
    }
}
