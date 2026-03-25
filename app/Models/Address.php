<?php

namespace App\Models;

use App\Models\Concerns\AuditsModelChanges;
use GuzzleHttp\TransferStats;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Address extends Model implements AuditableContract
{
    use AuditsModelChanges, HasUuids;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'type',
        'line1',
        'line2',
        'postcode',
        'country_id',
        'state_id',
        'district_id',
        'subdistrict_id',
        'city_id',
        'lat',
        'lng',
        'google_maps_url',
        'google_place_id',
        'waze_url',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (Address $address): void {
            if (! $address->isDirty('google_maps_url')) {
                return;
            }

            $address->google_maps_url = self::resolveGoogleMapsUrl($address->google_maps_url);

            $coordinates = self::extractCoordinatesFromGoogleMapsUrl($address->google_maps_url);

            if ($coordinates === null) {
                return;
            }

            $address->lat = $coordinates['lat'];
            $address->lng = $coordinates['lng'];
        });
    }

    private static function resolveGoogleMapsUrl(?string $url): ?string
    {
        $trimmedUrl = is_string($url) ? trim($url) : null;

        if (! filled($trimmedUrl)) {
            return null;
        }

        $trimmedUrl = self::unwrapGoogleConsentUrl($trimmedUrl);

        $host = parse_url($trimmedUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        if ($host !== 'maps.app.goo.gl') {
            return $trimmedUrl;
        }

        $effectiveUrl = null;

        try {
            Http::withHeaders([
                'User-Agent' => 'MajlisIlmu/1.0 (+https://majlisilmu.test)',
            ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 10,
                        'track_redirects' => true,
                    ],
                    'on_stats' => static function (TransferStats $stats) use (&$effectiveUrl): void {
                        $effectiveUri = $stats->getEffectiveUri();
                        $effectiveUrl = (string) $effectiveUri;
                    },
                ])
                ->timeout(10)
                ->get($trimmedUrl);
        } catch (\Throwable) {
            return $trimmedUrl;
        }

        $resolvedUrl = is_string($effectiveUrl) && $effectiveUrl !== '' ? $effectiveUrl : $trimmedUrl;

        return self::unwrapGoogleConsentUrl($resolvedUrl);
    }

    private static function unwrapGoogleConsentUrl(string $url): string
    {
        $currentUrl = $url;

        for ($redirectDepth = 0; $redirectDepth < 3; $redirectDepth++) {
            $host = parse_url($currentUrl, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';

            if ($host !== 'consent.google.com') {
                return $currentUrl;
            }

            $query = parse_url($currentUrl, PHP_URL_QUERY);

            if (! is_string($query) || $query === '') {
                return $currentUrl;
            }

            parse_str($query, $parameters);

            $continueUrl = $parameters['continue'] ?? null;

            if (! is_string($continueUrl) || trim($continueUrl) === '') {
                return $currentUrl;
            }

            $currentUrl = str_starts_with($continueUrl, '/')
                ? 'https://www.google.com'.$continueUrl
                : $continueUrl;
        }

        return $currentUrl;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function extractCoordinatesFromGoogleMapsUrl(?string $url): ?array
    {
        if (! is_string($url) || ! filled($url)) {
            return null;
        }

        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $matches) === 1) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            ];
        }

        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches) === 1) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            ];
        }

        if (preg_match('/(?:query|destination)=[^&#]*?(-?\d+\.\d+)(?:,|%2C)(-?\d+\.\d+)/i', $url, $matches) === 1) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            ];
        }

        return null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Subdistrict, $this>
     */
    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
