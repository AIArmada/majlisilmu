<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * Store a newly created report.
     * Per documentation B5b - POST /reports
     */
    public function store(Request $request): JsonResponse
    {
        $reporterFingerprint = $this->resolveReporterFingerprint($request);

        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['event', 'institution', 'speaker', 'reference', 'donation_channel'])],
            'entity_id' => ['required', 'uuid'],
            'category' => [
                'required',
                Rule::in([
                    'wrong_info',
                    'cancelled_not_updated',
                    'fake_speaker',
                    'fake_institution',
                    'fake_reference',
                    'inappropriate_content',
                    'donation_scam',
                    'other',
                ]),
            ],
            'description' => ['required_if:category,other', 'nullable', 'string', 'max:2000'],
        ]);

        // Verify entity exists
        $entityClass = $this->getEntityClass($validated['entity_type']);
        $entity = $entityClass::query()->find($validated['entity_id']);

        if (! $entity) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The specified entity does not exist.',
                ],
            ], 404);
        }

        try {
            $report = $this->reportService->submit(
                $entity,
                $validated['entity_type'],
                $request->user(),
                $reporterFingerprint,
                $validated['category'],
                $validated['description'] ?? null,
            );
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'duplicate_report') {
                throw $exception;
            }

            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'You have already reported this entity within the last 24 hours.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'id' => $report->id,
                'message' => 'Report submitted successfully. Our team will review it.',
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Get the model class for an entity type.
     */
    private function getEntityClass(string $entityType): string
    {
        return match ($entityType) {
            'event' => Event::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'reference' => Reference::class,
            'donation_channel' => DonationChannel::class,
            default => throw new InvalidArgumentException("Unsupported entity type [{$entityType}]"),
        };
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
