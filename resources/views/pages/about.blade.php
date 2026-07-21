@extends('layouts.app')

@section('title', 'Über das Projekt – PflegeIndex')
@section('description', 'Informationen über das unabhängige Informationsverzeichnis PflegeIndex.')

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Über das Projekt</span></p><h1>Über das Projekt</h1><p class="page-hero__lead">PflegeIndex ist ein unabhängiges Informationsverzeichnis.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <section class="detail-section"><h2>Unabhängiges Informationsangebot</h2><p>PflegeIndex ist ein unabhängiges Informationsverzeichnis. Die Website verfolgt derzeit keine kommerziellen Ziele.</p></section>
            <section class="detail-section"><h2>Datenquellen und Aktualität</h2><p>Die Daten wurden aus öffentlich zugänglichen Quellen zusammengetragen. PflegeIndex ist kein amtliches Register. Informationen können unvollständig oder veraltet sein.</p></section>
            <section class="detail-section"><h2>Wichtige Angaben bestätigen</h2><p>Bitte bestätigen Sie für Ihre Entscheidung wichtige Angaben, insbesondere Leistungen, Kosten, Verfügbarkeit und Kontaktdaten, direkt bei der jeweiligen Einrichtung. PflegeIndex ersetzt keine medizinische, pflegerische, rechtliche oder finanzielle Beratung und nimmt keine Qualitätsbewertung vor.</p></section>
            <section class="detail-section"><h2>Berichtigungen und Löschung</h2><p>Berichtigungen und die Löschung von Daten können per E-Mail an <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> angefragt werden.</p></section>
        </div>
    </div>
@endsection
