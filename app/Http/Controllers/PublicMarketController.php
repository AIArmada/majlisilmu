<?php

namespace App\Http\Controllers;

use App\Support\Location\PublicMarketPreference;
use App\Support\Location\PublicMarketRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicMarketController extends Controller
{
    public function __invoke(
        Request $request,
        string $market,
        PublicMarketRegistry $registry,
        PublicMarketPreference $preference,
    ): RedirectResponse {
        if (! $registry->has($market)) {
            abort(404);
        }

        if (! $registry->isEnabled($market)) {
            return redirect()->back();
        }

        $preference->set($market, $request);

        return redirect()->back();
    }
}
