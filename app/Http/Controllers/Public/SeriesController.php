<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Contracts\View\View;

class SeriesController extends Controller
{
    public function show(Series $series): View
    {
        if ($series->visibility !== 'public') {
            abort(404);
        }

        return view('pages.series.show', [
            'series' => $series,
        ]);
    }
}
