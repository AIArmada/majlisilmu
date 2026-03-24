<?php

namespace App\Providers;

use App\Ai\Listeners\RecordAiUsage;
use App\Models\ContributionRequest;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Inspiration;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Observers\EventObserver;
use App\Observers\InstitutionObserver;
use App\Observers\SpeakerObserver;
use App\Observers\TagObserver;
use App\Observers\VenueObserver;
use App\Support\Media\MediaFileNamer;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\TranscriptionGenerated;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    protected static bool $eventObserverRegistered = false;

    protected static bool $languageSwitchConfigured = false;

    protected static bool $mediaUploadConfigured = false;

    protected static bool $publicListingObserversRegistered = false;

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $filamentSignalsViews = base_path('../commerce/packages/filament-signals/resources/views');

        if (is_dir($filamentSignalsViews)) {
            $this->loadViewsFrom($filamentSignalsViews, 'filament-signals');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $signalsRoutes = base_path('../commerce/packages/signals/routes/api.php');

        if (! $this->app->routesAreCached() && is_file($signalsRoutes) && ! app('router')->has('signals.collect.pageview')) {
            $this->loadRoutesFrom($signalsRoutes);
        }

        // Register custom scripts
        FilamentAsset::register([
            Js::make('close-on-select', __DIR__.'/../../resources/js/filament/close-on-select.js'),
            Js::make('user-timezone', __DIR__.'/../../resources/js/filament/user-timezone.js'),
        ]);

        if (app()->runningUnitTests() || ! self::$eventObserverRegistered) {
            Event::observe(EventObserver::class);

            if (! app()->runningUnitTests()) {
                self::$eventObserverRegistered = true;
            }
        }

        if (app()->runningUnitTests() || ! self::$publicListingObserversRegistered) {
            Institution::observe(InstitutionObserver::class);
            Speaker::observe(SpeakerObserver::class);
            Venue::observe(VenueObserver::class);
            Tag::observe(TagObserver::class);

            if (! app()->runningUnitTests()) {
                self::$publicListingObserversRegistered = true;
            }
        }

        if (! app()->bound('ai.usage.listeners.registered')) {
            EventFacade::listen(AgentPrompted::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(AgentStreamed::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(ImageGenerated::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(TranscriptionGenerated::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(EmbeddingsGenerated::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(Reranked::class, [RecordAiUsage::class, 'handle']);
            EventFacade::listen(AudioGenerated::class, [RecordAiUsage::class, 'handle']);

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
            'contribution_request' => ContributionRequest::class,
            'membership_claim' => MembershipClaim::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'series' => Series::class,
            'venue' => Venue::class,
            'donation_channel' => DonationChannel::class,
            'reference' => Reference::class,
            'report' => Report::class,
            'inspiration' => Inspiration::class,
        ]);

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
                        $suffix = strtolower(substr(Str::ulid(), 0, 8));

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

                $upload->saveUploadedFileUsing(
                    static function (SpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
                        $context = self::mediaUploadDebugContext($component, $file, $record);

                        Log::info('temp_media_upload.start', $context);

                        if (! $record || ! method_exists($record, 'addMediaFromString')) {
                            Log::warning('temp_media_upload.missing_record_handler', $context);

                            return null;
                        }

                        try {
                            if (! $file->exists()) {
                                Log::warning('temp_media_upload.temp_file_missing', $context);

                                return null;
                            }
                        } catch (UnableToCheckFileExistence $exception) {
                            Log::warning('temp_media_upload.temp_file_unreadable', [
                                ...$context,
                                'exception_class' => $exception::class,
                                'exception_message' => $exception->getMessage(),
                                'exception' => $exception,
                            ]);

                            return null;
                        }

                        try {
                            $mediaAdder = $record->addMediaFromString($file->get());
                            $filename = $component->getUploadedFileNameForStorage($file);

                            $media = $mediaAdder
                                ->addCustomHeaders([...['ContentType' => $file->getMimeType()], ...$component->getCustomHeaders()])
                                ->usingFileName($filename)
                                ->usingName($component->getMediaName($file) ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                                ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
                                ->withCustomProperties($component->getCustomProperties($file))
                                ->withManipulations($component->getManipulations())
                                ->withResponsiveImagesIf($component->hasResponsiveImages())
                                ->withProperties($component->getProperties())
                                ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

                            Log::info('temp_media_upload.success', [
                                ...$context,
                                'media_id' => $media->getKey(),
                                'media_uuid' => $media->getAttributeValue('uuid'),
                                'stored_file_name' => $media->getAttributeValue('file_name'),
                                'stored_collection' => $media->getAttributeValue('collection_name'),
                                'stored_disk' => $media->getAttributeValue('disk'),
                                'stored_conversions_disk' => $media->getAttributeValue('conversions_disk'),
                            ]);

                            return $media->getAttributeValue('uuid');
                        } catch (Throwable $exception) {
                            Log::error('temp_media_upload.failed', [
                                ...$context,
                                'exception_class' => $exception::class,
                                'exception_message' => $exception->getMessage(),
                                'previous_exception_class' => $exception->getPrevious() ? $exception->getPrevious()::class : null,
                                'previous_exception_message' => $exception->getPrevious()?->getMessage(),
                                'exception' => $exception,
                            ]);

                            throw $exception;
                        }
                    }
                );
            });
            self::$mediaUploadConfigured = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function mediaUploadDebugContext(
        SpatieMediaLibraryFileUpload $component,
        TemporaryUploadedFile $file,
        ?Model $record,
    ): array {
        return array_filter([
            'record_type' => $record?->getMorphClass() ?? ($record ? $record::class : null),
            'record_id' => $record?->getKey(),
            'component' => $component::class,
            'component_state_path' => $component->getStatePath(),
            'collection' => $component->getCollection() ?? 'default',
            'disk' => $component->getDiskName(),
            'conversions_disk' => $component->getConversionsDisk(),
            'has_responsive_images' => $component->hasResponsiveImages(),
            'temporary_file_name' => $file->getFilename(),
            'temporary_file_path' => $file->getRealPath() ?: $file->getPathname(),
            'temporary_original_name' => $file->getClientOriginalName(),
            'temporary_extension' => $file->getClientOriginalExtension(),
            'temporary_mime_type' => $file->getMimeType(),
            'temporary_size' => $file->getSize(),
            'request_url' => request()->fullUrl(),
            'request_route' => request()->route()?->getName(),
            'request_host' => request()->getHost(),
            'request_livewire' => request()->header('x-livewire'),
            'user_id' => auth()->id(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
