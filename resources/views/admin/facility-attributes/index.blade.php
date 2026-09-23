@extends('layouts.admin')
@section('title', 'Einrichtungsattribute prüfen – PflegeIndex Verwaltung')
@section('content')
<main class="container admin-main">
    <div class="admin-title"><div><h1>Einrichtungsattribute prüfen</h1><p>Nur belegte Kandidaten aus offiziellen Quellen. Änderungen veröffentlichen keine Inhalte auf öffentlichen Seiten.</p></div></div>
    @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
    <form class="admin-filter" method="get" action="{{ route('admin.facility-attributes.index') }}">
        <select name="status" aria-label="Prüfstatus">@foreach(['candidate'=>'Kandidaten','needs_review'=>'Manuelle Prüfung','auto_approved'=>'Automatisch freigegeben','approved'=>'Freigegeben','rejected'=>'Abgelehnt','all'=>'Alle'] as $value=>$label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
        <select name="state" aria-label="Bundesland"><option value="">Alle Bundesländer</option><option value="brandenburg" @selected($state === 'brandenburg')>Brandenburg</option><option value="sachsen" @selected($state === 'sachsen')>Sachsen</option></select>
        <select name="attribute_key" aria-label="Attribut"><option value="">Alle Attribute</option>@foreach($attributeKeys as $key)<option value="{{ $key }}" @selected($attributeKey === $key)>{{ $key }}</option>@endforeach</select>
        <label><input type="checkbox" name="medical" value="1" @checked($medical)> Medizinische Attribute</label><button class="primary-button" type="submit">Filtern</button>
    </form>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Einrichtung</th><th>Attribut</th><th>Beleg</th><th>Zuordnung</th><th>Prüfstatus</th><th></th></tr></thead><tbody>
    @forelse($attributes as $attribute)<tr><td><strong>{{ $attribute->facility->name }}</strong><small>{{ $attribute->facility->postal_code }} {{ $attribute->facility->city->name }}</small></td><td><strong>{{ $attribute->attribute_key }}</strong><small>{{ $attribute->attribute_value }} · {{ $attribute->confidence }}</small></td><td><a href="{{ $attribute->source_url }}" target="_blank" rel="noopener noreferrer">Quelle öffnen</a><small>{{ \Illuminate\Support\Str::limit($attribute->source_excerpt, 220) }}</small></td><td>{{ $attribute->relation_reason }}</td><td><span class="status-pill">{{ $attribute->review_status }}</span></td><td><form method="post" action="{{ route('admin.facility-attributes.approve',$attribute) }}">@csrf<button type="submit">Freigeben</button></form><form method="post" action="{{ route('admin.facility-attributes.reject',$attribute) }}">@csrf<button type="submit">Ablehnen</button></form><form method="post" action="{{ route('admin.facility-attributes.needs-review',$attribute) }}">@csrf<button type="submit">Prüfen</button></form></td></tr>@empty<tr><td colspan="6">Keine Attribute für diesen Filter.</td></tr>@endforelse
    </tbody></table></div><x-pagination :paginator="$attributes" />
</main>
@endsection