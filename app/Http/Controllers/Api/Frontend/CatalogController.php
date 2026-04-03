<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Enums\MemberSubjectType;
use App\Enums\TagType;
use App\Support\Api\Frontend\FrontendCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends FrontendController
{
    public function __construct(
        private readonly FrontendCatalogService $catalogs,
    ) {}

    public function countries(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->countries(),
        ]);
    }

    public function states(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->states($request->integer('country_id')),
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->districts($request->integer('state_id')),
        ]);
    }

    public function subdistricts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->subdistricts(
                $request->integer('state_id'),
                $request->integer('district_id'),
            ),
        ]);
    }

    public function languages(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->languages(),
        ]);
    }

    public function tags(string $type, Request $request): JsonResponse
    {
        $tagType = TagType::tryFrom($type);
        abort_unless($tagType instanceof TagType, 404);

        return response()->json([
            'data' => $this->catalogs->tags($tagType, $request->string('q')->toString()),
        ]);
    }

    public function references(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->references($request->string('q')->toString()),
        ]);
    }

    public function submitInstitutions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->submitInstitutions(
                $this->currentUser($request),
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function submitSpeakers(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->submitSpeakers(
                $this->currentUser($request),
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function venues(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->venues($request->string('q')->toString()),
        ]);
    }

    public function spaces(Request $request): JsonResponse
    {
        $institutionId = $request->string('institution_id')->toString();

        return response()->json([
            'data' => $this->catalogs->spaces($institutionId !== '' ? $institutionId : null),
        ]);
    }

    public function membershipClaimSubjects(string $subjectType, Request $request): JsonResponse
    {
        $resolvedSubjectType = MemberSubjectType::fromRouteSegment($subjectType);
        abort_unless($resolvedSubjectType?->isClaimable(), 404);

        return response()->json([
            'data' => $this->catalogs->membershipClaimSubjects(
                $resolvedSubjectType,
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function prayerInstitutions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->prayerInstitutions($request->string('q')->toString()),
        ]);
    }

    public function institutionRoles(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogs->institutionRoleOptions(),
        ]);
    }
}
