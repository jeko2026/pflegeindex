@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($staticPages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
        @php
            $pageLastModified = $page['lastmod'] ?? $lastModified;
        @endphp
        @if ($pageLastModified)<lastmod>{{ \Illuminate\Support\Carbon::parse($pageLastModified)->toAtomString() }}</lastmod>@endif
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
        @php
            $cityLastModified = collect([$city->updated_at, config('seo_action.city_profiles.'.$city->slug) ? config('seo_action.reviewed_at') : null])->filter()->map(fn ($date) => \Illuminate\Support\Carbon::parse($date))->max();
        @endphp
        @if ($cityLastModified)<lastmod>{{ $cityLastModified->toAtomString() }}</lastmod>@endif
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
@foreach ($sachsenCities as $city)
    <url><loc>{{ route('sachsen.cities.show', $city) }}</loc><lastmod>{{ $city->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>
    @foreach ($city->facilities->groupBy(fn ($facility) => $facility->serviceTypes->pluck('slug')->all()) as $unused)
    @endforeach
    @foreach (['ambulante_pflege', 'pflegeheim', 'tagespflege', 'kurzzeitpflege'] as $service)
        @if ($city->facilities->filter(fn ($facility) => $facility->serviceTypes->contains('slug', $service))->count() >= 3)
        <url><loc>{{ route('sachsen.services.show', [$city, $service]) }}</loc><lastmod>{{ $city->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>
        @endif
    @endforeach
    @foreach ($city->facilities as $facility)
    <url><loc>{{ route('sachsen.facilities.show', [$city, $facility]) }}</loc><lastmod>{{ $facility->updated_at->toAtomString() }}</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
    @endforeach
@endforeach
</urlset>
