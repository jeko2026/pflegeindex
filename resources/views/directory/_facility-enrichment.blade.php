@if(($enrichmentGroups ?? []) !== [])
<section class="detail-section enrichment-section" aria-labelledby="facility-enrichment-title">
    <h2 id="facility-enrichment-title">Ausstattung &amp; Angebote</h2>
    <div class="enrichment-groups">
        @foreach($enrichmentGroups as $group)
            <div class="enrichment-group">
                <h3>{{ $group['heading'] }}</h3>
                <div class="enrichment-items">
                    @foreach($group['items'] as $item)<span>{{ $item['label'] }}</span>@endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif