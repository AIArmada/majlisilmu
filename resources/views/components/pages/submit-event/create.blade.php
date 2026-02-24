<?php

use App\Enums\EventAgeGroup;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\TagType;
use App\Forms\InstitutionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\Venue;
use App\Services\Ai\EventMediaExtractionService;
use App\Services\Captcha\TurnstileVerifier;
use App\Support\Timezone\UserTimezoneResolver;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use WithFileUploads;

    public ?array $data = [];

    public ?TemporaryUploadedFile $event_source_attachment = null;

    public function mount(): void
    {
        $timezone = $this->resolveUserTimezone();

        $this->form->fill([
            'submitter_name' => auth()->user()?->name,
            'submitter_email' => auth()->user()?->email,
            'children_allowed' => true,
            'gender' => EventGenderRestriction::All->value,
            'age_group' => [EventAgeGroup::AllAges],
            'languages' => [101], // Malay as default
            'event_format' => EventFormat::Physical,
            'visibility' => EventVisibility::Public->value,
            'location_same_as_institution' => true,
            'location_type' => 'institution',
            'is_muslim_only' => false,
            'captcha_token' => null,
            'timezone' => $timezone,
        ]);
    }

    public function extractEventFromMedia(EventMediaExtractionService $eventMediaExtractionService): void
    {
        $maxFileSizeKb = (int) config('ai.features.event_media_extraction.max_file_size_kb', 10240);
        $acceptedMimeTypes = config('ai.features.event_media_extraction.accepted_mime_types', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

        if (! is_array($acceptedMimeTypes) || $acceptedMimeTypes === []) {
            $acceptedMimeTypes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ];
        }

        $validated = Validator::make(
            ['event_source_attachment' => $this->event_source_attachment],
            [
                'event_source_attachment' => [
                    'required',
                    'file',
                    "max:{$maxFileSizeKb}",
                    'mimetypes:'.implode(',', $acceptedMimeTypes),
                ],
            ],
            [
                'event_source_attachment.required' => __('Sila muat naik poster, gambar, atau PDF terlebih dahulu.'),
                'event_source_attachment.file' => __('Fail yang dimuat naik tidak sah.'),
                'event_source_attachment.max' => __('Saiz fail melebihi had yang dibenarkan.'),
                'event_source_attachment.mimetypes' => __('Hanya fail PDF, JPEG, PNG, atau WEBP dibenarkan.'),
            ],
        )->validate();

        /** @var TemporaryUploadedFile $file */
        $file = $validated['event_source_attachment'];

        try {
            $extractedState = $eventMediaExtractionService->extract($file);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('Pengekstrakan AI gagal. Sila cuba semula.'))
                ->danger()
                ->send();

            return;
        }

        $mergedState = array_replace($this->data ?? [], $extractedState);
        $mergedState['age_group'] = $this->normalizeAgeGroupState($mergedState['age_group'] ?? []);

        if (
            in_array(EventAgeGroup::Children->value, $mergedState['age_group'], true) ||
            in_array(EventAgeGroup::AllAges->value, $mergedState['age_group'], true)
        ) {
            $mergedState['children_allowed'] = true;
        }

        $mimeType = (string) $file->getMimeType();

        if (str_starts_with($mimeType, 'image/') && blank($mergedState['poster'] ?? null)) {
            $mergedState['poster'] = $file;
        }

        $this->form->fill($mergedState);
        $this->data = $mergedState;

        $wizard = $this->form->getComponent(
            fn (mixed $component): bool => $component instanceof Wizard
        );

        if ($wizard instanceof Wizard) {
            $steps = array_values($wizard->getChildSchema()->getComponents());
            $lastStep = end($steps);

            if ($lastStep instanceof Step && filled($lastStep->getKey())) {
                $wizard->goToStep($lastStep->getKey());
            }
        }

        Notification::make()
            ->title(__('Maklumat majlis berjaya diekstrak dengan AI.'))
            ->success()
            ->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Event)
            ->schema([
                Wizard::make([
                    Step::make(__('Maklumat Majlis'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Select::make('event_type')
                                ->label(__('Jenis Majlis'))
                                ->required()
                                ->multiple()
                                ->closeOnSelect()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    if ($this->hasCommunityEventTypeSelection($state)) {
                                        $set('event_format', EventFormat::Physical->value);
                                    }
                                })
                                ->options(function (): array {
                                    return collect(EventType::cases())
                                        ->mapToGroups(fn (EventType $type) => [
                                            $type->getGroup() => [$type->value => $type->getLabel()],
                                        ])
                                        ->map(fn ($group) => $group->collapse())
                                        ->toArray();
                                })
                                ->searchable(),

                            Select::make('title')
                                ->label(__('Tajuk Majlis'))
                                ->required()
                                ->searchable()
                                ->allowHtml()
                                ->live()
                                ->getSearchResultsUsing(function (string $search): array {
                                    if (empty($search)) {
                                        return [];
                                    }

                                    $results = Event::query()
                                        ->whereRaw('LOWER(title) LIKE ?', ['%'.strtolower($search).'%'])
                                        ->where('status', 'approved')
                                        ->limit(10)
                                        ->pluck('title', 'title')
                                        ->toArray();

                                    // Add Quick Add option if no exact match
                                    $exactMatch = collect($results)->contains(fn ($value) => strtolower($value) === strtolower($search));

                                    if (! $exactMatch) {
                                        $results = ["__quick_add__{$search}" => "<span class='text-primary-600'>+ ".__('Tambah')." '{$search}'</span>"] + $results;
                                    }

                                    return $results;
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    // Clean up the quick_add prefix for display
                                    if (str_starts_with($value, '__quick_add__')) {
                                        return substr($value, strlen('__quick_add__'));
                                    }

                                    return $value;
                                })
                                ->afterStateUpdatedJs(<<<'JS'
                                    if ($state && $state.startsWith('__quick_add__')) {
                                        $set('title', $state.substring('__quick_add__'.length))
                                    }
                                JS)
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    // Skip quick-add values — they're new titles, not existing events
                                    if (! $state || str_starts_with($state, '__quick_add__')) {
                                        return;
                                    }

                                    // Find the latest approved event with this exact title
                                    $existingEvent = Event::query()
                                        ->where('title', $state)
                                        ->where('status', 'approved')
                                        ->with(['tags', 'references'])
                                        ->latest()
                                        ->first();

                                    if (! $existingEvent) {
                                        return;
                                    }

                                    // Populate event type
                                    if ($existingEvent->event_type) {
                                        $set('event_type', $existingEvent->event_type->map(fn ($e) => $e->value)->toArray());
                                    }

                                    // Populate tags by type
                                    $tagsByType = $existingEvent->tags->groupBy('type');

                                    if ($tagsByType->has(TagType::Domain->value)) {
                                        $set('domain_tags', $tagsByType->get(TagType::Domain->value)->pluck('id')->toArray());
                                    }
                                    if ($tagsByType->has(TagType::Discipline->value)) {
                                        $set('discipline_tags', $tagsByType->get(TagType::Discipline->value)->pluck('id')->toArray());
                                    }
                                    if ($tagsByType->has(TagType::Source->value)) {
                                        $set('source_tags', $tagsByType->get(TagType::Source->value)->pluck('id')->toArray());
                                    }
                                    if ($tagsByType->has(TagType::Issue->value)) {
                                        $set('issue_tags', $tagsByType->get(TagType::Issue->value)->pluck('id')->toArray());
                                    }

                                    // Populate references
                                    if ($existingEvent->references->isNotEmpty()) {
                                        $set('references', $existingEvent->references->pluck('id')->toArray());
                                    }
                                })
                                ->placeholder(__('Cari atau masukkan tajuk majlis...')),

                            RichEditor::make('description')
                                ->label(__('Keterangan'))
                                ->maxLength(5000)
                                ->placeholder(__('Terangkan mengenai majlis, topik yang akan dikupas, dll.')),

                            Grid::make(['default' => 1, 'sm' => 2, 'md' => 6])
                                ->schema([
                                    DatePicker::make('event_date')
                                        ->label(__('Tarikh'))
                                        ->required()
                                        ->native()
                                        ->minDate(now()->startOfDay())
                                        ->live()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                            $set('prayer_time', null)
                                                        JS)
                                        ->columnSpan(['default' => 1, 'md' => 2]),

                                    Select::make('prayer_time')
                                        ->label(__('Waktu'))
                                        ->required()
                                        ->afterStateUpdatedJs(<<<'JS'
                                            if ($state !== 'lain_waktu') {
                                                $set('custom_time', null)
                                            }
                                        JS)
                                        ->options(function (Get $get): array {
                                            $eventDate = $get('event_date');

                                            return collect(EventPrayerTime::cases())
                                                ->filter(function (EventPrayerTime $case) use ($eventDate) {
                                                    if (! $eventDate) {
                                                        // No date selected — show base options only (no Jumaat/Tarawih/Ramadhan-only options)
                                                        return ! in_array($case, [EventPrayerTime::SebelumJumaat, EventPrayerTime::SelepasJumaat, EventPrayerTime::SebelumMaghrib, EventPrayerTime::SelepasTarawih], true);
                                                    }

                                                    $timezone = $this->resolveUserTimezone();
                                                    $date = Carbon::parse($eventDate, $timezone)->startOfDay();

                                                    if ($case === EventPrayerTime::SebelumJumaat) {
                                                        return $date->isFriday();
                                                    }

                                                    if ($case === EventPrayerTime::SelepasJumaat) {
                                                        return $date->isFriday();
                                                    }

                                                    if ($case === EventPrayerTime::SebelumMaghrib) {
                                                        return $this->isRamadhan($date);
                                                    }

                                                    if ($case === EventPrayerTime::SelepasTarawih) {
                                                        return $this->isRamadhan($date);
                                                    }

                                                    return true;
                                                })
                                                ->mapWithKeys(fn (EventPrayerTime $case) => [$case->value => $case->getLabel()])
                                                ->toArray();
                                        })
                                        ->columnSpan(['default' => 1, 'md' => 2]),

                                    TimePicker::make('custom_time')
                                        ->label(__('Masa Mula'))
                                        ->helperText(__('Pilih masa mula majlis'))
                                        ->timezone('UTC')
                                        ->native()
                                        ->seconds(false)
                                        ->minutesStep(5)
                                        ->afterStateUpdatedJs(<<<'JS'
                                            const customTime = $state;
                                            const endTime = $get('end_time');
                                            const prayerTime = $get('prayer_time');
                                            
                                            if (prayerTime === 'lain_waktu' && customTime && endTime) {
                                                const startParts = customTime.split(':');
                                                const endParts = endTime.split(':');
                                                
                                                const startMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1] || 0);
                                                const endMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1] || 0);
                                                
                                                if (endMinutes <= startMinutes) {
                                                    $set('end_time', null);
                                                    new FilamentNotification()
                                                        .title(@js(__('Masa akhir mestilah selepas masa mula.')))
                                                        .warning()
                                                        .send();
                                                }
                                            }
                                        JS)
                                        ->visibleJs(<<<'JS'
                                                            $get('prayer_time') === 'lain_waktu'
                                                            JS)
                                        ->requiredIf('prayer_time', EventPrayerTime::LainWaktu)
                                        ->markAsRequired()
                                        ->columnSpan(['default' => 1, 'md' => 2])
                                        ->rule(function (Get $get): Closure {
                                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                                $eventDate = $get('event_date');
                                                $timezone = $this->resolveUserTimezone((string) ($get('timezone') ?? ''));
                                                $now = Carbon::now($timezone);

                                                if (! $eventDate || ! $value) {
                                                    return;
                                                }

                                                $eventDay = Carbon::parse($eventDate, $timezone)->startOfDay();

                                                // Only validate if event is today
                                                if ($eventDay->isSameDay($now)) {
                                                    $timeParts = explode(':', $value);
                                                    $selectedTime = $eventDay->copy()
                                                        ->setHour((int) $timeParts[0])
                                                        ->setMinute((int) $timeParts[1]);

                                                    if ($selectedTime->lessThan($now)) {
                                                        $fail(__('Masa yang dipilih tidak boleh pada masa lalu untuk majlis hari ini.'));
                                                    }
                                                }
                                            };
                                        }),

                                    TimePicker::make('end_time')
                                        ->label(__('Masa Akhir'))
                                        ->helperText(__('Pilihan: Bila majlis dijangka tamat.'))
                                        ->timezone('UTC')
                                        ->native()
                                        ->seconds(false)
                                        ->minutesStep(5)
                                        ->afterStateUpdatedJs(<<<'JS'
                                            const customTime = $get('custom_time');
                                            const endTime = $state;
                                            const prayerTime = $get('prayer_time');
                                            const estimatedStartByPrayer = {
                                                selepas_subuh: '06:30',
                                                selepas_zuhur: '13:30',
                                                sebelum_jumaat: '13:45',
                                                selepas_jumaat: '14:00',
                                                selepas_asar: '17:00',
                                                sebelum_maghrib: '19:45',
                                                selepas_maghrib: '20:00',
                                                selepas_isyak: '21:30',
                                                selepas_tarawih: '22:30',
                                            };
                                            
                                            const guessedStartTime = prayerTime === 'lain_waktu'
                                                ? customTime
                                                : (estimatedStartByPrayer[prayerTime] ?? null);
                                            
                                            if (guessedStartTime && endTime) {
                                                const startParts = guessedStartTime.split(':');
                                                const endParts = endTime.split(':');
                                                
                                                const startMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1] || 0);
                                                const endMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1] || 0);
                                                
                                                if (endMinutes <= startMinutes) {
                                                    $set('end_time', null);
                                                    new FilamentNotification()
                                                        .title(@js(__('Masa akhir mestilah selepas masa mula.')))
                                                        .warning()
                                                        .send();
                                                }
                                            }
                                        JS)
                                        ->columnSpan(['default' => 1, 'md' => 2])
                                        ->rule(function (Get $get): Closure {
                                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                                if (! $value) {
                                                    return; // Optional field
                                                }

                                                $prayerTimeRaw = $get('prayer_time');
                                                $startTime = $this->resolveStartTimeForComparison(
                                                    $prayerTimeRaw,
                                                    $get('custom_time')
                                                );

                                                if ($startTime === null) {
                                                    return;
                                                }

                                                $startParts = explode(':', $startTime);
                                                $endParts = explode(':', (string) $value);

                                                $startMinutes = ((int) $startParts[0]) * 60 + ((int) ($startParts[1] ?? 0));
                                                $endMinutes = ((int) $endParts[0]) * 60 + ((int) ($endParts[1] ?? 0));

                                                if ($endMinutes <= $startMinutes) {
                                                    $fail(__('Masa akhir mestilah selepas masa mula.'));
                                                }
                                            };
                                        }),
                                ]),

                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Radio::make('event_format')
                                        ->label(__('Format Majlis'))
                                        ->required()
                                        ->options(EventFormat::class)
                                        ->default(EventFormat::Physical)
                                        ->disableOptionWhen(fn (string $value, Get $get): bool =>
                                            $this->hasCommunityEventTypeSelection($get('event_type'))
                                            && $value !== EventFormat::Physical->value
                                        )
                                        ->inline(),

                                    Radio::make('visibility')
                                        ->label(__('Keterlihatan'))
                                        ->required()
                                        ->options(EventVisibility::class)
                                        ->default(EventVisibility::Public)
                                        ->inline(),

                                    TextInput::make('event_url')
                                        ->label(__('Pautan Majlis'))
                                        ->url()
                                        ->maxLength(255)
                                        ->placeholder(__('https://example.com/event')),

                                    TextInput::make('live_url')
                                        ->label(__('Pautan Siaran Langsung'))
                                        ->url()
                                        ->maxLength(255)
                                        ->placeholder(__('https://youtube.com/...'))
                                        ->visibleJs(<<<'JS'
                                                    ['online', 'hybrid'].includes($get('event_format'))
                                                    JS)
                                        ->required(fn (Get $get): bool => in_array($get('event_format'), [EventFormat::Online, EventFormat::Hybrid], true)),
                                ]),

                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Select::make('gender')
                                        ->label(__('Jantina'))
                                        ->required()
                                        ->options(EventGenderRestriction::class)
                                        ->default(EventGenderRestriction::All),

                                    Select::make('age_group')
                                        ->label(__('Peringkat Umur'))
                                        ->required()
                                        ->options(EventAgeGroup::class)
                                        ->closeOnSelect()
                                        ->multiple()
                                        ->live()
                                        ->afterStateUpdatedJs(<<<'JS'
                                                            const ageGroups = $state || []
                                                            if (ageGroups.includes('all_ages') && ageGroups.length > 1) {
                                                                $set('age_group', ageGroups.filter((group) => group !== 'all_ages'))
                                                                return
                                                            }
                                                            if (ageGroups.includes('children') || ageGroups.includes('all_ages')) {
                                                                $set('children_allowed', true)
                                                            }
                                                            JS)
                                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                                            $ageGroups = $this->normalizeAgeGroupState($state);

                                            if (in_array(EventAgeGroup::AllAges->value, $ageGroups, true) && count($ageGroups) > 1) {
                                                $ageGroups = array_values(array_filter(
                                                    $ageGroups,
                                                    fn (string $ageGroup): bool => $ageGroup !== EventAgeGroup::AllAges->value
                                                ));
                                                $set('age_group', $ageGroups);
                                            }

                                            if (
                                                in_array(EventAgeGroup::Children->value, $ageGroups, true) ||
                                                in_array(EventAgeGroup::AllAges->value, $ageGroups, true)
                                            ) {
                                                $set('children_allowed', true);
                                            }
                                        }),

                                    Select::make('languages')
                                        ->label(__('Bahasa'))
                                        ->helperText(__('Bahasa yang akan digunakan dalam majlis.'))
                                        ->placeholder(__('Pilih bahasa…'))
                                        ->closeOnSelect()
                                        ->multiple()
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->default([101])
                                        ->options(function () {
                                            $preferredOrder = ['ms', 'ar', 'en', 'id', 'zh', 'ta', 'jv'];
                                            $preferredLabels = [
                                                'ms' => 'Bahasa Melayu',
                                                'ar' => 'Bahasa Arab',
                                                'en' => 'Bahasa Inggeris',
                                                'id' => 'Bahasa Indonesia',
                                                'zh' => 'Bahasa Cina',
                                                'ta' => 'Bahasa Tamil',
                                                'jv' => 'Bahasa Jawa',
                                            ];

                                            $getLanguages = fn () => \Nnjeim\World\Models\Language::query()
                                                ->whereIn('code', $preferredOrder)
                                                ->get()
                                                ->sortBy(fn ($lang) => array_search($lang->code, $preferredOrder))
                                                ->mapWithKeys(function ($lang) use ($preferredLabels): array {
                                                    $label = $preferredLabels[$lang->code]
                                                        ?? $lang->name
                                                        ?? Str::upper($lang->code);

                                                    return [$lang->id => $label];
                                                })
                                                ->toArray();

                                            return Cache::remember('submit_languages_v2', 3600, $getLanguages);
                                        }),

                                    Toggle::make('children_allowed')
                                        ->label(__('Kanak-kanak Dibenarkan'))
                                        ->helperText(__('Adakah ibu bapa boleh membawa anak kecil ke majlis ini?'))
                                        ->default(true)
                                        ->inline(false)
                                        ->disabled(function (Get $get): bool {
                                            $ageGroups = $this->normalizeAgeGroupState($get('age_group'));

                                            return in_array(EventAgeGroup::Children->value, $ageGroups, true) ||
                                                in_array(EventAgeGroup::AllAges->value, $ageGroups, true);
                                        })
                                        ->dehydrated(),

                                    Toggle::make('is_muslim_only')
                                        ->label(__('Terbuka untuk Muslim Sahaja'))
                                        ->helperText(__('Pilih jika majlis ini hanya terbuka untuk penganut agama Islam.'))
                                        ->inline(false)
                                        ->default(false),
                                ]),
                        ]),

                    Step::make(__('Kategori & Bidang'))
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Select::make('domain_tags')
                                        ->label(__('Kategori'))
                                        ->helperText(__('Pilih kategori ceramah utama. Boleh pilih lebih daripada satu.'))
                                        ->closeOnSelect()
                                        ->placeholder(__('Pilih kategori…'))
                                        ->multiple()
                                        ->searchable(false)
                                        ->preload()
                                        ->native(false)
                                        ->getOptionLabelsUsing(function (array $values): array {
                                            $labels = [];
                                            $uuids = [];

                                            foreach ($values as $value) {
                                                if (is_string($value) && ! Str::isUuid($value)) {
                                                    $labels[$value] = $value;
                                                } else {
                                                    $uuids[] = $value;
                                                }
                                            }

                                            if (! empty($uuids)) {
                                                $labels = array_merge($labels, Tag::whereIn('id', $uuids)->get()->pluck('name', 'id')->toArray());
                                            }

                                            return $labels;
                                        })
                                        ->options(fn () => Cache::remember('submit_tags_domain_'.app()->getLocale(), 60, fn () => Tag::query()
                                            ->where('type', TagType::Domain)
                                            ->whereIn('status', ['verified', 'pending'])
                                            ->orderBy('order_column')
                                            ->get()
                                            ->mapWithKeys(fn (Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                        ->rules(['max:3'])
                                        ->validationMessages([
                                            'max' => __('Maksimum 3 kategori sahaja.'),
                                        ]),

                                    Select::make('discipline_tags')
                                        ->label(__('Bidang Ilmu'))
                                        ->helperText(__('Pilih bidang yang menggambarkan isi ceramah.'))
                                        ->placeholder(__('Pilih atau taip untuk tambah bidang…'))
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->allowHtml()
                                        ->options(fn () => Cache::remember('submit_tags_discipline_verified_'.app()->getLocale(), 60, fn () => Tag::query()
                                            ->where('type', TagType::Discipline)
                                            ->where('status', 'verified')
                                            ->orderBy('order_column')
                                            ->get()
                                            ->mapWithKeys(fn (Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                        ->getSearchResultsUsing(function (string $search): array {
                                            if (blank($search)) {
                                                // No search, let options() handle preload
                                                return [];
                                            }

                                            $results = Tag::query()
                                                ->where('type', TagType::Discipline)
                                                ->where('status', 'verified')
                                                ->where('name', 'like', "%{$search}%")
                                                ->limit(20)
                                                ->get()
                                                ->pluck('name', 'id')
                                                ->toArray();

                                            return ["__quick_add__{$search}" => "<span class='text-primary-600'>+ ".__('Tambah')." '{$search}'</span>"] + $results;
                                        })
                                        ->getOptionLabelsUsing(function (array $values): array {
                                            $labels = [];
                                            $uuids = [];

                                            foreach ($values as $value) {
                                                if (is_string($value) && ! Str::isUuid($value)) {
                                                    $labels[$value] = $value;
                                                } else {
                                                    $uuids[] = $value;
                                                }
                                            }

                                            if (! empty($uuids)) {
                                                $labels = array_merge($labels, Tag::whereIn('id', $uuids)->get()->pluck('name', 'id')->toArray());
                                            }

                                            return $labels;
                                        })
                                        ->afterStateUpdatedJs(<<<'JS'
                                            if (Array.isArray($state)) {
                                                const hasQuickAdd = $state.some(v => typeof v === 'string' && v.startsWith('__quick_add__'));
                                                if (hasQuickAdd) {
                                                    const cleaned = $state.map(v => (typeof v === 'string' && v.startsWith('__quick_add__')) ? v.substring(13) : v);
                                                    $set('discipline_tags', cleaned);
                                                    $nextTick(() => {
                                                        const wrapper = $el.querySelector('[wire\\:ignore]');
                                                        if (wrapper) {
                                                            Alpine.$data(wrapper)?.select?.closeDropdown();
                                                        }
                                                    });
                                                }
                                            }
                                        JS),
                                ]),

                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Select::make('source_tags')
                                        ->closeOnSelect()
                                        ->label(__('Sumber Utama'))
                                        ->helperText(__('Pilih sumber rujukan utama (jika ada).'))
                                        ->placeholder(__('Pilih sumber…'))
                                        ->multiple()
                                        ->preload()
                                        ->searchable(false)
                                        ->native(false)
                                        ->getOptionLabelsUsing(function (array $values): array {
                                            $labels = [];
                                            $uuids = [];

                                            foreach ($values as $value) {
                                                if (is_string($value) && ! Str::isUuid($value)) {
                                                    $labels[$value] = $value;
                                                } else {
                                                    $uuids[] = $value;
                                                }
                                            }

                                            if (! empty($uuids)) {
                                                $labels = array_merge($labels, Tag::whereIn('id', $uuids)->get()->pluck('name', 'id')->toArray());
                                            }

                                            return $labels;
                                        })
                                        ->options(fn () => Cache::remember('submit_tags_source_'.app()->getLocale(), 60, fn () => Tag::query()
                                            ->where('type', TagType::Source)
                                            ->whereIn('status', ['verified', 'pending'])
                                            ->orderBy('order_column')
                                            ->get()
                                            ->mapWithKeys(fn (Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())]))),

                                    Select::make('issue_tags')
                                        ->label(__('Tema / Isu'))
                                        ->helperText(__('Pilih tema supaya mudah dicari.'))
                                        ->placeholder(__('Pilih atau taip untuk tambah tema…'))
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->allowHtml()
                                        ->options(fn () => Cache::remember('submit_tags_issue_verified_'.app()->getLocale(), 60, fn () => Tag::query()
                                            ->where('type', TagType::Issue)
                                            ->where('status', 'verified')
                                            ->orderBy('order_column')
                                            ->get()
                                            ->mapWithKeys(fn (Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                        ->getSearchResultsUsing(function (string $search): array {
                                            if (blank($search)) {
                                                // No search, let options() handle preload
                                                return [];
                                            }

                                            $results = Tag::query()
                                                ->where('type', TagType::Issue)
                                                ->where('status', 'verified')
                                                ->where('name', 'like', "%{$search}%")
                                                ->limit(20)
                                                ->get()
                                                ->pluck('name', 'id')
                                                ->toArray();

                                            return ["__quick_add__{$search}" => "<span class='text-primary-600'>+ ".__('Tambah')." '{$search}'</span>"] + $results;
                                        })
                                        ->getOptionLabelsUsing(function (array $values): array {
                                            $labels = [];
                                            $uuids = [];

                                            foreach ($values as $value) {
                                                if (is_string($value) && ! Str::isUuid($value)) {
                                                    $labels[$value] = $value;
                                                } else {
                                                    $uuids[] = $value;
                                                }
                                            }

                                            if (! empty($uuids)) {
                                                $labels = array_merge($labels, Tag::whereIn('id', $uuids)->get()->pluck('name', 'id')->toArray());
                                            }

                                            return $labels;
                                        })
                                        ->afterStateUpdatedJs(<<<'JS'
                                            if (Array.isArray($state)) {
                                                const hasQuickAdd = $state.some(v => typeof v === 'string' && v.startsWith('__quick_add__'));
                                                if (hasQuickAdd) {
                                                    const cleaned = $state.map(v => (typeof v === 'string' && v.startsWith('__quick_add__')) ? v.substring(13) : v);
                                                    $set('issue_tags', cleaned);
                                                    $nextTick(() => {
                                                        const wrapper = $el.querySelector('[wire\\:ignore]');
                                                        if (wrapper) {
                                                            Alpine.$data(wrapper)?.select?.closeDropdown();
                                                        }
                                                    });
                                                }
                                            }
                                        JS),
                                ]),

                            Select::make('references')
                                ->label(__('Rujukan Kitab'))
                                ->helperText(__('Pilih kitab atau buku rujukan yang digunakan (jika ada).'))
                                ->placeholder(__('Cari atau pilih rujukan…'))
                                ->multiple()
                                ->closeOnSelect()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->relationship('references', 'title', fn (Builder $query) => $query->where('is_active', true))
                                ->createOptionForm([
                                    TextInput::make('title')
                                        ->label(__('Tajuk Kitab / Buku'))
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder(__('cth: Riyadhus Solihin, Ihya Ulumiddin')),
                                    TextInput::make('author')
                                        ->label(__('Pengarang'))
                                        ->maxLength(255)
                                        ->placeholder(__('cth: Imam Nawawi, Imam Ghazali')),
                                    Select::make('type')
                                        ->label(__('Jenis'))
                                        ->options([
                                            'kitab' => __('Kitab Turath'),
                                            'book' => __('Buku Moden'),
                                            'article' => __('Artikel'),
                                            'video' => __('Video'),
                                            'other' => __('Lain-lain'),
                                        ])
                                        ->default('kitab'),
                                    TextInput::make('publication_year')
                                        ->label(__('Tahun Terbitan'))
                                        ->numeric()
                                        ->minValue(1000)
                                        ->maxValue((int) now()->addYears(1)->format('Y'))
                                        ->placeholder(__('cth: 2018')),
                                    TextInput::make('publisher')
                                        ->label(__('Penerbit'))
                                        ->maxLength(255)
                                        ->placeholder(__('cth: Dar al-Kutub')),
                                    TextInput::make('reference_url')
                                        ->label(__('Pautan Rujukan'))
                                        ->url()
                                        ->maxLength(255)
                                        ->placeholder(__('https://...')),
                                    SpatieMediaLibraryFileUpload::make('front_cover')
                                        ->label(__('Muka Depan'))
                                        ->collection('front_cover')
                                        ->image()
                                        ->imageEditor()
                                        ->conversion('thumb')
                                        ->responsiveImages(),
                                    SpatieMediaLibraryFileUpload::make('back_cover')
                                        ->label(__('Muka Belakang'))
                                        ->collection('back_cover')
                                        ->image()
                                        ->imageEditor()
                                        ->conversion('thumb')
                                        ->responsiveImages(),
                                    SpatieMediaLibraryFileUpload::make('gallery')
                                        ->label(__('Galeri'))
                                        ->collection('gallery')
                                        ->multiple()
                                        ->image()
                                        ->imageEditor()
                                        ->conversion('gallery_thumb')
                                        ->responsiveImages()
                                        ->maxFiles(5)
                                        ->helperText(__('Sehingga 5 gambar tambahan')),
                                    Textarea::make('description')
                                        ->label(__('Keterangan Ringkas'))
                                        ->rows(3)
                                        ->placeholder(__('Nota ringkas tentang rujukan ini…'))
                                        ->columnSpanFull(),
                                ])
                                ->createOptionUsing(function (array $data, Schema $schema): string {
                                    $reference = Reference::create([
                                        'title' => $data['title'],
                                        'author' => $data['author'] ?? null,
                                        'type' => $data['type'] ?? 'kitab',
                                        'publication_year' => filled($data['publication_year'] ?? null) ? (string) $data['publication_year'] : null,
                                        'publisher' => $data['publisher'] ?? null,
                                        'description' => $data['description'] ?? null,
                                        'is_canonical' => false,
                                        'status' => 'pending',
                                        'is_active' => true,
                                    ]);

                                    // Save media uploads via Filament's relationship-saving mechanism
                                    $schema?->model($reference)->saveRelationships();

                                    // Save reference URL as social media link
                                    if (! empty($data['reference_url'])) {
                                        $reference->socialMedia()->create([
                                            'platform' => 'website',
                                            'url' => $data['reference_url'],
                                        ]);
                                    }

                                    return (string) $reference->getKey();
                                }),
                        ]),

                    Step::make(__('Penganjur & Lokasi'))
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Section::make(__('Penganjur'))
                                ->schema([
                                    Radio::make('organizer_type')
                                        ->label(__('Jenis Penganjur'))
                                        ->required()
                                        ->options([
                                            'institution' => __('Institusi'),
                                            'speaker' => __('Penceramah'),
                                        ])
                                        ->default('institution')
                                        ->inline(),

                                    Select::make('organizer_institution_id')
                                        ->label(__('Institusi'))
                                        ->options(fn () => Cache::remember('submit_institutions', 60, fn () => Institution::whereIn('status', ['verified', 'pending'])->where('is_active', true)->pluck('name', 'id')))
                                        ->searchable()
                                        ->preload()
                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'institution'
                                                            JS)
                                        ->required(fn (Get $get): bool => $get('organizer_type') === 'institution')
                                        ->createOptionForm(InstitutionFormSchema::createOptionForm())
                                        ->createOptionUsing(fn (array $data, Schema $schema): string => InstitutionFormSchema::createOptionUsing($data, $schema)),

                                    Select::make('organizer_speaker_id')
                                        ->label(__('Penceramah'))
                                        ->options(fn () => Cache::remember('submit_speakers', 60, fn () => Speaker::query()
                                            ->whereIn('status', ['verified', 'pending'])
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                                            ->all()))
                                        ->searchable()
                                        ->preload()
                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'speaker'
                                                            JS)
                                        ->required(fn (Get $get): bool => $get('organizer_type') === 'speaker')
                                        ->afterStateUpdatedJs(<<<'JS'
                                                            if ($state) {
                                                                const currentSpeakers = $get('speakers') || []
                                                                if (!currentSpeakers.includes($state)) {
                                                                    $set('speakers', [...currentSpeakers, $state])
                                                                }
                                                            }
                                                            JS)
                                        ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                        ->createOptionUsing(function (array $data, Schema $schema, Set $set, Get $get): string {
                                            return SpeakerFormSchema::createOptionUsing($data, $schema);
                                        }),
                                ]),

                            Section::make(__('Lokasi'))
                                ->visibleJs(<<<'JS'
                                                    $get('event_format') !== 'online' && $get('organizer_type')
                                                    JS)
                                ->schema([
                                    Toggle::make('location_same_as_institution')
                                        ->label(__('Sama seperti institusi penganjur'))
                                        ->default(true)
                                        ->inline(false)
                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'institution'
                                                            JS),

                                    Radio::make('location_type')
                                        ->label(__('Jenis Lokasi'))
                                        ->options([
                                            'institution' => __('Institusi'),
                                            'venue' => __('Tempat'),
                                        ])
                                        ->inline()
                                        ->default('institution')
                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'speaker' || !$get('location_same_as_institution')
                                                            JS)
                                        ->required(fn (Get $get): bool => ($get('organizer_type') === 'speaker' || ! $get('location_same_as_institution')) && $get('event_format') !== 'online'),

                                    Select::make('location_institution_id')
                                        ->label(__('Institusi'))
                                        ->options(fn () => Cache::remember('submit_institutions', 60, fn () => Institution::whereIn('status', ['verified', 'pending'])->where('is_active', true)->pluck('name', 'id')))
                                        ->searchable()
                                        ->preload()
                                        ->visibleJs(<<<'JS'
                                                            ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'institution'
                                                            JS)
                                        ->required(fn (Get $get): bool => ($get('organizer_type') === 'speaker' || ! $get('location_same_as_institution')) && $get('location_type') === 'institution')
                                        ->createOptionForm(InstitutionFormSchema::createOptionForm())
                                        ->createOptionUsing(fn (array $data, Schema $schema): string => InstitutionFormSchema::createOptionUsing($data, $schema)),

                                    Select::make('location_venue_id')
                                        ->label(__('Lokasi'))
                                        ->options(fn () => Cache::remember('submit_venues', 60, fn () => Venue::whereIn('status', ['verified', 'pending'])->where('is_active', true)->pluck('name', 'id')))
                                        ->searchable()
                                        ->preload()
                                        ->visibleJs(<<<'JS'
                                                            ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'venue'
                                                            JS)
                                        ->required(fn (Get $get): bool => ($get('organizer_type') === 'speaker' || ! $get('location_same_as_institution')) && $get('location_type') === 'venue')
                                        ->createOptionForm(VenueFormSchema::createOptionForm())
                                        ->createOptionUsing(fn (array $data, Schema $schema): string => VenueFormSchema::createOptionUsing($data, $schema)),

                                    Select::make('space_id')
                                        ->label(__('Ruang'))
                                        ->helperText(__('Pilihan: Pilih ruang tertentu di dalam institusi (cth: Dewan Utama, Ruang Solat).'))
                                        ->placeholder(__('Pilih ruang…'))
                                        ->searchable()
                                        ->preload()
                                        ->visibleJs(<<<'JS'
                                                            // Show space only for institution locations, not venues
                                                            ($get('organizer_type') === 'institution' && ($get('location_same_as_institution') !== false)) || 
                                                            (($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'institution')
                                                            JS)
                                        ->options(fn (): array => Space::query()
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->toArray()
                                        ),
                                ]),
                        ]),

                    Step::make(__('Penceramah & Media'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Section::make(__('Penceramah'))
                                ->schema([
                                    Select::make('speakers')
                                        ->label(__('Pilih Penceramah'))
                                        ->required()
                                        ->multiple()
                                        ->closeOnSelect()
                                        ->relationship('speakers', 'name', fn (Builder $query) => $query->whereIn('status', ['verified', 'pending'])->where('is_active', true))
                                        ->searchable()
                                        ->preload()
                                        ->getOptionLabelUsing(fn (mixed $value): ?string => Speaker::query()->find($value)?->formatted_name)
                                        ->getOptionLabelsUsing(fn (array $values): array => Speaker::query()
                                            ->whereIn('id', $values)
                                            ->get()
                                            ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                                            ->toArray())
                                        ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                        ->createOptionUsing(fn (array $data, Schema $schema): string => SpeakerFormSchema::createOptionUsing($data, $schema)),
                                ]),

                            Section::make(__('Media'))
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('poster')
                                        ->label(__('Gambar Utama'))
                                        ->collection('poster')
                                        ->image()
                                        ->imageEditor()
                                        ->imageAspectRatio(['3:2', '4:5'])
                                        ->imageEditorAspectRatioOptions(['3:2', '4:5'])
                                        ->conversion('thumb')
                                        ->responsiveImages()
                                        ->helperText(__('Gambar utama untuk paparan majlis.')),
                                    SpatieMediaLibraryFileUpload::make('gallery')
                                        ->label(__('Galeri'))
                                        ->collection('gallery')
                                        ->multiple()
                                        ->reorderable()
                                        ->maxFiles(10)
                                        ->image()
                                        ->imageEditor()
                                        ->conversion('thumb')
                                        ->responsiveImages()
                                        ->helperText(__('Gambar tambahan untuk galeri majlis.')),
                                ])
                                ->columns(['default' => 1, 'sm' => 2]),
                        ]),

                    Step::make(__('Semak & Hantar'))
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Hidden::make('timezone')
                                ->dehydrated()
                                ->default(fn (): string => $this->resolveUserTimezone()),

                            Hidden::make('captcha_token')
                                ->dehydrated(),

                            Section::make(__('Pratonton Penghantaran'))
                                ->description(__('Semak ringkasan ini sebelum anda menghantar.'))
                                ->schema([
                                    SchemaView::make('components.pages.submit-event.partials.review-preview'),
                                ]),

                            Section::make(__('Maklumat Anda'))
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2])
                                        ->schema([
                                            TextInput::make('submitter_name')
                                                ->label(__('Nama Anda'))
                                                ->required()
                                                ->maxLength(100),

                                            TextInput::make('submitter_email')
                                                ->label(__('Email'))
                                                ->email()
                                                ->maxLength(255)
                                                ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_phone'))),

                                            TextInput::make('submitter_phone')
                                                ->label(__('Telefon'))
                                                ->tel()
                                                ->maxLength(20)
                                                ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_email'))),
                                        ]),
                                ])
                                ->visible(fn () => ! auth()->check()),

                            Section::make(__('Nota untuk Pentadbir'))
                                ->description(__('Pilihan: Tambah maklumat atau permintaan khas untuk moderator.'))
                                ->schema([
                                    Textarea::make('notes')
                                        ->label(__('Nota'))
                                        ->rows(3)
                                        ->maxLength(1000)
                                        ->placeholder(__('cth: Keperluan khas, maklumat tambahan, atau apa sahaja yang perlu diketahui moderator...'))
                                        ->helperText(__('Maksimum 1000 aksara')),
                                ]),

                            Callout::make(__('Semakan Moderator'))
                                ->description(__('Majlis anda akan disemak oleh moderator kami dalam tempoh 24-48 jam. Anda akan dimaklumkan melalui e-mel setelah majlis diluluskan.'))
                                ->info(),
                        ]),
                ])
                    ->skippable()
                    ->persistStepInQueryString()
                    ->extraAlpineAttributes([
                        'x-init' => <<<'JS'
                            const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone

                            if (browserTimezone && $wire.get('data.timezone') !== browserTimezone) {
                                $wire.$set('data.timezone', browserTimezone)
                            }

                            window.__submitEventReviewRefreshed ??= false

                            $watch('step', () => {
                                if (isLastStep()) {
                                    if (window.__submitEventReviewRefreshed) {
                                        return
                                    }

                                    window.__submitEventReviewRefreshed = true
                                    $wire.$refresh()

                                    return
                                }

                                window.__submitEventReviewRefreshed = false
                            })
                        JS,
                    ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                                        <x-filament::button
                                            type="submit"
                                            size="lg"
                                            color="success"
                                            class="w-full"
                                        >
                                            {{ __('Hantar Majlis untuk Semakan') }}
                                        </x-filament::button>
                                    BLADE))),
            ])
            ->statePath('data');
    }

    public function submit(): mixed
    {
        $validated = $this->form->getState();
        $this->assertCaptchaIsValid($validated['captcha_token'] ?? null);

        // Enforce children_allowed when AllAges or Children is selected
        $ageGroups = $validated['age_group'] ?? [];
        if (
            in_array(EventAgeGroup::Children->value, $ageGroups, true) ||
            in_array(EventAgeGroup::AllAges->value, $ageGroups, true)
        ) {
            $validated['children_allowed'] = true;
        }

        $startsAt = $this->resolveStartsAt($validated);
        $timezone = $this->resolveUserTimezone($validated['timezone'] ?? null);

        if (
            $this->hasCommunityEventTypeSelection($validated['event_type'] ?? [])
            && (($validated['event_format'] ?? EventFormat::Physical->value) !== EventFormat::Physical->value)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.event_format' => __('Jenis majlis komuniti mesti menggunakan format fizikal.'),
            ]);
        }

        // Validate contextual prayer time constraints (client-side filtering removed)
        $prayerTimeRaw = $validated['prayer_time'] ?? '';
        $selectedPrayer = $prayerTimeRaw instanceof EventPrayerTime
            ? $prayerTimeRaw
            : EventPrayerTime::tryFrom($prayerTimeRaw);
        $eventDate = Carbon::parse($validated['event_date'], $timezone)->startOfDay();

        if (
            in_array($selectedPrayer, [EventPrayerTime::SebelumJumaat, EventPrayerTime::SelepasJumaat], true)
            && ! $eventDate->isFriday()
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.prayer_time' => __('Pilihan waktu Jumaat hanya boleh dipilih untuk hari Jumaat.'),
            ]);
        }

        if ($selectedPrayer === EventPrayerTime::SebelumMaghrib && ! $this->isRamadhan($eventDate, $timezone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.prayer_time' => __('Sebelum Maghrib hanya boleh dipilih semasa bulan Ramadhan.'),
            ]);
        }

        if ($selectedPrayer === EventPrayerTime::SelepasTarawih && ! $this->isRamadhan($eventDate, $timezone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.prayer_time' => __('Selepas Tarawih hanya boleh dipilih semasa bulan Ramadhan.'),
            ]);
        }

        $organizerType = null;
        $organizerId = null;
        $targetInstitutionId = null;
        $targetVenueId = null;
        $locationInstitutionId = null;

        $locationType = $validated['location_type'] ?? 'institution';
        $locationInstitutionId = $this->data['location_institution_id'] ?? null;
        $venueId = $this->data['location_venue_id'] ?? null;

        // If it was institution, we check if it was pre-selected or created
        if ($locationType === 'institution' && $locationInstitutionId) {
            $venueId = null;
        } elseif ($locationType === 'venue' && $venueId) {
            $locationInstitutionId = null;
        }

        if (($validated['organizer_type'] ?? null) === 'institution' && ! empty($validated['organizer_institution_id'])) {
            $organizerType = Institution::class;
            $organizerId = $validated['organizer_institution_id'];

            \Illuminate\Support\Facades\Log::info('Location logic check', [
                'location_same_as_institution' => $validated['location_same_as_institution'] ?? 'not set',
                'organizer_type' => $validated['organizer_type'] ?? 'not set',
            ]);
            if (($validated['location_same_as_institution'] ?? true) == true) {
                $targetInstitutionId = $this->data['organizer_institution_id'];
                $targetVenueId = null;
            } else {
                if (($validated['location_type'] ?? null) === 'institution') {
                    $targetInstitutionId = $this->data['location_institution_id'] ?? null;
                    $targetVenueId = null;
                } else {
                    $targetVenueId = $this->data['location_venue_id'] ?? null;
                    $targetInstitutionId = null;
                }
            }
        } elseif (($validated['organizer_type'] ?? null) === 'speaker' && ! empty($validated['organizer_speaker_id'])) {
            $organizerType = Speaker::class;
            $organizerId = $validated['organizer_speaker_id'];

            if ($locationInstitutionId) {
                $targetInstitutionId = $locationInstitutionId;
                $targetVenueId = null;
            } elseif ($venueId) {
                $targetInstitutionId = null;
                $targetVenueId = $venueId;
            }
        }

        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);
        $prayerReference = $prayerTime?->toPrayerReference();
        $prayerOffset = $prayerTime?->getDefaultOffset();
        $prayerDisplayText = $prayerTime && ! $prayerTime->isCustomTime() ? $prayerTime->getLabel() : null;

        $this->validateEndsAtAfterStartsAt($validated, $startsAt, $timezone);

        // Validate that the resolved start time is not in the past
        $now = Carbon::now($timezone);
        if ($startsAt->lessThanOrEqualTo($now)) {
            $errorField = $prayerTime?->isCustomTime() ? 'data.custom_time' : 'data.prayer_time';
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorField => __('Waktu majlis yang dipilih telah berlalu. Sila pilih waktu lain.'),
            ]);
        }

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(7),
            'description' => $validated['description'] ?? null,
            'timezone' => $timezone,
            'starts_at' => $startsAt,
            'ends_at' => $this->resolveEndsAt($validated, $startsAt, $timezone),
            'institution_id' => $targetInstitutionId,
            'venue_id' => $targetVenueId,
            'space_id' => $validated['space_id'] ?? null,
            'event_type' => $validated['event_type'] ?? [\App\Enums\EventType::KuliahCeramah],
            'gender' => $validated['gender'] ?? EventGenderRestriction::All->value,
            'age_group' => $validated['age_group'] ?? [EventAgeGroup::AllAges],
            'children_allowed' => $validated['children_allowed'] ?? true,
            'is_muslim_only' => $validated['is_muslim_only'] ?? false,
            'timing_mode' => $prayerTime?->isCustomTime() ? 'absolute' : 'prayer_relative',
            'prayer_reference' => $prayerReference?->value,
            'prayer_offset' => $prayerOffset?->value,
            'prayer_display_text' => $prayerDisplayText,
            'organizer_type' => $organizerType,
            'organizer_id' => $organizerId,
            'event_format' => $validated['event_format'] ?? EventFormat::Physical->value,
            'event_url' => $validated['event_url'] ?? null,
            'live_url' => $validated['live_url'] ?? null,
            'visibility' => $validated['visibility'] ?? EventVisibility::Public,
            'submitter_id' => auth()->id(),
        ]);

        // Attach selected space to institution if not already attached
        if (! empty($validated['space_id']) && ! empty($event->institution_id)) {
            $institution = Institution::find($event->institution_id);
            if ($institution && ! $institution->spaces()->where('spaces.id', $validated['space_id'])->exists()) {
                $institution->spaces()->attach($validated['space_id']);
            }
        }

        if (! empty($validated['speakers'])) {
            $event->speakers()->attach($validated['speakers']);
        }

        // Sync selected languages
        if (! empty($validated['languages'])) {
            $event->syncLanguages($validated['languages']);
        }

        // Attach tags (merge all selected tags from 4 type fields, resolving quick-add text values)
        $tagFieldMap = [
            'discipline_tags' => TagType::Discipline,
            'issue_tags' => TagType::Issue,
        ];

        $allTagIds = collect(array_merge(
            $validated['domain_tags'] ?? [],
            $validated['source_tags'] ?? [],
        ))
            ->filter(fn (mixed $value): bool => is_string($value) && Str::isUuid($value))
            ->values()
            ->all();

        // Resolve quick-add text values (non-UUID) to real tag records
        foreach ($tagFieldMap as $field => $tagType) {
            foreach ($validated[$field] ?? [] as $value) {
                if (Str::isUuid($value)) {
                    $allTagIds[] = $value;
                } else {
                    // Find or create tag from plain text
                    $tag = Tag::where('type', $tagType->value)
                        ->whereRaw("LOWER(name->>'ms') = ?", [strtolower($value)])
                        ->first();

                    if (! $tag) {
                        $tag = Tag::create([
                            'name' => ['ms' => $value, 'en' => $value],
                            'type' => $tagType->value,
                            'status' => 'pending',
                        ]);
                    }

                    $allTagIds[] = (string) $tag->id;
                }
            }
        }

        $allTagIds = collect($allTagIds)
            ->filter(fn (mixed $value): bool => is_string($value) && Str::isUuid($value))
            ->unique()
            ->values()
            ->all();

        if ($allTagIds !== []) {
            $tags = Tag::whereIn('id', $allTagIds)->get();
            $event->syncTags($tags);
        }

        $this->form->model($event);
        $this->form->saveRelationships();

        $submitterName = $validated['submitter_name'] ?? auth()->user()?->name;

        $submission = EventSubmission::create([
            'event_id' => $event->id,
            'submitter_name' => $submitterName,
            'submitted_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        if (! auth()->check()) {
            $this->storeSubmitterContacts($submission, $validated);
        }

        // Transition from Draft → Pending (notifies moderators)
        $event->status->transitionTo(\App\States\EventStatus\Pending::class);

        session()->flash('event_title', $event->title);

        return redirect()->route('submit-event.success');
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeAgeGroupState(mixed $state): array
    {
        if ($state instanceof \Illuminate\Support\Collection) {
            $state = $state->all();
        }

        if (! is_array($state)) {
            $state = [$state];
        }

        return collect($state)
            ->map(function (mixed $ageGroup): ?string {
                if ($ageGroup instanceof EventAgeGroup) {
                    return $ageGroup->value;
                }

                if (is_string($ageGroup) && filled($ageGroup)) {
                    return $ageGroup;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function hasCommunityEventTypeSelection(mixed $eventTypes): bool
    {
        if ($eventTypes instanceof \Illuminate\Support\Collection) {
            $eventTypes = $eventTypes->all();
        }

        if (! is_array($eventTypes)) {
            $eventTypes = [$eventTypes];
        }

        foreach ($eventTypes as $eventTypeValue) {
            $eventType = $eventTypeValue instanceof EventType
                ? $eventTypeValue
                : EventType::tryFrom((string) $eventTypeValue);

            if ($eventType?->isCommunity()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the starts_at datetime from event_date and prayer_time/custom_time.
     *
     * @param  array{event_date: string, prayer_time: string|EventPrayerTime, custom_time?: string|null, timezone?: string|null}  $validated
     */
    protected function resolveStartsAt(array $validated): Carbon
    {
        $timezone = $this->resolveUserTimezone($validated['timezone'] ?? null);
        $eventDate = Carbon::parse($validated['event_date'], $timezone)->startOfDay();
        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);

        if ($prayerTime?->isCustomTime() && ! empty($validated['custom_time'])) {
            $time = Carbon::parse($validated['custom_time']);

            return $eventDate->setTime($time->hour, $time->minute)->utc();
        }

        $defaultTimes = $this->getDefaultPrayerTimes();

        $timeString = $defaultTimes[$prayerTime?->value ?? ''] ?? '20:00';
        $time = Carbon::parse($timeString);

        return $eventDate->setTime($time->hour, $time->minute)->utc();
    }

    /**
     * Resolve the ends_at datetime from end_time using the same date as starts_at.
     *
     * @param  array{end_time?: string|null}  $validated
     */
    protected function resolveEndsAt(array $validated, Carbon $startsAt, string $timezone): ?Carbon
    {
        $endTimeValue = $validated['end_time'] ?? null;

        if (empty($endTimeValue)) {
            return null;
        }

        $time = Carbon::parse($endTimeValue);
        $startInUserTimezone = $startsAt->copy()->setTimezone($timezone);

        return $startInUserTimezone->setTime($time->hour, $time->minute)->utc();
    }

    protected function validateEndsAtAfterStartsAt(array $validated, Carbon $startsAt, string $timezone): void
    {
        $endTimeValue = $validated['end_time'] ?? null;

        if (! is_string($endTimeValue) || $endTimeValue === '') {
            return;
        }

        $endTime = Carbon::parse($endTimeValue);
        $startInUserTimezone = $startsAt->copy()->setTimezone($timezone);
        $endInUserTimezone = $startInUserTimezone->copy()->setTime($endTime->hour, $endTime->minute);

        if ($endInUserTimezone->lessThanOrEqualTo($startInUserTimezone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.end_time' => __('Masa akhir mestilah selepas masa mula.'),
            ]);
        }
    }

    protected function resolveStartTimeForComparison(mixed $prayerTimeValue, mixed $customTime): ?string
    {
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom((string) $prayerTimeValue);

        if ($prayerTime?->isCustomTime()) {
            return is_string($customTime) && $customTime !== '' ? $customTime : null;
        }

        if (! $prayerTime instanceof EventPrayerTime) {
            return null;
        }

        return $this->getDefaultPrayerTimes()[$prayerTime->value] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultPrayerTimes(): array
    {
        return [
            EventPrayerTime::SelepasSubuh->value => '06:30',
            EventPrayerTime::SelepasZuhur->value => '13:30',
            EventPrayerTime::SebelumJumaat->value => '13:45',
            EventPrayerTime::SelepasJumaat->value => '14:00',
            EventPrayerTime::SelepasAsar->value => '17:00',
            EventPrayerTime::SebelumMaghrib->value => '19:45',
            EventPrayerTime::SelepasMaghrib->value => '20:00',
            EventPrayerTime::SelepasIsyak->value => '21:30',
            EventPrayerTime::SelepasTarawih->value => '22:30',
        ];
    }

    /**
     * Check if a given date falls within Ramadhan.
     * This uses approximate Gregorian dates for Ramadhan periods.
     */
    protected function isRamadhan(Carbon $date, ?string $timezone = null): bool
    {
        $timezone = $this->resolveUserTimezone($timezone);
        $year = $date->year;

        // Ramadhan dates for common years (approximate)
        $ramadhanPeriods = [
            2026 => ['start' => '02-18', 'end' => '03-19'],
            2027 => ['start' => '02-07', 'end' => '03-08'],
            2028 => ['start' => '01-27', 'end' => '02-25'],
            2029 => ['start' => '01-16', 'end' => '02-13'],
            2030 => ['start' => '01-05', 'end' => '02-03'],
        ];

        if (! isset($ramadhanPeriods[$year])) {
            return false;
        }

        $period = $ramadhanPeriods[$year];
        $startDate = Carbon::parse("{$year}-{$period['start']}", $timezone)->startOfDay();
        $endDate = Carbon::parse("{$year}-{$period['end']}", $timezone)->endOfDay();

        return $date->between($startDate, $endDate);
    }

    protected function resolveUserTimezone(?string $preferredTimezone = null): string
    {
        return UserTimezoneResolver::resolve(request(), $preferredTimezone);
    }

    /**
     * @param  array{submitter_email?: string|null, submitter_phone?: string|null}  $validated
     */
    protected function storeSubmitterContacts(EventSubmission $submission, array $validated): void
    {
        $email = $validated['submitter_email'] ?? null;
        $phone = $validated['submitter_phone'] ?? null;

        if (filled($email)) {
            $submission->contacts()->create([
                'type' => ContactType::Main->value,
                'category' => ContactCategory::Email->value,
                'value' => $email,
                'is_public' => false,
            ]);
        }

        if (filled($phone)) {
            $submission->contacts()->create([
                'type' => ContactType::Main->value,
                'category' => ContactCategory::Phone->value,
                'value' => $phone,
                'is_public' => false,
            ]);
        }
    }

    protected function assertCaptchaIsValid(?string $captchaToken): void
    {
        $verifier = app(TurnstileVerifier::class);

        if (! $verifier->verify($captchaToken, request()->ip())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.captcha_token' => __('Sila lengkapkan pengesahan keselamatan sebelum menghantar.'),
            ]);
        }
    }
};
?>

@section('title', __('Hantar Majlis') . ' - ' . config('app.name'))

<style>
    /* Ensure wizard stepper header doesn't clip */
    .fi-sc-wizard-header {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .fi-sc-wizard-header::-webkit-scrollbar {
        height: 3px;
    }

    .fi-sc-wizard-header::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 9999px;
    }

    /* Style grouped select dropdown */
    .fi-dropdown-header {
        font-weight: 600;
        color: #64748b;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }

    .fi-dropdown-list {
        padding-left: 0;
    }

    .fi-dropdown-list-item {
        padding-left: 1.5rem !important;
    }

    /* Hide loading indicators by default - Livewire will show them during actual loading */
    .fi-loading-indicator {
        display: none;
    }
</style>

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="max-w-6xl xl:max-w-7xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Hantar Majlis Ilmu') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Kongsi majlis ilmu dengan komuniti. Penghantaran anda akan disemak sebelum diterbitkan.') }}
                </p>
            </div>

            <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">{{ __('Isi Automatik Dengan AI') }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ __('Muat naik poster, gambar, atau PDF majlis. Kami akan cuba isi borang ini secara automatik dan bawa anda terus ke pratonton.') }}
                </p>

                <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center">
                    <input
                        type="file"
                        wire:model="event_source_attachment"
                        accept=".pdf,image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                    >

                    <x-filament::button
                        type="button"
                        wire:click="extractEventFromMedia"
                        wire:loading.attr="disabled"
                        wire:target="event_source_attachment,extractEventFromMedia"
                        class="whitespace-nowrap"
                    >
                        {{ __('Ekstrak Dengan AI') }}
                    </x-filament::button>
                </div>

                @error('event_source_attachment')
                    <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                @enderror

                <p wire:loading wire:target="extractEventFromMedia" class="mt-2 text-sm text-primary-600">
                    {{ __('Sedang mengekstrak maklumat daripada fail...') }}
                </p>
            </div>

            <form wire:submit="submit" novalidate>
                {{ $this->form }}

                @if(config('services.turnstile.enabled') && filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key')))
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                        <p class="mb-3 text-sm font-semibold text-slate-700">{{ __('Pengesahan Keselamatan') }}</p>
                        <p class="mb-3 text-xs text-slate-500">
                            {{ __('Sila sahkan anda bukan robot sebelum menghantar majlis.') }}
                        </p>
                        <input id="submit-event-captcha-token" type="hidden" wire:model.live="data.captcha_token">
                        <div id="submit-event-turnstile" wire:ignore></div>

                        @error('data.captcha_token')
                            <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </form>

            <x-filament-actions::modals />
        </div>
    </div>
</div>

@if(config('services.turnstile.enabled') && filled(config('services.turnstile.site_key')))
    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
        <script>
            (() => {
                let isRendered = false;

                const setToken = (token) => {
                    const tokenInput = document.getElementById('submit-event-captcha-token');

                    if (!tokenInput) {
                        return;
                    }

                    tokenInput.value = token;
                    tokenInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                };

                const renderTurnstile = () => {
                    const container = document.getElementById('submit-event-turnstile');

                    if (!container || isRendered || typeof window.turnstile === 'undefined') {
                        return;
                    }

                    window.turnstile.render(container, {
                        sitekey: '{{ config('services.turnstile.site_key') }}',
                        callback: (token) => setToken(token),
                        'expired-callback': () => setToken(''),
                        'error-callback': () => setToken(''),
                    });

                    isRendered = true;
                };

                const boot = () => {
                    renderTurnstile();

                    window.setTimeout(renderTurnstile, 400);
                    window.setTimeout(renderTurnstile, 1200);
                };

                document.addEventListener('DOMContentLoaded', boot);
                document.addEventListener('livewire:navigated', boot);
            })();
        </script>
    @endpush
@endif
