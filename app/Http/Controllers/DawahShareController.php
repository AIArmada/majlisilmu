<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DawahShare\DawahShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DawahShareController extends Controller
{
    public function payload(Request $request, DawahShareService $dawahShareService): JsonResponse
    {
        $data = $this->validatedData($request);

        return response()->json($this->buildPayload($request, $dawahShareService, $data));
    }

    public function redirect(string $provider, Request $request, DawahShareService $dawahShareService): RedirectResponse
    {
        $data = $this->validatedData($request);

        try {
            return redirect()->away($dawahShareService->redirectUrl(
                provider: $provider,
                user: $this->authenticatedUser($request),
                url: $data['url'],
                shareText: $data['text'],
                fallbackTitle: $data['title'] ?? null,
                request: $request,
            ));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'url' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{url: string, text: string, title?: string}
     */
    protected function validatedData(Request $request): array
    {
        /** @var array{url: string, text: string, title?: string} $validated */
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'text' => ['required', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        return $validated;
    }

    protected function authenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  array{url: string, text: string, title?: string}  $data
     * @return array{url: string, platform_links: array<string, string>}
     */
    protected function buildPayload(Request $request, DawahShareService $dawahShareService, array $data): array
    {
        try {
            return $dawahShareService->sharePayload(
                $this->authenticatedUser($request),
                $data['url'],
                $data['text'],
                $data['title'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'url' => $exception->getMessage(),
            ]);
        }
    }
}
