@extends('layouts.app')
@php
    $currentPage = $facilities->currentPage();
    $title = 'Pflegeeinrichtungen in '.$city->name.($currentPage > 1 ? ' – Seite '.$currentPage : '').' – PflegeIndex';
    $description = $currentPage > 1 ? 'Seite '.$currentPage.' mit weiteren Pflegeeinrichtungen in '.$city->name.'.' : $facilityCount.' Pflegeeinrichtungen in '.$city->name.': Anschriften, Einrichtungsarten und verfügbare Kontaktdaten.';
    $canonical = $currentPage > 1 ? route($config['cityRoute'], [$city, 'page' => $currentPage]) : route($config['cityRoute'], $city);
    $breadcrumbs = [['name' => 'Startseite', 'url' => route('home')], ['name' => $config['stateName'], 'url' => $config['landUrl']]];
    if (($config['showDistrictBreadcrumb'] ?? false) && $city->geoMunicipality?->district) { $breadcrumbs[] = ['name' => $city->geoMunicipality->district->display_name, 'url' => route('districts.show', $city->geoMunicipality->district->slug)]; }
    $breadcrumbs[] = ['name' => $city->name, 'url' => $canonical];
    $contextKey = '@'.'context';
    $collectionSchema = [$contextKey => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'url' => $canonical, 'about' => ['@type' => 'Place', 'name' => $city->name, 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $city->name, 'addressRegion' => $config['stateName'], 'addressCountry' => 'DE']]];
    $breadcrumbSchema = [$contextKey => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($breadcrumbs)->values()->map(fn ($crumb, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['name'], 'item' => $crumb['url']])->all()];
@endphp
@section('title', $title)
@section('description', $description)
@section('canonical', $canonical)
@section('bodyClass', 'city-seo-page')
@push('head')
<meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:url" content="{{ $canonical }}"><meta property="og:type" content="website"><meta property="og:site_name" content="PflegeIndex"><meta property="og:locale" content="de_DE">
<script type="application/ld+json">{!! json_encode($collectionSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<section class="page-hero"><div class="container">@include('directory._breadcrumb', ['breadcrumbs' => $breadcrumbs])<h1>Pflegeeinrichtungen in {{ $city->name }}</h1><p class="page-hero__lead">{{ $config['cityIntro'] }}</p>@if($config['showSeoIntro'] && !empty($seoAction) && $currentPage === 1)<p>{{ $seoAction['focus'] }}</p>@endif</div></section>
<section class="section"><div class="container">@if($services->isNotEmpty())<nav aria-label="Pflegearten in {{ $city->name }}"><ul>@foreach($services as $service)<li><a href="{{ $service->url ?? route($config['serviceRoute'], [$city, $service->slug]) }}">{{ $service->name }}@if(filled($service->facility_count)) ({{ $service->facility_count }})@endif</a></li>@endforeach</ul></nav>@endif
<div class="region-summary"><div><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong><span>Einrichtungen</span></div><div><strong>{{ $typeCount }}</strong><span>Einrichtungsarten</span></div></div>
@if($config['showQuality'] && !empty($qualityStats))<section class="city-quality-summary" aria-labelledby="city-quality-title" data-city-quality-score="{{ $qualityStats['average_score'] }}"><div><p class="eyebrow">Datenqualität</p><h2 id="city-quality-title">Datenqualität in {{ $city->name }}</h2><p><strong>{{ $qualityStats['quality_label'] }}</strong></p></div><div><p>Geprüfte Einrichtungen: <b>{{ $qualityStats['verified_count'] }} von {{ $facilityCount }}</b></p><p>{{ $qualityStats['verified_percentage'] }} % der Einrichtungen wurden geprüft.</p><p>Die Angaben beziehen sich auf Kontakt- und Quelldaten, nicht auf die Pflegequalität.</p><div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $qualityStats['average_score'] }}"><span style="width: {{ $qualityStats['average_score'] }}%"></span></div></div></section>@endif
<div class="results-heading" style="margin-top:42px"><h2>Alle Pflegeeinrichtungen in {{ $city->name }}</h2><p>{{ $facilities->count() }} auf dieser Seite · alphabetisch sortiert</p></div>@include('directory._source-notice', ['sourceNotice' => $config['sourceNotice']])<div class="results-list" style="margin-top:16px">@foreach($facilities as $facility)@include('directory._facility-card', ['facility' => $facility, 'city' => $city, 'config' => $config])@endforeach</div><x-pagination :paginator="$facilities" /></div></section>
@if($nearbyCities->isNotEmpty())<section class="section section--white"><div class="container"><div class="section-heading"><p class="eyebrow">{{ $config['stateName'] }}</p><h2>{{ $config['nearbyTitle'] }}</h2></div><div class="city-grid">@foreach($nearbyCities as $nearbyCity)<a class="city-card" href="{{ route($config['cityRoute'], $nearbyCity) }}"><span class="city-card__pin">⌖</span><span><strong>{{ $nearbyCity->name }}</strong><small>{{ $nearbyCity->facilities_count }} Einrichtungen</small></span><span>→</span></a>@endforeach</div></div></section>@endif
@if($config['showFaq'])<section class="section section--faq"><div class="container"><div class="section-heading"><h2>Häufig gestellte Fragen</h2></div><div class="faq-accordion-list"><details class="faq-item"><summary class="faq-question">Welche Pflegeangebote gibt es in {{ $city->name }}?</summary><div class="faq-answer"><p>In {{ $city->name }} sind {{ $facilityCount }} Einrichtungen gelistet.</p></div></details></div></div></section>@endif
@endsection