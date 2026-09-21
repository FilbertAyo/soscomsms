<?php

namespace App\Services\Gateway;

use App\Enums\CampaignRecipientStatus;

final class ProviderDeliveryStatus
{
    public static function normalize(string $raw): ?CampaignRecipientStatus
    {
        $value = strtolower(trim($raw));
        $value = (string) preg_replace('/[\s_\-]+/', '', $value);

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
            'accepted' => CampaignRecipientStatus::Sent,
            default => null,
        };
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
