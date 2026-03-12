<?php

namespace App\Models;

use App\Enums\InspirationCategory;
use Database\Factories\InspirationFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Inspiration extends Model implements HasMedia
{
    /** @use HasFactory<InspirationFactory> */
    use HasFactory, HasUuids, InteractsWithMedia;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'locale',
        'title',
        'content',
        'source',
        'is_active',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'category' => InspirationCategory::class,
            'is_active' => 'boolean',
        ];
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    /** @var mixed $decoded */
                    $decoded = json_decode($value, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    return self::plainTextToRichContent($value);
                }

                return self::plainTextToRichContent('');
            },
            set: function (mixed $value): string {
                $normalized = self::normalizeRichContent($value);

                return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            },
        );
    }

    public function renderContentHtml(): string
    {
        return RichContentRenderer::make($this->content)->toHtml();
    }

    public function contentPreviewText(int $limit = 120): string
    {
        return Str::limit(trim(strip_tags($this->renderContentHtml())), $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public static function plainTextToRichContent(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => $text,
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeRichContent(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            return self::plainTextToRichContent($value);
        }

        return self::plainTextToRichContent('');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forLocale(Builder $query, ?string $locale = null): void
    {
        $query->where('locale', $locale ?? app()->getLocale());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('main')
            ->fit(Fit::Crop, 640, 480)
            ->sharpen(10)
            ->format('webp');
    }
}
