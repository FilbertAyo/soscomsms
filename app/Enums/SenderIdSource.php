<?php

namespace App\Enums;

enum SenderIdSource: string
{
    case Generic = 'generic';
    case Request = 'request';
    case Admin = 'admin';
}
