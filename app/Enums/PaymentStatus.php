<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Expired = 'expired';
}
