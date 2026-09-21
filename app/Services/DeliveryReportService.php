<?php

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GatewayDriver;
use App\Models\Delivery;
use App\Services\Gateway\ProviderDeliveryStatus;
use App\Services\Gateway\ProviderPhone;
use Carbon\Carbon;
use Throwable;

class DeliveryReportService
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function apply(
        string $providerMessageId,
        string $rawStatus,
        ?string $phone = null,
        ?string $occurredAt = null,
    ): ?Delivery {
        $normalized = ProviderDeliveryStatus::normalize($rawStatus);
        if ($normalized === null || $providerMessageId === '') {
            return null;
        }

        $delivery = Delivery::query()
            ->where('provider_message_id', $providerMessageId)
            ->with('campaignRecipient.campaign')
            ->first();

        if ($delivery === null) {
            return null;
        }

        $recipient = $delivery->campaignRecipient;
        if ($recipient === null) {
            return null;
        }

        if ($phone !== null && $phone !== '') {
            $expected = ProviderPhone::digits($recipient->phone);
            $incoming = ProviderPhone::digits($phone);
            if ($expected !== '' && $incoming !== '' && $expected !== $incoming) {
                return null;
            }
        }

        if (ProviderDeliveryStatus::isTerminal($recipient->status)) {
            return $delivery;
        }

        $recipient->status = $normalized;
        $recipient->save();

        $delivery->provider_response = $rawStatus;
        $delivery->status = match ($normalized) {
            CampaignRecipientStatus::Delivered => DeliveryStatus::Delivered,
            CampaignRecipientStatus::Failed,
            CampaignRecipientStatus::Rejected => DeliveryStatus::Failed,
            CampaignRecipientStatus::Pending,
            CampaignRecipientStatus::Queued,
            CampaignRecipientStatus::Sent,
            CampaignRecipientStatus::Unknown => DeliveryStatus::Submitted,
        };

        if ($normalized === CampaignRecipientStatus::Delivered) {
            $delivery->delivered_at = $this->parseOccurredAt($occurredAt) ?? now();
        }

        $delivery->save();
        $recipient->campaign?->refreshCounts();

        $freshRecipient = $recipient->fresh(['campaign', 'delivery']) ?? $recipient;
        $this->webhooks->deliveryUpdated($freshRecipient);
        if ($freshRecipient->campaign !== null) {
            $this->webhooks->campaignCompleted($freshRecipient->campaign);
        }

        return $delivery->fresh(['campaignRecipient.campaign']) ?? $delivery;
    }

    public function simulateFake(string $providerMessageId, string $phone): ?Delivery
    {
        if (config('sms.gateway') !== GatewayDriver::Fake->value) {
            return null;
        }

        if (! (bool) config('sms.fake.auto_dlr', true)) {
            return null;
        }

        $failPhones = array_map('strval', config('sms.fake.fail_dlr_phones', []));
        $digits = ProviderPhone::digits($phone);
        $shouldFail = false;
        foreach ($failPhones as $failPhone) {
            if ($failPhone === $phone || ProviderPhone::digits($failPhone) === $digits) {
                $shouldFail = true;
                break;
            }
        }
        $status = $shouldFail ? 'undelivered' : 'delivered';

        return $this->apply($providerMessageId, $status, $phone);
    }

    private function parseOccurredAt(?string $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
