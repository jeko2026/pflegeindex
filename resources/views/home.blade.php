@extends('layouts.app')

@section('title', 'PflegeIndex – Pflege einfach finden')
@section('description', 'Pflegeheime, Pflegedienste, Tagespflege und Krankenhäuser in Brandenburg finden.')

@section('content')
    <section class="home-hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">Das Pflegeverzeichnis für Deutschland</p>
                <h1>Passende Pflege in Ihrer Nähe <span>finden.</span></h1>
                <p class="hero-lead">PflegeIndex hilft Angehörigen und Pflegebedürftigen, Pflegeangebote übersichtlich zu entdecken und passende Einrichtungen zu vergleichen.</p>
                <form class="hero-search" method="get" action="{{ route('directory.index') }}">
                    <label class="field-wrap">
                        <span class="sr-only">Ort oder Postleitzahl</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg>
                        <input name="q" type="search" placeholder="Ort oder Postleitzahl" autocomplete="postal-code">
                    </label>
                    <label>
                        <span class="sr-only">Pflegeform</span>
                        <select name="type">
                            <option value="">Alle Pflegeformen</option>
                            <option>Stationäre/teilstationäre Pflege</option>
                            <option>Ambulante Pflege</option>
                            <option>Krankenhaus</option>
                        </select>
                    </label>
                    <button class="primary-button" type="submit">Pflege finden <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg></button>
                </form>
                <p class="search-hint">{{ number_format($facilityCount, 0, ',', '.') }} offizielle Basiseinträge aus Brandenburg, sortiert nach Ort und Einrichtungsart.</p>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <div class="map-card">
                    <span class="map-card__label">PflegeIndex Brandenburg</span>
                    <h2>Pflege in der Nähe</h2>
                    <div class="map-lines"><span class="map-pin one"></span><span class="map-pin two"></span><span class="map-pin three"></span></div>
                    <div class="map-caption"><strong>Passende Angebote</strong><span>übersichtlich vergleichen</span></div>
                </div>
            </div>
        </div>
    </section>

    <div class="container stats" aria-label="Datenstand">
        <div class="stat"><span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M4 20V8l8-5 8 5v12M9 20v-6h6v6M8 10h.01M16 10h.01"/></svg></span><div><strong>{{ number_format($facilityCount, 0, ',', '.') }} Profile</strong><span>aus dem offiziellen Verzeichnis</span></div></div>
        <div class="stat"><span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span><div><strong>{{ number_format($cityCount, 0, ',', '.') }} Orte</strong><span>alphabetisch erschlossen</span></div></div>
        <div class="stat"><span class="stat-icon"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span><div><strong>Offene Daten</strong><span>LASV Brandenburg · DL-DE Zero 2.0</span></div></div>
    </div>

    <section class="section section--white">
        <div class="container">
            <div class="section-heading"><p class="eyebrow">Pflegeangebote</p><h2>Welche Unterstützung suchen Sie?</h2><p>Starten Sie mit der passenden Pflegeform und grenzen Sie die Ergebnisse anschließend nach Ort ein.</p></div>
            <div class="category-grid">
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Stationäre/teilstationäre Pflege']) }}"><span class="category-icon"><svg viewBox="0 0 24 24"><path d="M4 20V8l8-5 8 5v12M9 20v-6h6v6"/></svg></span><h3>Stationäre Pflege</h3><p>Stationäre und teilstationäre Einrichtungen in Brandenburg.</p></a>
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Ambulante Pflege']) }}"><span class="category-icon"><svg viewBox="0 0 24 24"><path d="M4 19h16M6 19v-8h12v8M9 11V7h6v4M12 4v6M9 7h6"/></svg></span><h3>Ambulante Pflege</h3><p>Pflegedienste und Unterstützung im eigenen Zuhause.</p></a>
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Krankenhaus']) }}"><span class="category-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/></svg></span><h3>Krankenhäuser</h3><p>Krankenhäuser aus dem Pflegefonds-Einrichtungsverzeichnis.</p></a>
                <a class="category-card" href="{{ route('directory.index') }}"><span class="category-icon"><svg viewBox="0 0 24 24"><path d="M5 20V5h14v15M8 9h8M8 13h8M8 17h5"/></svg></span><h3>Alle Einrichtungen</h3><p>Den vollständigen Datenbestand durchsuchen und filtern.</p></a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container region-callout">
            <div><p class="eyebrow">Erste Region</p><h2>Pflegeangebote in Brandenburg</h2><p>Der erste PflegeIndex-Datenbestand umfasst {{ number_format($facilityCount, 0, ',', '.') }} offizielle Einträge und {{ $cityCount }} alphabetisch sortierte Orte.</p></div>
            <a class="primary-button" href="{{ route('region.show') }}">Region entdecken <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg></a>
        </div>
    </section>

    <section class="section section--white" id="so-funktioniert-es">
        <div class="container">
            <div class="section-heading"><p class="eyebrow">So funktioniert es</p><h2>In drei Schritten zur passenden Pflege</h2></div>
            <div class="steps">
                <article class="step"><span class="step-number">1</span><h3>Ort eingeben</h3><p>Suchen Sie nach Stadt oder Postleitzahl und wählen Sie die gewünschte Pflegeform.</p></article>
                <article class="step"><span class="step-number">2</span><h3>Angebote vergleichen</h3><p>Prüfen Sie Anschrift, Einrichtungsart und veröffentlichte Kontaktdaten.</p></article>
                <article class="step"><span class="step-number">3</span><h3>Kontakt aufnehmen</h3><p>Geprüfte Telefonnummern und Websites werden direkt im Profil angezeigt.</p></article>
            </div>
        </div>
    </section>
@endsection
