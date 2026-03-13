<?php

namespace App\Livewire\Pages\Contributions;

use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\ContributionRequest;
use App\Models\User;
use App\Services\ContributionWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
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

        return ContributionRequest::query()
            ->where('status', ContributionRequestStatus::Pending)
            ->with(['entity', 'proposer'])
            ->latest('created_at')
            ->get()
            ->filter(fn (ContributionRequest $request): bool => $this->canReview($user, $request))
            ->values();
    }

    public function approve(string $requestId, ContributionWorkflowService $workflow): void
    {
        $request = ContributionRequest::query()->with('entity')->findOrFail($requestId);
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->canReview($user, $request), 403);

        $workflow->approve($request, $user, $this->reviewNotes[$requestId] ?? null);
        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function reject(string $requestId, ContributionWorkflowService $workflow): void
    {
        $request = ContributionRequest::query()->with('entity')->findOrFail($requestId);
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->canReview($user, $request), 403);

        $workflow->reject(
            $request,
            $user,
            $this->rejectionReasons[$requestId] ?: 'rejected_by_reviewer',
            $this->reviewNotes[$requestId] ?? null,
        );

        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function cancel(string $requestId, ContributionWorkflowService $workflow): void
    {
        /** @var User $user */
        $user = auth()->user();
        $request = $user->contributionRequests()->findOrFail($requestId);

        $workflow->cancel($request, $user);
    }

    private function canReview(User $user, ContributionRequest $request): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'moderator'])) {
            return true;
        }

        if ($request->type === ContributionRequestType::Create) {
            return false;
        }

        return $request->entity instanceof Model
            && $user->can('update', $request->entity);
    }

    public function render(): View
    {
        return view('livewire.pages.contributions.index');
    }
}
