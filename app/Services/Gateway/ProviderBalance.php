<?php

namespace App\Services\Gateway;

final class ProviderBalance
{
    public function __construct(
        public int $smsBalance,
        public string $raw = '',
    ) {}
}
