<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $cities = City::query()
            ->select(['id', 'name', 'slug', 'updated_at'])
            ->where('state_slug', 'brandenburg')
            ->whereHas('facilities')
            ->with(['facilities' => function ($query): void {
                $query->select(['id', 'city_id', 'slug', 'updated_at'])->orderBy('slug');
            }])
            ->orderBy('slug')
            ->get();

        $lastModified = Facility::query()->max('updated_at');
        $lexiconTerms = collect(config('lexicon.terms', []))
            ->map(fn (array $term, string $slug): array => [
                'loc' => route('lexicon.show', $slug),
                'lastmod' => $term['checked_at'],
            ])
            ->values();
        $staticPages = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('directory.index'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('region.show'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('lexicon.index'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => route('pages.about'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        return response()
            ->view('seo.sitemap', compact('cities', 'staticPages', 'lastModified', 'lexiconTerms'), 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function robots(): Response
    {
        $contents = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($contents, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
