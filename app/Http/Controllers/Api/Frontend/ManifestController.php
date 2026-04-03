<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Enums\MemberSubjectType;
use App\Support\Api\Frontend\FrontendFormContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManifestController extends FrontendController
{
    public function __construct(
        private readonly FrontendFormContractService $contracts,
    ) {}

    public function manifest(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->manifest($this->currentUser($request)),
        ]);
    }

    public function submitEvent(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitEvent($this->currentUser($request)),
        ]);
    }

    public function submitInstitution(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitInstitution(),
        ]);
    }

    public function submitSpeaker(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->submitSpeaker(),
        ]);
    }

    public function membershipClaim(string $subjectType): JsonResponse
    {
        $resolvedSubjectType = MemberSubjectType::fromRouteSegment($subjectType);
        abort_unless($resolvedSubjectType?->isClaimable(), 404);

        return response()->json([
            'data' => $this->contracts->membershipClaim($resolvedSubjectType),
        ]);
    }

    public function report(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->report(),
        ]);
    }

    public function accountSettings(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'data' => $this->contracts->accountSettings($user),
        ]);
    }

    public function advancedEvent(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'data' => $this->contracts->advancedEvent($user),
        ]);
    }

    public function institutionWorkspace(): JsonResponse
    {
        return response()->json([
            'data' => $this->contracts->institutionWorkspace(),
        ]);
    }
}
