<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Speaker;
use Illuminate\Contracts\View\View;

class SpeakersController extends Controller
{
    public function index(): View
    {
        return view('pages.speakers.index');
    }

    public function show(Speaker $speaker): View
    {
        return view('pages.speakers.show', [
            'speaker' => $speaker,
        ]);
    }
}
