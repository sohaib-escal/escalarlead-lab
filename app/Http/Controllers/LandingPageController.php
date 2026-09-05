<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\LandingPageType;
use App\Services\CreativeFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function __construct(private readonly CreativeFilters $filters) {}

    public function index(): Response
    {
        $pages = LandingPage::with(['type:id,name', 'product:id,name,code,color'])
            ->withCount('creatives')
            ->orderBy('name')
            ->get()
            ->map(fn ($page) => [
                'id' => $page->id,
                'name' => $page->name,
                'url' => $page->url,
                'type' => $page->type?->name,
                'landing_page_type_id' => $page->landing_page_type_id,
                'product' => $page->product?->name,
                'product_id' => $page->product_id,
                'version' => $page->version,
                'notes' => $page->notes,
                'is_active' => $page->is_active,
                'creatives_count' => $page->creatives_count,
            ]);

        return Inertia::render('LandingPages/Index', [
            'landingPages' => $pages,
            'types' => LandingPageType::where('is_active', true)->orderBy('position')->get(['id', 'name']),
            'options' => $this->filters->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LandingPage::create($this->validated($request));

        return back()->with('success', 'Landing page ajoutée.');
    }

    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $landingPage->update($this->validated($request));

        return back()->with('success', 'Landing page mise à jour.');
    }

    public function destroy(LandingPage $landingPage): RedirectResponse
    {
        if ($landingPage->creatives()->exists()) {
            $landingPage->update(['is_active' => false]);

            return back()->with('success', 'Landing page désactivée (elle est utilisée par des créas).');
        }

        $landingPage->delete();

        return back()->with('success', 'Landing page supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'url' => ['required', 'url', 'max:500'],
            'landing_page_type_id' => ['nullable', 'exists:landing_page_types,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'version' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
