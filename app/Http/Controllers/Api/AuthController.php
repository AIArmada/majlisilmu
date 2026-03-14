<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthenticateApiUserAction;
use App\Actions\Auth\RegisterApiUserAction;
use App\Actions\Auth\RevokeCurrentApiTokenAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request, RegisterApiUserAction $registerApiUserAction): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $registerApiUserAction->handle($request->all(), (string) $validated['device_name'], $request);

        return response()->json([
            'message' => 'API account registered successfully.',
            'data' => [
                'access_token' => $result['access_token'],
                'token_type' => 'Bearer',
                'user' => $this->userData($result['user']),
            ],
        ], 201);
    }

    public function login(Request $request, AuthenticateApiUserAction $authenticateApiUserAction): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $authenticateApiUserAction->handle(
            (string) $validated['login'],
            (string) $validated['password'],
            (string) $validated['device_name'],
            $request,
        );

        return response()->json([
            'message' => 'API login successful.',
            'data' => [
                'access_token' => $result['access_token'],
                'token_type' => 'Bearer',
                'user' => $this->userData($result['user']),
            ],
        ]);
    }

    public function logout(Request $request, RevokeCurrentApiTokenAction $revokeCurrentApiTokenAction): JsonResponse
    {
        $revokeCurrentApiTokenAction->handle($this->currentUser($request));

        return response()->json([
            'message' => 'API token revoked successfully.',
        ]);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'timezone' => $user->timezone,
            'email_verified_at' => $user->email_verified_at instanceof DateTimeInterface
                ? $user->email_verified_at->format(DateTimeInterface::ATOM)
                : null,
            'phone_verified_at' => $user->phone_verified_at instanceof DateTimeInterface
                ? $user->phone_verified_at->format(DateTimeInterface::ATOM)
                : null,
        ];
    }
}
