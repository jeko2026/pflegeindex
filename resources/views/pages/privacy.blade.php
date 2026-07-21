@extends('layouts.app')

@section('title', 'Datenschutz – PflegeIndex')
@section('description', 'Datenschutzerklärung von PflegeIndex.com.')

@push('head')<meta name="robots" content="noindex,nofollow">@endpush

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Datenschutz</span></p><h1>Datenschutz</h1><p class="page-hero__lead">Informationen zur Verarbeitung personenbezogener Daten.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <section class="detail-section"><h2>1. Verantwortlicher</h2><p><strong>PflegeIndex.com</strong> – Projektbezeichnung<br>Verantwortlicher: Yevhenii V.<br>Herojiv Dnipra Str. 33<br>39800 Horischni Plavni<br>Ukraine<br>E-Mail: <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a></p></section>
            <section class="detail-section"><h2>2. Hosting und Server-Protokolle</h2><p>Beim Aufruf der Website verarbeitet der Webserver technisch erforderliche Daten, insbesondere IP-Adresse, Zeitpunkt, aufgerufene Datei, Browserinformationen und Referrer. Diese Daten werden zur technischen Bereitstellung und Sicherheit der Website verarbeitet.</p></section>
            <section class="detail-section"><h2>3. Suche und Filter</h2><p>Bei der Nutzung von Suche und Filtern werden die eingegebenen Suchbegriffe und ausgewählten Filter als URL-Parameter an den Webserver übertragen. Sie werden zur Ausgabe der angeforderten Ergebnisse verarbeitet und können Bestandteil der Server-Protokolle sein.</p></section>
            <section class="detail-section"><h2>4. Kontakt per E-Mail</h2><p>Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir Ihre Angaben zur Bearbeitung der Anfrage. Die Daten werden gelöscht, sobald die Anfrage abgeschlossen ist und keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p></section>
            <section class="detail-section"><h2>5. Verzeichnis- und Kontaktdaten</h2><p>PflegeIndex.com veröffentlicht Angaben aus öffentlichen Verzeichnissen und von öffentlich zugänglichen offiziellen Internetseiten. Betroffene können Berichtigung oder Löschung unter <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> verlangen.</p></section>
            <section class="detail-section"><h2>6. Cookies und Sitzungen</h2><p>Die öffentlichen Verzeichnisseiten benötigen keine Sitzungscookies. Für den geschützten Verwaltungsbereich wird eine technisch notwendige Laravel-Sitzung verwendet. Die aktuelle Version verwendet keine Analyse-, Werbe- oder Trackingdienste.</p></section>
            <section class="detail-section"><h2>7. Externe Inhalte und Links</h2><p>Die regulären öffentlichen Seiten laden Stylesheets, JavaScript und Schriftarten lokal und betten keine Karten ein. Der technische Status-Endpunkt <code>/up</code> verwendet die von Laravel bereitgestellte Statusansicht; dabei können Schriftarten von Bunny Fonts und JavaScript von jsDelivr geladen werden. Links zu Karten, Quellen und Websites von Einrichtungen stellen erst nach dem Anklicken eine Verbindung zum jeweiligen externen Anbieter her.</p></section>
        </div>
    </div>
@endsection
