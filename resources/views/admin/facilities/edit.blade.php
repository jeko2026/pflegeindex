@extends('layouts.admin')

@section('title', 'Einrichtung bearbeiten – PflegeIndex Verwaltung')

@section('content')
    @php
        $searchQuery = '"'.$facility->name.'" '.$facility->postal_code.' '.$facility->city->name.' offizielle Website';
        $searchUrl = 'https://www.google.com/search?q='.rawurlencode($searchQuery);
    @endphp
    <main class="container admin-main">
        <div class="admin-title">
            <div>
                <p>
                    @if($suggestion)
                        <a href="{{ route('admin.suggestions.show', $suggestion) }}">← Zur Kontaktprüfung</a>
                    @else
                        <a href="{{ route('admin.facilities.index') }}">← Einrichtungen</a>
                    @endif
                </p>
                <h1>{{ $facility->name }}</h1><p>{{ $facility->address }}, {{ $facility->postal_code }} {{ $facility->city->name }}</p>
            </div>
            <div class="admin-title__actions">
                <a class="admin-secondary-button" href="{{ $searchUrl }}" target="_blank" rel="noopener">Website suchen ↗</a>
                <a class="primary-button" href="{{ route('facilities.show', [$facility->city, $facility]) }}" target="_blank" rel="noopener">Öffentliche Seite</a>
            </div>
        </div>

        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
        @if($suggestion)<div class="admin-note">Sie bearbeiten die Kontaktdaten während der Prüfung eines Parser-Ergebnisses. Nach dem Speichern kehren Sie automatisch zu diesem Ergebnis zurück.</div>@endif
        @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if(filled($facility->description_draft))
            <form class="admin-form admin-form--content-review" method="post" action="{{ route('admin.facilities.description-draft', $facility) }}">
                @csrf
                <div class="admin-content-heading">
                    <div><span class="status-pill status-pill--pending">KI-Entwurf</span><h2>Beschreibung prüfen</h2></div>
                    <p>Noch nicht öffentlich. Sie können den Text ändern, speichern oder nach der Prüfung veröffentlichen.</p>
                </div>
                <div class="admin-form__grid">
                    <div class="admin-field admin-field--wide"><label for="description_draft">Entwurf für „Über diese Einrichtung“</label><textarea id="description_draft" name="description_draft" maxlength="3000" rows="9" required>{{ old('description_draft', $facility->description_draft) }}</textarea><span class="admin-help">Sachlicher eigenständiger Text auf Grundlage der unten genannten Quellen.</span></div>
                    <div class="admin-field admin-field--wide"><label for="description_draft_sources">Quellen</label><textarea id="description_draft_sources" name="description_draft_sources" rows="3" required>{{ old('description_draft_sources', implode("\n", $facility->description_draft_sources ?? [])) }}</textarea><span class="admin-help">Eine vollständige Webadresse pro Zeile.</span></div>
                    <div class="admin-field"><label for="description_draft_checked_at">Quellen geprüft am</label><input id="description_draft_checked_at" name="description_draft_checked_at" type="date" value="{{ old('description_draft_checked_at', $facility->description_draft_checked_at?->format('Y-m-d')) }}" required></div>
                </div>
                <div class="admin-actions admin-actions--review">
                    <button class="admin-secondary-button" name="action" type="submit" value="save">Entwurf speichern</button>
                    <button class="primary-button" name="action" type="submit" value="publish">Prüfen und veröffentlichen</button>
                    <button class="danger-button" name="action" type="submit" value="discard" formnovalidate onclick="return confirm('KI-Entwurf wirklich verwerfen?')">Entwurf verwerfen</button>
                </div>
            </form>
        @elseif($facility->description_checked_at)
            <div class="admin-note admin-note--published">Beschreibung veröffentlicht am {{ $facility->description_checked_at->format('d.m.Y') }}{{ $facility->description_ai_assisted ? ' · mit KI-Unterstützung erstellt' : '' }}.</div>
        @endif

        <form class="admin-form" method="post" action="{{ route('admin.facilities.update', $facility) }}">
            @csrf
            @method('put')
            @if($suggestion)<input name="suggestion_id" type="hidden" value="{{ $suggestion->id }}">@endif
            <div class="admin-form__grid">
                <div class="admin-field admin-field--wide"><label for="description">Aktuell veröffentlichte Beschreibung („Über diese Einrichtung“)</label><textarea id="description" name="description" maxlength="3000" rows="6" placeholder="Eigene sachliche Beschreibung der Einrichtung">{{ old('description', $facility->description) }}</textarea><span class="admin-help">Ein vorhandener KI-Entwurf wird erst nach „Prüfen und veröffentlichen“ in dieses Feld übernommen.</span></div>
                <div class="admin-field"><label for="phone">Telefon</label><input id="phone" name="phone" value="{{ old('phone', $facility->phone) }}" placeholder="+49..."><span class="admin-help">Am besten im internationalen Format.</span></div>
                <div class="admin-field"><label for="email">E-Mail</label><input id="email" name="email" type="email" value="{{ old('email', $facility->email) }}"></div>
                <div class="admin-field admin-field--wide"><label for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $facility->website) }}" placeholder="https://..."></div>
                <div class="admin-field admin-field--wide"><label for="contact_source">Quelle der Kontaktdaten</label><input id="contact_source" name="contact_source" type="url" value="{{ old('contact_source', $facility->contact_source) }}" placeholder="https://..."><span class="admin-help">Direkter Link zur offiziellen Seite mit den Kontaktdaten.</span></div>
                <div class="admin-field"><label for="contact_status">Prüfstatus</label><select id="contact_status" name="contact_status"><option value="">Noch offen</option><option value="verified" @selected(old('contact_status', $facility->contact_status) === 'verified')>Geprüft</option><option value="pending" @selected(old('contact_status', $facility->contact_status) === 'pending')>In Prüfung</option><option value="not_found" @selected(old('contact_status', $facility->contact_status) === 'not_found')>Nicht gefunden</option></select></div>
                <div class="admin-field"><label><span><input name="contact_locked" type="hidden" value="0"><input name="contact_locked" type="checkbox" value="1" @checked(old('contact_locked', $facility->contact_locked ?? true))> Vor automatischem Import schützen</span></label><span class="admin-help">Verhindert, dass Parser oder Datenimport diese Kontakte überschreibt.</span></div>
            </div>
            <div class="admin-actions"><button class="primary-button" type="submit">{{ $suggestion ? 'Speichern und zur Prüfung zurück' : 'Änderungen speichern' }}</button></div>
        </form>
    </main>
@endsection
