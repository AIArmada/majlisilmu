<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\Signals\ProductSignalsService;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RegisterApiUserAction
{
    use AsAction;

    public function __construct(
        private CreateNewUser $createNewUser,
        private ProductSignalsService $productSignalsService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{user: User, access_token: string}
     */
    public function handle(array $input, string $deviceName, Request $request): array
    {
        /** @var User $user */
        $user = $this->createNewUser->create($input);

        $accessToken = $user->createToken($deviceName)->plainTextToken;

        $this->productSignalsService->recordLogin($user, $request, 'api_token', true);

        return [
            'user' => $user,
            'access_token' => $accessToken,
        ];
    }
}
