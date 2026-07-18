@extends('layouts.app')

@section('title', 'Pflegeeinrichtungen in Brandenburg – PflegeIndex')
@section('description', "{$facilityCount} Pflegeeinrichtungen in {$cities->count()} Orten Brandenburgs entdecken.")

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Brandenburg</span></p>
            <h1>Pflege in Brandenburg</h1>
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
            <div class="results-heading" style="margin-top:42px"><h2>{{ number_format($facilities->total(), 0, ',', '.') }} Pflegeeinrichtungen</h2><p>{{ $facilities->count() }} auf dieser Seite · nach Ort und Name sortiert</p></div>
            <div class="results-list" style="margin-top:16px">
                @foreach($facilities as $facility)
                    @include('facilities._card', ['facility' => $facility])
                @endforeach
            </div>
            <x-pagination :paginator="$facilities" />
            <div class="section-heading section-heading--split" style="margin-top:72px"><div><p class="eyebrow">Ortsverzeichnis</p><h2>Pflegeangebote nach Ort</h2></div><a href="{{ route('directory.index') }}">Alle Einrichtungen durchsuchen</a></div>
            <div class="city-grid">
                @foreach($cities as $city)
                    <a class="city-card" href="{{ route('cities.show', $city) }}">
                        <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                        <span><strong>{{ $city->name }}</strong><small>{{ $city->facilities_count }} {{ $city->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small></span>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
