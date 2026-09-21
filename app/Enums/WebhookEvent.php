<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case DeliveryUpdated = 'delivery.updated';
    case CampaignCompleted = 'campaign.completed';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::DeliveryUpdated,
            self::CampaignCompleted,
        ];
    }
}
