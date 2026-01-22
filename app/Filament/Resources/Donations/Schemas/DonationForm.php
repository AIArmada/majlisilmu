<?php

namespace App\Filament\Resources\Donations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DonationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Owner')
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
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Account Details')
                    ->schema([
                        TextInput::make('label')
                            ->placeholder('e.g. Tabung Masjid, Dana Pembangunan'),
                        TextInput::make('recipient_name')
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
                    ])->columns(3),

                \Filament\Schemas\Components\Section::make('Payment Info')
                    ->schema([
                        // Bank Account Fields
                        TextInput::make('bank_name')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account'),
                        TextInput::make('account_number')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'bank_account'),

                        // DuitNow Fields
                        \Filament\Forms\Components\Select::make('duitnow_type')
                            ->options([
                                'mobile' => 'Mobile Number',
                                'nric' => 'NRIC',
                                'business' => 'Business Registration',
                                'passport' => 'Passport',
                            ])
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow'),
                        TextInput::make('duitnow_value')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'duitnow'),

                        // E-Wallet Fields
                        TextInput::make('ewallet_provider')
                            ->placeholder('e.g. TNG, GrabPay, ShopeePay')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet'),
                        TextInput::make('ewallet_handle')
                            ->label('Phone / ID')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet'),
                        Textarea::make('ewallet_qr_payload')
                            ->label('QR Data')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('method') === 'ewallet')
                            ->columnSpanFull(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Verification & Defaults')
                    ->schema([
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'unverified' => 'Unverified',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                                'inactive' => 'Inactive',
                            ])
                            ->required()
                            ->default('unverified'),
                        Toggle::make('is_default')
                            ->label('Default account for this entity')
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
