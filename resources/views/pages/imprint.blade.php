@extends('layouts.app')

@section('title', 'Impressum – PflegeIndex')
@section('description', 'Impressum von PflegeIndex.com.')

@push('head')<meta name="robots" content="noindex,nofollow">@endpush

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Impressum</span></p><h1>Impressum</h1><p class="page-hero__lead">Anbieterkennzeichnung für PflegeIndex.com.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <section class="detail-section"><h2>Angaben gemäß § 5 DDG</h2><p><strong>PflegeIndex.com</strong> – Projektbezeichnung<br>Inhaber: Yevhenii V.<br>Herojiv Dnipra Str. 33<br>39800 Horischni Plavni<br>Ukraine</p></section>
            <section class="detail-section"><h2>Kontakt</h2><p>E-Mail: <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a></p></section>
            <section class="detail-section"><h2>Verantwortlich für den Inhalt</h2><p>Yevhenii V.<br>Herojiv Dnipra Str. 33<br>39800 Horischni Plavni<br>Ukraine</p></section>
            <section class="detail-section"><h2>Art des Angebots</h2><p>PflegeIndex ist ein unabhängiges Informationsverzeichnis für Pflegeangebote. Das Verzeichnis ist kein amtliches Register und keine Qualitätsbewertung der aufgeführten Einrichtungen.</p></section>
            <section class="detail-section"><h2>Hinweise zu den Verzeichnisdaten</h2><p>Die Basisdaten stammen aus öffentlichen Verzeichnissen, insbesondere vom Landesamt für Soziales und Versorgung Brandenburg. Hinweise auf fehlerhafte Angaben senden Sie bitte an <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a>.</p></section>
        </div>
    </div>
@endsection
