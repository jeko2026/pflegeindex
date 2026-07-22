<section class="quality-panel" aria-labelledby="facility-quality-title" data-quality-score="{{ $quality->score }}">
    <header class="quality-panel__header">
        <div>
            <p class="quality-panel__eyebrow">PflegeIndex Qualität</p>
            <h2 id="facility-quality-title">Qualität der Informationen</h2>
        </div>
        <details class="quality-tooltip">
            <summary aria-label="Erläuterung zur PflegeIndex Qualität">?</summary>
            <span role="tooltip">Diese Bewertung beschreibt ausschließlich die Vollständigkeit und Qualität der vorliegenden Informationen. Sie ist keine Bewertung der Einrichtung.</span>
        </details>
    </header>

    <div class="quality-panel__summary">
        <strong aria-label="Datenqualität {{ $quality->score }} Prozent">{{ $quality->score }} <span>%</span></strong>
        <span>{{ $quality->fulfilledCount }} von {{ $quality->totalCount }} Qualitätsmerkmalen erfüllt</span>
    </div>
    <div class="quality-progress" role="progressbar" aria-label="Vollständigkeit der Einrichtungsinformationen" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $quality->score }}">
        <span style="width: {{ $quality->score }}%"></span>
    </div>

    @if($quality->badges() !== [])
        <div class="quality-badges" aria-label="Vorhandene Informationsbereiche">
            @foreach($quality->badges() as $badge)
                <span data-quality-badge="{{ $badge['key'] }}">{{ $badge['label'] }}</span>
            @endforeach
        </div>
    @endif

    <ul class="quality-checklist" aria-label="Erfüllte Qualitätsmerkmale">
        @foreach($quality->fulfilledCriteria() as $criterion)
            <li data-quality-criterion="{{ $criterion['key'] }}"><span aria-hidden="true">✓</span>{{ $criterion['label'] }}</li>
        @endforeach
    </ul>

    <p class="quality-panel__date">Stand der Bewertung: {{ ucfirst(now()->locale('de')->translatedFormat('F Y')) }}</p>
</section>
