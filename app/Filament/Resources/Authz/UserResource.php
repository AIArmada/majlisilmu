<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authz;

use AIArmada\FilamentAuthz\Resources\UserResource as BaseUserResource;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use App\Filament\Resources\Authz\UserResource\Pages\CreateUser;
use App\Filament\Resources\Authz\UserResource\Pages\EditUser;
use App\Filament\Resources\Authz\UserResource\Pages\ListUsers;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserResource extends BaseUserResource
{
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('User Details')
                ->schema(static::getConfiguredFormFields())
                ->columns(2),
            ...UserAuthzForm::components(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<DateTimePicker|TextInput>
     */
    protected static function getConfiguredFormFields(): array
    {
        $fields = (array) config('filament-authz.user_resource.form.fields', ['name', 'email', 'password']);

        /** @var list<DateTimePicker|TextInput> $components */
        $components = [];

        foreach ($fields as $field) {
            $components[] = match ($field) {
                'name' => TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                'email' => TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                'email_verified_at' => DateTimePicker::make('email_verified_at')
                    ->label('Email Verified At')
                    ->native(false)
                    ->seconds(false),
                'phone_verified_at' => DateTimePicker::make('phone_verified_at')
                    ->label('Phone Verified At')
                    ->native(false)
                    ->seconds(false),
                'password' => TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(function (?string $state): ?string {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        return Hash::make($state);
                    })
                    ->dehydrated(function (?string $state): bool {
                        return filled($state);
                    })
                    ->required(function (string $operation): bool {
                        return $operation === 'create';
                    })
                    ->maxLength(255),
                default => TextInput::make((string) $field)
                    ->maxLength(255),
            };
        }

        return $components;
    }
}
