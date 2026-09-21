<?php

namespace App\Services\Gateway;

final class GatewaySendResult
{
    public function __construct(
        public int $recipientId,
        public string $phone,
        public bool $accepted,
        public bool $timedOut = false,
        public ?string $providerMessageId = null,
        public string $code = '',
        public string $raw = '',
    ) {}
}
