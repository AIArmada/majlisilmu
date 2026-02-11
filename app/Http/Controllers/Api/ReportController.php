<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderationService
    ) {}

    /**
     * Store a newly created report.
     * Per documentation B5b - POST /reports
     */
    public function store(Request $request): JsonResponse
    {
        $reporterFingerprint = $this->resolveReporterFingerprint($request);

        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['event', 'institution', 'speaker', 'donation_channel'])],
            'entity_id' => ['required', 'uuid'],
            'category' => [
                'required',
                Rule::in([
                    'wrong_info',
                    'cancelled_not_updated',
                    'fake_speaker',
                    'inappropriate_content',
                    'donation_scam',
                    'other',
                ]),
            ],
            'description' => ['required_if:category,other', 'nullable', 'string', 'max:2000'],
        ]);

        // Verify entity exists
        $entityClass = $this->getEntityClass($validated['entity_type']);
        if (! $entityClass::where('id', $validated['entity_id'])->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The specified entity does not exist.',
                ],
            ], 404);
        }

        // Check for duplicate reports within 24 hours (per B9e)
        $existingReport = Report::query()
            ->where('entity_type', $validated['entity_type'])
            ->where('entity_id', $validated['entity_id'])
            ->where('reporter_fingerprint', $reporterFingerprint)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($existingReport) {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'You have already reported this entity within the last 24 hours.',
                ],
            ], 409);
        }

        $report = Report::create([
            'entity_type' => $validated['entity_type'],
            'entity_id' => $validated['entity_id'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'reporter_id' => $request->user()?->id,
            'reporter_fingerprint' => $reporterFingerprint,
            'status' => 'open',
        ]);

        // Handle high-risk reports per B6b
        if (in_array($validated['category'], ['donation_scam', 'fake_speaker'])) {
            $this->handleHighRiskReport($report);
        }

        // Check for escalation threshold (2 reports in 24 hours)
        $this->checkEscalationThreshold($validated['entity_type'], $validated['entity_id']);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'message' => 'Report submitted successfully. Our team will review it.',
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Handle high-risk report categories.
     */
    private function handleHighRiskReport(Report $report): void
    {
        if ($report->entity_type !== 'event') {
            return;
        }

        $event = Event::query()->find($report->entity_id);

        if (! $event) {
            return;
        }

        $this->queueEventForModeration($event, 'High-risk report: '.$report->category);
    }

    /**
     * Check if escalation threshold is met (2 unique reports in 24 hours).
     */
    private function checkEscalationThreshold(string $entityType, string $entityId): void
    {
        $recentReportCount = Report::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('created_at', '>=', now()->subDay())
            ->whereNotNull('reporter_fingerprint')
            ->distinct()
            ->count('reporter_fingerprint');

        if ($recentReportCount < 2 || $entityType !== 'event') {
            return;
        }

        $event = Event::query()->find($entityId);

        if (! $event) {
            return;
        }

        $this->queueEventForModeration($event, 'Escalated due to multiple reports within 24 hours.');
    }

    /**
     * Get the model class for an entity type.
     */
    private function getEntityClass(string $entityType): string
    {
        return match ($entityType) {
            'event' => \App\Models\Event::class,
            'institution' => \App\Models\Institution::class,
            'speaker' => \App\Models\Speaker::class,
            'donation_channel' => \App\Models\DonationChannel::class,
        };
    }

    private function queueEventForModeration(Event $event, string $note): void
    {
        if ((string) $event->status !== 'approved') {
            return;
        }

        $this->moderationService->remoderate($event, null, $note);
    }

    private function resolveReporterFingerprint(Request $request): string
    {
        $userId = $request->user()?->id;

        if (is_string($userId) && $userId !== '') {
            return 'user:'.$userId;
        }

        $ipAddress = (string) ($request->ip() ?? 'unknown-ip');
        $userAgent = trim((string) ($request->userAgent() ?? 'unknown-agent'));

        return 'guest:'.hash('sha256', "{$ipAddress}|{$userAgent}");
    }
}
