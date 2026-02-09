<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\TagType;
use App\Models\Event;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('EventViewTabs')
                    ->tabs([
                        Tab::make('Maklumat Majlis')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Maklumat Utama')
                                    ->schema([
                                        TextEntry::make('title')
                                            ->label('Tajuk Majlis'),
                                        TextEntry::make('slug')
                                            ->label('Slug'),
                                        TextEntry::make('description')
                                            ->label('Keterangan')
                                            ->state(fn (Event $record): string => self::normalizeDescription($record->description))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Section::make('Waktu & Format')
                                    ->schema([
                                        TextEntry::make('timing_mode')
                                            ->label('Mode Waktu')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->badge(),
                                        TextEntry::make('prayer_reference')
                                            ->label('Waktu Solat')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->placeholder('-'),
                                        TextEntry::make('prayer_offset')
                                            ->label('Offset')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->placeholder('-'),
                                        TextEntry::make('prayer_display_text')
                                            ->label('Paparan Waktu')
                                            ->placeholder('-'),
                                        TextEntry::make('starts_at')
                                            ->label('Waktu Mula')
                                            ->dateTime(),
                                        TextEntry::make('ends_at')
                                            ->label('Waktu Tamat')
                                            ->dateTime()
                                            ->placeholder('-'),
                                        TextEntry::make('timezone')
                                            ->label('Timezone')
                                            ->placeholder('-'),
                                        TextEntry::make('event_format')
                                            ->label('Format Majlis')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->badge(),
                                        TextEntry::make('visibility')
                                            ->label('Keterlihatan')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->badge(),
                                        TextEntry::make('event_url')
                                            ->label('Pautan Majlis')
                                            ->placeholder('-')
                                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                            ->openUrlInNewTab(),
                                        TextEntry::make('live_url')
                                            ->label('Pautan Siaran Langsung')
                                            ->placeholder('-')
                                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                            ->openUrlInNewTab(),
                                        TextEntry::make('recording_url')
                                            ->label('Pautan Rakaman')
                                            ->placeholder('-')
                                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                            ->openUrlInNewTab(),
                                    ])
                                    ->columns(2),
                                Section::make('Sasaran Peserta')
                                    ->schema([
                                        TextEntry::make('gender')
                                            ->label('Jantina')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state))
                                            ->badge(),
                                        TextEntry::make('age_group')
                                            ->label('Peringkat Umur')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumCollection($state))
                                            ->placeholder('-'),
                                        IconEntry::make('children_allowed')
                                            ->label('Kanak-kanak Dibenarkan')
                                            ->boolean(),
                                        IconEntry::make('is_muslim_only')
                                            ->label('Terbuka untuk Muslim Sahaja')
                                            ->boolean(),
                                        TextEntry::make('languages.name')
                                            ->label('Bahasa')
                                            ->listWithLineBreaks()
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Kategori & Bidang')
                            ->icon('heroicon-m-tag')
                            ->schema([
                                Section::make('Kategori')
                                    ->schema([
                                        TextEntry::make('event_type')
                                            ->label('Jenis Majlis')
                                            ->formatStateUsing(fn (mixed $state): string => self::formatEnumCollection($state))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                        TextEntry::make('domain_tags')
                                            ->label('Kategori')
                                            ->state(fn (Event $record): string => self::formatTagList($record, TagType::Domain))
                                            ->placeholder('-'),
                                        TextEntry::make('discipline_tags')
                                            ->label('Bidang Ilmu')
                                            ->state(fn (Event $record): string => self::formatTagList($record, TagType::Discipline))
                                            ->placeholder('-'),
                                        TextEntry::make('source_tags')
                                            ->label('Sumber Utama')
                                            ->state(fn (Event $record): string => self::formatTagList($record, TagType::Source))
                                            ->placeholder('-'),
                                        TextEntry::make('issue_tags')
                                            ->label('Tema / Isu')
                                            ->state(fn (Event $record): string => self::formatTagList($record, TagType::Issue))
                                            ->placeholder('-'),
                                        TextEntry::make('references.title')
                                            ->label('Rujukan Kitab / Buku')
                                            ->listWithLineBreaks()
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Penganjur & Lokasi')
                            ->icon('heroicon-m-building-office')
                            ->schema([
                                Section::make('Penganjur')
                                    ->schema([
                                        TextEntry::make('organizer_type')
                                            ->label('Jenis Penganjur')
                                            ->formatStateUsing(fn (?string $state): string => self::formatOrganizerType($state))
                                            ->placeholder('-'),
                                        TextEntry::make('organizer.name')
                                            ->label('Penganjur')
                                            ->placeholder('-'),
                                        TextEntry::make('series.title')
                                            ->label('Siri')
                                            ->listWithLineBreaks()
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Section::make('Lokasi')
                                    ->schema([
                                        TextEntry::make('institution.name')
                                            ->label('Institusi')
                                            ->placeholder('-'),
                                        TextEntry::make('venue.name')
                                            ->label('Lokasi')
                                            ->placeholder('-'),
                                        TextEntry::make('space.name')
                                            ->label('Ruang')
                                            ->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Penceramah & Media')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Section::make('Penceramah')
                                    ->schema([
                                        TextEntry::make('speakers.name')
                                            ->label('Penceramah')
                                            ->listWithLineBreaks()
                                            ->placeholder('-'),
                                    ]),
                                Section::make('Media')
                                    ->schema([
                                        ImageEntry::make('poster_image')
                                            ->label('Gambar Utama')
                                            ->state(fn (Event $record): ?string => $record->getFirstMediaUrl('poster') ?: null)
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                        ImageEntry::make('gallery_images')
                                            ->label('Galeri')
                                            ->state(fn (Event $record): array => $record->getMedia('gallery')->map(fn ($media): string => $media->getUrl())->values()->all())
                                            ->stacked()
                                            ->limit(6)
                                            ->limitedRemainingText(),
                                    ])
                                    ->columns(1),
                            ]),
                        Tab::make('Semak & Moderasi')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Section::make('Moderasi')
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'needs_changes' => 'info',
                                                'rejected' => 'danger',
                                                default => 'gray',
                                            }),
                                        IconEntry::make('is_priority')
                                            ->label('Priority Review')
                                            ->boolean(),
                                        IconEntry::make('is_featured')
                                            ->label('Featured Event')
                                            ->boolean(),
                                        TextEntry::make('published_at')
                                            ->label('Tarikh Terbit')
                                            ->dateTime()
                                            ->placeholder('-'),
                                        TextEntry::make('escalated_at')
                                            ->label('Tarikh Eskalasi')
                                            ->dateTime()
                                            ->placeholder('-'),
                                        TextEntry::make('submitter.email')
                                            ->label('Penghantar')
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')
                                            ->label('Dicipta Pada')
                                            ->dateTime(),
                                        TextEntry::make('updated_at')
                                            ->label('Dikemas Kini Pada')
                                            ->dateTime(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    protected static function normalizeDescription(mixed $description): string
    {
        if (is_string($description) && filled($description)) {
            return $description;
        }

        if (is_array($description)) {
            $html = data_get($description, 'html');

            if (is_string($html) && filled($html)) {
                return strip_tags($html);
            }

            $content = data_get($description, 'content');

            if (is_string($content) && filled($content)) {
                return $content;
            }

            $flattened = collect($description)
                ->flatten()
                ->filter(fn (mixed $value): bool => is_string($value) && filled($value))
                ->implode(' ');

            if ($flattened !== '') {
                return $flattened;
            }
        }

        return '-';
    }

    protected static function formatTagList(Event $event, TagType $type): string
    {
        $tags = $event->tags
            ->where('type', $type->value)
            ->pluck('name')
            ->map(fn (mixed $name): string => self::normalizeTagName($name))
            ->filter()
            ->values();

        if ($tags->isEmpty()) {
            return '-';
        }

        return $tags->implode(', ');
    }

    protected static function normalizeTagName(mixed $name): string
    {
        if (is_string($name)) {
            return $name;
        }

        if (is_array($name)) {
            $locale = app()->getLocale();

            return (string) ($name[$locale] ?? $name['en'] ?? collect($name)->first() ?? '');
        }

        return '';
    }

    protected static function formatOrganizerType(?string $state): string
    {
        return match ($state) {
            \App\Models\Institution::class, 'institution' => 'Institusi',
            \App\Models\Speaker::class, 'speaker' => 'Penceramah',
            default => '-',
        };
    }

    protected static function formatEnumCollection(mixed $state): string
    {
        $items = $state instanceof Collection
            ? $state->all()
            : (is_array($state) ? $state : [$state]);

        return collect($items)
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => self::formatEnumValue($value))
            ->filter()
            ->implode(', ');
    }

    protected static function formatEnumValue(mixed $state): string
    {
        if ($state instanceof BackedEnum) {
            if (method_exists($state, 'getLabel')) {
                return (string) $state->getLabel();
            }

            if (method_exists($state, 'label')) {
                return (string) $state->label();
            }

            return (string) $state->value;
        }

        return is_scalar($state) ? (string) $state : '';
    }
}
