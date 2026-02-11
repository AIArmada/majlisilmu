<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SocialMediaPlatform: string implements HasIcon, HasLabel
{
    case Facebook = 'facebook';
    case Twitter = 'twitter';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case LinkedIn = 'linkedin';
    case Website = 'website';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Facebook => __('Facebook'),
            self::Twitter => __('Twitter / X'),
            self::Instagram => __('Instagram'),
            self::YouTube => __('YouTube'),
            self::TikTok => __('TikTok'),
            self::Telegram => __('Telegram'),
            self::WhatsApp => __('WhatsApp'),
            self::LinkedIn => __('LinkedIn'),
            self::Website => __('Laman Web'),
            self::Other => __('Lain-lain'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Facebook => 'heroicon-m-globe-alt',
            self::Twitter => 'heroicon-m-chat-bubble-left',
            self::Instagram => 'heroicon-m-camera',
            self::YouTube => 'heroicon-m-play',
            self::TikTok => 'heroicon-m-musical-note',
            self::Telegram => 'heroicon-m-paper-airplane',
            self::WhatsApp => 'heroicon-m-phone',
            self::LinkedIn => 'heroicon-m-briefcase',
            self::Website => 'heroicon-m-globe-alt',
            self::Other => 'heroicon-m-link',
        };
    }

    /**
     * Get URL validation pattern for this platform.
     */
    public function getUrlPattern(): ?string
    {
        return match ($this) {
            self::Facebook => 'https://*facebook.com/*',
            self::Twitter => 'https://*twitter.com/*',
            self::Instagram => 'https://*instagram.com/*',
            self::YouTube => 'https://*youtube.com/*',
            self::TikTok => 'https://*tiktok.com/*',
            self::Telegram => 'https://t.me/*',
            self::WhatsApp => 'https://wa.me/*',
            self::LinkedIn => 'https://*linkedin.com/*',
            self::Website => null,
            self::Other => null,
        };
    }
}
