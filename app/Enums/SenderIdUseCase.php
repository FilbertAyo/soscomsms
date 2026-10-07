<?php

namespace App\Enums;

enum SenderIdUseCase: string
{
    case Transactional = 'transactional';
    case Promotional = 'promotional';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Transactional => 'Transactional',
            self::Promotional => 'Promotional',
            self::Both => 'Transactional & promotional',
        };
    }

    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }
}
