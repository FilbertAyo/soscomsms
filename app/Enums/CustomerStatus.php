<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case PendingProfile = 'pending_profile';
    case Active = 'active';
    case Suspended = 'suspended';
}
