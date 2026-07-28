<section class="facility-trust" aria-labelledby="facility-trust-title">
    <h2 id="facility-trust-title">{{ $qualityScore['trust']['label'] }}</h2>
    @if($qualityScore['trust']['verified_at'])
        <p>Zuletzt geprüft am {{ $qualityScore['trust']['verified_at'] }}</p>
    @endif
    @if($qualityScore['trust']['source'])
        <p>Quelle: <a href="{{ $qualityScore['trust']['source'] }}" target="_blank" rel="nofollow noopener noreferrer">Website des Anbieters</a></p>
    @endif
</section>
