<?php

namespace App\Livewire\Pages\Home;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia')]
class KimiHome extends Component
{
    public array $stats = [];

    public $featuredEvents;

    public $upcomingEvents;

    public array $categories = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadFeaturedEvents();
        $this->loadUpcomingEvents();
        $this->loadCategories();
    }

    private function loadStats(): void
    {
        $this->stats = Cache::remember('kimi_home_stats', 300, function () {
            return [
                'events' => Event::count(),
                'institutions' => Institution::count(),
                'speakers' => Speaker::count(),
                'this_week' => Event::whereBetween('starts_at', [now(), now()->addWeek()])->count(),
            ];
        });
    }

    private function loadFeaturedEvents(): void
    {
        $this->featuredEvents = Cache::remember('kimi_featured_events', 300, function () {
            return Event::with(['institution', 'speakers', 'media'])
                ->where('starts_at', '>=', now())
                ->where('is_active', true)
                ->whereIn('status', ['approved', 'pending'])
                ->orderBy('starts_at')
                ->limit(6)
                ->get();
        });
    }

    private function loadUpcomingEvents(): void
    {
        $this->upcomingEvents = Cache::remember('kimi_upcoming_events', 300, function () {
            return Event::with(['institution', 'speakers'])
                ->where('starts_at', '>=', now())
                ->where('is_active', true)
                ->whereIn('status', ['approved', 'pending'])
                ->orderBy('starts_at')
                ->limit(4)
                ->get();
        });
    }

    private function loadCategories(): void
    {
        $this->categories = [
            ['name' => 'Tazkirah', 'icon' => 'book-open', 'color' => 'emerald', 'search' => 'Tazkirah'],
            ['name' => 'Tafsir', 'icon' => 'document-text', 'color' => 'blue', 'search' => 'Tafsir'],
            ['name' => 'Fiqh', 'icon' => 'scale', 'color' => 'amber', 'search' => 'Fiqh'],
            ['name' => 'Aqidah', 'icon' => 'star', 'color' => 'violet', 'search' => 'Aqidah'],
            ['name' => 'Sirah', 'icon' => 'users', 'color' => 'rose', 'search' => 'Sirah'],
            ['name' => 'Hadith', 'icon' => 'chat-bubble-left-right', 'color' => 'teal', 'search' => 'Hadith'],
        ];
    }

    public function render()
    {
        return view('livewire.pages.home.kimi-home');
    }
}
