<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspiration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InspirationController extends Controller
{
    /**
     * List active inspirations with optional category filter.
     */
    public function index(Request $request): JsonResponse
    {
        $inspirations = QueryBuilder::for(Inspiration::query())
            ->allowedFilters([
                AllowedFilter::exact('category'),
                AllowedFilter::exact('locale'),
            ])
            ->allowedSorts(['created_at', 'title'])
            ->defaultSort('-created_at')
            ->where('is_active', true)
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        $inspirations->getCollection()->transform(function (Inspiration $inspiration): Inspiration {
            $inspiration->image_url = $inspiration->getFirstMediaUrl('main', 'thumb') ?: null;
            $inspiration->content_html = $inspiration->renderContentHtml();
            $inspiration->preview_text = $inspiration->contentPreviewText(150);

            return $inspiration;
        });

        return response()->json($inspirations);
    }

    /**
     * Show a single inspiration.
     */
    public function show(string $inspirationId): JsonResponse
    {
        $inspiration = Inspiration::query()
            ->where('id', $inspirationId)
            ->where('is_active', true)
            ->firstOrFail();

        $inspiration->image_url = $inspiration->getFirstMediaUrl('main', 'thumb') ?: null;
        $inspiration->content_html = $inspiration->renderContentHtml();

        return response()->json(['data' => $inspiration]);
    }

    /**
     * Return a random daily inspiration.
     */
    public function daily(Request $request): JsonResponse
    {
        $locale = $request->input('locale', app()->getLocale());

        $inspiration = Inspiration::query()
            ->where('is_active', true)
            ->where('locale', $locale)
            ->inRandomOrder()
            ->first();

        if (! $inspiration instanceof Inspiration) {
            $inspiration = Inspiration::query()
                ->where('is_active', true)
                ->inRandomOrder()
                ->first();
        }

        if (! $inspiration instanceof Inspiration) {
            return response()->json(['data' => null]);
        }

        $inspiration->image_url = $inspiration->getFirstMediaUrl('main', 'thumb') ?: null;
        $inspiration->content_html = $inspiration->renderContentHtml();

        return response()->json(['data' => $inspiration]);
    }
}
