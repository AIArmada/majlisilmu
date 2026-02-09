<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserRegistrationController extends Controller
{
    /**
     * List registrations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $registrations = $request->user()
            ->registrations()
            ->with([
                'event' => fn ($query) => $query
                    ->select('id', 'title', 'slug', 'starts_at', 'status', 'visibility', 'institution_id', 'venue_id')
                    ->with([
                        'institution:id,name,slug',
                        'venue:id,name',
                    ]),
            ])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $registrations->items(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                'pagination' => [
                    'page' => $registrations->currentPage(),
                    'per_page' => $registrations->perPage(),
                    'total' => $registrations->total(),
                ],
            ],
        ]);
    }
}
