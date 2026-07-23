@extends('layouts.app')

@php
    $editorialView = $facility->source_id === 'faehrmann-pflege-gmbh-16278-ade0d833b0'
        ? 'facilities.editorial.faehrmann-pflege-gmbh-16278'
        : null;
    $displayWebsite = $editorialView ? 'https://faehrmann-pflege.de/' : $facility->website;
    $hasDirectContact = filled($facility->phone) || filled($facility->email) || filled($displayWebsite);
    $canonicalUrl = route('facilities.show', [$city, $facility]);

    // Build rawDescription for SEO
    $rawDescription = '';
    if ($facility->source_id === 'faehrmann-pflege-gmbh-16278-ade0d833b0') {
        $rawDescription = 'Fährmann Pflege in Angermünde: häusliche Krankenpflege, Tagespflege, Service-Wohnen, betreutes Wohnen und Pflegeberatung im Überblick.';
    } elseif (filled($facility->description)) {
        $rawDescription = $facility->description;
    } else {
        $sectorMap = [
            'Ambulante Pflegeeinrichtung' => 'eine ambulante Pflegeeinrichtung',
            'Stationäre/teilstationäre Pflegeeinrichtung' => 'eine stationäre oder teilstationäre Pflegeeinrichtung',
            'Krankenhaus' => 'ein Krankenhaus',
        ];
        $fallbackParts = [];
        $fallbackParts[] = "{$facility->name} in {$city->name}";

        if (filled($facility->source_sector) && isset($sectorMap[$facility->source_sector])) {
            $fallbackParts[] = "ist {$sectorMap[$facility->source_sector]} im amtlichen Einrichtungsverzeichnis des Landes Brandenburg.";
        } else {
            $fallbackParts[] = "ist als „{$facility->type}“ im amtlichen Einrichtungsverzeichnis des Landes Brandenburg geführt.";
        }

        if (!empty($facility->care_types)) {
            $fallbackParts[] = "Die Einrichtung bietet folgende Pflegeleistungen: " . implode(', ', $facility->care_types) . ".";
        }

        $fallbackParts[] = "Adresse: {$facility->address}, {$facility->postal_code} {$city->name}.";
        $rawDescription = implode(' ', $fallbackParts);
    }

    // Clean and limit description helper
    $cleanAndLimitDescription = function (string $text, int $limit = 155): string {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        $invalidTrailingWords = [
            'in', 'an', 'auf', 'aus', 'bei', 'mit', 'nach', 'von', 'zu', 'für', 'um', 'durch', 'über', 'gegen',
            'vor', 'hinter', 'unter', 'neben', 'zwischen', 'und', 'oder', 'aber', 'wie', 'als', 'im', 'am',
            'vom', 'zum', 'zur', 'ins', 'ans', 'aufs', 'fürs', 'der', 'die', 'das', 'dem', 'den', 'des', 'ein',
            'eine', 'einer', 'einem', 'einen', 'eines'
        ];

        while (true) {
            $truncated = rtrim($truncated, " \t\n\r\0\x0B,.:;-_/\\|&+( )[ ]{ }");
            $words = explode(' ', $truncated);
            if (empty($words)) {
                break;
            }
            $lastWord = mb_strtolower(end($words));
            if (in_array($lastWord, $invalidTrailingWords, true)) {
                array_pop($words);
                $truncated = implode(' ', $words);
            } else {
                break;
            }
        }

        return $truncated . '...';
    };

    $pageTitle = $facility->source_id === 'faehrmann-pflege-gmbh-16278-ade0d833b0'
        ? 'Fährmann Pflege Angermünde: Leistungen, Tagespflege & Wohnen'
        : "{$facility->name} in {$city->name} – PflegeIndex";
    $pageDescription = $cleanAndLimitDescription($rawDescription, 155);

    $mapQuery = rawurlencode("{$facility->name}, {$facility->address}, {$facility->postal_code} {$city->name}, Deutschland");
    $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query='.$mapQuery;
    $correctionMailto = 'mailto:info@pflegeindex.com?subject='.rawurlencode('Datenfehler PflegeIndex')
        .'&body='.rawurlencode("Einrichtung: {$facility->name}\nSeite: {$canonicalUrl}\n\nHinweis:\n");
    $quality = \App\Projects\PflegeIndex\Trust\FacilityDataQuality::evaluate(
        facility: $facility,
        city: $city,
        canonicalUrl: $canonicalUrl,
        website: $displayWebsite,
        hasDescription: $editorialView !== null || filled($facility->description),
    );
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', $canonicalUrl)
@section('bodyClass', 'facility-seo-page')

@push('head')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="PflegeIndex">
    <meta property="og:locale" content="de_DE">
    @php
        $localBusinessSchema = array_filter([
            '@type' => 'LocalBusiness',
            'name' => $facility->name,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
            'telephone' => filled($facility->phone) ? $facility->phone : null,
            'email' => filled($facility->email) ? $facility->email : null,
            'sameAs' => $displayWebsite ? [$displayWebsite] : null,
            'hasOfferCatalog' => $editorialView ? [
                '@type' => 'OfferCatalog',
                'name' => 'Pflege- und Wohnangebote',
                'itemListElement' => collect([
                    'Häusliche Krankenpflege',
                    'Senioren-Tagespflege',
                    'Service-Wohnen',
                    'Betreutes Wohnen',
                    'Pflegeberatung',
                ])->map(static fn (string $service): array => [
                    '@type' => 'Offer',
                    'itemOffered' => ['@type' => 'Service', 'name' => $service],
                ])->all(),
            ] : null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $facility->address,
                'postalCode' => $facility->postal_code,
                'addressLocality' => $city->name,
                'addressRegion' => 'Brandenburg',
                'addressCountry' => 'DE',
            ],
        ], fn ($value) => $value !== null);
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                $localBusinessSchema,
                [
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
                            'name' => $city->state,
                            'item' => route('region.show'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $city->name,
                            'item' => route('cities.show', $city),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 4,
                            'name' => $facility->name,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <section class="page-hero" style="padding-top:30px;padding-bottom:30px">
        <div class="container">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumbs" style="margin:0">
                    <li><a href="{{ route('home') }}">Startseite</a></li>
                    <li><span aria-hidden="true">›</span><a href="{{ route('region.show') }}">{{ $city->state }}</a></li>
                    @if($city->geoMunicipality?->district)
                        <li><span aria-hidden="true">›</span><a href="{{ route('districts.show', $city->geoMunicipality->district->slug) }}">{{ $city->geoMunicipality->district->display_name }}</a></li>
                    @endif
                    <li><span aria-hidden="true">›</span><a href="{{ route('cities.show', $city) }}">{{ $city->name }}</a></li>
                    <li aria-current="page"><span aria-hidden="true">›</span><span>{{ $facility->name }}</span></li>
                </ol>
            </nav>

        </div>
    </section>
    <div class="container detail-layout">
        <div class="detail-main">
            <div class="detail-heading">
                <div><span class="type-badge">{{ $facility->type }}</span><span class="source-badge">Amtliche Grunddaten</span></div>
                <h1>{{ $facility->name }}</h1>
                @if($hasDirectContact)
                    <nav class="mobile-contact-actions" aria-label="Schnellkontakt">
                        @if($facility->phone)<a href="tel:{{ $facility->phone }}">Anrufen</a>@endif
                        @if($displayWebsite)<a href="{{ $displayWebsite }}" target="_blank" rel="noopener">Website</a>@endif
                        @if($facility->email)<a href="mailto:{{ $facility->email }}">E-Mail</a>@endif
                    </nav>
                @endif
                @include('facilities._quality', ['quality' => $quality])
                <p class="detail-address"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg> {{ $facility->address }}, {{ $facility->postal_code }} {{ $city->name }}</p>
                <p class="source-explanation">Die amtlichen Grunddaten stammen vom LASV; Kontaktdaten und Beschreibungen können redaktionell ergänzt sein.</p>
            </div>
            <section class="detail-section">
                <h2>{{ $editorialView ? 'Fährmann Pflege in Angermünde: Leistungen und Wohnangebote' : 'Über diese Einrichtung' }}</h2>
                @if($editorialView && view()->exists($editorialView))
                    @include($editorialView)
                @elseif(filled($facility->description))
                    <p>{!! nl2br(e($facility->description)) !!}</p>
                @else
                    <p>
                        @php
                            $sectorMap = [
                                'Ambulante Pflegeeinrichtung' => 'eine ambulante Pflegeeinrichtung',
                                'Stationäre/teilstationäre Pflegeeinrichtung' => 'eine stationäre oder teilstationäre Pflegeeinrichtung',
                                'Krankenhaus' => 'ein Krankenhaus',
                            ];
                        @endphp
                        @if(filled($facility->source_sector) && isset($sectorMap[$facility->source_sector]))
                            {{ $facility->name }} in {{ $city->name }} ist {{ $sectorMap[$facility->source_sector] }} im amtlichen Einrichtungsverzeichnis des Landes Brandenburg.
                        @else
                            {{ $facility->name }} in {{ $city->name }} ist als „{{ $facility->type }}“ im amtlichen Einrichtungsverzeichnis des Landes Brandenburg geführt.
                        @endif

                        @if(!empty($facility->care_types))
                            Die Einrichtung bietet folgende Pflegeleistungen: {{ implode(', ', $facility->care_types) }}.
                        @endif
                        Adresse: {{ $facility->address }}, {{ $facility->postal_code }} {{ $city->name }}.
                    </p>
                @endif
                @if(!$editorialView && $facility->description_checked_at && !empty($facility->description_sources))
                    <div class="description-sources">
                        <strong>Quellen der Beschreibung:</strong>
                        @foreach($facility->description_sources as $source)
                            @php
                                $sourceHost = parse_url($source, PHP_URL_HOST) ?: 'Quelle öffnen';
                            @endphp
                            <a href="{{ $source }}" target="_blank" rel="noopener nofollow">{{ $sourceHost }}</a>@if(!$loop->last)<span>·</span>@endif
                        @endforeach
                        <small>Geprüft am {{ $facility->description_checked_at->format('d.m.Y') }}</small>
                    </div>
                @endif
                <div class="notice notice--compact"><strong>Amtliche Grunddaten:</strong> Landesamt für Soziales und Versorgung Brandenburg, Stand 31.12.2025. Veröffentlicht unter Datenlizenz Deutschland – Zero – Version 2.0.</div>
            </section>
            <section class="detail-section"><h2>Einrichtungsart</h2><div class="check-grid">@foreach($facility->care_types ?? [$facility->type] as $careType)<span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3 3 7-7"/></svg>{{ $careType }}</span>@endforeach</div></section>
            @include('facilities._content')
        </div>
        <aside class="contact-card">
            <span class="contact-card__label">Kontakt</span>
            @if($hasDirectContact)
                @if($facility->phone)
                    <h2 class="contact-phone"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.3 3.5 10 7.8 8.1 10a15.7 15.7 0 0 0 5.9 5.9l2.2-1.9 4.3 2.7-.7 3.2c-.2.8-.9 1.3-1.7 1.3A15.3 15.3 0 0 1 2.8 5.9c0-.8.5-1.5 1.3-1.7l3.2-.7Z"/></svg><a href="tel:{{ $facility->phone }}">{{ $facility->formattedPhone() }}</a></h2>
                    <a class="contact-button" href="tel:{{ $facility->phone }}">Jetzt anrufen</a>
                @else
                    <h2 class="contact-phone contact-phone--pending">Kontakt zur Einrichtung</h2>
                @endif
                @if($facility->email)<a class="contact-secondary" href="mailto:{{ $facility->email }}">E-Mail senden</a>@endif
                @if($displayWebsite)<a class="contact-secondary" href="{{ $displayWebsite }}" target="_blank" rel="noopener">Website öffnen</a>@endif
                @if($facility->contact_status === 'verified' && $facility->contact_checked_at !== null && filled($facility->contact_source) && (filled($facility->phone) || filled($facility->email) || filled($facility->website)))
                    <small>
                        Kontaktdaten geprüft am {{ $facility->contact_checked_at->format('d.m.Y') }}
                        <br>
                        Quelle: 
                        @if(filter_var($facility->contact_source, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $facility->contact_source))
                            <a href="{{ $facility->contact_source }}" target="_blank" rel="nofollow noopener noreferrer">Website des Anbieters</a>
                        @else
                            Website des Anbieters
                        @endif
                    </small>
                @endif
            @else
                <h2 class="contact-phone contact-phone--pending">Kontakt</h2>
                <p style="margin-top:10px;margin-bottom:18px;font-size:14px;color:rgba(255,255,255,.82)">Für diese Einrichtung liegen derzeit keine direkten Kontaktdaten vor.</p>
                @php
                    $contactMailto = 'mailto:info@pflegeindex.com?subject=' . rawurlencode("Kontaktdaten ergänzen: {$facility->name}")
                        . '&body=' . rawurlencode("Einrichtung: {$facility->name}\nSeite: {$canonicalUrl}\n\nNeue Kontaktdaten (Telefon, E-Mail, Website):\n");
                @endphp
                <a class="contact-button" href="{{ $contactMailto }}">Kontaktdaten ergänzen</a>
            @endif
            <a class="contact-route" href="{{ $googleMapsUrl }}" target="_blank" rel="noopener noreferrer">In Google Maps öffnen</a>
            <a class="contact-report" href="{{ $correctionMailto }}">Datenfehler melden</a>
        </aside>
    </div>
    @if($relatedFacilities->isNotEmpty())
        <section class="section section--white" id="related-facilities" aria-labelledby="related-facilities-title">
            <div class="container">
                <div class="section-heading">
                    <h2 id="related-facilities-title">Weitere Pflegeeinrichtungen in {{ $city->name }}</h2>
                </div>
                <div class="results-list">
                    @foreach($relatedFacilities as $relatedFacility)
                        @include('facilities._card', ['facility' => $relatedFacility])
                    @endforeach
                </div>
                <p style="margin:24px 0 0"><a class="text-link" href="{{ route('cities.show', $city) }}">Alle Pflegeeinrichtungen in {{ $city->name }} ansehen</a></p>
            </div>
        </section>
    @endif
@endsection
