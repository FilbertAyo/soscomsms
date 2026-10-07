<?php

namespace App\Enums;

enum MessageTemplateType: string
{
    case Transactional = 'transactional';
    case Promotional = 'promotional';
    case Otp = 'otp';
    case Informational = 'informational';
}
