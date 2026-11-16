<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case LipaNamba = 'lipa_namba';
    case Snippe = 'snippe';
    case Cash = 'cash';
    case Bank = 'bank';
    case ManualMobile = 'manual_mobile';
}
