<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Contracts\View\View;

class InstitutionsController extends Controller
{
    public function index(): View
    {
        return view('pages.institutions.index');
    }

    public function show(Institution $institution): View
    {
        return view('pages.institutions.show', [
            'institution' => $institution,
        ]);
    }
}
