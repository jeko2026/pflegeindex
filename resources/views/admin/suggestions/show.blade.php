@extends('layouts.admin')

@section('title', 'Kontaktvorschlag prüfen – PflegeIndex Verwaltung')

@section('content')
    @php
        $facility = $suggestion->facility;
        $searchQuery = '"'.$facility->name.'" '.$facility->postal_code.' '.$facility->city->name.' offizielle Website';
        $searchUrl = 'https://www.google.com/search?q='.rawurlencode($searchQuery);
    @endphp
    <main class="container admin-main">
        <div class="admin-title">
            <div><p><a href="{{ route('admin.suggestions.index') }}">← Kontaktprüfung</a></p><h1>{{ $facility->name }}</h1><p>{{ $facility->address }}, {{ $facility->postal_code }} {{ $facility->city->name }}</p></div>
            <div class="admin-title__actions">
                <a class="admin-secondary-button" href="{{ route('admin.facilities.edit', ['facility' => $facility, 'suggestion' => $suggestion->id]) }}">Kontakte bearbeiten</a>
                <a class="primary-button" href="{{ route('facilities.show', [$facility->city, $facility]) }}" target="_blank" rel="noopener">Öffentliche Seite</a>
            </div>
        </div>

        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif

        <section class="admin-research">
            <div><strong>Offizielle Kontaktdaten prüfen</strong><span>Suchen Sie nach der Website der Einrichtung und tragen Sie bestätigte Daten manuell ein.</span></div>
            <div class="admin-title__actions">
                <a class="admin-secondary-button" href="{{ $searchUrl }}" target="_blank" rel="noopener">Website suchen ↗</a>
                <a class="primary-button" href="{{ route('admin.facilities.edit', ['facility' => $facility, 'suggestion' => $suggestion->id]) }}">Kontakte eintragen</a>
            </div>
        </section>

        <div class="comparison-grid">
            <section class="comparison-card">
                <h2>Aktuell veröffentlicht</h2>
                <dl class="comparison-list">
                    <dt>Telefon</dt><dd>{{ $suggestion->facility->formattedPhone() ?? '–' }}</dd>
                    <dt>E-Mail</dt><dd>{{ $suggestion->facility->email ?? '–' }}</dd>
                    <dt>Website</dt><dd>{{ $suggestion->facility->website ?? '–' }}</dd>
                    <dt>Status</dt><dd>{{ $suggestion->facility->contactStatusLabel() }}</dd>
                </dl>
            </section>
            <section class="comparison-card">
                <h2>Vorschlag des Parsers</h2>
                <dl class="comparison-list">
                    <dt>Ergebnis</dt><dd>{{ $suggestion->parserStatusLabel() }}</dd>
                    <dt>Telefon</dt><dd>{{ $suggestion->phone ?? '–' }}</dd>
                    <dt>E-Mail</dt><dd>{{ $suggestion->email ?? '–' }}</dd>
                    <dt>Website</dt><dd>{{ $suggestion->website ?? '–' }}</dd>
                    <dt>Sicherheit</dt><dd>{{ $suggestion->confidence !== null ? $suggestion->confidence.' %' : '–' }}</dd>
                    <dt>Geprüft</dt><dd>{{ $suggestion->checked_at?->format('d.m.Y H:i') ?? '–' }}</dd>
                </dl>
            </section>
        </div>

        <section class="admin-panel">
            <h2>Quellen</h2>
            <ul class="source-list">
                @if($suggestion->phone_source)<li>Telefon: <a href="{{ $suggestion->phone_source }}" target="_blank" rel="noopener">{{ $suggestion->phone_source }}</a></li>@endif
                @if($suggestion->email_source && $suggestion->email_source !== $suggestion->phone_source)<li>E-Mail: <a href="{{ $suggestion->email_source }}" target="_blank" rel="noopener">{{ $suggestion->email_source }}</a></li>@endif
                @foreach($suggestion->safePagesChecked() as $page)
                    @if($page !== $suggestion->phone_source && $page !== $suggestion->email_source)<li><a href="{{ $page }}" target="_blank" rel="noopener">{{ $page }}</a></li>@endif
                @endforeach
                @if(!$suggestion->phone_source && !$suggestion->email_source && $suggestion->safePagesChecked() === [])<li>Keine verifizierte Quelle gefunden.</li>@endif
            </ul>
        </section>

        @if($suggestion->decision === 'pending')
            @if($suggestion->parser_status === 'not_found')
                <p class="admin-note">Mit der Bestätigung wird diese Einrichtung als „Kontakt nicht gefunden“ markiert. Bereits vorhandene Kontakte werden dabei nicht gelöscht.</p>
            @endif
            <div class="review-actions">
                <form method="post" action="{{ route('admin.suggestions.accept', $suggestion) }}">@csrf<button class="primary-button" type="submit">{{ $suggestion->parser_status === 'verified' ? 'Kontaktdaten übernehmen' : 'Als „nicht gefunden“ bestätigen' }}</button></form>
                <form method="post" action="{{ route('admin.suggestions.reject', $suggestion) }}">@csrf<button class="danger-button" type="submit">Parser-Ergebnis ablehnen</button></form>
            </div>
        @else
            <div class="admin-alert">Entscheidung: {{ $suggestion->decisionLabel() }}@if($suggestion->reviewed_at) am {{ $suggestion->reviewed_at->format('d.m.Y H:i') }}@endif.</div>
        @endif
    </main>
@endsection
