<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

/**
 * Custom file namer for Spatie Media Library.
 *
 * Handles naming of conversion and responsive image files.
 * Original file naming is handled globally via SpatieMediaLibraryFileUpload::configureUsing()
 * in AppServiceProvider, which calls resolveBaseNameFromModel().
 *
 * Examples:
 *   Original:   forum-makan-01jxab3k.webp
 *   Conversion: forum-makan-01jxab3k-thumb.webp
 *   Responsive: forum-makan-01jxab3k___thumb_400_300.webp
 *
 * For models without a slug (e.g. Report), falls back to the collection name:
 *   evidence-01jxab3k.jpg
 */
class MediaFileNamer extends FileNamer
{
    public function originalFileName(string $fileName): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);

        return "{$baseName}-{$conversion->getName()}";
    }

    public function responsiveFileName(string $fileName): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    /**
     * Resolve a human-readable, slug-safe base name from a model.
     *
     * Used by AppServiceProvider's global SpatieMediaLibraryFileUpload configuration
     * to generate slug-based filenames for all media uploads.
     */
    public static function resolveBaseNameFromModel(?Model $model): string
    {
        if (! $model) {
            return 'media';
        }

        // Try slug first (Event, Speaker, Venue, Institution, Series)
        if (filled($model->getAttribute('slug'))) {
            return str($model->getAttribute('slug'))->slug()->toString();
        }

        // Try name (DonationChannel, models with a name attribute)
        if (filled($model->getAttribute('name'))) {
            return str($model->getAttribute('name'))->slug()->limit(80, '')->toString();
        }

        // Try title (Reference)
        if (filled($model->getAttribute('title'))) {
            return str($model->getAttribute('title'))->slug()->limit(80, '')->toString();
        }

        // Try label (DonationChannel fallback)
        if (filled($model->getAttribute('label'))) {
            return str($model->getAttribute('label'))->slug()->limit(80, '')->toString();
        }

        // Fallback: use morph alias or class basename
        $morphMap = array_flip(\Illuminate\Database\Eloquent\Relations\Relation::morphMap());
        $morphAlias = $morphMap[$model::class] ?? str(class_basename($model))->slug()->toString();

        return $morphAlias;
    }

    /**
     * Resolve a readable media name for the Media model's "name" column.
     */
    public static function resolveDisplayNameFromModel(
        ?Model $model,
        string $collection,
        string $originalFileName
    ): string {
        $collectionLabel = self::resolveCollectionLabel($collection);
        $subject = self::resolveSubjectLabel($model);

        if (filled($subject)) {
            return "{$collectionLabel} - {$subject}";
        }

        $originalLabel = Str::of(pathinfo($originalFileName, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();

        if (filled($originalLabel)) {
            return "{$collectionLabel} - {$originalLabel}";
        }

        return $collectionLabel;
    }

    protected static function resolveCollectionLabel(string $collection): string
    {
        return match ($collection) {
            'poster' => 'Event Poster',
            'cover' => 'Cover Image',
            'logo' => 'Logo',
            'avatar' => 'Avatar',
            'main' => 'Main Image',
            'gallery' => 'Gallery Image',
            'qr' => 'QR Code',
            'evidence' => 'Evidence File',
            default => Str::of($collection)
                ->replace(['_', '-'], ' ')
                ->headline()
                ->toString(),
        };
    }

    protected static function resolveSubjectLabel(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        foreach (['title', 'name', 'label', 'slug'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (filled($value) && is_scalar($value)) {
                return Str::of((string) $value)
                    ->replace(['_', '-'], ' ')
                    ->squish()
                    ->title()
                    ->toString();
            }
        }

        return null;
    }
}
