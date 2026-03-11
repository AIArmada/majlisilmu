<?php

namespace App\Services\DawahShare;

use App\Enums\DawahShareOutcomeType;
use App\Enums\DawahShareSubjectType;
use App\Enums\DawahShareVisitKind;
use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\DawahShareOutcome;
use App\Models\DawahShareShareEvent;
use App\Models\DawahShareVisit;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DawahShareService
{
    /**
     * @return list<string>
     */
    public function supportedProviders(): array
    {
        return ['whatsapp', 'telegram', 'line', 'facebook', 'x', 'instagram', 'tiktok', 'email'];
    }

    /**
     * @return array{url: string, platform_links: array<string, string>}
     */
    public function sharePayload(?User $user, string $url, string $shareText, ?string $fallbackTitle = null): array
    {
        if ($user instanceof User) {
            $link = $this->createOrReuseLink($user, $url, $fallbackTitle);
            $shareUrl = $this->sharedUrlForLink($link);

            return [
                'url' => $shareUrl,
                'platform_links' => $this->platformLinks($shareUrl, $shareText),
            ];
        }

        $shareUrl = $this->normalizeAbsoluteInternalUrl($url);

        return [
            'url' => $shareUrl,
            'platform_links' => $this->platformLinks($shareUrl, $shareText),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function redirectLinks(string $url, string $shareText, ?string $fallbackTitle = null): array
    {
        $normalizedUrl = $this->normalizeAbsoluteInternalUrl($url);
        $query = [
            'url' => $normalizedUrl,
            'text' => $shareText,
        ];

        if (filled($fallbackTitle)) {
            $query['title'] = $fallbackTitle;
        }

        return collect($this->supportedProviders())
            ->mapWithKeys(fn (string $provider): array => [
                $provider => route('dawah-share.redirect', ['provider' => $provider] + $query),
            ])
            ->all();
    }

    public function redirectUrl(
        string $provider,
        ?User $user,
        string $url,
        string $shareText,
        ?string $fallbackTitle = null,
        ?Request $request = null
    ): string {
        $provider = $this->normalizeProvider($provider) ?? throw new \InvalidArgumentException('Unsupported share provider.');

        if (! $user instanceof User) {
            return $this->platformLink($provider, $this->normalizeAbsoluteInternalUrl($url), $shareText);
        }

        $link = $this->createOrReuseLink($user, $url, $fallbackTitle);
        $this->recordOutboundShare($link, $user, $provider, $request);

        return $this->platformLink($provider, $this->sharedUrlForLink($link, $provider), $shareText);
    }

    public function attributedUrl(User $user, string $url, ?string $fallbackTitle = null): string
    {
        $link = $this->createOrReuseLink($user, $url, $fallbackTitle);

        return $this->sharedUrlForLink($link);
    }

    public function createOrReuseLink(User $user, string $url, ?string $fallbackTitle = null): DawahShareLink
    {
        $target = $this->classifyUrl($url, $fallbackTitle);

        /** @var DawahShareLink|null $link */
        $link = DawahShareLink::query()
            ->where('user_id', $user->id)
            ->where('canonical_url', $target['canonical_url'])
            ->first();

        if ($link instanceof DawahShareLink) {
            $link->fill([
                'destination_url' => $target['destination_url'],
                'title_snapshot' => $target['title_snapshot'],
                'metadata' => $target['metadata'],
                'last_shared_at' => now(),
            ]);
            $link->save();

            return $link;
        }

        /** @var DawahShareLink $link */
        $link = DawahShareLink::query()->create([
            'user_id' => $user->id,
            'subject_type' => $target['subject_type'],
            'subject_id' => $target['subject_id'],
            'subject_key' => $target['subject_key'],
            'destination_url' => $target['destination_url'],
            'canonical_url' => $target['canonical_url'],
            'share_token' => Str::random(40),
            'title_snapshot' => $target['title_snapshot'],
            'metadata' => $target['metadata'],
            'last_shared_at' => now(),
        ]);

        return $link;
    }

    /**
     * @return array<string, string>
     */
    public function platformLinks(string $url, string $shareText): array
    {
        return collect($this->supportedProviders())
            ->mapWithKeys(fn (string $provider): array => [
                $provider => $this->platformLink($provider, $this->appendShareProvider($url, $provider), $shareText),
            ])
            ->all();
    }

    public function captureRequest(Request $request): ?string
    {
        $cookieState = $this->readCookieState($request);

        if ($this->shouldIgnoreRequest($request)) {
            return $cookieState['encoded'] ?? null;
        }

        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return $cookieState['encoded'] ?? null;
        }

        if ($this->isBotRequest($request)) {
            return $cookieState['encoded'] ?? null;
        }

        $parameter = (string) config('dawah-share.query_parameter', 'mi_share');
        $signedToken = $request->query($parameter);
        $visitorKey = $cookieState['visitor_key'] ?? (string) Str::ulid();
        $shareProvider = $this->shareProviderFromRequest($request);

        if (is_string($signedToken) && $signedToken !== '') {
            $link = $this->resolveLinkFromSignedToken($signedToken);

            if ($link instanceof DawahShareLink) {
                $attribution = DawahShareAttribution::query()->create([
                    'link_id' => $link->id,
                    'user_id' => $link->user_id,
                    'visitor_key' => $visitorKey,
                    'cookie_value' => (string) Str::ulid(),
                    'landing_url' => $this->cleanTrackedUrl($request->fullUrl()),
                    'referrer_url' => $request->headers->get('referer'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'query' => Arr::except($request->query(), [
                            $parameter,
                            (string) config('dawah-share.provider_query_parameter', 'mi_channel'),
                        ]),
                        'share_provider' => $shareProvider,
                    ],
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'expires_at' => now()->addDays((int) config('dawah-share.ttl_days', 30)),
                ]);

                $this->recordVisit(
                    $attribution,
                    $request,
                    DawahShareVisitKind::Landing,
                    $this->cleanTrackedUrl($request->fullUrl())
                );

                return $this->encodeCookieState($visitorKey, $attribution->cookie_value);
            }
        }

        $attribution = $this->resolveActiveAttribution($request);

        if (! $attribution instanceof DawahShareAttribution) {
            return $cookieState['encoded'] ?? null;
        }

        $attribution->forceFill([
            'last_seen_at' => now(),
            'expires_at' => now()->addDays((int) config('dawah-share.ttl_days', 30)),
        ])->save();

        $cleanUrl = $this->cleanTrackedUrl($request->fullUrl());
        $kind = $this->visitKindForRequest($attribution, $cleanUrl);

        if (! $this->recentDuplicateVisitExists($attribution, $cleanUrl)) {
            $this->recordVisit($attribution, $request, $kind, $cleanUrl);
        }

        return $this->encodeCookieState($attribution->visitor_key, $attribution->cookie_value);
    }

    public function resolveActiveAttribution(?Request $request = null): ?DawahShareAttribution
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return null;
        }

        $cookieState = $this->readCookieState($request);
        $cookieValue = $cookieState['attribution_cookie'] ?? null;

        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        /** @var DawahShareAttribution|null $attribution */
        $attribution = DawahShareAttribution::query()
            ->with('link')
            ->where('cookie_value', $cookieValue)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $attribution;
    }

    public function recordSignup(User $user, ?Request $request = null): ?DawahShareOutcome
    {
        $attribution = $this->resolveActiveAttribution($request);

        if (! $attribution instanceof DawahShareAttribution) {
            return null;
        }

        $attribution->forceFill([
            'signed_up_user_id' => $user->id,
            'last_seen_at' => now(),
        ])->save();

        return $this->recordOutcome(
            type: DawahShareOutcomeType::Signup,
            outcomeKey: 'signup:user:'.$user->id,
            actor: $user,
            request: $request,
            metadata: [
                'signed_up_user_id' => $user->id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordOutcome(
        DawahShareOutcomeType $type,
        string $outcomeKey,
        ?Model $subject = null,
        ?User $actor = null,
        ?Request $request = null,
        array $metadata = []
    ): ?DawahShareOutcome {
        $attribution = $this->resolveActiveAttribution($request);

        if (! $attribution instanceof DawahShareAttribution) {
            return null;
        }

        /** @var DawahShareOutcome|null $existing */
        $existing = DawahShareOutcome::query()
            ->where('outcome_key', $outcomeKey)
            ->first();

        if ($existing instanceof DawahShareOutcome) {
            return $existing;
        }

        $attribution->loadMissing('link');
        $subjectData = $subject instanceof Model
            ? $this->classifySubjectModel($subject)
            : [
                'subject_type' => $attribution->link->subject_type,
                'subject_id' => $attribution->link->subject_id,
                'subject_key' => $attribution->link->subject_key,
            ];

        /** @var DawahShareOutcome $outcome */
        $outcome = DawahShareOutcome::query()->create([
            'link_id' => $attribution->link_id,
            'attribution_id' => $attribution->id,
            'sharer_user_id' => $attribution->user_id,
            'actor_user_id' => $actor?->id,
            'outcome_type' => $type->value,
            'subject_type' => $subjectData['subject_type'],
            'subject_id' => $subjectData['subject_id'],
            'subject_key' => $subjectData['subject_key'],
            'outcome_key' => $outcomeKey,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        return $outcome;
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function classifyUrl(string $url, ?string $fallbackTitle = null): array
    {
        $absoluteUrl = $this->normalizeAbsoluteInternalUrl($url);
        $parsed = parse_url($absoluteUrl) ?: [];
        $path = (string) ($parsed['path'] ?? '/');
        $query = $this->normalizeQueryFromUrl($absoluteUrl);
        $routeMatch = $this->matchInternalRoute($path, $query);

        if ($routeMatch !== null) {
            return $this->classifyMatchedRoute($routeMatch['name'], $routeMatch['parameters'], $query, $fallbackTitle);
        }

        if (preg_match('#^/events/([^/]+)$#', $path, $matches) === 1 || preg_match('#^/majlis/([^/]+)$#', $path, $matches) === 1) {
            $event = Event::query()->where('slug', $matches[1])->first();

            if ($event instanceof Event) {
                return $this->subjectResult(
                    DawahShareSubjectType::Event,
                    $event->id,
                    'event:'.$event->id,
                    route('events.show', $event),
                    route('events.show', $event),
                    $event->title,
                    ['slug' => $event->slug]
                );
            }
        }

        return $this->subjectResult(
            DawahShareSubjectType::Page,
            null,
            'page:'.trim($path, '/'),
            $absoluteUrl,
            $this->buildAbsoluteUrl($path, $query),
            $fallbackTitle ?: config('app.name'),
            [
                'path' => $path,
                'query' => $query,
            ],
        );
    }

    /**
     * @return array{subject_type: string, subject_id: string|null, subject_key: string}
     */
    public function classifySubjectModel(Model $subject): array
    {
        return match (true) {
            $subject instanceof Event => [
                'subject_type' => DawahShareSubjectType::Event->value,
                'subject_id' => $subject->id,
                'subject_key' => 'event:'.$subject->id,
            ],
            $subject instanceof Institution => [
                'subject_type' => DawahShareSubjectType::Institution->value,
                'subject_id' => $subject->id,
                'subject_key' => 'institution:'.$subject->id,
            ],
            $subject instanceof Speaker => [
                'subject_type' => DawahShareSubjectType::Speaker->value,
                'subject_id' => $subject->id,
                'subject_key' => 'speaker:'.$subject->id,
            ],
            $subject instanceof Series => [
                'subject_type' => DawahShareSubjectType::Series->value,
                'subject_id' => $subject->id,
                'subject_key' => 'series:'.$subject->id,
            ],
            $subject instanceof Reference => [
                'subject_type' => DawahShareSubjectType::Reference->value,
                'subject_id' => $subject->id,
                'subject_key' => 'reference:'.$subject->id,
            ],
            default => [
                'subject_type' => DawahShareSubjectType::Page->value,
                'subject_id' => null,
                'subject_key' => 'page:model:'.$subject->getMorphClass().':'.$subject->getKey(),
            ],
        };
    }

    private function signedToken(string $shareToken): string
    {
        $signature = hash_hmac('sha256', $shareToken, (string) config('dawah-share.signing_key'));

        return $shareToken.'.'.$signature;
    }

    private function resolveLinkFromSignedToken(string $signedToken): ?DawahShareLink
    {
        $parts = explode('.', $signedToken, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$token, $signature] = $parts;

        $expected = hash_hmac('sha256', $token, (string) config('dawah-share.signing_key'));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        /** @var DawahShareLink|null $link */
        $link = DawahShareLink::query()
            ->where('share_token', $token)
            ->first();

        return $link;
    }

    private function encodeCookieState(string $visitorKey, string $attributionCookie): string
    {
        return base64_encode(json_encode([
            'visitor_key' => $visitorKey,
            'attribution_cookie' => $attributionCookie,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{visitor_key?: string, attribution_cookie?: string, encoded?: string}
     */
    private function readCookieState(Request $request): array
    {
        $cookieName = (string) config('dawah-share.cookie.name', 'mi_dawah_share');
        $encoded = $request->cookie($cookieName);

        if (! is_string($encoded) || $encoded === '') {
            return [];
        }

        $payload = $this->decodeCookieStatePayload($encoded);

        if (is_array($payload) && ! $this->isDecodedCookieStatePayload($payload)) {
            $payload = null;
        }

        if (! is_array($payload)) {
            try {
                $decrypted = app('encrypter')->decrypt($encoded, false);
            } catch (\Throwable) {
                $decrypted = null;
            }

            if (! is_string($decrypted) || $decrypted === '') {
                return [];
            }

            $decrypted = CookieValuePrefix::validate(
                $cookieName,
                $decrypted,
                app('encrypter')->getAllKeys(),
            );

            if (! is_string($decrypted) || $decrypted === '') {
                return [];
            }

            $payload = $this->decodeCookieStatePayload($decrypted);

            if (! is_array($payload) || ! $this->isDecodedCookieStatePayload($payload)) {
                return [];
            }
        }

        return [
            'visitor_key' => is_string($payload['visitor_key'] ?? null) ? $payload['visitor_key'] : null,
            'attribution_cookie' => is_string($payload['attribution_cookie'] ?? null) ? $payload['attribution_cookie'] : null,
            'encoded' => $encoded,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeCookieStatePayload(string $encoded): ?array
    {
        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        try {
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isDecodedCookieStatePayload(array $payload): bool
    {
        return is_string($payload['visitor_key'] ?? null)
            && $payload['visitor_key'] !== ''
            && is_string($payload['attribution_cookie'] ?? null)
            && $payload['attribution_cookie'] !== '';
    }

    private function recentDuplicateVisitExists(DawahShareAttribution $attribution, string $cleanUrl): bool
    {
        return DawahShareVisit::query()
            ->where('attribution_id', $attribution->id)
            ->where('visited_url', $cleanUrl)
            ->where('occurred_at', '>=', now()->subMinutes((int) config('dawah-share.visit_dedupe_minutes', 5)))
            ->exists();
    }

    private function visitKindForRequest(DawahShareAttribution $attribution, string $cleanUrl): DawahShareVisitKind
    {
        if ($cleanUrl === $attribution->landing_url) {
            return DawahShareVisitKind::Return;
        }

        return DawahShareVisitKind::Navigated;
    }

    private function recordVisit(
        DawahShareAttribution $attribution,
        Request $request,
        DawahShareVisitKind $kind,
        string $cleanUrl
    ): DawahShareVisit {
        $target = $this->classifyUrl($cleanUrl, $attribution->link?->title_snapshot);

        /** @var DawahShareVisit $visit */
        $visit = DawahShareVisit::query()->create([
            'link_id' => $attribution->link_id,
            'attribution_id' => $attribution->id,
            'visitor_key' => $attribution->visitor_key,
            'visited_url' => $cleanUrl,
            'subject_type' => $target['subject_type'],
            'subject_id' => $target['subject_id'],
            'subject_key' => $target['subject_key'],
            'visit_kind' => $kind->value,
            'metadata' => [
                'referrer_url' => $request->headers->get('referer'),
                'query' => Arr::except($request->query(), [
                    (string) config('dawah-share.query_parameter', 'mi_share'),
                    (string) config('dawah-share.provider_query_parameter', 'mi_channel'),
                ]),
                'share_provider' => data_get($attribution->metadata, 'share_provider'),
            ],
            'occurred_at' => now(),
        ]);

        return $visit;
    }

    private function cleanTrackedUrl(string $url): string
    {
        $parsed = parse_url($this->normalizeAbsoluteInternalUrl($url)) ?: [];
        $path = (string) ($parsed['path'] ?? '/');
        $query = $this->normalizeQueryFromUrl($url);

        unset($query[(string) config('dawah-share.query_parameter', 'mi_share')]);
        unset($query[(string) config('dawah-share.provider_query_parameter', 'mi_channel')]);

        return $this->buildAbsoluteUrl($path, $query);
    }

    private function normalizeAbsoluteInternalUrl(string $url): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $host = parse_url($url, PHP_URL_HOST);
            $allowedHost = parse_url($baseUrl, PHP_URL_HOST);
            $currentHost = request()->getHost();

            if ($host !== null && ! in_array($host, array_filter([$allowedHost, $currentHost]), true)) {
                throw new \InvalidArgumentException('Only internal MajlisIlmu URLs can be shared.');
            }

            return $url;
        }

        $path = str_starts_with($url, '/') ? $url : '/'.$url;

        return $baseUrl.$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeQueryFromUrl(string $url): array
    {
        parse_str((string) parse_url($this->normalizeAbsoluteInternalUrl($url), PHP_URL_QUERY), $query);

        $query = Arr::where($query, fn (mixed $value): bool => $this->filledQueryValue($value));
        unset($query[(string) config('dawah-share.query_parameter', 'mi_share')]);

        return $this->sortQueryRecursively($query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function sortQueryRecursively(array $query): array
    {
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $query[$key] = $this->sortNestedArray($value);
            }
        }

        ksort($query);

        return $query;
    }

    public function recordOutboundShare(
        DawahShareLink $link,
        User $user,
        string $provider,
        ?Request $request = null
    ): DawahShareShareEvent {
        /** @var DawahShareShareEvent $event */
        $event = DawahShareShareEvent::query()->create([
            'link_id' => $link->id,
            'user_id' => $user->id,
            'provider' => $provider,
            'event_type' => 'outbound_click',
            'metadata' => [
                'referrer_url' => $request?->headers->get('referer'),
                'user_agent' => $request?->userAgent(),
                'ip_address' => $request?->ip(),
            ],
            'occurred_at' => now(),
        ]);

        return $event;
    }

    private function sharedUrlForLink(DawahShareLink $link, ?string $provider = null): string
    {
        $parameter = (string) config('dawah-share.query_parameter', 'mi_share');
        $signedToken = $this->signedToken($link->share_token);

        return $this->appendShareProvider($this->appendQueryParameters($link->destination_url, [
            $parameter => $signedToken,
        ]), $provider);
    }

    private function platformLink(string $provider, string $url, string $shareText): string
    {
        $encodedUrl = urlencode($url);
        $encodedText = urlencode($shareText);
        $encodedBody = urlencode($shareText."\n".$url);

        return match ($provider) {
            'whatsapp' => "https://wa.me/?text={$encodedText}%20{$encodedUrl}",
            'telegram' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedText}",
            'line' => "https://social-plugins.line.me/lineit/share?url={$encodedUrl}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'x' => "https://x.com/intent/tweet?text={$encodedText}&url={$encodedUrl}",
            'instagram' => 'https://www.instagram.com/',
            'tiktok' => 'https://www.tiktok.com/',
            'email' => "mailto:?subject={$encodedText}&body={$encodedBody}",
            default => $url,
        };
    }

    private function appendShareProvider(string $url, ?string $provider): string
    {
        $provider = $this->normalizeProvider($provider);

        if (! is_string($provider)) {
            return $url;
        }

        return $this->appendQueryParameters($url, [
            (string) config('dawah-share.provider_query_parameter', 'mi_channel') => $provider,
        ]);
    }

    private function shareProviderFromRequest(Request $request): ?string
    {
        $provider = $request->query((string) config('dawah-share.provider_query_parameter', 'mi_channel'));

        return is_string($provider) ? $this->normalizeProvider($provider) : null;
    }

    private function normalizeProvider(?string $provider): ?string
    {
        if (! is_string($provider) || $provider === '') {
            return null;
        }

        return in_array($provider, $this->supportedProviders(), true) ? $provider : null;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function sortNestedArray(array $value): array
    {
        if (array_is_list($value)) {
            sort($value);

            return $value;
        }

        foreach ($value as $key => $nested) {
            if (is_array($nested)) {
                $value[$key] = $this->sortNestedArray($nested);
            }
        }

        ksort($value);

        return $value;
    }

    private function filledQueryValue(mixed $value): bool
    {
        if (is_array($value)) {
            return Arr::where($value, fn (mixed $nested): bool => $this->filledQueryValue($nested)) !== [];
        }

        return filled($value);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{name: string, parameters: array<string, mixed>}|null
     */
    private function matchInternalRoute(string $path, array $query): ?array
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path.'?'.http_build_query($query), 'GET'));
        } catch (NotFoundHttpException) {
            return null;
        }

        $name = $route->getName();

        if (! is_string($name) || $name === '') {
            return null;
        }

        return [
            'name' => $name,
            'parameters' => $route->parameters(),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function classifyMatchedRoute(string $routeName, array $parameters, array $query, ?string $fallbackTitle): array
    {
        return match ($routeName) {
            'events.show' => $this->eventTarget((string) ($parameters['event'] ?? '')),
            'institutions.show' => $this->institutionTarget((string) ($parameters['institution'] ?? '')),
            'speakers.show' => $this->speakerTarget((string) ($parameters['speaker'] ?? '')),
            'series.show' => $this->seriesTarget((string) ($parameters['series'] ?? '')),
            'references.show' => $this->referenceTarget((string) ($parameters['reference'] ?? '')),
            'events.index' => $this->searchOrPageTarget($query, $fallbackTitle),
            default => $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:'.$routeName,
                $this->buildAbsoluteUrl((string) parse_url(route($routeName, $parameters, false), PHP_URL_PATH), $query),
                $this->buildAbsoluteUrl((string) parse_url(route($routeName, $parameters, false), PHP_URL_PATH), $query),
                $fallbackTitle ?: Str::headline(str_replace('.', ' ', $routeName)),
                [
                    'route_name' => $routeName,
                    'parameters' => $parameters,
                    'query' => $query,
                ],
            ),
        };
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function eventTarget(string $slug): array
    {
        $event = Event::query()->where('slug', $slug)->first();

        if (! $event instanceof Event) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:majlis:'.$slug,
                $this->buildAbsoluteUrl('/majlis/'.$slug, []),
                $this->buildAbsoluteUrl('/majlis/'.$slug, []),
                config('app.name'),
                ['slug' => $slug],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Event,
            $event->id,
            'event:'.$event->id,
            route('events.show', $event),
            route('events.show', $event),
            $event->title,
            ['slug' => $event->slug],
        );
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function institutionTarget(string $slug): array
    {
        $institution = Institution::query()->where('slug', $slug)->first();

        if (! $institution instanceof Institution) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:institusi:'.$slug,
                $this->buildAbsoluteUrl('/institusi/'.$slug, []),
                $this->buildAbsoluteUrl('/institusi/'.$slug, []),
                config('app.name'),
                ['slug' => $slug],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Institution,
            $institution->id,
            'institution:'.$institution->id,
            route('institutions.show', $institution),
            route('institutions.show', $institution),
            $institution->name,
            ['slug' => $institution->slug],
        );
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function speakerTarget(string $slug): array
    {
        $speaker = Speaker::query()->where('slug', $slug)->first();

        if (! $speaker instanceof Speaker) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:penceramah:'.$slug,
                $this->buildAbsoluteUrl('/penceramah/'.$slug, []),
                $this->buildAbsoluteUrl('/penceramah/'.$slug, []),
                config('app.name'),
                ['slug' => $slug],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Speaker,
            $speaker->id,
            'speaker:'.$speaker->id,
            route('speakers.show', $speaker),
            route('speakers.show', $speaker),
            $speaker->formatted_name,
            ['slug' => $speaker->slug],
        );
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function seriesTarget(string $slug): array
    {
        $series = Series::query()->where('slug', $slug)->first();

        if (! $series instanceof Series) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:series:'.$slug,
                $this->buildAbsoluteUrl('/siri/'.$slug, []),
                $this->buildAbsoluteUrl('/siri/'.$slug, []),
                config('app.name'),
                ['slug' => $slug],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Series,
            $series->id,
            'series:'.$series->id,
            route('series.show', $series),
            route('series.show', $series),
            $series->title,
            ['slug' => $series->slug],
        );
    }

    /**
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function referenceTarget(string $referenceId): array
    {
        $reference = Reference::query()->find($referenceId);

        if (! $reference instanceof Reference) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:reference:'.$referenceId,
                $this->buildAbsoluteUrl('/rujukan/'.$referenceId, []),
                $this->buildAbsoluteUrl('/rujukan/'.$referenceId, []),
                config('app.name'),
                ['reference' => $referenceId],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Reference,
            $reference->id,
            'reference:'.$reference->id,
            route('references.show', $reference),
            route('references.show', $reference),
            $reference->title,
            ['reference' => $reference->id],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function searchOrPageTarget(array $query, ?string $fallbackTitle): array
    {
        $canonicalUrl = $this->buildAbsoluteUrl('/majlis', $query);

        if ($query === []) {
            return $this->subjectResult(
                DawahShareSubjectType::Page,
                null,
                'page:events.index',
                $canonicalUrl,
                $canonicalUrl,
                $fallbackTitle ?: __('Events'),
                ['route_name' => 'events.index'],
            );
        }

        return $this->subjectResult(
            DawahShareSubjectType::Search,
            null,
            'search:'.$canonicalUrl,
            $canonicalUrl,
            $canonicalUrl,
            $fallbackTitle ?: __('Search Results'),
            ['query' => $query],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     subject_type: string,
     *     subject_id: string|null,
     *     subject_key: string,
     *     destination_url: string,
     *     canonical_url: string,
     *     title_snapshot: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function subjectResult(
        DawahShareSubjectType $type,
        ?string $subjectId,
        string $subjectKey,
        string $destinationUrl,
        string $canonicalUrl,
        string $titleSnapshot,
        array $metadata
    ): array {
        return [
            'subject_type' => $type->value,
            'subject_id' => $subjectId,
            'subject_key' => $subjectKey,
            'destination_url' => $destinationUrl,
            'canonical_url' => $canonicalUrl,
            'title_snapshot' => $titleSnapshot,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildAbsoluteUrl(string $path, array $query): string
    {
        $base = rtrim((string) config('app.url'), '/').(str_starts_with($path, '/') ? $path : '/'.$path);

        if ($query === []) {
            return $base;
        }

        return $base.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function appendQueryParameters(string $url, array $params): string
    {
        $parsed = parse_url($url) ?: [];
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query = array_merge($query, $params);
        $base = strtok($url, '?') ?: $url;

        return $base.'?'.http_build_query($query);
    }

    private function isBotRequest(Request $request): bool
    {
        $userAgent = Str::lower((string) $request->userAgent());

        if ($userAgent === '') {
            return false;
        }

        foreach ((array) config('dawah-share.bot_user_agents', []) as $needle) {
            if (str_contains($userAgent, Str::lower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function shouldIgnoreRequest(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'dawah-share.');
    }
}
