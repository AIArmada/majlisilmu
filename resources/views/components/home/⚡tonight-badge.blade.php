<?php

use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function tonightCount(): int
    {
        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereBetween('starts_at', [now(), now()->endOfDay()])
            ->count();
    }
};
?>

@placeholder
<div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-4 py-2 text-sm text-emerald-300 mb-8 backdrop-blur-sm">
    <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
    </span>
    <span class="animate-pulse w-16 h-4 bg-emerald-400/30 rounded"></span>
</div>
@endplaceholder

<div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-4 py-2 text-sm text-emerald-300 mb-8 backdrop-blur-sm">
    <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
    </span>
    {{ $this->tonightCount }} {{ __('majlis berlangsung malam ini') }}
</div>