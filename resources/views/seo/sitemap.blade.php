@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($staticPages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
        @if ($lastModified)<lastmod>{{ \Illuminate\Support\Carbon::parse($lastModified)->toAtomString() }}</lastmod>@endif
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
@endforeach
@foreach ($lexiconTerms as $term)
    <url>
        <loc>{{ $term['loc'] }}</loc>
        <lastmod>{{ $term['lastmod'] }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
@foreach ($districts as $district)
    <url>
        <loc>{{ route('districts.show', $district->slug) }}</loc>
        @if ($district->updated_at)<lastmod>{{ $district->updated_at->toAtomString() }}</lastmod>@endif
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
@foreach ($cities as $city)
    <url>
        <loc>{{ route('cities.show', $city) }}</loc>
        @if ($city->updated_at)<lastmod>{{ $city->updated_at->toAtomString() }}</lastmod>@endif
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@foreach ($city->facilities as $facility)
    <url>
        <loc>{{ route('facilities.show', [$city, $facility]) }}</loc>
        @if ($facility->updated_at)<lastmod>{{ $facility->updated_at->toAtomString() }}</lastmod>@endif
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
@endforeach
</urlset>
