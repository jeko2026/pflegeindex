@extends('layouts.app')
@php
$currentPage = $facilities?->currentPage() ?? 1;
$pageTitle = $currentPage > 1 ? "Pflegeeinrichtungen in {$config['stateName']} – Seite {$currentPage} – PflegeIndex" : 'Pflegeeinrichtungen in '.$config['stateName'].' – PflegeIndex';
$pageDescription = $currentPage > 1 ? "Seite {$currentPage} mit weiteren Pflegeeinrichtungen in {$config['stateName']}." : $facilityCount.' Pflegeeinrichtungen in '.$cities->count().' Orten '.$config['stateName'].'s entdecken.';
$pageUrl = $currentPage > 1 ? route(request()->route()->getName(), ['page' => $currentPage]) : $config['landUrl'];
$schema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$pageTitle,'description'=>$pageDescription,'url'=>$pageUrl,'inLanguage'=>'de-DE','isPartOf'=>['@type'=>'WebSite','name'=>'PflegeIndex','url'=>route('home')],'about'=>['@type'=>'AdministrativeArea','name'=>$config['stateName']]];
@endphp
@section('title',$pageTitle)
@section('description',$pageDescription)
@section('canonical',$pageUrl)
@push('head')
<meta property="og:type" content="website"><meta property="og:title" content="{{ $pageTitle }}"><meta property="og:description" content="{{ $pageDescription }}"><meta property="og:url" content="{{ $pageUrl }}"><meta property="og:site_name" content="PflegeIndex"><meta property="og:locale" content="de_DE">
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
@endpush
@section('content')
<section class="page-hero"><div class="container"><h1>Pflegeeinrichtungen in {{ $config['stateName'] }}</h1><p class="page-hero__lead">{{ $config['intro'] }}</p></div></section>
<section class="section"><div class="container"><div class="region-summary"><div><strong>{{ number_format($facilityCount,0,',','.') }}</strong><span>Einrichtungen</span></div><div><strong>{{ $cities->count() }}</strong><span>Orte</span></div><div><strong>{{ $typeCount }}</strong><span>Einrichtungsarten</span></div></div>
@if((!$facilities || $facilities->onFirstPage()) && ($districts ?? collect())->isNotEmpty())<section><div class="section-heading" style="margin-top:42px"><p class="eyebrow">Regionen</p><h2>Landkreise und kreisfreie Städte</h2></div><div class="city-grid">@foreach($districts as $district)<a class="city-card" href="{{ route('districts.show',$district->slug) }}"><span class="city-card__pin">⌖</span><span><strong>{{ $district->display_name }}</strong><small>{{ $district->type === 'landkreis' ? 'Landkreis' : 'Kreisfreie Stadt' }} · {{ $district->linked_cities_count }} Orte · {{ $district->facilities_count }} Einrichtungen</small></span><span>→</span></a>@endforeach</div></section>@endif
@if(!$facilities || $facilities->onFirstPage())<section><div class="section-heading section-heading--split" style="margin-top:42px"><div><p class="eyebrow">Ortsverzeichnis</p><h2>Pflegeeinrichtungen nach Stadt</h2></div><a href="{{ route('directory.index') }}">Alle Einrichtungen durchsuchen</a></div><div class="city-grid">@foreach($cities as $city)<a class="city-card" href="{{ route($config['cityRoute'],$city) }}"><span class="city-card__pin">⌖</span><span><strong>{{ $city->name }}</strong><small>{{ $city->facilities_count }} Einrichtungen</small></span><span>→</span></a>@endforeach</div></section>@endif
@if($facilities)<section><div class="results-heading" style="margin-top:72px"><h2>Alle Pflegeeinrichtungen in {{ $config['stateName'] }}</h2><p>{{ $facilities->count() }} auf dieser Seite</p></div><div class="results-list">@foreach($facilities as $facility)@include('directory._facility-card',['facility'=>$facility,'config'=>$config])@endforeach</div><x-pagination :paginator="$facilities" /></section>@endif
</div></section>
@endsection