<?php

namespace App\Filament\Resources\SlugRedirects;

use App\Filament\Resources\SlugRedirects\Pages\ListSlugRedirects;
use App\Filament\Resources\SlugRedirects\Pages\ViewSlugRedirect;
use App\Filament\Resources\SlugRedirects\Schemas\SlugRedirectInfolist;
use App\Filament\Resources\SlugRedirects\Tables\SlugRedirectsTable;
use App\Models\SlugRedirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SlugRedirectResource extends Resource
{
    protected static ?string $model = SlugRedirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Slug Redirect';

    protected static ?string $pluralModelLabel = 'Slug Redirects';

    protected static ?string $navigationLabel = 'Slug Redirects';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    public static function infolist(Schema $schema): Schema
    {
        return SlugRedirectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlugRedirectsTable::configure($table);
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
            'index' => ListSlugRedirects::route('/'),
            'view' => ViewSlugRedirect::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
