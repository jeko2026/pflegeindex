@extends('layouts.app')

@php
    $currentPage = $facilities->currentPage();
    $totalFormatted = number_format($totalCount, 0, ',', '.');
    $pageTitle = $currentPage > 1
        ? "Pflegeangebote finden – Seite {$currentPage} – PflegeIndex"
        : "Pflegeheime & Pflegedienste in Brandenburg finden – {$totalFormatted} Einrichtungen | PflegeIndex";
    $pageDescription = $currentPage > 1
        ? "Seite {$currentPage} mit weiteren Pflegeangeboten in Brandenburg."
        : "Über {$totalFormatted} Pflegeheime, Pflegedienste und Krankenhäuser in Brandenburg. Nach Ort, PLZ, Name und Einrichtungsart filtern – mit amtlichen Basisdaten des LASV.";
    $canonicalUrl = $currentPage > 1
        ? route('directory.index', ['page' => $currentPage])
        : route('directory.index');

    $entryTypeOverview = [
        [
            'title' => 'Ambulante Pflege',
            'description' => 'Pflegedienste, die Pflege- und Betreuungsleistungen im häuslichen Umfeld erbringen.',
            'filterValue' => 'Ambulante Pflege',
        ],
        [
            'title' => 'Stationäre/teilstationäre Pflege',
            'description' => 'Pflegeheime, Tages- und Kurzzeitpflegeeinrichtungen mit Betreuung vor Ort.',
            'filterValue' => 'Stationäre/teilstationäre Pflege',
        ],
        [
            'title' => 'Krankenhäuser',
            'description' => 'Kliniken mit pflegerischem Bezug, die im LASV-Verzeichnis geführt werden.',
            'filterValue' => 'Krankenhaus',
        ],
    ];

    $featuredCitySlugs = ['potsdam', 'cottbus', 'oranienburg'];
    $featuredCityLinks = $cities
        ->whereIn('slug', $featuredCitySlugs)
        ->sortBy(fn ($featuredCity) => array_search($featuredCity->slug, $featuredCitySlugs, true))
        ->values()
        ->merge(
            $cities
                ->whereNotIn('slug', $featuredCitySlugs)
                ->sortByDesc('facilities_count')
                ->take(3)
                ->values()
        );

    $directoryFaqItems = [
        [
            'question' => 'Was ist der Unterschied zwischen einem Pflegeheim und einem Pflegedienst?',
            'answer' => 'Ein Pflegeheim betreut Bewohnerinnen und Bewohner stationär oder teilstationär vor Ort. Ein ambulanter Pflegedienst unterstützt pflegebedürftige Menschen in ihrem eigenen Zuhause.',
        ],
        [
            'question' => 'Wie aktuell sind die Daten auf PflegeIndex?',
            'answer' => 'Die amtlichen Basisdaten stammen vom Landesamt für Soziales und Versorgung Brandenburg. Ergänzende Kontaktdaten werden fortlaufend recherchiert und geprüft; der Prüfstatus wird auf den Einrichtungsseiten ausgewiesen.',
        ],
        [
            'question' => 'Sind alle Pflegeeinrichtungen in Brandenburg erfasst?',
            'answer' => 'PflegeIndex basiert auf dem amtlichen Einrichtungsverzeichnis des Landes Brandenburg und wird laufend um recherchierte Kontaktdaten ergänzt. Hinweise auf fehlende oder fehlerhafte Angaben können über die Kontaktmöglichkeit der Website gemeldet werden.',
        ],
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)

@if($hasFilterParameters)
    @push('head')
        <meta name="robots" content="noindex,follow">
    @endpush
@endif

@push('head')
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @php
        $directoryCollectionSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
        ];
        $directoryItemListSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_values(array_map(
                static fn ($entry, $index) => [
                    '@type' => 'ListItem',
                    'position' => $facilities->firstItem() !== null ? $facilities->firstItem() + $index : $index + 1,
                    'name' => $entry->name,
                    'url' => $entry->url,
                ],
                $facilities->items(),
                array_keys($facilities->items()),
            )),
        ];
        $directoryBreadcrumbSchema = [
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
                    'name' => 'Pflege finden',
                    'item' => $canonicalUrl,
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($directoryCollectionSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($directoryItemListSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($directoryBreadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Pflege finden</span></p>
            <h1>Pflegeangebote finden</h1>
            <p class="page-hero__lead">Durchsuchen Sie {{ $totalFormatted }} Einrichtungen in Brandenburg nach Name, Adresse, Ort und Einrichtungsart.</p>
            <p>PflegeIndex führt drei Arten von Einrichtungen: ambulante Pflegedienste, die Pflege im eigenen Zuhause übernehmen, stationäre und teilstationäre Einrichtungen wie Pflegeheime und Tagespflegen sowie Krankenhäuser mit pflegerischem Bezug. Nutzen Sie die Filter, um nach Ort, Postleitzahl, Name oder Einrichtungsart einzugrenzen.</p>
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
            <div class="notice">Offizielle Basisdaten des LASV Brandenburg. Ergänzende Telefonnummern, E-Mail-Adressen und Websites können aus recherchierten Quellen stammen. Der jeweilige Prüfstatus wird im Profil ausgewiesen.</div>
            <div class="results-list" style="margin-top:16px">
                @forelse($facilities as $facility)
                    @include('facilities._card', ['facility' => $facility])
                @empty
                    <div class="empty-state">
                        <h3>Keine passenden Einrichtungen</h3>
                        <p>Ändern Sie den Suchbegriff oder setzen Sie die Filter zurück. Für die gewählte Kombination aus Suchbegriff und Filter wurden keine Einrichtungen gefunden.</p>
                        <div class="empty-state__actions">
                            <a class="primary-button" href="{{ route('directory.index') }}">Filter zurücksetzen</a>
                            <a class="secondary-button" href="{{ route('region.show') }}">Alle Orte in Brandenburg</a>
                            <a class="secondary-button" href="{{ route('home') }}">Zur Startseite</a>
                        </div>
                    </div>
                @endforelse

            </div>
            <x-pagination :paginator="$facilities" />
        </section>
    </div>

    <section class="section section--white" aria-labelledby="einrichtungsarten-title">
        <div class="container">
            <div class="section-heading">
                <h2 id="einrichtungsarten-title">Einrichtungsarten im Überblick</h2>
            </div>
            <div class="category-grid">
                @foreach($entryTypeOverview as $entryType)
                    <a class="category-card" href="{{ route('directory.index', ['type' => $entryType['filterValue']]) }}">
                        <h3>{{ $entryType['title'] }}</h3>
                        <p>{{ $entryType['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if($featuredCityLinks->isNotEmpty())
        <section class="section section--white" aria-labelledby="ausgewaehlte-staedte-title">
            <div class="container">
                <div class="section-heading">
                    <h2 id="ausgewaehlte-staedte-title">Pflegeangebote in ausgewählten Städten</h2>
                </div>
                <div class="city-grid">
                    @foreach($featuredCityLinks as $linkedCity)
                        <a class="city-card" href="{{ route('cities.show', $linkedCity) }}">
                            <span class="city-card__pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
                            <span><strong>{{ $linkedCity->name }}</strong><small>{{ $linkedCity->facilities_count }} {{ $linkedCity->facilities_count === 1 ? 'Einrichtung' : 'Einrichtungen' }}</small></span>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section--faq" aria-labelledby="directory-faq-title">
        <div class="container">
            <div class="section-heading">
                <h2 id="directory-faq-title">Häufig gestellte Fragen</h2>
            </div>
            <div class="faq-accordion-list">
                @foreach($directoryFaqItems as $faqItem)
                    <details class="faq-item">
                        <summary class="faq-question">{{ $faqItem['question'] }}</summary>
                        <div class="faq-answer">
                            <p>{{ $faqItem['answer'] }}</p>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endsection
