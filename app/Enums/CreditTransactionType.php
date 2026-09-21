<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case Refund = 'refund';
    case Grant = 'grant';
    case Reversal = 'reversal';
}
