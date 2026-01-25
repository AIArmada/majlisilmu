<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Store a newly created report.
     * Per documentation B5b - POST /reports
     */
    public function store(Request $request): JsonResponse
    {
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
            ->where(function ($query) use ($request) {
                if ($request->user()) {
                    $query->where('reporter_id', $request->user()->id);
                } else {
                    $query->whereNull('reporter_id');
                }
            })
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
        if ($report->entity_type === 'event') {
            DB::table('events')
                ->where('id', $report->entity_id)
                ->update(['status' => 'pending']);
        }

        // TODO: Notify moderators and super admin
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
            ->distinct('reporter_id')
            ->count();

        if ($recentReportCount >= 2 && $entityType === 'event') {
            DB::table('events')
                ->where('id', $entityId)
                ->update(['status' => 'pending']);
        }
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
}
