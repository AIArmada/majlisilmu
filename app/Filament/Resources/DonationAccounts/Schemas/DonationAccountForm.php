<?php

namespace App\Filament\Resources\DonationAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DonationAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Donation Account')
                    ->components([
                        Select::make('institution_id')
                            ->relationship('institution', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('label')
                            ->maxLength(255),
                        TextInput::make('recipient_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('bank_name')
                            ->maxLength(255),
                        TextInput::make('account_number')
                            ->maxLength(255),
                        TextInput::make('duitnow_id')
                            ->maxLength(255),
                        Select::make('qr_asset_id')
                            ->relationship('qrAsset', 'original_name')
                            ->searchable()
                            ->preload(),
                        Select::make('verification_status')
                            ->options([
                                'unverified' => 'Unverified',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
