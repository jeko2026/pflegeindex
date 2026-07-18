@extends('layouts.app')

@php
    $pageTitle = "Pflegeheime in {$city->name} – PflegeIndex";
    $pageDescription = "{$facilityCount} Pflegeeinrichtungen in {$city->name}: Anschriften, Einrichtungsarten und geprüfte Kontaktdaten.";
    $canonicalUrl = route('cities.show', $city);
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
                    <li aria-current="page"><span aria-hidden="true">›</span><span>{{ $city->name }}</span></li>
                </ol>
            </nav>
            <h1>Pflegeheime in {{ $city->name }}</h1>
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
                <div class="results-heading" style="margin-top:42px">
                    <h2 id="city-facilities-title">Alle Pflegeheime in {{ $city->name }}</h2>
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
@endsection
