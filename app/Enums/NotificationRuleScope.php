<?php

namespace App\Enums;

enum NotificationRuleScope: string
{
    case Family = 'family';
    case Trigger = 'trigger';
}
