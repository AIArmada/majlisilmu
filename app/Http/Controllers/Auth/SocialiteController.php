<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Redirect to the OAuth provider.
     */
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the OAuth callback.
     */
    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception) {
            return redirect()->route('login')->with('error', __('Unable to authenticate. Please try again.'));
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account) {
            $account->update([
                'avatar_url' => $socialUser->getAvatar(),
            ]);

            $user = $account->user;
        } else {
            $user = User::query()->where('email', $socialUser->getEmail())->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'email_verified_at' => now(),
                ]);
            }

            SocialAccount::query()->updateOrCreate([
                'user_id' => $user->id,
                'provider' => $provider,
            ], [
                'provider_id' => $socialUser->getId(),
                'avatar_url' => $socialUser->getAvatar(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('home'));
    }
}
