<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\ApproveContributionRequestAction;
use App\Actions\Contributions\CancelContributionRequestAction;
use App\Actions\Contributions\RejectContributionRequestAction;
use App\Actions\Contributions\ResolvePendingContributionApprovalsAction;
use App\Actions\Contributions\ResolveReviewableContributionRequestAction;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\ContributionRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Contributions')]
class Index extends Component
{
    use InteractsWithToasts;

    /** @var array<string, string> */
    public array $reviewNotes = [];

    /** @var array<string, string> */
    public array $rejectionReasons = [];

    public function mount(): void
    {
        abort_unless(auth()->user() instanceof User, 403);
    }

    /**
     * @return Collection<int, ContributionRequest>
     */
    #[Computed]
    public function myRequests(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->contributionRequests()
            ->with(['entity', 'reviewer'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ContributionRequest>
     */
    #[Computed]
    public function pendingApprovals(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return app(ResolvePendingContributionApprovalsAction::class)->handle($user);
    }

    public function approve(
        string $requestId,
        ApproveContributionRequestAction $approveContributionRequestAction,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $request = $resolveReviewableContributionRequestAction->handle($user, $requestId);

        $approveContributionRequestAction->handle($request, $user, $this->reviewNotes[$requestId] ?? null);
        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function reject(
        string $requestId,
        RejectContributionRequestAction $rejectContributionRequestAction,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $request = $resolveReviewableContributionRequestAction->handle($user, $requestId);

        $rejectContributionRequestAction->handle(
            $request,
            $user,
            $this->rejectionReasons[$requestId] ?: 'rejected_by_reviewer',
            $this->reviewNotes[$requestId] ?? null,
        );

        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function cancel(string $requestId, CancelContributionRequestAction $cancelContributionRequestAction): void
    {
        /** @var User $user */
        $user = auth()->user();
        $request = $user->contributionRequests()->findOrFail($requestId);

        $cancelContributionRequestAction->handle($request, $user);
    }

    public function render(): View
    {
        return view('livewire.pages.contributions.index');
    }
}
