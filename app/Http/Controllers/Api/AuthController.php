<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthenticateApiUserAction;
use App\Actions\Auth\RegisterApiUserAction;
use App\Actions\Auth\RevokeCurrentApiTokenAction;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

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

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker($this->passwordBroker())->sendResetLink([
            'email' => (string) $validated['email'],
        ]);

        return response()->json([
            'message' => 'If we found an account with that email address, we have emailed a password reset link.',
        ]);
    }

    public function resetPassword(Request $request, ResetUserPassword $resetUserPassword): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker($this->passwordBroker())->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (mixed $user, string $password) use ($resetUserPassword): void {
                abort_unless($user instanceof User, 500);

                $resetUserPassword->reset($user, [
                    'password' => $password,
                    'password_confirmation' => $password,
                ]);

                $user->forceFill([
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! is_string($user->email) || trim($user->email) === '') {
            return response()->json([
                'message' => 'An email address is required to send a verification email.',
            ], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is already verified.',
            ], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully.',
        ]);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function passwordBroker(): string
    {
        return (string) config('fortify.passwords', 'users');
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
