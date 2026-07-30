@extends('layouts.admin')

@section('title', 'Datenqualität – PflegeIndex Verwaltung')

@section('content')
    <main class="container admin-main">
        <div class="admin-title">
            <div><h1>Data Quality Dashboard</h1><p>Prioritäten für die nächste manuelle Datenprüfung.</p></div>
            <a class="primary-button" href="{{ route('admin.facilities.index', ['status' => 'unverified']) }}">Prüfung starten</a>
        </div>

        <section aria-labelledby="overview-title">
            <h2 class="admin-section-title" id="overview-title">Übersicht</h2>
            <div class="admin-grid admin-quality-stats">
                <div class="admin-stat"><strong>{{ number_format($overview['total_facilities'], 0, ',', '.') }}</strong><span>Einrichtungen</span></div>
                <div class="admin-stat"><strong>{{ number_format($overview['verified_count'], 0, ',', '.') }}</strong><span>Geprüft</span></div>
                <div class="admin-stat"><strong>{{ number_format($overview['unverified_count'], 0, ',', '.') }}</strong><span>Offen</span></div>
                <div class="admin-stat admin-quality-progress">
                    <strong>{{ number_format($overview['verified_percentage'], 0, ',', '.') }} %</strong><span>Fortschritt</span>
                    <div class="admin-quality-progress__bar" role="progressbar" aria-label="Prüffortschritt" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $overview['verified_percentage'] }}"><span style="width:{{ $overview['verified_percentage'] }}%"></span></div>
                    <small>{{ number_format($overview['verified_count'], 0, ',', '.') }} von {{ number_format($overview['total_facilities'], 0, ',', '.') }} Einrichtungen geprüft</small>
                </div>
            </div>
        </section>

        <section class="admin-panel admin-open-tasks" aria-labelledby="open-tasks-title">
            <h2 id="open-tasks-title">Offene Aufgaben</h2>
            <div class="admin-task-grid">
                @foreach($open_tasks as $task)
                    @if($task['count'] > 0)
                        <a class="admin-task-card" href="{{ route('admin.facilities.index', ['missing' => $task['key']]) }}">
                            <strong>{{ $task['label'] }}</strong>
                            <span>{{ number_format($task['count'], 0, ',', '.') }} {{ strtolower($task['description']) }}</span>
                            <b>Jetzt prüfen</b>
                        </a>
                    @else
                        <div class="admin-task-card admin-task-card--empty">
                            <strong>{{ $task['label'] }}</strong>
                            <span>Keine offenen Aufgaben</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="admin-next-step" aria-labelledby="next-step-title">
            <div><h2 id="next-step-title">Nächster Schritt</h2>
                @if($next_task)
                    <p>{{ number_format($next_task['count'], 0, ',', '.') }} {{ strtolower($next_task['description']) }}</p><small>Beginnen Sie mit dem größten offenen Datenbestand.</small>
                @else
                    <p>Für Telefon, E-Mail und Website sind aktuell keine offenen Aufgaben vorhanden.</p>
                @endif
            </div>
            @if($next_task)
                <a class="primary-button" href="{{ route('admin.facilities.index', ['missing' => $next_task['key']]) }}">{{ $next_task['action'] }}</a>
            @endif
        </section>

        <section class="admin-panel admin-quality-summary" aria-labelledby="quality-summary-title">
            <h2 id="quality-summary-title">Datenqualität</h2>
            <strong>{{ number_format($overview['average_score'], 0, ',', '.') }} %</strong>
            <p>Durchschnittlicher Quality Score</p>
            <small>Der Quality Score beschreibt die Vollständigkeit und Prüfbarkeit der gespeicherten Daten.</small>
        </section>

        <section class="admin-panel" aria-labelledby="city-priority-title">
            <h2 id="city-priority-title">Städte mit Verbesserungsbedarf</h2>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Stadt</th><th>Einrichtungen</th><th>Durchschnittlicher Score</th><th>Geprüft</th><th>Offen</th><th>Geprüft %</th><th>Priorität</th><th>Aktion</th></tr></thead><tbody>
                @forelse($cities as $city)
                    <tr><td>{{ $city['name'] }}</td><td>{{ number_format($city['total_facilities'], 0, ',', '.') }}</td><td>{{ number_format($city['average_score'], 0, ',', '.') }} %</td><td>{{ number_format($city['verified_count'], 0, ',', '.') }}</td><td>{{ number_format($city['unverified_count'], 0, ',', '.') }}</td><td>{{ number_format($city['verified_percentage'], 0, ',', '.') }} %</td><td><span class="admin-priority admin-priority--{{ strtolower($city['priority']) }}">{{ $city['priority'] }}</span></td><td><a href="{{ route('admin.facilities.index', ['city' => $city['city_id']]) }}">Einrichtungen prüfen</a></td></tr>
                @empty
                    <tr><td colspan="8">Keine Städte gefunden.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="admin-panel">
            <h2>Einrichtungen mit Verbesserungsbedarf</h2>
            <form method="get" class="admin-filter admin-filter--quality">
                <select name="city" aria-label="Stadt"><option value="">Alle Städte</option>@foreach($cities_filter as $city)<option value="{{ $city['id'] }}" @selected($filters['city'] === (int) $city['id'])>{{ $city['name'] }}</option>@endforeach</select>
                <select name="status" aria-label="Kontaktstatus"><option value="">Alle Status</option><option value="verified" @selected($filters['status'] === 'verified')>Geprüft</option><option value="unverified" @selected($filters['status'] === 'unverified')>Offen</option><option value="pending" @selected($filters['status'] === 'pending')>In Prüfung</option><option value="not_found" @selected($filters['status'] === 'not_found')>Nicht gefunden</option></select>
                <input type="number" name="max_score" min="0" max="100" placeholder="Max. Datenqualität" value="{{ $filters['max_score'] ?? '' }}">
                <label><input type="checkbox" name="missing_phone" value="1" @checked($filters['missing_phone'])> ohne Telefon</label>
                <label><input type="checkbox" name="missing_email" value="1" @checked($filters['missing_email'])> ohne E-Mail</label>
                <label><input type="checkbox" name="missing_website" value="1" @checked($filters['missing_website'])> ohne Website</label>
                <button class="primary-button" type="submit">Filtern</button>
            </form>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Einrichtung</th><th>Stadt</th><th>Datenqualität</th><th>Kontaktstatus</th><th>Fehlende Daten</th><th>Aktion</th></tr></thead><tbody>
                @forelse($facilities as $facility)
                    <tr><td>{{ $facility['name'] }}</td><td>{{ $facility['city_name'] }}</td><td>{{ $facility['score'] }} %</td><td><span class="status-pill status-pill--{{ $facility['status'] ?: 'missing' }}">{{ $facility['status_label'] }}</span></td><td>@if($facility['missing'] === []) – @else {{ implode(', ', $facility['missing']) }} @endif</td><td><a href="{{ route('admin.facilities.edit', $facility['id']) }}">Bearbeiten</a></td></tr>
                @empty
                    <tr><td colspan="6">Keine Einrichtungen entsprechen den ausgewählten Filtern.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
@endsection
