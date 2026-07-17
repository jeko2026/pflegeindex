@extends('layouts.app')

@section('title', 'Über PflegeIndex')
@section('description', 'Informationen über PflegeIndex, Datenquellen und redaktionelle Grundsätze.')

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Über uns</span></p><h1>Über PflegeIndex</h1><p class="page-hero__lead">Ein übersichtliches, unabhängiges Verzeichnis für Pflegeangebote.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <section class="detail-section"><h2>Unser Ziel</h2><p>PflegeIndex.com soll Pflegebedürftigen, Angehörigen und Interessierten helfen, Pflegeangebote leichter zu finden. Angaben werden verständlich dargestellt und nach Ort sowie Einrichtungsart erschlossen.</p></section>
            <section class="detail-section"><h2>Datenquellen</h2><p>Die Basisdaten stammen aus öffentlichen Verzeichnissen, derzeit insbesondere vom Landesamt für Soziales und Versorgung Brandenburg. Ergänzende Kontaktdaten werden anhand öffentlich zugänglicher offizieller Internetseiten geprüft.</p></section>
            <section class="detail-section"><h2>Unabhängigkeit</h2><p>PflegeIndex.com ist nicht mit den gelisteten Einrichtungen, Pflegekassen oder Behörden verbunden. Eine Aufnahme in das Verzeichnis stellt keine Empfehlung oder Qualitätsbewertung dar.</p></section>
            <section class="detail-section"><h2>Aktualität und Korrekturen</h2><p>Pflegeangebote und Ansprechpartner können sich ändern. Hinweise auf fehlerhafte Angaben können an <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> gesendet werden und werden vor einer Änderung geprüft.</p></section>
            <section class="detail-section"><h2>Wichtiger Hinweis</h2><p>PflegeIndex.com bietet allgemeine Informationen und ersetzt keine medizinische, pflegerische, rechtliche oder finanzielle Beratung.</p></section>
        </div>
    </div>
@endsection
