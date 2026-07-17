@extends('layouts.admin')

@section('title', 'Einrichtungen – PflegeIndex Verwaltung')

@section('content')
    <main class="container admin-main">
        <div class="admin-title"><div><h1>Einrichtungen</h1><p>{{ number_format($facilities->total(), 0, ',', '.') }} passende Einträge.</p></div></div>
        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form class="admin-filter admin-filter--facilities" method="get" action="{{ route('admin.facilities.index') }}">
            <input name="q" type="search" value="{{ $query }}" placeholder="Name, Stadt oder PLZ" aria-label="Einrichtung suchen">
            <select name="status" aria-label="Kontaktstatus"><option value="">Alle Status</option><option value="verified" @selected($status === 'verified')>Geprüft</option><option value="pending" @selected($status === 'pending')>In Prüfung</option><option value="not_found" @selected($status === 'not_found')>Nicht gefunden</option><option value="missing" @selected($status === 'missing')>Noch offen</option></select>
            @php($activeContactFilters = collect([$phone, $email, $website, $source])->filter()->count())
            <details class="admin-contact-filter" @if($activeContactFilters) open @endif>
                <summary>Kontaktdaten @if($activeContactFilters)<span>{{ $activeContactFilters }} aktiv</span>@endif</summary>
                <div class="admin-contact-filter__panel">
                    @foreach([
                        'phone' => ['Telefon', $phone],
                        'email' => ['E-Mail', $email],
                        'website' => ['Website', $website],
                        'source' => ['Kontaktquelle', $source],
                    ] as $field => [$label, $value])
                        <div class="admin-contact-filter__row" role="group" aria-label="{{ $label }}">
                            <strong>{{ $label }}</strong>
                            <label><input name="{{ $field }}" type="radio" value="" @checked($value === '')> Alle</label>
                            <label><input name="{{ $field }}" type="radio" value="with" @checked($value === 'with')> Mit</label>
                            <label><input name="{{ $field }}" type="radio" value="without" @checked($value === 'without')> Ohne</label>
                        </div>
                    @endforeach
                </div>
            </details>
            <select name="content" aria-label="Beschreibungsstatus"><option value="">Alle Beschreibungen</option><option value="draft" @selected($content === 'draft')>KI-Entwurf vorhanden</option><option value="published" @selected($content === 'published')>Beschreibung veröffentlicht</option></select>
            <button class="primary-button" type="submit">Filtern</button>
        </form>
        <form id="bulk-description-form" method="post" action="{{ route('admin.facilities.description-drafts.publish') }}">
            @csrf
            @if($hasDraftsOnPage)
                <div class="admin-bulk-bar">
                    <label><input id="select-all-drafts" type="checkbox"> Alle Entwürfe auf dieser Seite auswählen</label>
                    <button id="publish-selected-drafts" class="primary-button" type="submit" disabled>Ausgewählte veröffentlichen</button>
                </div>
            @endif
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th class="admin-checkbox-column"><span class="visually-hidden">Auswahl</span></th><th>Einrichtung</th><th>Ort</th><th>Telefon</th><th>E-Mail</th><th>Website</th><th>Kontakt</th><th>Beschreibung</th><th></th></tr></thead><tbody>
            @foreach($facilities as $facility)
                <tr>
                    <td class="admin-checkbox-column">
                        @if(filled($facility->description_draft))
                            <input class="draft-checkbox" name="facility_ids[]" type="checkbox" value="{{ $facility->id }}" aria-label="Entwurf für {{ $facility->name }} auswählen">
                        @else
                            <span aria-hidden="true">–</span>
                        @endif
                    </td>
                    <td><strong>{{ $facility->name }}</strong><small>{{ $facility->type }}</small></td>
                    <td>{{ $facility->postal_code }} {{ $facility->city->name }}</td>
                    <td>@if($facility->phone)<span class="admin-contact-present" title="Telefon vorhanden" aria-label="Telefon vorhanden">+</span>@else – @endif</td>
                    <td>@if($facility->email)<span class="admin-contact-present" title="E-Mail vorhanden" aria-label="E-Mail vorhanden">+</span>@else – @endif</td>
                    <td>@if($facility->website)<a href="{{ $facility->website }}" target="_blank" rel="noopener">Website</a>@else – @endif</td>
                    <td><span class="status-pill status-pill--{{ $facility->contact_status }}">{{ $facility->contactStatusLabel() }}</span></td>
                    <td>
                        @if(filled($facility->description_draft))
                            <span class="status-pill status-pill--pending">KI-Entwurf</span>
                        @elseif($facility->description_checked_at)
                            <span class="status-pill status-pill--verified">Veröffentlicht</span>
                        @else
                            <span class="status-pill">Noch offen</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.facilities.edit', $facility) }}">Bearbeiten</a></td>
                </tr>
            @endforeach
        </tbody></table></div>
        </form>
        <x-pagination :paginator="$facilities" />
    </main>

    @if($hasDraftsOnPage)
        <script>
            (() => {
                const form = document.getElementById('bulk-description-form');
                const selectAll = document.getElementById('select-all-drafts');
                const button = document.getElementById('publish-selected-drafts');
                const checkboxes = [...document.querySelectorAll('.draft-checkbox')];

                const update = () => {
                    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                    button.disabled = selected === 0;
                    button.textContent = selected === 0
                        ? 'Ausgewählte veröffentlichen'
                        : `${selected} ausgewählte veröffentlichen`;
                    selectAll.checked = selected > 0 && selected === checkboxes.length;
                    selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
                };

                selectAll.addEventListener('change', () => {
                    checkboxes.forEach((checkbox) => checkbox.checked = selectAll.checked);
                    update();
                });
                checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));
                form.addEventListener('submit', (event) => {
                    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;

                    if (selected === 0 || ! window.confirm(`${selected} geprüfte Beschreibungen jetzt öffentlich anzeigen?`)) {
                        event.preventDefault();
                    }
                });
                update();
            })();
        </script>
    @endif
@endsection
