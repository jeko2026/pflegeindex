@extends('layouts.app')

@php
    $currentPage = $facilities->currentPage();
    $pageTitle = $currentPage > 1
        ? "Pflegeangebote finden – Seite {$currentPage} – PflegeIndex"
        : 'Pflegeangebote finden – PflegeIndex';
    $pageDescription = $currentPage > 1
        ? "Seite {$currentPage} mit weiteren Pflegeangeboten in Brandenburg."
        : 'Pflegeangebote in Brandenburg nach Ort, Postleitzahl, Name und Einrichtungsart durchsuchen.';
    $canonicalUrl = $currentPage > 1
        ? route('directory.index', ['page' => $currentPage])
        : route('directory.index');
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)

@if($hasFilterParameters)
    @push('head')
        <meta name="robots" content="noindex,follow">
    @endpush
@endif

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Pflege finden</span></p>
            <h1>Pflegeangebote finden</h1>
            <p class="page-hero__lead">Durchsuchen Sie {{ number_format($totalCount, 0, ',', '.') }} Einrichtungen in Brandenburg nach Name, Adresse, Ort und Einrichtungsart.</p>
        </div>
    </section>

    <div class="container catalog-layout">
        <aside class="filter-panel">
            <h2>Ergebnisse filtern</h2>
            <form method="get" action="{{ route('directory.index') }}">
                <div class="filter-field"><label for="q">Ort, PLZ oder Name</label><input id="q" name="q" type="search" value="{{ $query }}" placeholder="z. B. Potsdam"></div>
                <div class="filter-field"><label for="type">Einrichtungsart</label><select id="type" name="type"><option value="">Alle Einrichtungen</option>@foreach($types as $type)<option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="filter-field"><label for="city">Stadt</label><select id="city" name="city"><option value="">Alle Städte</option>@foreach($cities as $city)<option value="{{ $city->slug }}" @selected($selectedCity === $city->slug)>{{ $city->name }} ({{ $city->facilities_count }})</option>@endforeach</select></div>
                <div class="filter-actions"><button class="primary-button" type="submit">Ergebnisse anzeigen</button><a class="reset-button" href="{{ route('directory.index') }}">Filter zurücksetzen</a></div>
            </form>
        </aside>

        <section>
            <div class="results-heading"><h2>{{ number_format($facilities->total(), 0, ',', '.') }} Ergebnisse</h2><p>{{ $facilities->count() }} auf dieser Seite · nach Ort und Name sortiert</p></div>
            <div class="notice">Offizielle Basisdaten des LASV Brandenburg. Ergänzende Telefonnummern, E-Mail-Adressen und Websites werden nur nach Prüfung einer offiziellen Quelle veröffentlicht.</div>
            <div class="results-list" style="margin-top:16px">
                @forelse($facilities as $facility)
                    @include('facilities._card', ['facility' => $facility])
                @empty
                    <div class="empty-state"><h3>Keine passenden Einrichtungen</h3><p>Ändern Sie den Suchbegriff oder setzen Sie die Filter zurück.</p></div>
                @endforelse
            </div>
            <x-pagination :paginator="$facilities" />
        </section>
    </div>
@endsection
