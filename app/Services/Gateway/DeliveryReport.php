<?php

namespace App\Services\Gateway;

final class DeliveryReport
{
    public function __construct(
        public string $providerMessageId,
        public string $status,
        public string $raw = '',
        public ?string $occurredAt = null,
    ) {}
}
