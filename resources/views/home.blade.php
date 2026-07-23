@extends('layouts.app')

@php
    $pageTitle = 'PflegeIndex – Pflege einfach finden';
    $pageDescription = 'Pflegeheime, Pflegedienste, Tagespflege und Krankenhäuser in Brandenburg finden.';
    $pageUrl = route('home');
    $organizationId = $pageUrl.'#organization';
    $websiteId = $pageUrl.'#website';
    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $organizationId,
        'name' => 'PflegeIndex',
        'url' => $pageUrl,
        'logo' => asset('logo.svg'),
    ];
    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $websiteId,
        'name' => 'PflegeIndex',
        'url' => $pageUrl,
        'inLanguage' => 'de-DE',
        'publisher' => ['@id' => $organizationId],
    ];
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'Wie finde ich einen Pflegedienst oder ein Pflegeheim?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Nutzen Sie die Suchleiste auf der Startseite, geben Sie einen Ort oder eine Postleitzahl ein und wählen Sie die gewünschte Pflegeform. Alternativ können Sie direkt über die Kategorien oder die Liste der beliebten Städte nach passenden Angeboten in Ihrer Region suchen.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'Woher stammen die Daten auf PflegeIndex?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Die amtlichen Grunddaten stammen vom Landesamt für Soziales und Versorgung (LASV) Brandenburg. Kontaktangaben, E-Mail-Adressen, Websites und redaktionelle Beschreibungen werden gesondert geprüft und auf Basis offizieller Quellen ergänzt.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'Ist die Nutzung von PflegeIndex kostenlos?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Ja, die Recherche, Filterung und Nutzung des gesamten PflegeIndex-Verzeichnisses ist für Pflegebedürftige und ihre Angehörigen vollständig kostenlos.'
                ]
            ]
        ]
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="home-hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">PflegeIndex startet in Brandenburg</p>
                <h1>Passende Pflege in Ihrer Nähe <span>finden.</span></h1>
                <p class="hero-lead">PflegeIndex hilft Angehörigen und Pflegebedürftigen, Pflegeangebote in Brandenburg übersichtlich zu entdecken und passende Einrichtungen zu vergleichen.</p>
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

                @if(!empty($topCities))
                    <nav class="search-suggestions" aria-label="Beliebte Städte">
                        <span class="search-suggestions__label">Beliebte Städte:</span>
                        <ul class="search-suggestions__list">
                            @foreach($topCities as $topCity)
                                <li><a href="{{ route('cities.show', $topCity['slug']) }}">{{ $topCity['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
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

    <div class="stats-bar-wrapper">
        <div class="container stats-bar" aria-label="PflegeIndex in Zahlen">
            <span class="stats-bar-title">PflegeIndex in Zahlen:</span>
            <span class="stats-bar-item"><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong> Einrichtungen</span>
            <span class="stats-bar-item"><strong>{{ number_format($cityCount, 0, ',', '.') }}</strong> Städte & Gemeinden</span>
            <span class="stats-bar-item">Region: <strong>Brandenburg</strong></span>
        </div>
    </div>

    <section class="section section--white">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Pflegeangebote</p>
                <h2>Welche Unterstützung suchen Sie?</h2>
                <p>Starten Sie mit der passenden Pflegeform und grenzen Sie die Ergebnisse anschließend nach Ort ein.</p>
            </div>
            <div class="category-grid">
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Ambulante Pflege']) }}">
                    <span class="category-icon"><svg viewBox="0 0 24 24"><path d="M4 19h16M6 19v-8h12v8M9 11V7h6v4M12 4v6M9 7h6"/></svg></span>
                    <h3>Ambulante Pflegedienste</h3>
                    <p>Unterstützung, Sachleistungen und Pflege im eigenen Zuhause.</p>
                </a>
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Stationäre/teilstationäre Pflege']) }}">
                    <span class="category-icon"><svg viewBox="0 0 24 24"><path d="M4 20V8l8-5 8 5v12M9 20v-6h6v6"/></svg></span>
                    <h3>Stationäre und teilstationäre Pflege</h3>
                    <p>Pflegeheime, Kurzzeitpflege und Tagespflege im Vergleich.</p>
                </a>
                <a class="category-card" href="{{ route('directory.index', ['type' => 'Krankenhaus']) }}">
                    <span class="category-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/></svg></span>
                    <h3>Krankenhäuser</h3>
                    <p>Krankenhäuser aus dem offiziellen Verzeichnis des Landes.</p>
                </a>
                <a class="category-card" href="{{ route('directory.index') }}">
                    <span class="category-icon"><svg viewBox="0 0 24 24"><path d="M5 20V5h14v15M8 9h8M8 13h8M8 17h5"/></svg></span>
                    <h3>Alle Einrichtungen</h3>
                    <p>Den vollständigen Datenbestand durchsuchen und filtern.</p>
                </a>
            </div>
        </div>
    </section>

    <section class="section section--white section--benefits">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Vorteile</p>
                <h2>Warum PflegeIndex?</h2>
                <p>Die amtlichen Grunddaten stammen vom LASV Brandenburg. Kontaktdaten und Beschreibungen können redaktionell ergänzt sein.</p>
            </div>
            <div class="benefits-grid">
                <article class="benefit-card">
                    <h3>Amtliche Grunddaten</h3>
                    <p>Die Basisdaten stammen aus öffentlich zugänglichen amtlichen Registern des Landesamtes für Soziales und Versorgung Brandenburg.</p>
                </article>
                <article class="benefit-card">
                    <h3>Kostenlos nutzbar</h3>
                    <p>Die Suche, Filterung und Nutzung des gesamten PflegeIndex-Katalogs ist für Pflegebedürftige und Angehörige vollkommen gebührenfrei.</p>
                </article>
                <article class="benefit-card">
                    <h3>Schnelle Orientierung</h3>
                    <p>Finden Sie Angebote passend nach Pflegeart, Name, Ort oder Postleitzahl und nehmen Sie direkt Kontakt auf.</p>
                </article>
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

    <section class="section section--faq" id="faq">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Häufige Fragen</p>
                <h2>Fragen & Antworten</h2>
                <p>Hilfreiche Informationen zur Nutzung von PflegeIndex.</p>
            </div>
            <div class="faq-accordion-list">
                <details class="faq-item">
                    <summary class="faq-question">Wie finde ich einen Pflegedienst oder ein Pflegeheim?</summary>
                    <div class="faq-answer">
                        <p>Nutzen Sie die Suchleiste auf der Startseite, geben Sie einen Ort oder eine Postleitzahl ein und wählen Sie die gewünschte Pflegeform. Alternativ können Sie direkt über die Kategorien oder die Liste der beliebten Städte nach passenden Angeboten in Ihrer Region suchen.</p>
                    </div>
                </details>
                <details class="faq-item">
                    <summary class="faq-question">Woher stammen die Daten auf PflegeIndex?</summary>
                    <div class="faq-answer">
                        <p>Die amtlichen Grunddaten stammen vom Landesamt für Soziales und Versorgung (LASV) Brandenburg. Kontaktangaben, E-Mail-Adressen, Websites und redaktionelle Beschreibungen werden gesondert geprüft und auf Basis offizieller Quellen ergänzt.</p>
                    </div>
                </details>
                <details class="faq-item">
                    <summary class="faq-question">Ist die Nutzung von PflegeIndex kostenlos?</summary>
                    <div class="faq-answer">
                        <p>Ja, die Recherche, Filterung und Nutzung des gesamten PflegeIndex-Verzeichnisses ist für Pflegebedürftige und ihre Angehörigen vollständig kostenlos.</p>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <section class="section section--white" aria-labelledby="popular-searches-title">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Direktsuche</p>
                <h2 id="popular-searches-title">Beliebte Suchen</h2>
                <p>Häufig gesuchte Pflegeangebote in Brandenburg – direkt zum Ergebnis.</p>
            </div>
            <nav aria-label="Beliebte Suchen">
                <ul class="popular-searches-list">
                    @foreach($popularSearches as $search)
                        <li>
                            <a class="popular-search-link" href="{{ route('directory.index', ['q' => $search['query'], 'type' => $search['type']]) }}">
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M8.5 3a5.5 5.5 0 0 1 4.23 9.02L17 16.29l-.71.71-4.27-4.27A5.5 5.5 0 1 1 8.5 3Zm0 1a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z"/></svg>
                                {{ $search['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </section>

    <section class="section section--white section--footer-links" aria-label="Beliebte Verzeichnisse">
        <div class="container">
            <div class="footer-links-grid">
                <div>
                    <h3>Regionale Navigation</h3>
                    <ul>
                        <li><a href="{{ route('region.show') }}">Alle Städte in Brandenburg</a></li>
                        <li><a href="{{ route('region.show') }}">Landkreise und kreisfreie Städte</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Pflegeformen</h3>
                    <ul>
                        <li><a href="{{ route('directory.index', ['type' => 'Ambulante Pflege']) }}">Ambulante Pflege</a></li>
                        <li><a href="{{ route('directory.index', ['type' => 'Stationäre/teilstationäre Pflege']) }}">Stationäre Pflege</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection
