<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Enums\MemberSubjectType;
use App\Support\Api\Frontend\FrontendFormContractService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(
    'Manifest',
    'Machine-readable public flow discovery and field contracts. '
    .'Use these endpoints first when you need defaults, required fields, catalogs, or conditional rules before posting data.',
    weight: 10,
)]
class ManifestController extends FrontendController
{
    public function __construct(
        private readonly FrontendFormContractService $contracts,
    ) {}

    #[Endpoint(
        title: 'Discover public client flows',
        description: 'Returns the public capability manifest for frontend and mobile clients. '
            .'Use it to discover available flows, auth requirements, schema endpoints, and follow or update endpoint templates.',
    )]
    public function manifest(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->manifest($this->currentUser($request)),
        ]);
    }

    #[Endpoint(
        title: 'Get submit-event field contract',
        description: 'Returns the canonical field contract for public event submission. '
            .'Use `fields`, `defaults`, `conditional_rules`, and catalog URLs here before calling `POST /submit-event`.',
    )]
    public function submitEvent(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitEvent($this->currentUser($request)),
        ]);
    }

    #[Endpoint(
        title: 'Get institution contribution field contract',
        description: 'Returns the canonical field contract for authenticated public institution creation. '
            .'Use this before calling `POST /contributions/institutions`. '
            .'Institution create still accepts a full institution address payload, including `address.country_id`.',
    )]
    public function submitInstitution(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitInstitution(),
        ]);
    }

    #[Endpoint(
        title: 'Get speaker contribution field contract',
        description: 'Returns the canonical field contract for authenticated public speaker creation. '
            .'Use this before calling `POST /contributions/speakers`. '
            .'Speaker create uses a region-only address payload, so clients must not send `address.country_id` or detailed street/map keys.',
    )]
    public function submitSpeaker(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitSpeaker(),
        ]);
    }

    #[Endpoint(
        title: 'Get membership-claim field contract',
        description: 'Returns the field contract for claiming membership on a supported public subject type.',
    )]
    public function membershipClaim(string $subjectType): JsonResponse
    {
        $resolvedSubjectType = MemberSubjectType::fromRouteSegment($subjectType);
        abort_unless($resolvedSubjectType?->isClaimable(), 404);

        return response()->json([
            'data' => $this->contracts->membershipClaim($resolvedSubjectType),
        ]);
    }

    #[Endpoint(
        title: 'Get report field contract',
        description: 'Returns the field contract for authenticated content or data reports before calling `POST /reports`.',
    )]
    public function report(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->report(),
        ]);
    }

    #[Endpoint(
        title: 'Get account-settings field contract',
        description: 'Returns the authenticated account-settings contract, including the update endpoint and supporting catalog endpoints.',
    )]
    public function accountSettings(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'data' => $this->contracts->accountSettings($user),
        ]);
    }

    #[Endpoint(
        title: 'Get advanced-event field contract',
        description: 'Returns the authenticated field contract for creating an advanced parent program before calling `POST /advanced-events`.',
    )]
    public function advancedEvent(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'data' => $this->contracts->advancedEvent($user),
        ]);
    }

    #[Endpoint(
        title: 'Get institution-workspace field contract',
        description: 'Returns the authenticated member-management contract for institution workspaces, including add, update, and remove endpoint templates.',
    )]
    public function institutionWorkspace(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->institutionWorkspace(),
        ]);
    }
}
