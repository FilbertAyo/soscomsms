<?php

namespace App\Support;

final class MailLogo
{
    public static function path(): string
    {
        return public_path('images/soscom-sms-icon.png');
    }

    public static function src(?object $message = null): string
    {
        $path = self::path();

        if (is_file($path) && $message !== null && method_exists($message, 'embed')) {
            return $message->embed($path);
        }

        return url('/images/soscom-sms-icon.png');
    }
}
