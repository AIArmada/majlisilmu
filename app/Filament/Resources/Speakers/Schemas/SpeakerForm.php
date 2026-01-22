<?php

namespace App\Filament\Resources\Speakers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SpeakerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('bio')
                            ->columnSpanFull()
                            ->maxLength(5000),
                    ])
                    ->columns(2),
                Section::make('Contact')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('contacts')
                            ->relationship()
                            ->schema([
                                Select::make('category')
                                    ->options([
                                        'email' => 'Email',
                                        'phone' => 'Phone',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(255)
                                    ->label(fn (Get $get) => match ($get('category')) {
                                        'email' => 'Email Address',
                                        'phone' => 'Phone Number',
                                        default => 'Value',
                                    })
                                    ->email(fn (Get $get) => $get('category') === 'email')
                                    ->tel(fn (Get $get) => $get('category') === 'phone'),
                                Select::make('type')
                                    ->options([
                                        'main' => 'Main',
                                        'work' => 'Work',
                                        'personal' => 'Personal',
                                        'whatsapp' => 'WhatsApp',
                                    ])
                                    ->default('main')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => ($state['category'] ?? 'Contact').': '.($state['value'] ?? '')),
                    ]),
                Section::make('Avatar')
                    ->components([
                        TextInput::make('avatar_url')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Social Media')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('socialMedia')
                            ->relationship()
                            ->schema([
                                Select::make('platform')
                                    ->options([
                                        'facebook' => 'Facebook',
                                        'instagram' => 'Instagram',
                                        'youtube' => 'YouTube',
                                        'tiktok' => 'TikTok',
                                        'twitter' => 'X (Twitter)',
                                        'linkedin' => 'LinkedIn',
                                        'website' => 'Website',
                                        'other' => 'Other',
                                    ])
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('username')
                                    ->label('Username / Handle')
                                    ->placeholder('@username')
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null),
                    ]),
                Section::make('Status')
                    ->components([
                        Select::make('status')
                            ->options([
                                'unverified' => 'Unverified',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }
}
