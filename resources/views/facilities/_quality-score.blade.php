<section class="quality-score-panel" aria-labelledby="facility-data-quality-title" data-quality-score-unified="{{ $qualityScore['score'] }}">
    <div class="quality-score-panel__heading">
        <div>
            <p class="quality-score-panel__eyebrow">Datenqualität</p>
            <h2 id="facility-data-quality-title">{{ $qualityScore['quality_label'] }}</h2>
        </div>
        <strong class="quality-score-panel__score quality-score-panel__score--{{ $qualityScore['quality_color'] }}" aria-label="Quality Score {{ $qualityScore['score'] }} von 100">{{ $qualityScore['score'] }}<span>/100</span></strong>
    </div>
    <div class="quality-score-panel__bar" role="progressbar" aria-label="Quality Score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $qualityScore['progress_percentage'] }}">
        <span class="quality-score-panel__bar-fill quality-score-panel__bar-fill--{{ $qualityScore['quality_color'] }}" style="width: {{ $qualityScore['progress_percentage'] }}%"></span>
    </div>
    @if($qualityScore['review_documented'])
        <p class="quality-score-panel__meta">Kontaktdaten geprüft am: {{ $qualityScore['verified_at'] }}</p>
        @if($qualityScore['source'])
            <p class="quality-score-panel__meta">Quelle: <a href="{{ $qualityScore['source'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $qualityScore['source'] }}</a></p>
        @endif
    @else
        <p class="quality-score-panel__meta">Noch nicht vollständig geprüft.</p>
    @endif
</section>
