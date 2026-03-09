<?php

namespace App\Enums;

enum NotificationDestinationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Revoked = 'revoked';
}
