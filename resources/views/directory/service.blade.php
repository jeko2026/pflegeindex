@extends('layouts.app')
@php
    $heading = $type->name.' in '.$city->name;
    $canonical = route($config['serviceRoute'], [$city, $type->slug]);
    $pageTitle = $editorial['title'] ?? ($heading.' – PflegeIndex');
    $pageDescription = $editorial['description'] ?? ($heading.' mit Anschrift und Kontaktdaten.');
    $breadcrumbs = [['name'=>'Startseite','url'=>route('home')],['name'=>$config['stateName'],'url'=>$config['landUrl']],['name'=>$city->name,'url'=>route($config['cityRoute'],$city)],['name'=>$type->name,'url'=>$canonical]];
    $contextKey = '@'.'context';
    $schema = [$contextKey => 'https://schema.org', '@graph' => [
        ['@type'=>'CollectionPage','name'=>$heading,'url'=>$canonical,'mainEntity'=>['@type'=>'ItemList','numberOfItems'=>$facilities->count(),'itemListElement'=>$facilities->values()->map(fn ($item, $index) => ['@type'=>'ListItem','position'=>$index+1,'url'=>route($config['facilityRoute'], [$city, $item])])->all()]],
        ['@type'=>'BreadcrumbList','itemListElement'=>collect($breadcrumbs)->values()->map(fn ($crumb, $index) => ['@type'=>'ListItem','position'=>$index+1,'name'=>$index===0?'PflegeIndex':$crumb['name'],'item'=>$crumb['url']])->all()],
    ]];
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonical)
@section('bodyClass', 'city-seo-page')
@push('head')
<meta name="robots" content="{{ ($noindex ?? false) ? 'noindex, follow' : 'index, follow' }}">
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<section class="page-hero"><div class="container">@include('directory._breadcrumb',['breadcrumbs'=>$breadcrumbs])<h1>{{ $heading }}</h1><p class="page-hero__lead">{{ $facilities->count() }} Einrichtungen</p>@if(!empty($editorial))<p>{{ $editorial['intro'] }}</p>@endif</div></section>
<section class="section"><div class="container"><div class="results-heading"><h2 id="angebote">Alle Pflegeeinrichtungen in {{ $city->name }}</h2><p>{{ $facilities->count() }} Einrichtungen · alphabetisch sortiert</p></div>@include('directory._source-notice',['sourceNotice'=>$config['sourceNotice']])<div class="results-list" style="margin-top:16px">@foreach($facilities as $facility)@include('directory._facility-card',['facility'=>$facility,'city'=>$city,'config'=>$config])@endforeach</div></div></section>
@if(!empty($editorial['faq']) && $config['showFaq'])<section class="section section--faq"><div class="container"><div class="section-heading"><h2>Häufig gestellte Fragen</h2></div><div class="faq-accordion-list">@foreach($editorial['faq'] as $item)<details class="faq-item"><summary class="faq-question">{{ $item['question'] }}</summary><div class="faq-answer"><p>{{ $item['answer'] }}</p></div></details>@endforeach</div></div></section>@endif
@endsection
