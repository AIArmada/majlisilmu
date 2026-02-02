<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\PreNominal;
use App\Enums\VenueType;
use App\Models\District;
use App\Models\Subdistrict;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\EventType;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Topic;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;
    use WithFileUploads;

    public ?array $data = [];
    public ?string $selectedDate = null;
    public array $prayerTimeOptions = [];

    public function mount(): void
    {
        $this->selectedDate = null;
        $this->updatePrayerTimeOptions();
        $defaultPrayerTime = array_key_first($this->prayerTimeOptions) ?? EventPrayerTime::SelepasMaghrib->value;

        $this->form->fill([
            'submitter_name' => auth()->user()?->name,
            'submitter_email' => auth()->user()?->email,
            'children_allowed' => true,
            'gender' => EventGenderRestriction::All->value,
            'age_group' => [EventAgeGroup::AllAges],
            'event_format' => EventFormat::Physical,
            'location_same_as_institution' => true,
        ]);
    }

    public function updatePrayerTimeOptions(): void
    {
        $this->prayerTimeOptions = $this->getPrayerTimeOptions($this->selectedDate);
    }

    public function updateDateAndPrayerTimes(?string $newDate): void
    {
        $this->selectedDate = $newDate;
        $this->updatePrayerTimeOptions();
        $this->data['event_date'] = $newDate;
        
        // Update prayer_time if current selection is no longer valid
        $currentPrayerTime = $this->data['prayer_time'] ?? null;
        if (! $currentPrayerTime || ! array_key_exists($currentPrayerTime, $this->prayerTimeOptions)) {
            $this->data['prayer_time'] = array_key_first($this->prayerTimeOptions);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Event())
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make(__('Event Details'))
                                    ->schema([
                                        Select::make('title')
                                            ->label(__('Tajuk Majlis'))
                                            ->required()
                                            ->searchable()
                                            ->getSearchResultsUsing(fn (string $search): array => Event::query()
                                                ->whereRaw('LOWER(title) LIKE ?', ['%'.strtolower($search).'%'])
                                                ->where('status', 'approved')
                                                ->limit(10)
                                                ->pluck('title', 'title')
                                                ->toArray())
                                            ->getOptionLabelUsing(fn ($value): ?string => $value)
                                            ->allowHtml(false)
                                            ->createOptionForm([
                                                TextInput::make('title')
                                                    ->label(__('Event Title'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Kuliah Maghrib: Tafsir Surah Al-Kahfi')),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => $data['title'])
                                            ->placeholder(__('Search or enter event title...')),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('topics')
                                                    ->label(__('Topics'))
                                                    ->required()
                                                    ->multiple()
                                                    ->options(function (): array {
                                                        $topics = Topic::where('status', 'verified')
                                                            ->with(['parent.parent.parent'])
                                                            ->get();
                                                        
                                                        $grouped = [];
                                                        
                                                        foreach ($topics as $topic) {
                                                            // Skip root level topics (they're just group headers)
                                                            if ($topic->parent_id === null) {
                                                                continue;
                                                            }
                                                            
                                                            // Traverse up to find the root ancestor for grouping
                                                            $current = $topic->parent;
                                                            while ($current && $current->parent_id !== null) {
                                                                $current = $current->parent;
                                                            }
                                                            $groupName = $current->name;
                                                            
                                                            if (!isset($grouped[$groupName])) {
                                                                $grouped[$groupName] = [];
                                                            }
                                                            
                                                            // Format the label based on depth (Level 2 = plain, Level 3+ = arrow)
                                                            if ($topic->parent->parent_id === null) {
                                                                // This is a category (level 2, child of root)
                                                                $label = $topic->name;
                                                            } else {
                                                                // This is a subcategory or deeper (level 3+)
                                                                $label = '  → ' . $topic->name;
                                                            }
                                                            
                                                            $grouped[$groupName][$topic->id] = $label;
                                                        }
                                                        
                                                        return $grouped;
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->createOptionForm([
                                                        TextInput::make('name')
                                                            ->label(__('Topic Name'))
                                                            ->required()
                                                            ->maxLength(100)
                                                            ->placeholder(__('e.g., Tafsir, Hadis, Akhlak')),
                                                    ])
                                                    ->createOptionUsing(function (array $data): string {
                                                        $topic = Topic::create([
                                                            'name' => $data['name'],
                                                            'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                            'status' => 'pending',
                                                            'is_official' => false,
                                                            'parent_id' => null,
                                                        ]);

                                                        return (string) $topic->getKey();
                                                    }),

                                                Select::make('event_type_id')
                                                    ->label(__('Jenis Majlis'))
                                                    ->required()
                                                    ->options(function (): array {
                                                        $types = EventType::where('is_active', true)
                                                            ->with(['parent.parent.parent'])
                                                            ->get();
                                                        
                                                        $grouped = [];
                                                        
                                                        foreach ($types as $type) {
                                                            // Skip root level types (they're just group headers)
                                                            if ($type->parent_id === null) {
                                                                continue;
                                                            }
                                                            
                                                            // Traverse up to find the root ancestor for grouping
                                                            $current = $type->parent;
                                                            while ($current && $current->parent_id !== null) {
                                                                $current = $current->parent;
                                                            }
                                                            $groupName = $current->name;
                                                            
                                                            if (!isset($grouped[$groupName])) {
                                                                $grouped[$groupName] = [];
                                                            }
                                                            
                                                            // Format the label based on depth (Level 2 = plain, Level 3+ = arrow)
                                                            if ($type->parent->parent_id === null) {
                                                                // This is a category (level 2, child of root)
                                                                $label = $type->name;
                                                            } else {
                                                                // This is a subcategory or deeper (level 3+)
                                                                $label = '  → ' . $type->name;
                                                            }
                                                            
                                                            $grouped[$groupName][$type->id] = $label;
                                                        }
                                                        
                                                        return $grouped;
                                                    })
                                                    ->searchable()
                                                    ->preload(),
                                            ]),

                                        Textarea::make('description')
                                            ->label(__('Description'))
                                            ->required()
                                            ->maxLength(5000)
                                            ->rows(4)
                                            ->placeholder(__('Describe the event, topics to be covered, etc.')),    

                                        Grid::make(3)
                                            ->schema([
                                                DatePicker::make('event_date')
                                                    ->label(__('Tarikh'))
                                                    ->required()
                                                    ->native()
                                                    ->timezone('Asia/Kuala_Lumpur')
                                                    ->minDate(now()->startOfDay())
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                                        $this->selectedDate = $state;
                                                        $this->updatePrayerTimeOptions();
                                                        $current = $get('prayer_time');

                                                        if (! $current || ! array_key_exists($current, $this->prayerTimeOptions)) {
                                                            $set('prayer_time', array_key_first($this->prayerTimeOptions));
                                                        }
                                                    })
                                                    ->extraFieldWrapperAttributes([
                                                        'x-data' => '{}',
                                                        'x-on:change' => '$wire.updateDateAndPrayerTimes($event.target.value)',
                                                    ]),

                                                Select::make('prayer_time')
                                                    ->label(__('Waktu'))
                                                    ->required()
                                                    ->options(fn (): array => $this->prayerTimeOptions)
                                                    ->live(),

                                                TimePicker::make('custom_time')
                                                    ->label(__('Masa'))
                                                    ->native()
                                                    ->timezone('Asia/Kuala_Lumpur')
                                                    ->visible(function (Get $get): bool {
                                                        $prayerTime = $get('prayer_time');
                                                        return $prayerTime === EventPrayerTime::LainWaktu;
                                                    })
                                                    ->required(function (Get $get): bool {
                                                        $prayerTime = $get('prayer_time');
                                                        return $prayerTime === EventPrayerTime::LainWaktu;
                                                    })
                                                    ->rule(function (Get $get): Closure {
                                                        return function (string $attribute, $value, Closure $fail) use ($get) {
                                                            $eventDate = $get('event_date');
                                                            $timezone = 'Asia/Kuala_Lumpur';
                                                            $now = Carbon::now($timezone);
                                                            
                                                            if (! $eventDate) {
                                                                return;
                                                            }

                                                            $eventDay = Carbon::parse($eventDate, $timezone)->startOfDay();
                                                            
                                                            // Only validate if event is today
                                                            if ($eventDay->isSameDay($now)) {
                                                                // Parse the time value (format: HH:MM or HH:MM:SS)
                                                                $timeParts = explode(':', $value);
                                                                $selectedTime = $eventDay->copy()
                                                                    ->setHour((int) $timeParts[0])
                                                                    ->setMinute((int) $timeParts[1]);
                                                                
                                                                // Check if selected time is in the past
                                                                if ($selectedTime->lessThan($now)) {
                                                                    $fail(__('Masa yang dipilih tidak boleh pada masa lalu untuk majlis hari ini.'));
                                                                }
                                                            }
                                                        };
                                                    }),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                Radio::make('event_format')
                                                    ->label(__('Event Format'))
                                                    ->required()
                                                    ->options(EventFormat::class)
                                                    ->default(EventFormat::Physical)
                                                    ->inline()
                                                    ->live(),

                                                TextInput::make('event_url')
                                                    ->label(__('Event URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://example.com/event')),

                                                TextInput::make('live_url')
                                                    ->label(__('Live URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://youtube.com/...'))
                                                    ->visible(fn (Get $get): bool => in_array($get('event_format'), [EventFormat::Online, EventFormat::Hybrid], true))
                                                    ->required(fn (Get $get): bool => in_array($get('event_format'), [EventFormat::Online, EventFormat::Hybrid], true)),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('gender')
                                                    ->label(__('Gender'))
                                                    ->required()
                                                    ->options(EventGenderRestriction::class)
                                                    ->default(EventGenderRestriction::All),

                                                Select::make('age_group')
                                                    ->label(__('Age Group'))
                                                    ->required()
                                                    ->options(EventAgeGroup::class)
                                                    ->multiple()
                                                    // ->default([EventAgeGroup::AllAges->value, EventAgeGroup::Adults->value])
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, array|string|null $state): void {
                                                        $ageGroups = collect(is_array($state) ? $state : [$state])
                                                            ->filter()
                                                            ->values();

                                                        if ($ageGroups->contains(EventAgeGroup::Children) || 
                                                            $ageGroups->contains(EventAgeGroup::AllAges)) {
                                                            $set('children_allowed', true);
                                                        }
                                                    }),

                                                Toggle::make('children_allowed')
                                                    ->label(__('Children Allowed'))
                                                    ->default(true)
                                                    ->inline(false)
                                                    ->live()
                        
                                                    ->disabled(function (Get $get): bool {
                                                        $ageGroups = $get('age_group') ?? [];
                                                        return in_array(EventAgeGroup::Children, $ageGroups, true) || 
                                                               in_array(EventAgeGroup::AllAges, $ageGroups, true);
                                                    })
                                                    ->dehydrated()
                                                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                                        // Force children_allowed to true if age_group contains Children or AllAges
                                                        $ageGroups = $get('age_group') ?? [];
                                                        $shouldBeTrue = in_array(EventAgeGroup::Children, $ageGroups, true) ||
                                                                       in_array(EventAgeGroup::AllAges, $ageGroups, true);
                                                        
                                                        if ($shouldBeTrue && ! $state) {
                                                            $set('children_allowed', true);
                                                        }
                                                    }),
                                            ]),
                                    ]),

                                Section::make(__('Organizer'))
                                    ->schema([
                                        Radio::make('organizer_type')
                                            ->label(__('Organizer Type'))
                                            ->required()
                                            ->options([
                                                'institution' => __('Institution'),
                                                'speaker' => __('Speaker'),
                                            ])
                                            ->default('institution')
                                            ->inline()
                                            ->live(),

                                        Select::make('organizer_institution_id')
                                            ->label(__('Institution'))
                                            ->relationship('institution', 'name', fn (Builder $query) => $query->whereIn('status', ['verified', 'pending']))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => $get('organizer_type') === 'institution')
                                            ->required(fn (Get $get): bool => $get('organizer_type') === 'institution')
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Institution Name'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Masjid Al-Falah, Surau An-Nur')),
                                                    
                                                Select::make('type')
                                                    ->label(__('Institution Type'))
                                                    ->required()
                                                    ->options(InstitutionType::class)
                                                    ->placeholder(__('Select type...')),

                                                SpatieMediaLibraryFileUpload::make('cover')
                                                    ->label(__('Cover Image'))
                                                    ->collection('cover')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(5120)
                                                    ->helperText(__('Header or banner image')),

                                                SpatieMediaLibraryFileUpload::make('gallery')
                                                    ->label(__('Gallery'))
                                                    ->collection('gallery')
                                                    ->multiple()
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(5120)
                                                    ->maxFiles(10)
                                                    ->helperText(__('Up to 10 photos of the institution')),

                                                TextInput::make('line1')
                                                    ->label(__('Address Line 1'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., No. 123, Jalan Masjid')),

                                                TextInput::make('line2')
                                                    ->label(__('Address Line 2'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Taman Indah')),

                                                TextInput::make('postcode')
                                                    ->label(__('Postcode'))
                                                    ->maxLength(16)
                                                    ->placeholder(__('e.g., 50000')),

                                                Select::make('state_id')
                                                    ->label(__('State'))
                                                    ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set): void {
                                                        $set('district_id', null);
                                                        $set('subdistrict_id', null);
                                                    }),

                                                Select::make('district_id')
                                                    ->label(__('Daerah'))
                                                    ->options(function (Get $get) {
                                                        $stateId = $get('state_id');
                                                        if (! $stateId) {
                                                            return [];
                                                        }

                                                        return District::where('state_id', $stateId)
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('subdistrict_id', null))
                                                    ->visible(fn (Get $get): bool => filled($get('state_id'))),

                                                Select::make('subdistrict_id')
                                                    ->label(__('Daerah Kecil / Bandar / Mukim'))
                                                    ->options(function (Get $get) {
                                                        $districtId = $get('district_id');
                                                        if (! $districtId) {
                                                            return [];
                                                        }

                                                        return Subdistrict::where('district_id', $districtId)
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->visible(fn (Get $get): bool => filled($get('district_id'))),

                                                TextInput::make('google_maps_url')
                                                    ->label(__('Google Maps URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://maps.google.com/...')),

                                                TextInput::make('waze_url')
                                                    ->label(__('Waze URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://waze.com/ul/...')),

                                                Repeater::make('social_media')
                                                    ->label(__('Social Media'))
                                                    ->schema([
                                                        Select::make('platform')
                                                            ->label(__('Platform'))
                                                            ->required()
                                                            ->options([
                                                                'facebook' => 'Facebook',
                                                                'twitter' => 'Twitter / X',
                                                                'instagram' => 'Instagram',
                                                                'youtube' => 'YouTube',
                                                                'tiktok' => 'TikTok',
                                                                'telegram' => 'Telegram',
                                                                'whatsapp' => 'WhatsApp',
                                                                'website' => 'Website',
                                                            ])
                                                            ->searchable(),
                                                        TextInput::make('url')
                                                            ->label(__('URL'))
                                                            ->required()
                                                            ->url()
                                                            ->maxLength(255)
                                                            ->placeholder(__('https://...')),
                                                        TextInput::make('username')
                                                            ->label(__('Username'))
                                                            ->maxLength(255)
                                                            ->placeholder(__('@username')),
                                                    ])
                                                    ->collapsible()
                                                    ->defaultItems(0)
                                                    ->addActionLabel(__('Add Social Media'))
                                                    ->helperText(__('Add social media links for this institution')),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $institution = Institution::create([
                                                    'name' => $data['name'],
                                                    'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                    'type' => $data['type'],
                                                    'status' => 'pending',
                                                ]);

                                                // Create address if provided
                                                if (! empty($data['line1']) || ! empty($data['state_id'])) {
                                                    $institution->address()->create([
                                                        'type' => 'main',
                                                        'line1' => $data['line1'] ?? null,
                                                        'line2' => $data['line2'] ?? null,
                                                        'postcode' => $data['postcode'] ?? null,
                                                        'country_id' => 132, // Malaysia
                                                        'state_id' => $data['state_id'] ?? null,
                                                        'district_id' => $data['district_id'] ?? null,
                                                        'subdistrict_id' => $data['subdistrict_id'] ?? null,
                                                        'google_maps_url' => $data['google_maps_url'] ?? null,
                                                        'waze_url' => $data['waze_url'] ?? null,
                                                    ]);
                                                }

                                                // Create social media entries if provided
                                                if (! empty($data['social_media'])) {
                                                    foreach ($data['social_media'] as $social) {
                                                        $institution->socialMedia()->create([
                                                            'platform' => $social['platform'],
                                                            'url' => $social['url'],
                                                            'username' => $social['username'] ?? null,
                                                        ]);
                                                    }
                                                }

                                                // Media (logo, cover, gallery) are handled automatically by Filament/Spatie integration

                                                return (string) $institution->getKey();
                                            }),

                                        Select::make('organizer_speaker_id')
                                            ->label(__('Speaker'))
                                            ->options(fn () => Speaker::query()
                                                ->whereIn('status', ['verified', 'pending'])
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => $get('organizer_type') === 'speaker')
                                            ->required(fn (Get $get): bool => $get('organizer_type') === 'speaker')
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                                if ($state) {
                                                    $currentSpeakers = $get('speakers') ?? [];
                                                    if (! in_array($state, $currentSpeakers, true)) {
                                                        $set('speakers', array_merge($currentSpeakers, [$state]));
                                                    }
                                                }
                                            })
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Speaker Name'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Ustaz Ahmad bin Hassan')),
                                                
                                                Radio::make('gender')
                                                    ->label(__('Gender'))
                                                    ->required()
                                                    ->options(Gender::class)
                                                    ->default(Gender::Male->value)
                                                    ->inline(),

                                                SpatieMediaLibraryFileUpload::make('avatar')
                                                    ->label(__('Avatar'))
                                                    ->collection('avatar')
                                                    ->avatar()
                                                    ->imageEditor()
                                                    ->image()
                                                    ->maxSize(5120)
                                                    ->helperText(__('Recommended: Square image, at least 400x400px')),

                                                Select::make('honorific')
                                                    ->label(__('Honorific'))
                                                    ->multiple()
                                                    ->options(Honorific::class)
                                                    ->searchable()
                                                    ->placeholder(__('Select honorifics')),

                                                Select::make('pre_nominal')
                                                    ->label(__('Pre-nominal'))
                                                    ->multiple()
                                                    ->options(PreNominal::class)
                                                    ->searchable()
                                                    ->placeholder(__('Select pre-nominals')),

                                                TextInput::make('job_title')
                                                    ->label(__('Job Title'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Imam, Lecturer')),

                                                Select::make('state_id')
                                                    ->label(__('State'))
                                                    ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload(),

                                                Select::make('institutions')
                                                    ->label(__('Affiliated Institutions'))
                                                    ->relationship('institutions', 'name')
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload(),
                                            ])
                                            ->createOptionUsing(function (array $data, Set $set, Get $get): string {
                                                $speaker = Speaker::create([
                                                    'name' => $data['name'],
                                                    'gender' => $data['gender'] ?? Gender::Male->value,
                                                    'honorific' => ! empty($data['honorific']) ? $data['honorific'] : null,
                                                    'pre_nominal' => ! empty($data['pre_nominal']) ? $data['pre_nominal'] : null,
                                                    'job_title' => $data['job_title'] ?? null,
                                                    'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                    'status' => 'pending',
                                                ]);

                                                if (! empty($data['state_id'])) {
                                                    $speaker->address()->create([
                                                        'state_id' => $data['state_id'],
                                                    ]);
                                                }

                                                if (! empty($data['institutions'])) {
                                                    $speaker->institutions()->attach($data['institutions']);
                                                }

                                                return (string) $speaker->getKey();
                                            }),
                                    ]),

                                Section::make(__('Location'))
                                    ->visible(fn (Get $get): bool => $get('event_format') !== EventFormat::Online)
                                    ->schema([
                                        Toggle::make('location_same_as_institution')
                                            ->label(__('Same as organizer institution'))
                                            ->default(true)
                                            ->inline(false)
                                            ->live()
                                            ->visible(fn (Get $get): bool => $get('organizer_type') === 'institution'),

                                        Select::make('location_id')
                                            ->label(__('Location'))
                                            ->options(function (Get $get): array {
                                                $options = [];

                                                if ($get('organizer_type') === 'institution') {
                                                    $institutionId = $get('organizer_institution_id');

                                                    if ($institutionId) {
                                                        $institution = Institution::query()
                                                            ->whereKey($institutionId)
                                                            ->first();

                                                        if ($institution) {
                                                            $options['institution:' . $institution->id] = __('Institution: :name', ['name' => $institution->name]);
                                                        }
                                                    }
                                                } else {
                                                    $institutions = Institution::query()
                                                        ->whereIn('status', ['verified', 'pending'])
                                                        ->orderBy('name')
                                                        ->get(['id', 'name']);

                                                    foreach ($institutions as $institution) {
                                                        $options['institution:' . $institution->id] = __('Institution: :name', ['name' => $institution->name]);
                                                    }
                                                }

                                                $venues = Venue::query()
                                                    ->whereIn('status', ['verified', 'pending'])
                                                    ->orderBy('name')
                                                    ->get(['id', 'name']);

                                                foreach ($venues as $venue) {
                                                    $options['venue:' . $venue->id] = __('Venue: :name', ['name' => $venue->name]);
                                                }

                                                return $options;
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => $get('organizer_type') === 'speaker' || ! $get('location_same_as_institution'))
                                            ->required(fn (Get $get): bool => $get('organizer_type') === 'speaker' || ! $get('location_same_as_institution'))
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Venue Name'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Dewan Serbaguna, Hall A')),
                                                    
                                                Select::make('type')
                                                    ->label(__('Venue Type'))
                                                    ->required()
                                                    ->options(VenueType::class)
                                                    ->placeholder(__('Select type...')),

                                                SpatieMediaLibraryFileUpload::make('cover')
                                                    ->label(__('Cover Image'))
                                                    ->collection('main')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(5120)
                                                    ->helperText(__('Header or banner image')),

                                                SpatieMediaLibraryFileUpload::make('gallery')
                                                    ->label(__('Gallery'))
                                                    ->collection('gallery')
                                                    ->multiple()
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(5120)
                                                    ->maxFiles(10)
                                                    ->helperText(__('Up to 10 photos of the venue')),

                                                TextInput::make('line1')
                                                    ->label(__('Address Line 1'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., No. 123, Jalan Utama')),

                                                TextInput::make('line2')
                                                    ->label(__('Address Line 2'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Taman Indah')),

                                                TextInput::make('postcode')
                                                    ->label(__('Postcode'))
                                                    ->maxLength(16)
                                                    ->placeholder(__('e.g., 50000')),

                                                Select::make('state_id')
                                                    ->label(__('State'))
                                                    ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set): void {
                                                        $set('district_id', null);
                                                        $set('subdistrict_id', null);
                                                    }),

                                                Select::make('district_id')
                                                    ->label(__('Daerah'))
                                                    ->options(function (Get $get) {
                                                        $stateId = $get('state_id');
                                                        if (! $stateId) {
                                                            return [];
                                                        }

                                                        return District::where('state_id', $stateId)
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('subdistrict_id', null))
                                                    ->visible(fn (Get $get): bool => filled($get('state_id'))),

                                                Select::make('subdistrict_id')
                                                    ->label(__('Daerah Kecil / Bandar / Mukim'))
                                                    ->options(function (Get $get) {
                                                        $districtId = $get('district_id');
                                                        if (! $districtId) {
                                                            return [];
                                                        }

                                                        return Subdistrict::where('district_id', $districtId)
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->visible(fn (Get $get): bool => filled($get('district_id'))),

                                                TextInput::make('google_maps_url')
                                                    ->label(__('Google Maps URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://maps.google.com/...')),

                                                TextInput::make('waze_url')
                                                    ->label(__('Waze URL'))
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder(__('https://waze.com/ul/...')),

                                                Repeater::make('social_media')
                                                    ->label(__('Social Media'))
                                                    ->schema([
                                                        Select::make('platform')
                                                            ->label(__('Platform'))
                                                            ->required()
                                                            ->options([
                                                                'facebook' => 'Facebook',
                                                                'twitter' => 'Twitter / X',
                                                                'instagram' => 'Instagram',
                                                                'youtube' => 'YouTube',
                                                                'tiktok' => 'TikTok',
                                                                'telegram' => 'Telegram',
                                                                'whatsapp' => 'WhatsApp',
                                                                'website' => 'Website',
                                                            ])
                                                            ->searchable(),
                                                        TextInput::make('url')
                                                            ->label(__('URL'))
                                                            ->required()
                                                            ->url()
                                                            ->maxLength(255)
                                                            ->placeholder(__('https://...')),
                                                        TextInput::make('username')
                                                            ->label(__('Username'))
                                                            ->maxLength(255)
                                                            ->placeholder(__('@username')),
                                                    ])
                                                    ->collapsible()
                                                    ->defaultItems(0)
                                                    ->addActionLabel(__('Add Social Media'))
                                                    ->helperText(__('Add social media links for this venue')),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $venue = Venue::create([
                                                    'name' => $data['name'],
                                                    'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                    'type' => $data['type'],
                                                    'status' => 'pending',
                                                ]);

                                                // Create address if provided
                                                if (! empty($data['line1']) || ! empty($data['state_id'])) {
                                                    $venue->address()->create([
                                                        'type' => 'main',
                                                        'line1' => $data['line1'] ?? null,
                                                        'line2' => $data['line2'] ?? null,
                                                        'postcode' => $data['postcode'] ?? null,
                                                        'country_id' => 132, // Malaysia
                                                        'state_id' => $data['state_id'] ?? null,
                                                        'district_id' => $data['district_id'] ?? null,
                                                        'subdistrict_id' => $data['subdistrict_id'] ?? null,
                                                        'google_maps_url' => $data['google_maps_url'] ?? null,
                                                        'waze_url' => $data['waze_url'] ?? null,
                                                    ]);
                                                }

                                                // Create social media entries if provided
                                                if (! empty($data['social_media'])) {
                                                    foreach ($data['social_media'] as $social) {
                                                        $venue->socialMedia()->create([
                                                            'platform' => $social['platform'],
                                                            'url' => $social['url'],
                                                            'username' => $social['username'] ?? null,
                                                        ]);
                                                    }
                                                }

                                                // Media (cover, gallery) are handled automatically by Filament/Spatie integration

                                                return 'venue:' . (string) $venue->getKey();
                                            }),
                                    ]),

                                Section::make(__('Speakers'))
                                    ->schema([
                                        Select::make('speakers')
                                            ->label(__('Select Speakers'))
                                            ->required()
                                            ->multiple()
                                            ->relationship('speakers', 'name', fn (Builder $query) => $query->whereIn('status', ['verified', 'pending']))
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Speaker Name'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Ustaz Ahmad bin Hassan')),
                                                
                                                Radio::make('gender')
                                                    ->label(__('Gender'))
                                                    ->required()
                                                    ->options(Gender::class)
                                                    ->default(Gender::Male->value)
                                                    ->inline(),

                                                SpatieMediaLibraryFileUpload::make('avatar')
                                                    ->label(__('Avatar'))
                                                    ->collection('avatar')
                                                    ->avatar()
                                                    ->imageEditor()
                                                    ->image()
                                                    ->maxSize(5120)
                                                    ->helperText(__('Recommended: Square image, at least 400x400px')),

                                                Select::make('honorific')
                                                    ->label(__('Honorific'))
                                                    ->multiple()
                                                    ->options(Honorific::class)
                                                    ->searchable()
                                                    ->placeholder(__('Select honorifics')),

                                                Select::make('pre_nominal')
                                                    ->label(__('Pre-nominal'))
                                                    ->multiple()
                                                    ->options(PreNominal::class)
                                                    ->searchable()
                                                    ->placeholder(__('Select pre-nominals')),

                                                TextInput::make('job_title')
                                                    ->label(__('Job Title'))
                                                    ->maxLength(255)
                                                    ->placeholder(__('e.g., Imam, Lecturer')),

                                                Select::make('state_id')
                                                    ->label(__('State'))
                                                    ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload(),

                                                Select::make('institutions')
                                                    ->label(__('Affiliated Institutions'))
                                                    ->relationship('institutions', 'name')
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload(),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $speaker = Speaker::create([
                                                    'name' => $data['name'],
                                                    'gender' => $data['gender'] ?? Gender::Male->value,
                                                    'honorific' => ! empty($data['honorific']) ? $data['honorific'] : null,
                                                    'pre_nominal' => ! empty($data['pre_nominal']) ? $data['pre_nominal'] : null,
                                                    'job_title' => $data['job_title'] ?? null,
                                                    'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                    'status' => 'pending',
                                                ]);

                                                if (! empty($data['state_id'])) {
                                                    $speaker->address()->create([
                                                        'state_id' => $data['state_id'],
                                                    ]);
                                                }

                                                if (! empty($data['institutions'])) {
                                                    $speaker->institutions()->attach($data['institutions']);
                                                }

                                                return (string) $speaker->getKey();
                                            }),
                                    ]),

                                Section::make(__('Media'))
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('poster')
                                            ->label(__('Main Image'))
                                            ->collection('poster')
                                            ->image()
                                            ->imageEditor()
                                            ->responsiveImages()
                                            ->helperText(__('Main featured image for the event.')),
                                        SpatieMediaLibraryFileUpload::make('gallery')
                                            ->label(__('Gallery'))
                                            ->collection('gallery')
                                            ->multiple()
                                            ->reorderable()
                                            ->image()
                                            ->imageEditor()
                                            ->responsiveImages()
                                            ->helperText(__('Additional images for the event gallery.')),
                                    ])
                                    ->columns(2),
                            ]),

                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('Your Details'))
                                    ->schema([
                                        TextInput::make('submitter_name')
                                            ->label(__('Your Name'))
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('submitter_email')
                                            ->label(__('Email'))
                                            ->email()
                                            ->maxLength(255)
                                            ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_phone'))),

                                        TextInput::make('submitter_phone')
                                            ->label(__('Phone'))
                                            ->tel()
                                            ->maxLength(20)
                                            ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_email'))),
                                    ])
                                    ->visible(fn () => ! auth()->check()),

                                Section::make(__('Submit'))
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                Actions::make([
                                                    Action::make('submit')
                                                        ->label(__('Submit Event for Review'))
                                                        ->size('lg')
                                                        ->color('success')
                                                        ->action('submit')
                                                        ->extraAttributes(['class' => 'w-full']),
                                                ]),
                                            ]),
                                    ]),

                                Section::make(__('Submission Info'))
                                    ->schema([
                                        Placeholder::make('info')
                                            ->hiddenLabel()
                                            ->content(__('Your event will be reviewed by our moderators within 24-48 hours.')),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): ?\Livewire\Features\SupportRedirects\Redirector
    {
        $validated = $this->form->getState();

        // Enforce children_allowed when AllAges or Children is selected
        $ageGroups = $validated['age_group'] ?? [];
        if (in_array(EventAgeGroup::Children->value, $ageGroups, true) || 
            in_array(EventAgeGroup::AllAges->value, $ageGroups, true)) {
            $validated['children_allowed'] = true;
        }

        $startsAt = $this->resolveStartsAt($validated);

        $organizerType = null;
        $organizerId = null;
        $institutionId = null;
        $venueId = null;
        $locationInstitutionId = null;

        $locationValue = $validated['location_id'] ?? null;
        if (is_string($locationValue)) {
            if (str_starts_with($locationValue, 'venue:')) {
                $venueId = substr($locationValue, strlen('venue:'));
            }

            if (str_starts_with($locationValue, 'institution:')) {
                $locationInstitutionId = substr($locationValue, strlen('institution:'));
            }
        }

        if (($validated['organizer_type'] ?? null) === 'institution' && ! empty($validated['organizer_institution_id'])) {
            $organizerType = Institution::class;
            $organizerId = $validated['organizer_institution_id'];
            $institutionId = $validated['organizer_institution_id'];
        } elseif (($validated['organizer_type'] ?? null) === 'speaker' && ! empty($validated['organizer_speaker_id'])) {
            $organizerType = Speaker::class;
            $organizerId = $validated['organizer_speaker_id'];
        }

        $useInstitutionLocation = ($validated['location_same_as_institution'] ?? false) && ($validated['organizer_type'] ?? null) === 'institution';
        if (! $useInstitutionLocation) {
            if ($locationInstitutionId) {
                $institutionId = $locationInstitutionId;
                $venueId = null;
            }
        } else {
            $venueId = null;
        }

        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);
        $prayerReference = $prayerTime?->toPrayerReference();
        $prayerOffset = $prayerTime?->getDefaultOffset();
        $prayerDisplayText = $prayerTime && ! $prayerTime->isCustomTime() ? $prayerTime->getLabel() : null;

        $timezone = 'Asia/Kuala_Lumpur';
        $now = Carbon::now($timezone);
        $eventDate = Carbon::parse($validated['event_date'], $timezone)->startOfDay();
        if ($eventDate->isSameDay($now)) {
            if ($prayerTime?->isCustomTime()) {
                $customTimeValue = $validated['custom_time'] ?? null;
                if ($customTimeValue) {
                    $customTime = Carbon::parse($customTimeValue, 'Asia/Kuala_Lumpur');
                    $customDateTime = $eventDate->copy()->setTime($customTime->hour, $customTime->minute);

                    if (! $customDateTime->greaterThan($now)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'data.custom_time' => __('Please select a time after now.'),
                        ]);
                    }
                }
            } else {
                $defaultTimes = $this->getDefaultPrayerTimes();
                $timeString = $defaultTimes[$prayerTime?->value ?? ''] ?? null;

                if ($timeString) {
                    $candidate = Carbon::parse($eventDate->toDateString().' '.$timeString, 'Asia/Kuala_Lumpur');

                    if (! $candidate->greaterThan($now)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'data.prayer_time' => __('Please select a time after now.'),
                        ]);
                    }
                }
            }
        }

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(6),
            'description' => $validated['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => null,
            'institution_id' => $institutionId,
            'venue_id' => $venueId,
            'event_type_id' => $validated['event_type_id'] ?? EventType::getDefault()?->id,
            'gender' => $validated['gender'] ?? EventGenderRestriction::All->value,
            'age_group' => $validated['age_group'] ?? [EventAgeGroup::AllAges->value],
            'children_allowed' => $validated['children_allowed'] ?? true,
            'timing_mode' => $prayerTime?->isCustomTime() ? 'absolute' : 'prayer_relative',
            'prayer_reference' => $prayerReference?->value,
            'prayer_offset' => $prayerOffset?->value,
            'prayer_display_text' => $prayerDisplayText,
            'organizer_type' => $organizerType,
            'organizer_id' => $organizerId,
            'event_format' => $validated['event_format'] ?? EventFormat::Physical->value,
            'event_url' => $validated['event_url'] ?? null,
            'live_url' => $validated['live_url'] ?? null,
            'status' => 'pending',
            'visibility' => 'public',
            'submitter_id' => auth()->id(),
        ]);

        if (! empty($validated['speakers'])) {
            $event->speakers()->attach($validated['speakers']);
        }

        if (! empty($validated['topics'])) {
            $event->topics()->attach($validated['topics']);
        }

        $this->form->model($event);
        $this->form->saveRelationships();

        $submitterName = $validated['submitter_name'] ?? auth()->user()?->name;

        $submission = EventSubmission::create([
            'event_id' => $event->id,
            'submitter_name' => $submitterName,
            'submitted_by' => auth()->id(),
        ]);

        if (! auth()->check()) {
            $this->storeSubmitterContacts($submission, $validated);
        }

        session()->flash('event_title', $event->title);

        return redirect()->route('submit-event.success');
    }

    /**
     * Resolve the starts_at datetime from event_date and prayer_time/custom_time.
     *
     * @param array{event_date: string, prayer_time: string|EventPrayerTime, custom_time?: string|null} $validated
     */
    protected function resolveStartsAt(array $validated): Carbon
    {
        $eventDate = Carbon::parse($validated['event_date'], 'Asia/Kuala_Lumpur')->startOfDay();
        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);

        if ($prayerTime?->isCustomTime() && ! empty($validated['custom_time'])) {
            $time = Carbon::parse($validated['custom_time']);

            return $eventDate->setTime($time->hour, $time->minute);
        }

        $defaultTimes = $this->getDefaultPrayerTimes();

        $timeString = $defaultTimes[$prayerTime?->value ?? ''] ?? '20:00';
        $time = Carbon::parse($timeString);

        return $eventDate->setTime($time->hour, $time->minute);
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultPrayerTimes(): array
    {
        return [
            EventPrayerTime::SelepasSubuh->value => '06:30',
            EventPrayerTime::SelepasZuhur->value => '13:30',
            EventPrayerTime::SelepasJumaat->value => '14:00',
            EventPrayerTime::SelepasAsar->value => '17:00',
            EventPrayerTime::SelepasMaghrib->value => '20:00',
            EventPrayerTime::SelepasIsyak->value => '21:30',
            EventPrayerTime::SelepasTarawikh->value => '22:30',
        ];
    }

    /**
     * Check if a given date falls within Ramadhan.
     * This uses approximate Gregorian dates for Ramadhan periods.
     */
    protected function isRamadhan(Carbon $date): bool
    {
        $year = $date->year;
        
        // Ramadhan dates for common years (approximate)
        $ramadhanPeriods = [
            2026 => ['start' => '02-18', 'end' => '03-19'],
            2027 => ['start' => '02-07', 'end' => '03-08'],
            2028 => ['start' => '01-27', 'end' => '02-25'],
            2029 => ['start' => '01-16', 'end' => '02-13'],
            2030 => ['start' => '01-05', 'end' => '02-03'],
        ];

        if (!isset($ramadhanPeriods[$year])) {
            return false;
        }

        $period = $ramadhanPeriods[$year];
        $startDate = Carbon::parse("{$year}-{$period['start']}", 'Asia/Kuala_Lumpur')->startOfDay();
        $endDate = Carbon::parse("{$year}-{$period['end']}", 'Asia/Kuala_Lumpur')->endOfDay();

        return $date->between($startDate, $endDate);
    }

    /**
     * @return array<string, string>
     */
    public function getPrayerTimeOptions(?string $eventDate): array
    {
        $options = [];
        $timezone = 'Asia/Kuala_Lumpur';
        $now = Carbon::now($timezone);
        $eventDay = $eventDate ? Carbon::parse($eventDate, $timezone)->startOfDay() : null;
        $isFriday = $eventDay && $eventDay->isFriday();
        $isRamadhan = $eventDay && $this->isRamadhan($eventDay);

        foreach (EventPrayerTime::cases() as $case) {
            // Skip Selepas Jumaat if not Friday
            if ($case === EventPrayerTime::SelepasJumaat && !$isFriday) {
                continue;
            }

            // Skip Selepas Tarawikh if not Ramadhan
            if ($case === EventPrayerTime::SelepasTarawikh && !$isRamadhan) {
                continue;
            }

            if ($case === EventPrayerTime::LainWaktu) {
                $options[$case->value] = $case->getLabel();
                continue;
            }

            if (! $eventDate) {
                $options[$case->value] = $case->getLabel();
                continue;
            }

            if (! $eventDay->isSameDay($now)) {
                $options[$case->value] = $case->getLabel();
                continue;
            }

            $timeString = $this->getDefaultPrayerTimes()[$case->value] ?? null;
            if (! $timeString) {
                $options[$case->value] = $case->getLabel();
                continue;
            }

            $candidate = Carbon::parse($eventDay->toDateString().' '.$timeString, 'Asia/Kuala_Lumpur');
            if ($candidate->greaterThan($now)) {
                $options[$case->value] = $case->getLabel();
            }
        }

        return $options;
    }

    /**
     * @param array{submitter_email?: string|null, submitter_phone?: string|null} $validated
     */
    protected function storeSubmitterContacts(EventSubmission $submission, array $validated): void
    {
        $email = $validated['submitter_email'] ?? null;
        $phone = $validated['submitter_phone'] ?? null;

        if (filled($email)) {
            $submission->contacts()->create([
                'type' => 'main',
                'category' => 'email',
                'value' => $email,
                'is_public' => false,
            ]);
        }

        if (filled($phone)) {
            $submission->contacts()->create([
                'type' => 'main',
                'category' => 'phone',
                'value' => $phone,
                'is_public' => false,
            ]);
        }
    }
};
?>

@section('title', __('Submit Event') . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Submit an Event') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Share a Majlis Ilmu with the community. Your submission will be reviewed before publishing.') }}
                </p>
            </div>

            <form wire:submit="submit">
                {{ $this->form }}
            </form>

            <x-filament-actions::modals />
        </div>
    </div>
</div>
