<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Telegram = 'telegram';
    case Line = 'line';
    case Wechat = 'wechat';
    case Messenger = 'messenger';
    case InstagramDm = 'instagram_dm';
    case Push = 'push';
    case InApp = 'in_app';
}
