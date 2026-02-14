<?php

namespace App\Filament\Resources\DonationChannels\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DonationChannelForm
{
    public static function configure(Schema $schema, bool $withOwnerSection = true): Schema
    {
        $components = [];

        if ($withOwnerSection) {
            $components[] = \Filament\Schemas\Components\Section::make('Owner')
                ->schema([
                    \Filament\Forms\Components\Select::make('donatable_type')
                        ->options([
                            \App\Models\Institution::class => 'Institution',
                            \App\Models\Speaker::class => 'Speaker',
                            \App\Models\Event::class => 'Event',
                        ])
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Select::make('donatable_id')
                        ->label('Recipient Entity')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $type = $get('donatable_type');
                            if (! $type) {
                                return [];
                            }

                            return $type::query()->pluck('name', 'id');
                        }),
                ])->columns(2);
        }

        $components[] = \Filament\Schemas\Components\Section::make('Account Details')
            ->schema([
                TextInput::make('label')
                    ->placeholder('e.g. Tabung Masjid, Dana Pembangunan'),
                TextInput::make('recipient')
                    ->required()
                    ->placeholder('Full name on account'),
                \Filament\Forms\Components\Select::make('method')
                    ->options([
                        'bank_account' => 'Bank Account',
                        'duitnow' => 'DuitNow',
                        'ewallet' => 'E-Wallet',
                    ])
                    ->required()
                    ->live(),
            ])->columns(3);

        $components[] = \Filament\Schemas\Components\Section::make('Payment Info')
            ->schema([
                // Bank Account Fields
                TextInput::make('bank_name')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account'),
                TextInput::make('bank_code')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account'),
                TextInput::make('account_number')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account'),

                // DuitNow Fields
                TextInput::make('duitnow_type')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow'),
                TextInput::make('duitnow_value')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow'),

                // E-Wallet Fields
                TextInput::make('ewallet_provider')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet'),
                TextInput::make('ewallet_handle')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet'),
                Textarea::make('ewallet_qr_payload')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet'),
            ])->columns(3);

        $components[] = \Filament\Schemas\Components\Section::make('QR Code')
            ->schema([
                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('qr')
                    ->label('QR Image')
                    ->collection('qr')
                    ->image()
                    ->imageEditor()
                    ->avatar()
                    ->conversion('thumb')
                    ->helperText('Upload an official payment QR image.'),
            ]);

        $components[] = \Filament\Schemas\Components\Section::make('Verification')
            ->schema([
                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'unverified' => 'Unverified',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                        'inactive' => 'Inactive',
                    ])
                    ->default('unverified')
                    ->required(),
                Toggle::make('is_default')
                    ->label('Default')
                    ->helperText('Only one default channel per method is allowed'),
                Textarea::make('reference_note')
                    ->label('Reference Note')
                    ->columnSpanFull(),
            ])->columns(2);

        return $schema
            ->components($components);
    }
}
