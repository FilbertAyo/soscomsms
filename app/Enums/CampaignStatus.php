<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Completed = 'completed';
    case PartiallyDelivered = 'partially_delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
