@extends('layouts.admin')

@section('title', 'Übersicht – PflegeIndex Verwaltung')

@section('content')
    <main class="container admin-main">
        <div class="admin-title"><div><h1>Übersicht</h1><p>Aktueller Stand des PflegeIndex-Verzeichnisses.</p></div><a class="primary-button" href="{{ route('admin.facilities.index') }}">Einrichtungen bearbeiten</a></div>
        <div class="admin-grid">
            <div class="admin-stat"><strong>{{ number_format($facilityCount, 0, ',', '.') }}</strong><span>Einrichtungen</span></div>
            <div class="admin-stat"><strong>{{ number_format($cityCount, 0, ',', '.') }}</strong><span>Orte</span></div>
            <div class="admin-stat"><strong>{{ number_format($verifiedCount, 0, ',', '.') }}</strong><span>geprüfte Kontakte</span></div>
            <div class="admin-stat"><strong>{{ number_format($withoutPhoneCount, 0, ',', '.') }}</strong><span>ohne Telefonnummer</span></div>
            <div class="admin-stat"><strong>{{ number_format($pendingSuggestionCount, 0, ',', '.') }}</strong><span>Kontakte zu prüfen</span></div>
        </div>
        <section class="admin-panel">
            <h2>Zuletzt geprüfte Kontakte</h2>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Einrichtung</th><th>Ort</th><th>Status</th><th>Geprüft</th></tr></thead><tbody>
                @forelse($recentFacilities as $facility)
                    <tr><td><a href="{{ route('admin.facilities.edit', $facility) }}">{{ $facility->name }}</a></td><td>{{ $facility->city->name }}</td><td><span class="status-pill status-pill--{{ $facility->contact_status }}">{{ $facility->contactStatusLabel() }}</span></td><td>{{ $facility->contact_checked_at?->format('d.m.Y H:i') }}</td></tr>
                @empty
                    <tr><td colspan="4">Noch keine Kontakte geprüft.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
@endsection
