@extends('layouts.app')

@section('title', 'Seite nicht gefunden – PflegeIndex')
@section('description', 'Die angeforderte Seite wurde nicht gefunden.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">Fehler 404</p>
            <h1>Seite nicht gefunden</h1>
            <p class="page-hero__lead">Die angeforderte Seite existiert nicht oder wurde verschoben.</p>
            <div class="empty-state__actions">
                <a class="primary-button" href="{{ route('directory.index') }}">Pflegeeinrichtungen suchen</a>
                <a class="secondary-button" href="{{ route('home') }}">Zur Startseite</a>
            </div>
        </div>
    </section>
@endsection
