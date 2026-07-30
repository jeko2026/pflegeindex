@props(['score'])

@php
    $value = max(0, min(100, (int) $score));
    [$level, $label] = match (true) {
        $value >= 70 => ['high', 'hohe Datenqualität'],
        $value >= 30 => ['medium', 'mittlere Datenqualität'],
        default => ['low', 'niedrige Datenqualität'],
    };
@endphp

<div class="admin-score admin-score--{{ $level }}">
    <strong>{{ $value }} %</strong>
    <div
        class="admin-score__bar"
        role="progressbar"
        aria-label="Datenqualität: {{ $value }} Prozent, {{ $label }}"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="{{ $value }}"
        style="--quality-score: {{ $value }}%;"
    ><span></span></div>
</div>
