<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Pflegeheime und Pflegedienste mit PflegeIndex finden.')">
    <meta name="theme-color" content="#163a63">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <title>@yield('title', 'PflegeIndex – Pflege einfach finden')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/styles.css') }}?v=20260718-1">
    @stack('head')
    <script defer src="{{ asset('assets/app.js') }}"></script>
</head>
<body class="@yield('bodyClass')">
    <div class="demo-strip">Offizielle Basisdaten des LASV Brandenburg · Datenstand 31.12.2025 · Kontaktangaben werden ergänzt.</div>
    <header class="site-header">
        <div class="container header-inner">
            <a class="site-logo" href="{{ route('home') }}" aria-label="PflegeIndex Startseite">
                <img src="{{ asset('logo.svg') }}" alt="PflegeIndex" width="205" height="45">
            </a>
            <button class="nav-toggle" type="button" aria-label="Navigation öffnen" aria-expanded="false" data-nav-toggle><span></span><span></span><span></span></button>
            <nav class="main-nav" data-nav aria-label="Hauptnavigation">
                <a href="{{ route('directory.index') }}" @if(request()->routeIs('directory.*', 'cities.*', 'facilities.*')) aria-current="page" @endif>Pflege finden</a>
                <a href="{{ route('region.show') }}" @if(request()->routeIs('region.*')) aria-current="page" @endif>Brandenburg</a>
                <a href="{{ route('lexicon.index') }}" @if(request()->routeIs('lexicon.*')) aria-current="page" @endif>Pflegelexikon</a>
                <a href="{{ route('home') }}#so-funktioniert-es">So funktioniert es</a>
                <a class="header-cta" href="{{ route('directory.index') }}">Suche starten</a>
            </nav>
        </div>
    </header>

    <main>@yield('content')</main>

    <footer class="site-footer">
        <div class="container footer-main">
            <div><img src="{{ asset('logo-light.svg') }}" alt="PflegeIndex"><p>Das unabhängige Verzeichnis für Pflegeangebote in Deutschland – aktuell in Entwicklung.</p></div>
            <div class="footer-column">
                <strong>Pflege finden</strong>
                <a href="{{ route('directory.index', ['type' => 'Stationäre/teilstationäre Pflege']) }}">Stationäre Pflege</a>
                <a href="{{ route('directory.index', ['type' => 'Ambulante Pflege']) }}">Ambulante Pflege</a>
                <a href="{{ route('directory.index', ['type' => 'Krankenhaus']) }}">Krankenhäuser</a>
            </div>
            <div class="footer-column">
                <strong>PflegeIndex</strong>
                <a href="{{ route('lexicon.index') }}">Pflegelexikon</a>
                <a href="{{ route('pages.about') }}">Über uns</a>
                <a href="{{ route('region.show') }}">Brandenburg</a>
                <a href="mailto:info@pflegeindex.com">Kontakt</a>
                <a href="{{ route('pages.imprint') }}">Impressum</a>
                <a href="{{ route('pages.privacy') }}">Datenschutz</a>
            </div>
        </div>
        <div class="container footer-bottom"><span>© PflegeIndex.com · Alle Rechte vorbehalten</span><span>Basisdaten: LASV Brandenburg · DL-DE Zero 2.0</span></div>
    </footer>
</body>
</html>
