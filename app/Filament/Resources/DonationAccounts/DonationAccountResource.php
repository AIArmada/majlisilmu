<?php

namespace App\Filament\Resources\DonationAccounts;

use App\Filament\Resources\DonationAccounts\Pages\CreateDonationAccount;
use App\Filament\Resources\DonationAccounts\Pages\EditDonationAccount;
use App\Filament\Resources\DonationAccounts\Pages\ListDonationAccounts;
use App\Filament\Resources\DonationAccounts\Schemas\DonationAccountForm;
use App\Filament\Resources\DonationAccounts\Tables\DonationAccountsTable;
use App\Models\DonationAccount;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DonationAccountResource extends Resource
{
    protected static ?string $model = DonationAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'recipient_name';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function form(Schema $schema): Schema
    {
        return DonationAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DonationAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonationAccounts::route('/'),
            'create' => CreateDonationAccount::route('/create'),
            'edit' => EditDonationAccount::route('/{record}/edit'),
        ];
    }
}
