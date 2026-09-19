@extends('layouts.app')

@php
    $heading = $category['label'].' in '.$city->name;
    $pageTitle = $heading.' | PflegeIndex';
    $pageDescription = $heading.': Adressen, Telefonnummern, Websites und weitere Kontaktdaten auf PflegeIndex.';
    $canonicalUrl = route('cities.care.show', [$city, $category['slug']]);
    $breadcrumbs = [
        ['name' => 'PflegeIndex', 'item' => route('home')],
        ['name' => 'Brandenburg', 'item' => route('region.show')],
        ['name' => $city->name, 'item' => route('cities.show', $city)],
        ['name' => $category['label'], 'item' => $canonicalUrl],
    ];
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'CollectionPage',
                'name' => $heading,
                'description' => $pageDescription,
                'url' => $canonicalUrl,
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $facilities->count(),
                    'itemListElement' => $facilities->values()->map(fn ($facility, $index) => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $facility->name,
                        'url' => route('facilities.show', [$city, $facility]),
                    ])->all(),
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->map(fn ($item, $index) => [
                    '@type' => 'ListItem', 'position' => $index + 1, ...$item,
                ])->all(),
            ],
        ],
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)
@section('bodyClass', 'city-seo-page')

@push('head')
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="page-hero">
        <div class="container">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumbs" style="margin:0">
                    @foreach($breadcrumbs as $breadcrumb)
                        <li @if($loop->last) aria-current="page" @endif>
                            @unless($loop->first)<span aria-hidden="true">›</span>@endunless
                            @if($loop->last)
                                <span>{{ $breadcrumb['name'] }}</span>
                            @else
                                <a href="{{ $breadcrumb['item'] }}">{{ $breadcrumb['name'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <h1>{{ $heading }}</h1>
            <p class="page-hero__lead">Auf PflegeIndex finden Sie aktuell {{ $facilities->count() }} {{ $facilities->count() === 1 ? 'Einrichtung' : 'Einrichtungen' }} für {{ $category['label'] }} in {{ $city->name }}. Vergleichen Sie Adressen, verfügbare Kontaktdaten und weitere Informationen zu den einzelnen Einrichtungen.</p>
            <a href="{{ route('cities.show', $city) }}">Alle Pflegeeinrichtungen in {{ $city->name }}</a>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="results-heading">
                <h2>{{ $facilities->count() }} {{ $facilities->count() === 1 ? 'Einrichtung' : 'Einrichtungen' }}</h2>
                <p>Alphabetisch sortiert</p>
            </div>
            <div class="notice">Amtliche Basisdaten: LASV Brandenburg, Stand 31.12.2025. Ergänzende Kontaktdaten und ihr Prüfstatus werden im jeweiligen Profil ausgewiesen. Die Zuordnung zu dieser Übersicht erfolgt anhand der hinterlegten Pflegeart und bei zusammengefassten Pflegearten anhand der Einrichtungsbezeichnung; die Übersicht erhebt keinen Anspruch auf Vollständigkeit.</div>
            <div class="results-list" style="margin-top:16px">
                @foreach($facilities as $facility)
                    @include('facilities._card', ['facility' => $facility])
                @endforeach
            </div>
        </div>
    </section>
@endsection
