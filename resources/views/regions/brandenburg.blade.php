@extends('layouts.app')

@php
    $currentPage = $facilities->currentPage();
    $pageTitle = $currentPage > 1
        ? "Pflegeeinrichtungen in Brandenburg – Seite {$currentPage} – PflegeIndex"
        : 'Pflegeeinrichtungen in Brandenburg – PflegeIndex';
    $pageDescription = $currentPage > 1
        ? "Seite {$currentPage} mit weiteren Pflegeeinrichtungen in Brandenburg."
        : "{$facilityCount} Pflegeeinrichtungen in {$cities->count()} Orten Brandenburgs entdecken.";
    $pageUrl = $currentPage > 1
        ? route('region.show', ['page' => $currentPage])
        : route('region.show');
    $ogImageUrl = asset('assets/og-image.png');
    $collectionPageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDescription,
        'url' => $pageUrl,
        'inLanguage' => 'de-DE',
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'PflegeIndex',
            'url' => route('home'),
        ],
        'about' => [
            '@type' => 'AdministrativeArea',
            'name' => 'Brandenburg',
        ],
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $pageUrl)

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <script type="application/ld+json">{!! json_encode($collectionPageSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>Pflegeheime in Brandenburg</h1>
            <p class="page-hero__lead">Alle erfassten Pflegeangebote nach Stadt und Gemeinde – auf Basis des offiziellen Pflegefonds-Verzeichnisses.</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="region-summary">
                <div><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong><span>Einrichtungen</span></div>
                <div><strong>{{ $cities->count() }}</strong><span>Orte</span></div>
                <div><strong>{{ $typeCount }}</strong><span>Einrichtungsarten</span></div>
            </div>
            @if($facilities->onFirstPage())
                @if($districts->isNotEmpty())
                    <section id="brandenburg-districts" aria-labelledby="brandenburg-districts-title">
                        <div class="section-heading section-heading--split" style="margin-top:42px">
                            <div><p class="eyebrow">Regionen</p><h2 id="brandenburg-districts-title">Landkreise und kreisfreie Städte</h2></div>
                        </div>
                        <div class="city-grid">
                            @foreach($districts as $district)
                                <a class="city-card" href="{{ route('districts.show', $district->slug) }}">
                                    <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                                    <span>
                                        <strong>{{ $district->display_name }}</strong>
                                        <small>{{ $district->type === 'landkreis' ? 'Landkreis' : 'Kreisfreie Stadt' }} · {{ $district->linked_cities_count }} {{ $district->linked_cities_count === 1 ? 'Ort' : 'Orte' }} · {{ $district->facilities_count }} {{ $district->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small>
                                    </span>
                                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
                <section id="brandenburg-cities" aria-labelledby="brandenburg-cities-title">
                    <div class="section-heading section-heading--split" style="margin-top:42px"><div><p class="eyebrow">Ortsverzeichnis</p><h2 id="brandenburg-cities-title">Pflegeheime nach Stadt</h2></div><a href="{{ route('directory.index') }}">Alle Einrichtungen durchsuchen</a></div>
                    <div class="city-grid">
                        @foreach($cities as $city)
                            <a class="city-card" href="{{ url('/brandenburg/'.$city->slug.'.html') }}">
                                <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                                <span><strong>{{ $city->name }}</strong><small>{{ $city->facilities_count }} {{ $city->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small></span>
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
            <section id="brandenburg-facilities" aria-labelledby="brandenburg-facilities-title">
                <div class="results-heading" style="margin-top:72px"><h2 id="brandenburg-facilities-title">Alle Pflegeheime in Brandenburg</h2><p>{{ $facilities->count() }} auf dieser Seite · nach Ort und Name sortiert</p></div>
                <div class="results-list" style="margin-top:16px">
                    @foreach($facilities as $facility)
                        @include('facilities._card', ['facility' => $facility])
                    @endforeach
                </div>
                <x-pagination :paginator="$facilities" />
            </section>
        </div>
    </section>
@endsection
