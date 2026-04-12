<?php

namespace App\Actions\Speakers;

use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Models\Country;
use App\Models\Speaker;
use App\Support\Location\PreferredCountryResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateSpeakerSlugAction
{
    use AsAction;

    public function __construct(
        private readonly SyncSlugRedirectAction $syncSlugRedirectAction,
    ) {}

    public function syncSpeakerSlugsForName(string $name): bool
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            return false;
        }

        $speakers = Speaker::query()
            ->where('speakers.name', $normalizedName)
            ->with([
                'address.country',
            ])
            ->get();

        $didChange = false;

        foreach ($this->orderedSpeakers($speakers) as $speaker) {
            $didChange = $this->syncSpeakerSlug($speaker) || $didChange;
        }

        return $didChange;
    }

    public function syncSpeakerSlug(Speaker $speaker): bool
    {
        $slug = $this->forSpeaker($speaker);

        if ($speaker->slug === $slug) {
            return false;
        }

        $previousSlug = is_string($speaker->slug) ? $speaker->slug : null;

        Speaker::withoutTimestamps(function () use ($speaker, $slug): void {
            $speaker->forceFill([
                'slug' => $slug,
            ])->saveQuietly();
        });

        $this->syncSlugRedirectAction->handle($speaker, $previousSlug);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $name, array $payload = [], ?string $ignoreSpeakerId = null): string
    {
        $normalizedName = trim($name);
        $displayName = $this->displayName($normalizedName, $payload);
        $nameSlug = Str::slug($displayName !== '' ? $displayName : $normalizedName);

        if ($nameSlug === '') {
            $nameSlug = 'speaker';
        }

        $countrySuffix = $this->countrySuffix($payload);
        $sequence = $this->nextSequenceForExactIdentity($normalizedName, $displayName, $countrySuffix, $ignoreSpeakerId);

        do {
            $candidateParts = [$nameSlug];

            if ($sequence > 1) {
                $candidateParts[] = (string) $sequence;
            }

            if ($countrySuffix !== '') {
                $candidateParts[] = $countrySuffix;
            }

            $candidate = implode('-', $candidateParts);
            $sequence++;
        } while ($this->slugExists($candidate, $ignoreSpeakerId));

        return $candidate;
    }

    public function forSpeaker(Speaker $speaker): string
    {
        $speaker->loadMissing([
            'address.country',
        ]);

        $address = $speaker->addressModel;

        return $this->handle(
            $speaker->name,
            [
                'honorific' => $speaker->honorific,
                'pre_nominal' => $speaker->pre_nominal,
                'post_nominal' => $speaker->post_nominal,
                'country_id' => $address?->country_id,
                'country_code' => $address?->country?->iso2,
            ],
            (string) $speaker->getKey(),
        );
    }

    private function nextSequenceForExactIdentity(string $name, string $displayName, string $countrySuffix, ?string $ignoreSpeakerId): int
    {
        $matchingSpeakers = Speaker::query()
            ->where('speakers.name', $name)
            ->with([
                'address.country',
            ])
            ->get()
            ->filter(fn (Speaker $speaker): bool => $this->countrySuffixForSpeaker($speaker) === $countrySuffix
                && $this->displayNameForSpeaker($speaker) === $displayName);

        if ($ignoreSpeakerId !== null && $ignoreSpeakerId !== '') {
            $existingSequence = $this->existingSpeakerSequence($matchingSpeakers, $ignoreSpeakerId);

            if ($existingSequence !== null) {
                return $existingSequence;
            }

            $matchingSpeakers = $matchingSpeakers
                ->reject(fn (Speaker $speaker): bool => (string) $speaker->getKey() === $ignoreSpeakerId)
                ->values();
        }

        $matchingCount = $matchingSpeakers->count();

        return $matchingCount > 0 ? $matchingCount + 1 : 1;
    }

    /**
     * @param  Collection<int, Speaker>  $matchingSpeakers
     */
    private function existingSpeakerSequence(Collection $matchingSpeakers, string $speakerId): ?int
    {
        $orderedSpeakers = $this->orderedSpeakers($matchingSpeakers);

        $existingIndex = $orderedSpeakers->search(
            fn (Speaker $speaker): bool => (string) $speaker->getKey() === $speakerId,
        );

        if (! is_int($existingIndex)) {
            return null;
        }

        return $existingIndex + 1;
    }

    /**
     * @param  Collection<int, Speaker>  $speakers
     * @return Collection<int, Speaker>
     */
    private function orderedSpeakers(Collection $speakers): Collection
    {
        return $speakers
            ->sort(function (Speaker $left, Speaker $right): int {
                $leftCreatedAt = $left->created_at?->getTimestamp() ?? 0;
                $rightCreatedAt = $right->created_at?->getTimestamp() ?? 0;

                if ($leftCreatedAt !== $rightCreatedAt) {
                    return $leftCreatedAt <=> $rightCreatedAt;
                }

                return strcmp((string) $left->getKey(), (string) $right->getKey());
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function countrySuffix(array $payload): string
    {
        $countryCode = $this->resolveCountryCode($payload);
        $countryId = $this->integerValue($payload['country_id'] ?? null);

        if (($countryCode === null || $countryId === null) && is_array($payload['address'] ?? null)) {
            /** @var array<string, mixed> $address */
            $address = $payload['address'];
            $countryCode ??= $this->resolveCountryCode($address);
            $countryId ??= $this->integerValue($address['country_id'] ?? null);
        }

        if ($countryId === null) {
            $countryId = app(PreferredCountryResolver::class)->resolveId();
        }

        if ($countryCode === null && $countryId !== null) {
            $countryCode = Country::query()
                ->whereKey($countryId)
                ->value('iso2');
        }

        return $this->countryCodeSegment($countryCode) ?? '';
    }

    private function countrySuffixForSpeaker(Speaker $speaker): string
    {
        $speaker->loadMissing([
            'address.country',
        ]);

        $address = $speaker->addressModel;

        return $this->countrySuffix([
            'country_id' => $address?->country_id,
            'country_code' => $address?->country?->iso2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function displayName(string $name, array $payload): string
    {
        return Speaker::formatDisplayedName(
            $name,
            $payload['honorific'] ?? null,
            $payload['pre_nominal'] ?? null,
            $payload['post_nominal'] ?? null,
        );
    }

    private function displayNameForSpeaker(Speaker $speaker): string
    {
        return Speaker::formatDisplayedName(
            $speaker->name,
            $speaker->honorific,
            $speaker->pre_nominal,
            $speaker->post_nominal,
        );
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function resolveCountryCode(array $address): ?string
    {
        $countryCode = $address['country_code'] ?? null;

        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = trim($countryCode);

        return $countryCode !== '' ? $countryCode : null;
    }

    private function slugExists(string $slug, ?string $ignoreSpeakerId): bool
    {
        return Speaker::query()
            ->where('slug', $slug)
            ->when(
                $ignoreSpeakerId !== null && $ignoreSpeakerId !== '',
                fn ($query) => $query->where('speakers.id', '!=', $ignoreSpeakerId),
            )
            ->exists();
    }

    private function countryCodeSegment(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $segment = Str::lower(trim($value));

        return $segment !== '' ? $segment : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $integer = (int) $trimmed;

        return $integer > 0 ? $integer : null;
    }
}
