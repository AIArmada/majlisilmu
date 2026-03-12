<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ShareTracking\ShareTrackingAttributionData;
use App\Data\ShareTracking\ShareTrackingLinkData;
use App\Data\ShareTracking\ShareTrackingOutcomeData;
use App\Enums\DawahShareOutcomeType;
use App\Models\User;
use App\Services\ShareTracking\AffiliatesShareTrackingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class ShareTrackingService
{
    public function __construct(
        private AffiliatesShareTrackingService $affiliatesShareTrackingService,
    ) {}

    /**
     * @return list<string>
     */
    public function supportedProviders(): array
    {
        return $this->affiliatesShareTrackingService->supportedProviders();
    }

    /**
     * @return array{url: string, platform_links: array<string, string>}
     */
    public function sharePayload(?User $user, string $url, string $shareText, ?string $fallbackTitle = null): array
    {
        return $this->affiliatesShareTrackingService->sharePayload($user, $url, $shareText, $fallbackTitle);
    }

    /**
     * @return array<string, string>
     */
    public function redirectLinks(string $url, string $shareText, ?string $fallbackTitle = null): array
    {
        return $this->affiliatesShareTrackingService->redirectLinks($url, $shareText, $fallbackTitle);
    }

    public function redirectUrl(
        string $provider,
        ?User $user,
        string $url,
        string $shareText,
        ?string $fallbackTitle = null,
        ?Request $request = null,
    ): string {
        return $this->affiliatesShareTrackingService->redirectUrl($provider, $user, $url, $shareText, $fallbackTitle, $request);
    }

    public function attributedUrl(User $user, string $url, ?string $fallbackTitle = null): string
    {
        return $this->affiliatesShareTrackingService->attributedUrl($user, $url, $fallbackTitle);
    }

    public function captureRequest(Request $request): ?string
    {
        return $this->affiliatesShareTrackingService->captureRequest($request);
    }

    public function resolveActiveAttribution(?Request $request = null): ?ShareTrackingAttributionData
    {
        return $this->affiliatesShareTrackingService->resolveActiveAttribution($request);
    }

    public function recordSignup(User $user, ?Request $request = null): ?ShareTrackingOutcomeData
    {
        return $this->affiliatesShareTrackingService->recordSignup($user, $request);
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
        array $metadata = [],
    ): ?ShareTrackingOutcomeData {
        return $this->affiliatesShareTrackingService->recordOutcome($type, $outcomeKey, $subject, $actor, $request, $metadata);
    }

    public function createOrReuseLink(User $user, string $url, ?string $fallbackTitle = null): ShareTrackingLinkData
    {
        return $this->affiliatesShareTrackingService->createOrReuseLink($user, $url, $fallbackTitle);
    }

    public function deleteUserTracking(User $user): void
    {
        $this->affiliatesShareTrackingService->deleteUserTracking($user);
    }
}
