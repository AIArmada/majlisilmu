<?php

namespace App\Actions\Events;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveAdvancedBuilderMembershipOptionsAction
{
    use AsAction;

    /**
     * @return array{
     *     institution_options: array<string, string>,
     *     speaker_options: array<string, string>
     * }
     */
    public function handle(User $user): array
    {
        return [
            'institution_options' => $user->institutions()
                ->whereIn('status', ['verified', 'pending'])
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('institutions.name', 'institutions.id')
                ->all(),
            'speaker_options' => $user->speakers()
                ->whereIn('status', ['verified', 'pending'])
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('speakers.name', 'speakers.id')
                ->all(),
        ];
    }
}
