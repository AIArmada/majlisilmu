<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;
    use WithFileUploads;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'submitter_name' => auth()->user()?->name,
            'submitter_email' => auth()->user()?->email,
            'children_allowed' => true,
            'event_type_id' => EventType::getDefault()?->id,
            'gender' => EventGenderRestriction::All->value,
            'age_group' => [EventAgeGroup::AllAges->value],
        ]);
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
                                        TextInput::make('title')
                                            ->label(__('Event Title'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('e.g., Kuliah Maghrib: Tafsir Surah Al-Kahfi')),

                                        Select::make('topics')
                                            ->label(__('Topics'))
                                            ->required()
                                            ->multiple()
                                            ->relationship('topics', 'name', fn (Builder $query) => $query->where('status', 'verified'))
                                            ->searchable()
                                            ->preload(),

                                        Grid::make(2)
                                            ->schema([
                                                DateTimePicker::make('starts_at')
                                                    ->label(__('Start Date & Time'))
                                                    ->required()
                                                    ->native()
                                                    ->timezone('Asia/Kuala_Lumpur')
                                                    ->after('now'),

                                                DateTimePicker::make('ends_at')
                                                    ->label(__('End Date & Time'))
                                                    ->required()
                                                    ->native()
                                                    ->timezone('Asia/Kuala_Lumpur')
                                                    ->after('starts_at'),
                                            ]),

                                        Textarea::make('description')
                                            ->label(__('Description'))
                                            ->required()
                                            ->maxLength(5000)
                                            ->rows(4)
                                            ->placeholder(__('Describe the event, topics to be covered, etc.')),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('event_type_id')
                                                    ->label(__('Event Type'))
                                                    ->required()
                                                    ->relationship('eventType', 'name')
                                                    ->searchable(),

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
                                                    ->default([EventAgeGroup::AllAges->value])
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, array|string|null $state): void {
                                                        $ageGroups = collect(is_array($state) ? $state : [$state])
                                                            ->filter()
                                                            ->values();

                                                        if ($ageGroups->contains(EventAgeGroup::Children->value)) {
                                                            $set('children_allowed', true);
                                                        }
                                                    }),

                                                Toggle::make('children_allowed')
                                                    ->label(__('Children Allowed'))
                                                    ->default(true)
                                                    ->inline(false)
                                                    ->disabled(fn (Get $get) => in_array(EventAgeGroup::Children->value, $get('age_group') ?? [], true))
                                                    ->dehydrated(),
                                            ]),
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

                                Section::make(__('Location'))
                                    ->schema([
                                        Select::make('institution')
                                            ->label(__('Institution'))
                                            ->relationship('institution', 'name', fn (Builder $query) => $query->whereIn('status', ['verified', 'pending']))
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Institution Name'))
                                                    ->required()
                                                    ->lazy()
                                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                                TextInput::make('slug')
                                                    ->label(__('Slug'))
                                                    ->required()
                                                    ->unique(Institution::class, 'slug'),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $institution = Institution::create([
                                                    'name' => $data['name'],
                                                    'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(6),
                                                    'status' => 'pending',
                                                ]);

                                                return (string) $institution->getKey();
                                            }),

                                        Select::make('venue')
                                            ->label(__('Venue'))
                                            ->relationship('venue', 'name', fn (Builder $query) => $query->whereIn('status', ['verified', 'pending']))
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Venue Name'))
                                                    ->required(),
                                                TextInput::make('address_line1')
                                                    ->label(__('Address')),
                                                Select::make('state_id')
                                                    ->label(__('State'))
                                                    ->options(State::pluck('name', 'id'))
                                                    ->searchable(),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $venue = Venue::create([
                                                    'name' => $data['name'],
                                                    'slug' => Str::slug($data['name']).'-'.Str::random(6),
                                                    'status' => 'pending',
                                                ]);

                                                if (! empty($data['address_line1']) || ! empty($data['state_id'])) {
                                                    $venue->address()->create([
                                                        'line1' => $data['address_line1'] ?? null,
                                                        'state_id' => $data['state_id'] ?? null,
                                                    ]);
                                                }

                                                return (string) $venue->getKey();
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
                                                    ->lazy()
                                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                                TextInput::make('slug')
                                                    ->label(__('Slug'))
                                                    ->required()
                                                    ->unique(Speaker::class, 'slug'),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $speaker = Speaker::create([
                                                    'name' => $data['name'],
                                                    'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(6),
                                                    'status' => 'pending',
                                                ]);

                                                return (string) $speaker->getKey();
                                            }),
                                    ]),
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

    public function submit(): \Livewire\Features\SupportRedirects\Redirector
    {
        $validated = $this->form->getState();

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(6),
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'institution_id' => $validated['institution'] ?? null,
            'venue_id' => $validated['venue'] ?? null,
            'event_type_id' => $validated['event_type_id'] ?? EventType::getDefault()?->id,
            'gender' => $validated['gender'] ?? EventGenderRestriction::All->value,
            'age_group' => $validated['age_group'] ?? [EventAgeGroup::AllAges->value],
            'children_allowed' => $validated['children_allowed'] ?? true,
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
