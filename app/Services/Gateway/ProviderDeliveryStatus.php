<?php

namespace App\Services\Gateway;

use App\Enums\CampaignRecipientStatus;

final class ProviderDeliveryStatus
{
    public static function normalize(string $raw): ?CampaignRecipientStatus
    {
        $value = strtolower(trim($raw));
        $value = (string) preg_replace('/[\s_\-]+/', '', $value);

        if ($value === '') {
            return null;
        }

        return match ($value) {
            'delivered',
            'delivrd',
            'delivery',
            'success',
            'successful',
            'sendsuccessful',
            'deliverysuccess',
            '000',
            'ok',
            'fakedelivered' => CampaignRecipientStatus::Delivered,
            'rejected',
            'dnd',
            '006',
            '001',
            'invalidreceiver' => CampaignRecipientStatus::Rejected,
            'failed',
            'undelivered',
            'undeliv',
            'expired',
            'expird',
            'unsuccessful',
            '013' => CampaignRecipientStatus::Failed,
            'sent',
            'submitted',
            'pending',
            'queued',
            'accept',
            'accepted',
            'buffered',
            'enroute',
            'ack' => CampaignRecipientStatus::Sent,
            'unknown',
            'unknwn',
            'notconfirmed',
            'notrcvd',
            'noreport' => CampaignRecipientStatus::Unknown,
            default => null,
        };
    }

    public static function isSyntheticProviderId(string $providerMessageId): bool
    {
        return str_starts_with(strtoupper($providerMessageId), 'MSH-R');
    }

    public static function isTerminal(CampaignRecipientStatus $status): bool
    {
        return match ($status) {
            CampaignRecipientStatus::Delivered,
            CampaignRecipientStatus::Failed,
            CampaignRecipientStatus::Rejected => true,
            CampaignRecipientStatus::Pending,
            CampaignRecipientStatus::Queued,
            CampaignRecipientStatus::Sent,
            CampaignRecipientStatus::Unknown => false,
        };
    }
}
