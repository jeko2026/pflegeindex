@extends('layouts.app')

@php
    $currentPage = $facilities->currentPage();
    $pageTitle = $currentPage > 1
        ? "Pflegeeinrichtungen in {$city->name} – Seite {$currentPage} – PflegeIndex"
        : "Pflegeeinrichtungen in {$city->name} – PflegeIndex";
    $pageDescription = $currentPage > 1
        ? "Seite {$currentPage} mit weiteren Pflegeeinrichtungen in {$city->name}."
        : "{$facilityCount} Pflegeeinrichtungen in {$city->name}: Anschriften, Einrichtungsarten und verfügbare Kontaktdaten.";
    $canonicalUrl = $currentPage > 1
        ? route('cities.show', [$city, 'page' => $currentPage])
        : route('cities.show', $city);
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)
@section('bodyClass', 'city-seo-page')

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    @php
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
            'about' => [
                '@type' => 'Place',
                'name' => $city->name,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $city->name,
                    'addressRegion' => $city->state,
                    'addressCountry' => 'DE',
                ],
            ],
        ];
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
                    'name' => $city->name,
                    'item' => $canonicalUrl,
                ],
            ],
        ];
    @endphp
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
                    @if($city->geoMunicipality?->district)
                        <li><span aria-hidden="true">›</span><a href="{{ route('districts.show', $city->geoMunicipality->district->slug) }}">{{ $city->geoMunicipality->district->display_name }}</a></li>
                    @endif
                    <li aria-current="page"><span aria-hidden="true">›</span><span>{{ $city->name }}</span></li>
                </ol>
            </nav>
            <h1>Pflegeeinrichtungen in {{ $city->name }}</h1>
            <p class="page-hero__lead">Pflegeeinrichtungen in {{ $city->name }} mit Anschrift, Einrichtungsart und verfügbaren Kontaktdaten.</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="region-summary">
                <div><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong><span>Einrichtungen</span></div>
                <div><strong>{{ $typeCount }}</strong><span>Einrichtungsarten</span></div>
            </div>
            <section aria-labelledby="city-facilities-title">
                @if($qualityStats)
                    <section class="city-quality-summary" aria-labelledby="city-quality-title" data-city-quality-score="{{ $qualityStats['average_score'] }}">
                        <div class="city-quality-summary__heading">
                            <div>
                                <p class="eyebrow">Datenqualität</p>
                                <h2 id="city-quality-title">Datenqualität in {{ $city->name }}</h2>
                            </div>
                            <strong>{{ $qualityStats['quality_label'] }}</strong>
                        </div>
                        <p>Durchschnittliche Datenqualität: <b>{{ number_format($qualityStats['average_score'], 0, ',', '.') }} %</b></p>
                        <p>Geprüfte Einrichtungen: <b>{{ $qualityStats['verified_count'] }} von {{ $qualityStats['total_facilities'] }}</b></p>
                        <p>{{ number_format($qualityStats['verified_percentage'], 0, ',', '.') }} % der Einrichtungen wurden geprüft</p>
                        <p>Die Angaben beziehen sich auf Vollständigkeit und dokumentierte Prüfung der Einrichtungsdaten, nicht auf die Pflegequalität.</p>
                        <div class="city-quality-summary__bar" role="progressbar" aria-label="Durchschnittliche Datenqualität" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $qualityStats['progress_percentage'] }}"><span class="city-quality-summary__bar-fill city-quality-summary__bar-fill--{{ $qualityStats['quality_color'] }}" style="width:{{ $qualityStats['progress_percentage'] }}%"></span></div>
                    </section>
                @endif
                <div class="results-heading" style="margin-top:42px">
                    <h2 id="city-facilities-title">Alle Pflegeeinrichtungen in {{ $city->name }}</h2>
                    <p>{{ $facilities->count() }} auf dieser Seite · alphabetisch sortiert</p>
                </div>
                <div class="notice">Basisdaten: Landesamt für Soziales und Versorgung Brandenburg, Stand 31.12.2025.</div>
                <div class="results-list" style="margin-top:16px">
                    @foreach($facilities as $facility)
                        @include('facilities._card', ['facility' => $facility])
                    @endforeach
                </div>
                <x-pagination :paginator="$facilities" />
            </section>
        </div>
    </section>

    @if($nearbyCities->isNotEmpty())
        <section class="section section--white" aria-labelledby="nearby-cities-title">
            <div class="container">
                <div class="section-heading section-heading--split">
                    <div>
                        <p class="eyebrow">Region</p>
                        <h2 id="nearby-cities-title">Städte in der Nähe</h2>
                    </div>
                    @if($city->geoMunicipality?->district)
                        <a href="{{ route('districts.show', $city->geoMunicipality->district->slug) }}">Alle Orte im {{ $city->geoMunicipality->district->display_name }}</a>
                    @endif
                </div>
                <div class="city-grid">
                    @foreach($nearbyCities as $nearbyCity)
                        <a class="city-card" href="{{ route('cities.show', $nearbyCity) }}">
                            <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                            <span><strong>{{ $nearbyCity->name }}</strong><small>{{ $nearbyCity->facilities_count }} {{ $nearbyCity->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small></span>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
