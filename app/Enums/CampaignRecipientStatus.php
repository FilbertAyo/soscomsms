<?php

namespace App\Enums;

enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
