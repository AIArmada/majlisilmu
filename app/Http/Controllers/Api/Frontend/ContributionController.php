<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Actions\Contributions\ApplyDirectContributionUpdateAction;
use App\Actions\Contributions\ApproveContributionRequestAction;
use App\Actions\Contributions\CancelContributionRequestAction;
use App\Actions\Contributions\RejectContributionRequestAction;
use App\Actions\Contributions\ResolveContributionChangedPayloadAction;
use App\Actions\Contributions\ResolveContributionSubjectPresentationAction;
use App\Actions\Contributions\ResolveContributionSubmissionStateAction;
use App\Actions\Contributions\ResolveContributionUpdateContextAction;
use App\Actions\Contributions\ResolveLatestPendingContributionRequestAction;
use App\Actions\Contributions\ResolveOwnContributionRequestAction;
use App\Actions\Contributions\ResolvePendingContributionApprovalsAction;
use App\Actions\Contributions\ResolveReviewableContributionRequestAction;
use App\Actions\Contributions\SubmitContributionUpdateRequestAction;
use App\Actions\Contributions\SubmitStagedContributionCreateAction;
use App\Enums\ContributionSubjectType;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Filament\Resources\ContributionRequests\Support\ContributionRequestPresenter;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Api\Frontend\FrontendMediaSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContributionController extends FrontendController
{
    public function index(Request $request, ResolvePendingContributionApprovalsAction $resolvePendingContributionApprovalsAction): JsonResponse
    {
        $user = $this->requireUser($request);

        $myRequests = $user->contributionRequests()
            ->with(['entity', 'proposer', 'reviewer'])
            ->latest('created_at')
            ->get();

        $pendingApprovals = $resolvePendingContributionApprovalsAction->handle($user);

        return response()->json([
            'data' => [
                'my_requests' => $myRequests->map(fn (ContributionRequest $contributionRequest): array => $this->contributionRequestData($contributionRequest, $user))->all(),
                'pending_approvals' => $pendingApprovals->map(fn (ContributionRequest $contributionRequest): array => $this->contributionRequestData($contributionRequest, $user))->all(),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function storeInstitution(
        Request $request,
        SubmitStagedContributionCreateAction $submitStagedContributionCreateAction,
        FrontendMediaSyncService $frontendMediaSyncService,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $this->ensureDirectoryFeedbackAllowed($user);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_column(InstitutionType::cases(), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'address' => ['required', 'array'],
            'address.country_id' => ['required', 'integer'],
            'address.state_id' => ['nullable', 'integer'],
            'address.district_id' => ['nullable', 'integer'],
            'address.subdistrict_id' => ['nullable', 'integer'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postcode' => ['nullable', 'string', 'max:16'],
            'address.lat' => ['nullable', 'numeric'],
            'address.lng' => ['nullable', 'numeric'],
            'address.google_maps_url' => ['required', 'url', 'max:255'],
            'address.google_place_id' => ['nullable', 'string', 'max:255'],
            'address.waze_url' => ['nullable', 'url', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.category' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.value' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.type' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_public' => ['nullable', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media', 'string', 'max:255'],
            'social_media.*.username' => ['nullable', 'string', 'max:255'],
            'social_media.*.url' => ['nullable', 'url', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $institution = $submitStagedContributionCreateAction->handle(
            ContributionSubjectType::Institution,
            $validated,
            $user,
            function (Institution $institution) use ($request, $frontendMediaSyncService): void {
                $frontendMediaSyncService->syncSingle($institution, $request->file('cover'), 'cover');
                $frontendMediaSyncService->syncMultiple(
                    $institution,
                    is_array($request->file('gallery')) ? $request->file('gallery') : null,
                    'gallery',
                );
            },
        );

        return response()->json([
            'data' => [
                'institution' => [
                    'id' => $institution->getKey(),
                    'slug' => $institution->slug,
                    'name' => $institution->name,
                    'status' => $institution->status,
                ],
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ], 201);
    }

    public function storeSpeaker(
        Request $request,
        SubmitStagedContributionCreateAction $submitStagedContributionCreateAction,
        FrontendMediaSyncService $frontendMediaSyncService,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $this->ensureDirectoryFeedbackAllowed($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))],
            'is_freelance' => ['nullable', 'boolean'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'honorific' => ['nullable', 'array'],
            'honorific.*' => ['string', Rule::in(array_column(Honorific::cases(), 'value'))],
            'pre_nominal' => ['nullable', 'array'],
            'pre_nominal.*' => ['string', Rule::in(array_column(PreNominal::cases(), 'value'))],
            'post_nominal' => ['nullable', 'array'],
            'post_nominal.*' => ['string', Rule::in(array_column(PostNominal::cases(), 'value'))],
            'bio' => ['nullable'],
            'address' => ['required', 'array'],
            'address.country_id' => ['required', 'integer'],
            'address.state_id' => ['nullable', 'integer'],
            'address.district_id' => ['nullable', 'integer'],
            'address.subdistrict_id' => ['nullable', 'integer'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postcode' => ['nullable', 'string', 'max:16'],
            'address.lat' => ['nullable', 'numeric'],
            'address.lng' => ['nullable', 'numeric'],
            'address.google_maps_url' => ['nullable', 'url', 'max:255'],
            'address.google_place_id' => ['nullable', 'string', 'max:255'],
            'address.waze_url' => ['nullable', 'url', 'max:255'],
            'qualifications' => ['nullable', 'array'],
            'qualifications.*.institution' => ['required_with:qualifications.*.degree', 'nullable', 'string', 'max:255'],
            'qualifications.*.degree' => ['required_with:qualifications.*.institution', 'nullable', 'string', 'max:255'],
            'qualifications.*.field' => ['nullable', 'string', 'max:255'],
            'qualifications.*.year' => ['nullable', 'digits:4'],
            'language_ids' => ['nullable', 'array'],
            'language_ids.*' => ['integer'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.category' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.value' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.type' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_public' => ['nullable', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media', 'string', 'max:255'],
            'social_media.*.username' => ['nullable', 'string', 'max:255'],
            'social_media.*.url' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $speaker = $submitStagedContributionCreateAction->handle(
            ContributionSubjectType::Speaker,
            $validated,
            $user,
            function (Speaker $speaker) use ($request, $frontendMediaSyncService): void {
                $frontendMediaSyncService->syncSingle($speaker, $request->file('avatar'), 'avatar');
                $frontendMediaSyncService->syncSingle($speaker, $request->file('cover'), 'cover');
                $frontendMediaSyncService->syncMultiple(
                    $speaker,
                    is_array($request->file('gallery')) ? $request->file('gallery') : null,
                    'gallery',
                );
            },
        );

        return response()->json([
            'data' => [
                'speaker' => [
                    'id' => $speaker->getKey(),
                    'slug' => $speaker->slug,
                    'name' => $speaker->name,
                    'status' => $speaker->status,
                ],
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ], 201);
    }

    public function suggestContext(
        string $subjectType,
        string $subject,
        Request $request,
        ResolveContributionUpdateContextAction $resolveContributionUpdateContextAction,
        ResolveContributionSubjectPresentationAction $resolveContributionSubjectPresentationAction,
        ResolveLatestPendingContributionRequestAction $resolveLatestPendingContributionRequestAction,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $this->ensureDirectoryFeedbackAllowed($user);

        $context = $resolveContributionUpdateContextAction->handle($subjectType, $subject);
        $entity = $context['entity'];

        abort_unless($user->can('view', $entity), 403);

        return response()->json([
            'data' => [
                'entity' => $this->entityData($entity),
                'initial_state' => $context['initial_state'],
                'subject_presentation' => $resolveContributionSubjectPresentationAction->handle($entity),
                'can_direct_edit' => $user->can('update', $entity),
                'latest_pending_request' => ($latestPendingRequest = $resolveLatestPendingContributionRequestAction->handle($user, $entity)) instanceof ContributionRequest
                    ? $this->contributionRequestData($latestPendingRequest, $user)
                    : null,
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function suggestUpdate(
        string $subjectType,
        string $subject,
        Request $request,
        ResolveContributionUpdateContextAction $resolveContributionUpdateContextAction,
        ResolveContributionChangedPayloadAction $resolveContributionChangedPayloadAction,
        ResolveContributionSubmissionStateAction $resolveContributionSubmissionStateAction,
        ApplyDirectContributionUpdateAction $applyDirectContributionUpdateAction,
        SubmitContributionUpdateRequestAction $submitContributionUpdateRequestAction,
        ResolveContributionSubjectPresentationAction $resolveContributionSubjectPresentationAction,
        FrontendMediaSyncService $frontendMediaSyncService,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $this->ensureDirectoryFeedbackAllowed($user);

        $context = $resolveContributionUpdateContextAction->handle($subjectType, $subject);
        $entity = $context['entity'];
        $originalData = $context['initial_state'];

        abort_unless($user->can('view', $entity), 403);

        $payload = collect($request->all())
            ->only(array_merge(array_keys($originalData), ['proposer_note']))
            ->all();

        if (isset($payload['proposer_note']) && ! is_string($payload['proposer_note'])) {
            throw ValidationException::withMessages([
                'proposer_note' => __('The proposer note must be a string.'),
            ]);
        }

        $submissionState = $resolveContributionSubmissionStateAction->handle($payload);
        $changes = $resolveContributionChangedPayloadAction->handle($submissionState['state'], $originalData);
        $canDirectEdit = $user->can('update', $entity);
        $hasInstitutionCoverChange = $canDirectEdit && $entity instanceof Institution && $request->hasFile('cover');

        if ($changes === [] && ! $hasInstitutionCoverChange) {
            throw ValidationException::withMessages([
                'data' => __('Make at least one change before continuing.'),
            ]);
        }

        if ($canDirectEdit) {
            if ($changes !== []) {
                $applyDirectContributionUpdateAction->handle($entity, $changes);
            }

            if ($hasInstitutionCoverChange) {
                $frontendMediaSyncService->syncSingle($entity, $request->file('cover'), 'cover');
            }

            return response()->json([
                'data' => [
                    'entity' => $this->entityData($entity->fresh() ?? $entity),
                    'subject_presentation' => $resolveContributionSubjectPresentationAction->handle($entity),
                    'mode' => 'direct_edit',
                ],
                'meta' => [
                    'request_id' => $this->requestId($request),
                ],
            ]);
        }

        $contributionRequest = $submitContributionUpdateRequestAction->handle(
            $entity,
            $user,
            $changes,
            $submissionState['proposer_note'],
        );

        return response()->json([
            'data' => [
                'request' => $this->contributionRequestData($contributionRequest, $user),
                'mode' => 'review',
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ], 201);
    }

    public function approve(
        string $requestId,
        Request $request,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
        ApproveContributionRequestAction $approveContributionRequestAction,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $contributionRequest = $resolveReviewableContributionRequestAction->handle($user, $requestId);
        $reviewNote = $request->validate(['reviewer_note' => ['nullable', 'string', 'max:2000']])['reviewer_note'] ?? null;

        $approvedRequest = $approveContributionRequestAction->handle($contributionRequest, $user, $reviewNote);

        return response()->json([
            'data' => [
                'request' => $this->contributionRequestData($approvedRequest, $user),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function reject(
        string $requestId,
        Request $request,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
        RejectContributionRequestAction $rejectContributionRequestAction,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $validated = $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(ContributionRequestPresenter::rejectionReasonOptions()))],
            'reviewer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $contributionRequest = $resolveReviewableContributionRequestAction->handle($user, $requestId);
        $rejectedRequest = $rejectContributionRequestAction->handle(
            $contributionRequest,
            $user,
            (string) $validated['reason_code'],
            $validated['reviewer_note'] ?? null,
        );

        return response()->json([
            'data' => [
                'request' => $this->contributionRequestData($rejectedRequest, $user),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function cancel(
        string $requestId,
        Request $request,
        ResolveOwnContributionRequestAction $resolveOwnContributionRequestAction,
        CancelContributionRequestAction $cancelContributionRequestAction,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $contributionRequest = $resolveOwnContributionRequestAction->handle($user, $requestId);
        $cancelledRequest = $cancelContributionRequestAction->handle($contributionRequest, $user);

        return response()->json([
            'data' => [
                'request' => $this->contributionRequestData($cancelledRequest, $user),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    private function ensureDirectoryFeedbackAllowed(User $user): void
    {
        if (! $user->canSubmitDirectoryFeedback()) {
            abort(403, $user->directoryFeedbackBanMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function contributionRequestData(ContributionRequest $contributionRequest, User $currentUser): array
    {
        $presentation = $contributionRequest->entity instanceof Event
            || $contributionRequest->entity instanceof Institution
            || $contributionRequest->entity instanceof Reference
            || $contributionRequest->entity instanceof Speaker
                ? app(ResolveContributionSubjectPresentationAction::class)->handle($contributionRequest->entity)
                : null;

        return [
            'id' => $contributionRequest->getKey(),
            'type' => $this->enumValue($contributionRequest->type),
            'type_label' => ContributionRequestPresenter::labelForType($contributionRequest->type),
            'subject_type' => $this->enumValue($contributionRequest->subject_type),
            'subject_label' => ContributionRequestPresenter::labelForSubject($contributionRequest->subject_type),
            'entity_title' => ContributionRequestPresenter::entityTitle($contributionRequest),
            'status' => $this->enumValue($contributionRequest->status),
            'status_label' => ContributionRequestPresenter::labelForStatus($contributionRequest->status),
            'reason_code' => $contributionRequest->reason_code,
            'proposer_note' => $contributionRequest->proposer_note,
            'reviewer_note' => $contributionRequest->reviewer_note,
            'changed_fields' => array_keys($contributionRequest->proposed_data ?? []),
            'created_at' => $this->optionalDateTimeString($contributionRequest->created_at),
            'reviewed_at' => $this->optionalDateTimeString($contributionRequest->reviewed_at),
            'cancelled_at' => $this->optionalDateTimeString($contributionRequest->cancelled_at),
            'can_cancel' => $contributionRequest->isPending() && (string) $contributionRequest->proposer_id === (string) $currentUser->getKey(),
            'presentation' => $presentation,
            'proposer' => $contributionRequest->proposer?->only(['id', 'name', 'email']),
            'reviewer' => $contributionRequest->reviewer?->only(['id', 'name', 'email']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entityData(Event|Institution|Reference|Speaker $entity): array
    {
        return match (true) {
            $entity instanceof Institution => [
                'id' => $entity->getKey(),
                'type' => 'institution',
                'slug' => $entity->slug,
                'title' => $entity->display_name,
                'status' => $entity->status,
            ],
            $entity instanceof Speaker => [
                'id' => $entity->getKey(),
                'type' => 'speaker',
                'slug' => $entity->slug,
                'title' => $entity->formatted_name,
                'status' => $entity->status,
            ],
            $entity instanceof Reference => [
                'id' => $entity->getKey(),
                'type' => 'reference',
                'slug' => $entity->slug,
                'title' => $entity->title,
                'status' => $entity->status,
            ],
            default => [
                'id' => $entity->getKey(),
                'type' => 'event',
                'slug' => $entity->slug,
                'title' => $entity->title,
                'status' => (string) $entity->status,
            ],
        };
    }
}
