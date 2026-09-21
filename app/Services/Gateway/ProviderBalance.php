<?php

namespace App\Services\Gateway;

final class ProviderBalance
{
    public function __construct(
        public int $smsBalance,
        public string $raw = '',
        public bool $parsed = true,
        public ?string $error = null,
    ) {}
}
