<?php

namespace App\Support;

final class PersonalizedMessage
{
    public const PLACEHOLDER = '{name}';

    public static function contains(string $template): bool
    {
        return str_contains($template, self::PLACEHOLDER);
    }

    public static function cleanName(string $name): string
    {
        $name = str_replace(self::PLACEHOLDER, '', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        $name = trim($name);

        if ($name === '' || ! preg_match('/\p{L}/u', $name)) {
            return '';
        }

        return mb_substr($name, 0, 120);
    }

    public static function render(string $template, string $name): string
    {
        return str_replace(self::PLACEHOLDER, self::cleanName($name), $template);
    }
}
