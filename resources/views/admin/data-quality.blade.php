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

        @php
            $activeQuery = array_filter([
                'task' => $filters['task'],
                'city' => $filters['city'],
                'status' => $filters['status'],
                'max_score' => $filters['max_score'],
                'sort' => $filters['sort'],
                'direction' => $filters['direction'],
            ], fn ($value) => $value !== null && $value !== '' && $value !== false);
            $withoutCityQuery = $activeQuery;
            unset($withoutCityQuery['city']);
            $qualityDirection = $filters['sort'] === 'quality_score' && $filters['direction'] === 'asc' ? 'desc' : 'asc';
            $cityDirection = $filters['sort'] === 'city' && $filters['direction'] === 'asc' ? 'desc' : 'asc';
            $filterChips = [];
            if ($active_city_name) {
                $query = $activeQuery;
                unset($query['city']);
                $filterChips[] = ['label' => 'Stadt: '.$active_city_name, 'query' => $query];
            }
            if ($filters['status'] !== '') {
                $statusLabel = match ($filters['status']) {
                    'verified' => 'Geprüft',
                    'unverified' => 'Offen',
                    'pending' => 'In Prüfung',
                    'not_found' => 'Nicht gefunden',
                    default => $filters['status'],
                };
                $query = $activeQuery;
                unset($query['status']);
                $filterChips[] = ['label' => 'Status: '.$statusLabel, 'query' => $query];
            }
            if ($filters['max_score'] !== null) {
                $query = $activeQuery;
                unset($query['max_score']);
                $filterChips[] = ['label' => 'Score ≤ '.$filters['max_score'].' %', 'query' => $query];
            }
            $sortFilters = $activeQuery;
            unset($sortFilters['sort'], $sortFilters['direction']);
        @endphp

        <section class="admin-panel" aria-labelledby="facility-queue-title">
            <div class="admin-queue-heading">
                <h2 id="facility-queue-title">
                    Einrichtungen prüfen
                    @if ($active_city_name)
                        — {{ $active_city_name }}
                    @endif
                </h2>
                @if ($active_city_name)
                    <a href="{{ route('admin.data-quality', $withoutCityQuery) }}">← Alle Städte anzeigen</a>
                @endif
            </div>
            <form method="get" class="admin-filter admin-filter--quality">
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                <label class="admin-task-filter">
                    <span>Aktive Aufgabe</span>
                    <select name="task">
                        <option value="open" @selected($filters['task'] === 'open')>Alle offenen Einrichtungen</option>
                        <option value="missing_phone" @selected($filters['task'] === 'missing_phone')>Ohne Telefon</option>
                        <option value="missing_email" @selected($filters['task'] === 'missing_email')>Ohne E-Mail</option>
                        <option value="missing_website" @selected($filters['task'] === 'missing_website')>Ohne Website</option>
                    </select>
                </label>
                <div class="admin-filter-toolbar">
                    <fieldset class="admin-additional-filter">
                        <legend>Weitere Filter</legend>
                        <div class="admin-quality-filter__fields">
                            <label class="admin-quality-filter__field">
                                <span>Stadt</span>
                                <select name="city">
                                    <option value="">Alle Städte</option>
                                    @foreach ($cities_filter as $city)
                                        <option value="{{ $city['id'] }}" @selected($filters['city'] === (int) $city['id'])>{{ $city['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-quality-filter__field"><span>Status</span><select name="status"><option value="">Alle Status</option><option value="verified" @selected($filters['status'] === 'verified')>Geprüft</option><option value="unverified" @selected($filters['status'] === 'unverified')>Offen</option><option value="pending" @selected($filters['status'] === 'pending')>In Prüfung</option><option value="not_found" @selected($filters['status'] === 'not_found')>Nicht gefunden</option></select></label>
                            <label class="admin-quality-filter__field"><span>Quality Score bis (%)</span><input type="number" name="max_score" min="0" max="100" placeholder="z. B. 30" value="{{ $filters['max_score'] ?? '' }}"></label>
                        </div>
                    </fieldset>
                    <div class="admin-quality-filter__actions">
                        <button class="primary-button" type="submit">Ergebnisse anzeigen</button>
                        <a class="admin-secondary-button" href="{{ route('admin.data-quality') }}">Filter zurücksetzen</a>
                    </div>
                </div>
            </form>
            <div class="admin-queue-context">
                <p class="admin-filter-result">
                    @if($filtered_count === 0)
                        <strong>0</strong> Einrichtungen gefunden
                    @else
                        <strong>{{ number_format($displayed_count, 0, ',', '.') }}</strong> von <strong>{{ number_format($filtered_count, 0, ',', '.') }}</strong> Einrichtungen angezeigt
                    @endif
                </p>
                @if($filterChips !== [])
                    <div class="admin-filter-chips" aria-label="Aktive weitere Filter">
                        @foreach($filterChips as $chip)
                            <a href="{{ route('admin.data-quality', $chip['query']) }}" aria-label="Filter {{ $chip['label'] }} entfernen">{{ $chip['label'] }} <span aria-hidden="true">×</span></a>
                        @endforeach
                    </div>
                @endif
                <form class="admin-sort-select" method="get">
                    @foreach($sortFilters as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <label class="sr-only" for="queue-sort">Sortierung</label>
                    <select id="queue-sort" name="sort" onchange="this.form.submit()">
                        <option value="quality_score" @selected($filters['sort'] === 'quality_score' && $filters['direction'] === 'asc')>Niedrigster Quality Score zuerst</option>
                        <option value="quality_score_desc" @selected($filters['sort'] === 'quality_score' && $filters['direction'] === 'desc')>Höchster Quality Score zuerst</option>
                        <option value="city" @selected($filters['sort'] === 'city' && $filters['direction'] === 'asc')>Stadt (A–Z)</option>
                        <option value="city_desc" @selected($filters['sort'] === 'city' && $filters['direction'] === 'desc')>Stadt (Z–A)</option>
                        <option value="updated_at" @selected($filters['sort'] === 'updated_at')>Zuletzt aktualisiert</option>
                    </select>
                    <noscript><button type="submit">Sortieren</button></noscript>
                </form>
            </div>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr>
                <th>Einrichtung</th>
                <th aria-sort="{{ $filters['sort'] === 'city' ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                    <a class="admin-sort-link" href="{{ route('admin.data-quality', array_merge($activeQuery, ['sort' => 'city', 'direction' => $cityDirection])) }}" aria-label="Nach Stadt {{ $cityDirection === 'asc' ? 'aufsteigend' : 'absteigend' }} sortieren">
                        Stadt
                        @if ($filters['sort'] === 'city')
                            <span aria-hidden="true">{{ $filters['direction'] === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </a>
                </th>
                <th aria-sort="{{ $filters['sort'] === 'quality_score' ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                    <a class="admin-sort-link" href="{{ route('admin.data-quality', array_merge($activeQuery, ['sort' => 'quality_score', 'direction' => $qualityDirection])) }}" aria-label="Nach Datenqualität {{ $qualityDirection === 'asc' ? 'aufsteigend' : 'absteigend' }} sortieren">
                        Datenqualität
                        @if ($filters['sort'] === 'quality_score')
                            <span aria-hidden="true">{{ $filters['direction'] === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </a>
                </th>
                <th>Kontaktstatus</th><th>Fehlende Daten</th><th>Aktion</th>
            </tr></thead><tbody>
                @forelse($facilities as $facility)
                    <tr>
                        <td>{{ $facility['name'] }}</td>
                        <td>{{ $facility['city_name'] }}</td>
                        <td><x-admin.quality-score :score="$facility['score']" /></td>
                        <td><span class="status-pill status-pill--{{ $facility['status'] ?: 'missing' }}">{{ $facility['status_label'] }}</span></td>
                        <td>
                            @if($facility['missing'] === [])
                                –
                            @else
                                <div class="admin-missing-data">
                                    @foreach(array_slice($facility['missing'], 0, 3) as $missing)
                                        <span class="admin-missing-chip admin-missing-chip--{{ $missing['key'] }}">{{ $missing['label'] }}</span>
                                    @endforeach
                                    @if(count($facility['missing']) > 3)
                                        <details class="admin-missing-more">
                                            <summary>+{{ count($facility['missing']) - 3 }} weitere</summary>
                                            <div>
                                                @foreach(array_slice($facility['missing'], 3) as $missing)
                                                    <span class="admin-missing-chip admin-missing-chip--{{ $missing['key'] }}">{{ $missing['label'] }}</span>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td><a class="admin-table-action" href="{{ route('admin.facilities.edit', $facility['id']) }}">Prüfen →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Keine Einrichtungen entsprechen den ausgewählten Filtern.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
@endsection
