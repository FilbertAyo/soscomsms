<?php

namespace App\Contracts;

use App\Services\Gateway\DeliveryReport;
use App\Services\Gateway\GatewaySendResult;
use App\Services\Gateway\OutboundSms;
use App\Services\Gateway\ProviderBalance;

interface SmsGatewayInterface
{
    public function send(OutboundSms $message): GatewaySendResult;

    /**
     * @param  list<OutboundSms>  $messages
     * @return list<GatewaySendResult>
     */
    public function sendBulk(array $messages): array;

    public function checkBalance(): ProviderBalance;

    public function checkDeliveryStatus(string $providerMessageId): DeliveryReport;
}
