@extends('layouts.admin')

@section('title', 'Data Quality Dashboard – PflegeIndex')

@section('content')
    <main class="container admin-main">
        <div class="admin-title"><div><h1>Data Quality Dashboard</h1><p>Prioritäten für die nächste manuelle Datenprüfung.</p></div><a class="primary-button" href="{{ route('admin.facilities.index') }}">Einrichtungen bearbeiten</a></div>

        <div class="admin-grid admin-quality-stats">
            <div class="admin-stat"><strong>{{ number_format($overview['total_facilities'], 0, ',', '.') }}</strong><span>Einrichtungen</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['verified_count'], 0, ',', '.') }}</strong><span>Verified</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['unverified_count'], 0, ',', '.') }}</strong><span>Unverified</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['verified_percentage'], 0, ',', '.') }} %</strong><span>Verified-Anteil</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['average_score'], 0, ',', '.') }} %</strong><span>Durchschnittlicher Quality Score</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['without_phone'], 0, ',', '.') }}</strong><span>ohne Telefon</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['without_email'], 0, ',', '.') }}</strong><span>ohne E-Mail</span></div>
            <div class="admin-stat"><strong>{{ number_format($overview['without_website'], 0, ',', '.') }}</strong><span>ohne Website</span></div>
        </div>

        <section class="admin-panel">
            <h2>Städte mit Verbesserungsbedarf</h2>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Stadt</th><th>Einrichtungen</th><th>Durchschnittlicher Score</th><th>Verified</th><th>Unverified</th><th>Verified %</th><th>Aktion</th></tr></thead><tbody>
                @forelse($cities as $city)
                    <tr><td>{{ $city['name'] }}</td><td>{{ $city['total_facilities'] }}</td><td>{{ number_format($city['average_score'], 0, ',', '.') }} %</td><td>{{ $city['verified_count'] }}</td><td>{{ $city['unverified_count'] }}</td><td>{{ number_format($city['verified_percentage'], 0, ',', '.') }} %</td><td><a href="{{ route('admin.facilities.index', ['q' => $city['name']]) }}">Einrichtungen öffnen</a></td></tr>
                @empty
                    <tr><td colspan="7">Keine Städte gefunden.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="admin-panel">
            <h2>Einrichtungen mit Verbesserungsbedarf</h2>
            <form method="get" class="admin-filter admin-filter--quality">
                <select name="city" aria-label="Stadt"><option value="">Alle Städte</option>@foreach($cities_filter as $city)<option value="{{ $city['id'] }}" @selected($filters['city'] === (int) $city['id'])>{{ $city['name'] }}</option>@endforeach</select>
                <select name="status" aria-label="Verification Status"><option value="">Alle Status</option><option value="verified" @selected($filters['status'] === 'verified')>Verified</option><option value="pending" @selected($filters['status'] === 'pending')>Pending</option><option value="not_found" @selected($filters['status'] === 'not_found')>Nicht gefunden</option></select>
                <input type="number" name="max_score" min="0" max="100" placeholder="Max. Quality Score" value="{{ $filters['max_score'] ?? '' }}">
                <label><input type="checkbox" name="missing_phone" value="1" @checked($filters['missing_phone'])> ohne Telefon</label>
                <label><input type="checkbox" name="missing_email" value="1" @checked($filters['missing_email'])> ohne E-Mail</label>
                <label><input type="checkbox" name="missing_website" value="1" @checked($filters['missing_website'])> ohne Website</label>
                <button class="primary-button" type="submit">Filtern</button>
            </form>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Einrichtung</th><th>Stadt</th><th>Quality Score</th><th>Verification Status</th><th>Fehlende Daten</th><th>Aktion</th></tr></thead><tbody>
                @forelse($facilities as $facility)
                    <tr><td>{{ $facility['name'] }}</td><td>{{ $facility['city_name'] }}</td><td>{{ $facility['score'] }} %</td><td><span class="status-pill status-pill--{{ $facility['status'] ?: 'missing' }}">{{ $facility['status'] ?: 'Noch offen' }}</span></td><td>@if($facility['missing'] === []) – @else {{ implode(', ', $facility['missing']) }} @endif</td><td><a href="{{ route('admin.facilities.edit', $facility['id']) }}">Bearbeiten</a></td></tr>
                @empty
                    <tr><td colspan="6">Keine Einrichtungen entsprechen den ausgewählten Filtern.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
@endsection
