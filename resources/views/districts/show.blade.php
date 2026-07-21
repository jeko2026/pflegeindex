@extends('layouts.app')

@php
    $isCounty = $district->type === 'landkreis';
    $districtName = $district->display_name;
    $areaLabel = $isCounty ? "Landkreis {$districtName}" : $districtName;
    $heading = $isCounty ? "Pflegeheime im Landkreis {$districtName}" : "Pflegeheime in {$districtName}";
    $currentPage = $facilities->currentPage();
    $pageTitle = $currentPage > 1
        ? "{$heading} – Seite {$currentPage} | PflegeIndex"
        : "{$heading} | PflegeIndex";
    $cityLabel = $linkedCityCount === 1 ? 'einem Ort' : "{$linkedCityCount} Orten";
    $pageDescription = $currentPage > 1
        ? ($isCounty
            ? "Seite {$currentPage} mit weiteren Pflegeeinrichtungen im Landkreis {$districtName}."
            : "Seite {$currentPage} mit weiteren Pflegeeinrichtungen in {$districtName}.")
        : ($isCounty
            ? "{$facilityCount} Pflegeeinrichtungen in {$cityLabel} im Landkreis {$districtName}. Angebote vergleichen und passende Einrichtungen finden."
            : "{$facilityCount} Pflegeeinrichtungen in {$districtName}. Angebote vergleichen und passende Einrichtungen finden.");
    $canonicalUrl = $currentPage > 1
        ? route('districts.show', [$district->slug, 'page' => $currentPage])
        : route('districts.show', $district->slug);
    $itemList = $facilities->map(fn ($facility, $index) => [
        '@type' => 'ListItem',
        'position' => ($facilities->firstItem() ?? 1) + $index,
        'name' => $facility->name,
        'url' => $facility->url,
    ])->values()->all();
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $heading,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'PflegeIndex',
            'url' => route('home'),
        ],
        'about' => [
            '@type' => 'AdministrativeArea',
            'name' => $areaLabel,
            'identifier' => $district->ags,
        ],
    ];

    if ($itemList !== []) {
        $schema['mainEntity'] = [
            '@type' => 'ItemList',
            'numberOfItems' => count($itemList),
            'itemListElement' => $itemList,
        ];
    }

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Startseite',
                'item' => route('home'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Brandenburg',
                'item' => route('region.show'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $areaLabel,
                'item' => $canonicalUrl,
            ],
        ],
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)
@section('bodyClass', 'district-seo-page')

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="page-hero">
        <div class="container">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumbs" style="margin:0">
                    <li><a href="{{ route('home') }}">Startseite</a></li>
                    <li><span aria-hidden="true">›</span><a href="{{ route('region.show') }}">Brandenburg</a></li>
                    <li aria-current="page"><span aria-hidden="true">›</span><span>{{ $areaLabel }}</span></li>
                </ol>
            </nav>
            <h1>{{ $heading }}</h1>
            <p class="page-hero__lead">
                @if($isCounty)
                    Übersicht der über PflegeIndex verfügbaren Pflegeeinrichtungen im Landkreis {{ $districtName }}.
                @else
                    Übersicht der über PflegeIndex verfügbaren Pflegeeinrichtungen in {{ $districtName }}.
                @endif
            </p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="region-summary">
                <div><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong><span>{{ $facilityCount === 1 ? 'Einrichtung' : 'Einrichtungen' }}</span></div>
                <div><strong>{{ $linkedCityCount }}</strong><span>{{ $linkedCityCount === 1 ? 'Ort' : 'Orte' }}</span></div>
            </div>

            @if($cities->isNotEmpty())
                <section aria-labelledby="district-cities-title">
                    <div class="section-heading section-heading--split" style="margin-top:42px">
                        <div><p class="eyebrow">Ortsverzeichnis</p><h2 id="district-cities-title">Pflegeheime nach Stadt</h2></div>
                    </div>
                    <div class="city-grid">
                        @foreach($cities as $city)
                            <a class="city-card" href="{{ route('cities.show', $city) }}">
                                <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                                <span><strong>{{ $city->name }}</strong><small>{{ $city->facilities_count }} {{ $city->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small></span>
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="district-facilities-title">
                <div class="results-heading" style="margin-top:72px">
                    <h2 id="district-facilities-title">{{ $heading }}</h2>
                    @if($facilities->isNotEmpty())
                        <p>{{ $facilities->count() }} auf dieser Seite · nach Ort und Name sortiert</p>
                    @endif
                </div>
                @if($facilities->isEmpty())
                    <div class="notice" style="margin-top:16px">Für {{ $areaLabel }} sind derzeit keine bestätigten Pflegeeinrichtungen verfügbar.</div>
                @else
                    <div class="results-list" style="margin-top:16px">
                        @foreach($facilities as $facility)
                            @include('facilities._card', ['facility' => $facility])
                        @endforeach
                    </div>
                    <x-pagination :paginator="$facilities" />
                @endif
            </section>
        </div>
    </section>
@endsection
