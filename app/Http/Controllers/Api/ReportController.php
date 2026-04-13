<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reports\ResolveReportCategoryOptionsAction;
use App\Actions\Reports\ResolveReportEntityMetadataAction;
use App\Actions\Reports\ResolveReporterFingerprintAction;
use App\Actions\Reports\SubmitReportAction;
use App\Data\Api\Report\ReportSubmissionData;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Store a newly created report.
     * Per documentation B5b - POST /reports
     */
    public function store(
        Request $request,
        ResolveReportCategoryOptionsAction $resolveReportCategoryOptionsAction,
        ResolveReportEntityMetadataAction $resolveReportEntityMetadataAction,
        SubmitReportAction $submitReportAction,
        ResolveReporterFingerprintAction $resolveReporterFingerprintAction,
    ): JsonResponse {
        $this->authorize('create', Report::class);

        $reporterFingerprint = $resolveReporterFingerprintAction->handle($request);

        $validated = $request->validate([
            'entity_type' => ['required', Rule::in($resolveReportEntityMetadataAction->validKeys())],
            'entity_id' => ['required', 'uuid'],
            'category' => [
                'required',
                Rule::in($resolveReportCategoryOptionsAction->validKeys()),
            ],
            'description' => ['required_if:category,other', 'nullable', 'string', 'max:2000'],
        ]);

        // Verify entity exists
        $entityClass = $resolveReportEntityMetadataAction->handle($validated['entity_type'])['model_class'];
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
            /** @var User|null $user */
            $user = $request->user();

            $report = $submitReportAction->handle(
                $entity,
                $validated['entity_type'],
                $user,
                $reporterFingerprint,
                $validated['category'],
                $validated['description'] ?? null,
                $request,
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
            'data' => ReportSubmissionData::fromModel($report)->toArray(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }
}
