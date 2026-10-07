<?php

namespace App\Enums;

enum MessageTemplateType: string
{
    case Transactional = 'transactional';
    case Promotional = 'promotional';
    case Informational = 'informational';
}
