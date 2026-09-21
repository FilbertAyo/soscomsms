<?php

namespace App\Services\Gateway;

use App\Enums\MessageEncoding;

final class OutboundSms
{
    public function __construct(
        public int $recipientId,
        public string $phone,
        public string $sender,
        public string $body,
        public MessageEncoding $encoding,
        public int $segmentCount = 1,
        public ?int $campaignId = null,
    ) {}
}
