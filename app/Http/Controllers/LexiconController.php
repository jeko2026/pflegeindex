<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LexiconController extends Controller
{
    public function index(): View
    {
        $terms = collect(config('lexicon.terms', []))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE);
        $groups = $terms->groupBy('letter', preserveKeys: true);

        return view('lexicon.index', compact('terms', 'groups'));
    }

    public function show(string $slug): View
    {
        $terms = config('lexicon.terms', []);
        abort_unless(isset($terms[$slug]), 404);

        $term = $terms[$slug];
        $details = config('lexicon_details', []);
        $termDetails = $details['terms'][$slug] ?? [];
        $legalNote = $details['legal_profiles'][$termDetails['legal_profile'] ?? 'orientation'] ?? null;
        $example = $termDetails['example'] ?? null;
        $relatedTerms = collect($term['related'] ?? [])
            ->filter(fn (string $relatedSlug): bool => isset($terms[$relatedSlug]))
            ->map(fn (string $relatedSlug): array => [
                'slug' => $relatedSlug,
                ...$terms[$relatedSlug],
            ])
            ->values();

        return view('lexicon.show', compact('slug', 'term', 'legalNote', 'example', 'relatedTerms'));
    }
}
