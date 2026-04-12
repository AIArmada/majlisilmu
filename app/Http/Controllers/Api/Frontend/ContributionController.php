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
use App\Services\ContributionEntityMutationService;
use App\Support\Api\Frontend\FrontendMediaSyncService;
use App\Support\Events\EventContributionUpdateStateMapper;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group(
    'Contribution',
    'Authenticated public contribution flows for creating institutions or speakers and suggesting edits to existing events, institutions, speakers, or references. '
    .'Update suggestions are permission-aware: the same endpoint either edits directly or creates a review request.',
    weight: 20,
)]
class ContributionController extends FrontendController
{
    #[Endpoint(
        title: 'List contribution requests and pending approvals',
        description: 'Returns the authenticated user\'s own contribution requests plus any pending approvals they are allowed to review.',
    )]
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

    #[Endpoint(
        title: 'Create an institution contribution',
        description: 'Creates a new public institution contribution request. '
            .'The proposer is not automatically added as an institution owner, admin, editor, or member; they only receive review outcome notifications. '
            .'Duplicate institutions are rejected when the normalized name and locality match an existing institution. '
            .'Fetch `GET /forms/contributions/institutions` first to discover required fields, defaults, media support, and conditional rules.',
    )]
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
            'address' => ['present', 'array'],
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
            'message' => __('Thank you. Your institution submission has been received. We will notify you if it is approved or rejected.'),
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

    #[Endpoint(
        title: 'Create a speaker contribution',
        description: 'Creates a new public speaker contribution request using a region-only address payload (`state_id`, `district_id`, `subdistrict_id`). The speaker country follows the active country scope automatically. '
            .'Clients must not send `address.country_id` or detailed street/map keys here. Duplicate speakers are rejected when the normalized name, gender, and title sets match an existing speaker. '
            .'Fetch `GET /forms/contributions/speakers` first to discover required fields, defaults, media support, and conditional rules.',
    )]
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
            'address.country_id' => ['prohibited'],
            'address.state_id' => ['nullable', 'integer'],
            'address.district_id' => ['nullable', 'integer'],
            'address.subdistrict_id' => ['nullable', 'integer'],
            'address.line1' => ['prohibited'],
            'address.line2' => ['prohibited'],
            'address.postcode' => ['prohibited'],
            'address.lat' => ['prohibited'],
            'address.lng' => ['prohibited'],
            'address.google_maps_url' => ['prohibited'],
            'address.google_place_id' => ['prohibited'],
            'address.waze_url' => ['prohibited'],
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
            'message' => __('Thank you. Your speaker submission has been received. We will notify you if it is approved or rejected.'),
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

    #[PathParameter(
        'subjectType',
        'Editable public subject type. Supported public route values are `majlis`, `institusi`, `penceramah`, and `rujukan`.',
        example: 'penceramah',
    )]
    #[PathParameter(
        'subject',
        'Target entity slug or UUID.',
        example: 'ustaz-hasan',
    )]
    #[Endpoint(
        title: 'Get editable contribution context',
        description: 'Returns the current editable state, presentation metadata, and permission flags for an existing subject. '
            .'Call this before submitting an update so you know whether the caller can edit directly, which sparse top-level fields are supported, and whether a pending request already exists. '
            .'Only direct-edit media fields exposed in `direct_edit_media_fields` are uploadable, currently institution `cover`, speaker `avatar`/`cover`, and event `poster`/`gallery`.',
    )]
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
        $initialState = $this->apiInitialState($entity, $context['initial_state']);
        $canDirectEdit = $user->can('update', $entity);

        abort_unless($user->can('view', $entity), 403);

        return response()->json([
            'data' => [
                'entity' => $this->entityData($entity),
                'initial_state' => $initialState,
                'accepts_partial_updates' => $context['contract']['accepts_partial_updates'],
                'fields' => $context['contract']['fields'],
                'conditional_rules' => $context['contract']['conditional_rules'],
                'direct_edit_media_fields' => $canDirectEdit ? $context['contract']['direct_edit_media_fields'] : [],
                'subject_presentation' => $resolveContributionSubjectPresentationAction->handle($entity),
                'can_direct_edit' => $canDirectEdit,
                'latest_pending_request' => ($latestPendingRequest = $resolveLatestPendingContributionRequestAction->handle($user, $entity)) instanceof ContributionRequest
                    ? $this->contributionRequestData($latestPendingRequest, $user)
                    : null,
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    #[PathParameter(
        'subjectType',
        'Editable public subject type. Supported public route values are `majlis`, `institusi`, `penceramah`, and `rujukan`.',
        example: 'majlis',
    )]
    #[PathParameter(
        'subject',
        'Target entity slug or UUID.',
        example: 'kuliah-maghrib-masjid-jamek',
    )]
    #[BodyParameter(
        'proposer_note',
        'Optional note from the proposer explaining why the requested changes are needed.',
        required: false,
        type: 'string',
        infer: false,
        example: 'Please update the speaker list and correct the title spelling.',
    )]
    #[Endpoint(
        title: 'Submit a contribution update',
        description: 'Submits a sparse top-level payload for an existing event, institution, speaker, or reference. '
            .'Fetch `GET /forms/contributions/{subjectType}/{subject}/suggest` first to discover the editable field contract and current values. '
            .'Only files named in `direct_edit_media_fields` may be uploaded, and only when the current user can edit the subject directly. '
            .'If the caller can update the subject directly, the changes are applied immediately and the response `mode` is `direct_edit`; otherwise a contribution review request is created and the response `mode` is `review`.',
    )]
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
        ContributionEntityMutationService $contributionEntityMutationService,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $this->ensureDirectoryFeedbackAllowed($user);

        $context = $resolveContributionUpdateContextAction->handle($subjectType, $subject);
        $entity = $context['entity'];
        $publicInitialState = $this->apiInitialState($entity, $context['initial_state']);
        $comparableOriginalData = $entity instanceof Event
            ? $context['initial_state']
            : $publicInitialState;
        $canDirectEdit = $user->can('update', $entity);
        $directEditMediaFields = $canDirectEdit
            ? array_values(array_filter(
                $context['contract']['direct_edit_media_fields'] ?? [],
                static fn (string $field): bool => $field !== '',
            ))
            : [];

        abort_unless($user->can('view', $entity), 403);

        $uploadedFiles = array_keys($request->allFiles());

        if ($uploadedFiles !== []) {
            $unsupportedFileFields = array_values(array_diff($uploadedFiles, $directEditMediaFields));

            if ($unsupportedFileFields !== []) {
                throw ValidationException::withMessages([
                    'files' => [__('This update flow only supports the direct-edit media fields exposed by the contract for this subject.')],
                ]);
            }

            validator($request->all(), $this->directEditMediaValidationRules($directEditMediaFields))->validate();
        }

        $payload = collect($request->all())
            ->only(array_merge(array_keys($publicInitialState), ['proposer_note']))
            ->all();

        $validatedPayload = validator(
            $payload,
            array_merge(
                $contributionEntityMutationService->updateRulesFor($entity),
                ['proposer_note' => ['sometimes', 'nullable', 'string', 'max:2000']],
            ),
        )->validate();

        $submissionState = $resolveContributionSubmissionStateAction->handle($validatedPayload);
        $normalizedState = $entity instanceof Event && $submissionState['state'] !== []
            ? EventContributionUpdateStateMapper::toPersistenceState(array_replace_recursive($publicInitialState, $submissionState['state']))
            : $submissionState['state'];
        $changes = $resolveContributionChangedPayloadAction->handle($normalizedState, $comparableOriginalData);
        $hasDirectEditMediaChange = collect($directEditMediaFields)
            ->contains(fn (string $field): bool => $request->hasFile($field));

        if ($changes === [] && ! $hasDirectEditMediaChange) {
            throw ValidationException::withMessages([
                'data' => __('Make at least one change before continuing.'),
            ]);
        }

        if ($canDirectEdit) {
            if ($changes !== []) {
                $applyDirectContributionUpdateAction->handle($entity, $changes);
            }

            if ($hasDirectEditMediaChange) {
                $this->syncDirectEditMediaChanges($entity, $request, $frontendMediaSyncService, $directEditMediaFields);
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

    #[Endpoint(
        title: 'Approve a contribution request',
        description: 'Approves a reviewable contribution request and applies the proposed changes.',
    )]
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

    #[Endpoint(
        title: 'Reject a contribution request',
        description: 'Rejects a reviewable contribution request with a reason code and optional reviewer note.',
    )]
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

    #[Endpoint(
        title: 'Cancel a contribution request',
        description: 'Cancels the authenticated user\'s own pending contribution request.',
    )]
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
     * @param  array<string, mixed>  $initialState
     * @return array<string, mixed>
     */
    private function apiInitialState(Event|Institution|Reference|Speaker $entity, array $initialState): array
    {
        if ($entity instanceof Event) {
            $helperState = EventContributionUpdateStateMapper::toHelperState($initialState);

            unset(
                $helperState['starts_at'],
                $helperState['ends_at'],
                $helperState['timing_mode'],
                $helperState['prayer_reference'],
                $helperState['prayer_offset'],
                $helperState['prayer_display_text'],
                $helperState['organizer_id'],
                $helperState['institution_id'],
                $helperState['venue_id'],
            );

            return $helperState;
        }

        if (! $entity instanceof Speaker) {
            return $initialState;
        }

        $speakerAddress = is_array($initialState['address'] ?? null)
            ? $initialState['address']
            : [];

        $initialState['address'] = [
            'state_id' => $speakerAddress['state_id'] ?? null,
            'district_id' => $speakerAddress['district_id'] ?? null,
            'subdistrict_id' => $speakerAddress['subdistrict_id'] ?? null,
        ];

        return $initialState;
    }

    /**
     * @param  list<string>  $directEditMediaFields
     * @return array<string, mixed>
     */
    private function directEditMediaValidationRules(array $directEditMediaFields): array
    {
        $rules = [];

        if (in_array('avatar', $directEditMediaFields, true)) {
            $rules['avatar'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'];
        }

        if (in_array('cover', $directEditMediaFields, true)) {
            $rules['cover'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'];
        }

        if (in_array('poster', $directEditMediaFields, true)) {
            $rules['poster'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'];
        }

        if (in_array('gallery', $directEditMediaFields, true)) {
            $rules['gallery'] = ['nullable', 'array'];
            $rules['gallery.*'] = ['image', 'mimes:jpg,jpeg,png,webp'];
        }

        return $rules;
    }

    /**
     * @param  list<string>  $directEditMediaFields
     */
    private function syncDirectEditMediaChanges(
        Event|Institution|Reference|Speaker $entity,
        Request $request,
        FrontendMediaSyncService $frontendMediaSyncService,
        array $directEditMediaFields,
    ): void {
        foreach ($directEditMediaFields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            if ($field === 'gallery') {
                $frontendMediaSyncService->syncMultiple(
                    $entity,
                    is_array($request->file('gallery')) ? $request->file('gallery') : null,
                    'gallery',
                    replace: true,
                );

                continue;
            }

            $frontendMediaSyncService->syncSingle($entity, $request->file($field), $field);
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
