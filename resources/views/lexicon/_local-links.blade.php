@php
    $serviceSlug = match ($slug) {
        'ambulante-pflege', 'pflegesachleistung', 'behandlungspflege' => 'ambulante-pflegedienste',
        'tagespflege' => 'tagespflege',
        'pflegeheim', 'eigenanteil', 'stationaere-pflege' => 'pflegeheime',
        default => null,
    };
    $localLinks = [];
    if ($serviceSlug) {
        $candidates = \App\Models\City::query()->where('state_slug', 'brandenburg')
            ->whereIn('slug', array_keys(config('care_pages.pilots', [])))->orderBy('name')->get();
        foreach ($candidates as $localCity) {
            foreach (app(\App\Services\CarePageService::class)->links($localCity) as $link) {
                if ($link['slug'] === $serviceSlug && count($localLinks) < 4) {
                    $localLinks[] = ['url' => $link['url'], 'label' => $localCity->name.': '.$link['label']];
                }
            }
        }
    }
@endphp
@if($serviceSlug)
    <section class="detail-section" aria-labelledby="lokale-angebote">
        <h2 id="lokale-angebote">Von der Information zum passenden Kontakt</h2>
        <p>Wenn die benötigte Pflegeform geklärt ist, können Sie konkrete Einrichtungen vergleichen. Prüfen Sie den Leistungsumfang und besprechen Sie die persönliche Situation direkt mit dem Anbieter.</p>
        <ul>
            @foreach($localLinks as $localLink)
                <li><a href="{{ $localLink['url'] }}">{{ $localLink['label'] }}</a></li>
            @endforeach
            <li><a href="{{ route('region.show') }}">Weitere Orte in Brandenburg auswählen</a></li>
            @if($serviceSlug === 'pflegeheime')
                <li><a href="{{ route('guides.care-costs') }}">Kostenbestandteile eines Heimangebots prüfen</a></li>
            @endif
        </ul>
    </section>
@endif
