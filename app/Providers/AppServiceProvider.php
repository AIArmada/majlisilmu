<?php

namespace App\Providers;

use App\Ai\Listeners\RecordAiUsage;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use App\Observers\EventObserver;
use App\Observers\EventRecurrenceRuleObserver;
use App\Observers\EventSessionObserver;
use App\Support\Media\MediaFileNamer;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\TranscriptionGenerated;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppServiceProvider extends ServiceProvider
{
    protected static bool $eventObserverRegistered = false;

    protected static bool $languageSwitchConfigured = false;

    protected static bool $mediaUploadConfigured = false;

    protected static bool $eventScheduleObserversRegistered = false;

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentTimezone::set('Asia/Kuala_Lumpur');

        // Register custom scripts
        FilamentAsset::register([
            Js::make('close-on-select', __DIR__.'/../../resources/js/filament/close-on-select.js'),
        ]);

        if (! self::$eventObserverRegistered) {
            Event::observe(EventObserver::class);
            self::$eventObserverRegistered = true;
        }

        if (! self::$eventScheduleObserversRegistered) {
            EventSession::observe(EventSessionObserver::class);
            EventRecurrenceRule::observe(EventRecurrenceRuleObserver::class);
            self::$eventScheduleObserversRegistered = true;
        }

        if (! app()->bound('ai.usage.listeners.registered')) {
            \Illuminate\Support\Facades\Event::listen(AgentPrompted::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(AgentStreamed::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(ImageGenerated::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(TranscriptionGenerated::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(EmbeddingsGenerated::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(Reranked::class, [RecordAiUsage::class, 'handle']);
            \Illuminate\Support\Facades\Event::listen(AudioGenerated::class, [RecordAiUsage::class, 'handle']);

            app()->instance('ai.usage.listeners.registered', true);
        }

        if (! self::$languageSwitchConfigured) {
            LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
                $switch->locales(['en', 'ms', 'jv', 'ta', 'zh']);
            });
            self::$languageSwitchConfigured = true;
        }

        Relation::enforceMorphMap([
            'user' => User::class,
            'event' => Event::class,
            'event_submission' => EventSubmission::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'series' => Series::class,
            'venue' => Venue::class,
            'donation_channel' => DonationChannel::class,
            'reference' => Reference::class,
            'report' => Report::class,
        ]);

        if (! Select::hasMacro('closeOnSelect')) {
            // Register closeOnSelect macro for Filament Select component
            // This allows multi-select dropdowns to close after each selection
            Select::macro('closeOnSelect', function (bool $condition = true): static {
                if ($condition) {
                    /** @phpstan-ignore-next-line */
                    $this->extraAttributes([
                        'x-close-on-select' => true,
                    ]);
                }

                return $this;
            });
        }

        if (! self::$mediaUploadConfigured) {
            // Configure SpatieMediaLibraryFileUpload to use slug-based filenames globally.
            // This runs after the model is saved so $record always has a slug/name.
            SpatieMediaLibraryFileUpload::configureUsing(function (SpatieMediaLibraryFileUpload $upload): void {
                $maxUploadSizeKb = (int) ceil(((int) config('media-library.max_file_size', 10 * 1024 * 1024)) / 1024);

                $upload
                    ->placeholder(__('filament-forms::components.file_upload.placeholder'))
                    ->maxSize($maxUploadSizeKb)
                    ->maxParallelUploads(2)
                    ->appendFiles()
                    ->customHeaders([
                        'CacheControl' => 'public, max-age=31536000, immutable',
                    ]);

                $upload->getUploadedFileNameForStorageUsing(
                    static function (SpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file): string {
                        $record = $component->getRecord();
                        $extension = $file->getClientOriginalExtension();
                        $baseName = MediaFileNamer::resolveBaseNameFromModel($record);

                        // Append 8-char ULID suffix for uniqueness
                        $suffix = strtolower(substr(\Illuminate\Support\Str::ulid(), 0, 8));

                        return "{$baseName}-{$suffix}.{$extension}";
                    }
                );

                $upload->mediaName(
                    static fn (TemporaryUploadedFile $file): string => MediaFileNamer::resolveDisplayNameFromModel(
                        $upload->getRecord(),
                        $upload->getCollection() ?? 'media',
                        $file->getClientOriginalName(),
                    )
                );

                $upload->customProperties(
                    static fn (TemporaryUploadedFile $file): array => [
                        'collection' => $upload->getCollection() ?? 'default',
                        'original_file_name' => $file->getClientOriginalName(),
                    ]
                );
            });
            self::$mediaUploadConfigured = true;
        }
    }
}
