<?php

namespace App\Models;

use GuzzleHttp\TransferStats;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Http;

class Address extends Model
{
    use HasUuids;

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

        $host = parse_url($trimmedUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        if ($host !== 'maps.app.goo.gl') {
            return self::normalizeGoogleMapsUrlLength($trimmedUrl);
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
                        $effectiveUrl = $effectiveUri ? (string) $effectiveUri : null;
                    },
                ])
                ->timeout(10)
                ->get($trimmedUrl);
        } catch (\Throwable) {
            return self::normalizeGoogleMapsUrlLength($trimmedUrl);
        }

        $resolvedUrl = filled($effectiveUrl) ? $effectiveUrl : $trimmedUrl;

        return self::normalizeGoogleMapsUrlLength($resolvedUrl);
    }

    private static function normalizeGoogleMapsUrlLength(string $url): string
    {
        if (strlen($url) <= 255) {
            return $url;
        }

        $placeName = self::extractGooglePlaceName($url);

        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $matches) === 1) {
            $placeCoordinates = $matches[1].','.$matches[2];
            $preciseQuery = trim($placeName !== '' ? $placeName.' '.$placeCoordinates : $placeCoordinates);
            $compactUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($preciseQuery);

            if (strlen($compactUrl) <= 255) {
                return $compactUrl;
            }

            $coordsOnlyCompactUrl = 'https://www.google.com/maps/search/?api=1&query='.$placeCoordinates;

            if (strlen($coordsOnlyCompactUrl) <= 255) {
                return $coordsOnlyCompactUrl;
            }
        }

        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches) === 1) {
            $compactUrl = 'https://www.google.com/maps/search/?api=1&query='.$matches[1].','.$matches[2];

            if (strlen($compactUrl) <= 255) {
                return $compactUrl;
            }
        }

        $strippedUrl = preg_replace('/[?#].*$/', '', $url) ?? $url;

        if (strlen($strippedUrl) <= 255) {
            return $strippedUrl;
        }

        return 'https://www.google.com/maps';
    }

    private static function extractGooglePlaceName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '';
        }

        if (preg_match('#/maps/place/([^/]+)#', $path, $matches) !== 1) {
            return '';
        }

        $rawName = urldecode($matches[1]);
        $normalizedName = str_replace('+', ' ', $rawName);

        return trim($normalizedName);
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
