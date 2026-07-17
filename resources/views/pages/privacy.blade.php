@extends('layouts.app')

@section('title', 'Datenschutz – PflegeIndex')
@section('description', 'Datenschutzerklärung von PflegeIndex.com.')

@push('head')<meta name="robots" content="noindex,nofollow">@endpush

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Datenschutz</span></p><h1>Datenschutz</h1><p class="page-hero__lead">Informationen zur Verarbeitung personenbezogener Daten.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <div class="notice legal-warning"><strong>Entwurf:</strong> Betreiberanschrift und Hostinganbieter müssen vor der Veröffentlichung ergänzt werden.</div>
            <section class="detail-section"><h2>1. Verantwortlicher</h2><p><strong>PflegeIndex.com</strong> – Projektbezeichnung<br>Verantwortlicher: <span class="legal-placeholder">[Name oder Firma ergänzen]</span><br>Anschrift: <span class="legal-placeholder">[vollständige Anschrift ergänzen]</span><br>E-Mail: <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a></p></section>
            <section class="detail-section"><h2>2. Hosting und Server-Protokolle</h2><p>Beim Aufruf der veröffentlichten Website kann der Hostinganbieter technisch erforderliche Daten verarbeiten, insbesondere IP-Adresse, Zeitpunkt, aufgerufene Datei, Browserinformationen und Referrer. Hostinganbieter und Speicherdauer werden nach Auswahl des Hostings ergänzt.</p></section>
            <section class="detail-section"><h2>3. Kontakt per E-Mail</h2><p>Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir Ihre Angaben zur Bearbeitung der Anfrage. Die Daten werden gelöscht, sobald die Anfrage abgeschlossen ist und keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p></section>
            <section class="detail-section"><h2>4. Verzeichnis- und Kontaktdaten</h2><p>PflegeIndex.com veröffentlicht Angaben aus öffentlichen Verzeichnissen und von öffentlich zugänglichen offiziellen Internetseiten. Betroffene können Berichtigung oder Löschung unter <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> verlangen.</p></section>
            <section class="detail-section"><h2>5. Cookies und Reichweitenmessung</h2><p>Die aktuelle Version setzt keine nicht notwendigen Cookies ein und verwendet keine Analyse-, Werbe- oder Trackingdienste. Externe Webfonts werden nicht geladen.</p></section>
        </div>
    </div>
@endsection
