@extends('layouts.app')

@section('title', "Pflegeeinrichtungen in {$city->name} – PflegeIndex")
@section('description', "{$facilities->total()} Pflegeeinrichtungen in {$city->name}: Anschriften, Einrichtungsarten und geprüfte Kontaktdaten.")
@section('bodyClass', 'city-seo-page')

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><a href="{{ route('region.show') }}">Brandenburg</a><span>›</span><span>{{ $city->name }}</span></p>
            <h1>Pflegeeinrichtungen in {{ $city->name }}</h1>
            <p class="page-hero__lead">{{ $facilities->total() }} Pflegeangebote mit Anschrift und Einrichtungsart.</p>
        </div>
    </section>
    <div class="container catalog-layout city-catalog-layout">
        <aside class="filter-panel city-facts">
            <h2>{{ $city->name }}</h2>
            <p><strong>{{ $facilities->total() }}</strong> Einrichtungen im offiziellen Verzeichnis.</p>
            <a class="primary-button" href="{{ route('directory.index', ['city' => $city->slug]) }}">Ergebnisse filtern</a>
        </aside>
        <section>
            <div class="results-heading"><h2>{{ $facilities->total() }} Einrichtungen</h2><p>{{ $facilities->count() }} auf dieser Seite · alphabetisch sortiert</p></div>
            <div class="notice">Basisdaten: Landesamt für Soziales und Versorgung Brandenburg, Stand 31.12.2025.</div>
            <div class="results-list" style="margin-top:16px">
                @foreach($facilities as $facility)
                    @include('facilities._card', ['facility' => $facility])
                @endforeach
            </div>
            <x-pagination :paginator="$facilities" />
        </section>
    </div>
@endsection
