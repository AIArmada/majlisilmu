<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventsController extends Controller
{
    public function index(): View
    {
        return view('pages.events.index');
    }

    public function show(Event $event): View
    {
        if ($event->status !== 'approved' || $event->visibility !== 'public' || $event->published_at === null) {
            abort(404);
        }

        return view('pages.events.show', [
            'event' => $event,
        ]);
    }
}
