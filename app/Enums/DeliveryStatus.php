<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
