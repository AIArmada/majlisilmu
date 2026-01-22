<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationExportController extends Controller
{
    /**
     * Export registrations for an event as CSV.
     * Per documentation B9d: exports require institution owner/admin role and are audit logged.
     */
    public function export(Request $request, Event $event): StreamedResponse|JsonResponse
    {
        // Check authorization via EventPolicy
        if (Gate::denies('exportRegistrations', $event)) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'You are not authorized to export registrations for this event.',
                ],
            ], 403);
        }

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        // Log the export action using Laravel Auditing (per B9d)
        Audit::create([
            'user_type' => get_class($request->user()),
            'user_id' => $request->user()->id,
            'event' => 'export_registrations',
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
            'old_values' => [],
            'new_values' => [
                'count' => $registrations->count(),
                'exported_at' => now()->toIso8601String(),
            ],
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $filename = "registrations-{$event->slug}-".now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($registrations) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Registration ID',
                'Name',
                'Email',
                'Phone',
                'Status',
                'Registered At',
            ]);

            foreach ($registrations as $registration) {
                fputcsv($handle, [
                    $registration->id,
                    $registration->name ?? $registration->user?->name,
                    $registration->email ?? $registration->user?->email,
                    $registration->phone,
                    $registration->status,
                    $registration->created_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
