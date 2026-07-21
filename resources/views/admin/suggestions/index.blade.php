@extends('layouts.admin')

@section('title', 'Kontaktprüfung – PflegeIndex Verwaltung')

@section('content')
    <main class="container admin-main">
        <div class="admin-title"><div><h1>Kontaktprüfung</h1><p>{{ number_format($suggestions->total(), 0, ',', '.') }} Ergebnisse in dieser Auswahl.</p></div></div>

        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
        @if($summary = session('import_summary'))
            <div class="admin-alert">Import abgeschlossen: {{ $summary['created'] }} neu, {{ $summary['updated'] }} aktualisiert, {{ $summary['unknown'] }} unbekannte Einrichtungen. {{ $summary['rejected_urls'] }} ungültige URLs wurden verworfen. {{ $summary['pending'] }} Ergebnisse warten insgesamt auf Prüfung.</div>
        @endif
        @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form class="admin-upload" method="post" action="{{ route('admin.suggestions.upload') }}" enctype="multipart/form-data">
            @csrf
            <div><h2>Parser-Ergebnisse importieren</h2><p>JSON-Datei auswählen. Der Import startet keinen Tavily-Aufruf und erstellt keine doppelten Vorschläge.</p><input name="results_file" type="file" accept="application/json,.json" required></div>
            <button class="primary-button" type="submit">Datei importieren</button>
        </form>

        <form class="admin-filter" method="get" action="{{ route('admin.suggestions.index') }}">
            <select name="decision" aria-label="Entscheidung"><option value="">Alle Entscheidungen</option><option value="pending" @selected($decision === 'pending')>Zu prüfen</option><option value="accepted" @selected($decision === 'accepted')>Angenommen</option><option value="rejected" @selected($decision === 'rejected')>Abgelehnt</option></select>
            <select name="parser_status" aria-label="Parser-Ergebnis"><option value="">Alle Parser-Ergebnisse</option><option value="verified" @selected($parserStatus === 'verified')>Kontakt gefunden</option><option value="not_found" @selected($parserStatus === 'not_found')>Nicht gefunden</option></select>
            <button class="primary-button" type="submit">Filtern</button>
        </form>

        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Einrichtung</th><th>Parser-Ergebnis</th><th>Vorschlag</th><th>Entscheidung</th><th></th></tr></thead><tbody>
            @forelse($suggestions as $suggestion)
                @php
                    $searchQuery = '"'.$suggestion->facility->name.'" '.$suggestion->facility->postal_code.' '.$suggestion->facility->city->name.' offizielle Website';
                    $searchUrl = 'https://www.google.com/search?q='.rawurlencode($searchQuery);
                    $currentContacts = collect([
                        $suggestion->facility->formattedPhone(),
                        $suggestion->facility->email,
                        $suggestion->facility->website,
                    ])->filter();
                @endphp
                <tr>
                    <td>
                        <strong>{{ $suggestion->facility->name }}</strong>
                        <small>{{ $suggestion->facility->postal_code }} {{ $suggestion->facility->city->name }}</small>
                        @if($currentContacts->isNotEmpty())
                            <small>Aktuell: {{ $currentContacts->join(' · ') }}</small>
                        @endif
                    </td>
                    <td>{{ $suggestion->parserStatusLabel() }}@if($suggestion->confidence !== null)<small>Sicherheit: {{ $suggestion->confidence }} %</small>@endif</td>
                    <td>{{ $suggestion->phone ?? 'Kein Telefon' }}@if($suggestion->email)<small>{{ $suggestion->email }}</small>@endif</td>
                    <td><span class="status-pill status-pill--{{ $suggestion->decision }}">{{ $suggestion->decisionLabel() }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.suggestions.show', $suggestion) }}">Prüfen</a>
                            <a class="table-action-muted" href="{{ $searchUrl }}" target="_blank" rel="noopener">Website suchen ↗</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Keine Ergebnisse für diesen Filter.</td></tr>
            @endforelse
        </tbody></table></div>
        <x-pagination :paginator="$suggestions" />
    </main>
@endsection
