@php
    $availableLexiconTerms = config('lexicon.terms', []);
    $contentLinks = collect([
        'pflegegrad',
        'kurzzeitpflege',
        'pflegeversicherung',
        'ambulante-pflege',
        'stationaere-pflege',
    ])->filter(static fn (string $slug): bool => isset($availableLexiconTerms[$slug]));
@endphp

<section class="detail-section content-guidance" aria-labelledby="facility-guidance-title">
    <h2 id="facility-guidance-title">Was Sie wissen sollten</h2>
    <ul>
        <li>Vor einem Vertragsabschluss sollte möglichst eine Besichtigung erfolgen.</li>
        <li>Klären Sie vorab, welche Pflege- und Unterstützungsleistungen angeboten werden.</li>
        <li>Informieren Sie sich direkt über freie Plätze und mögliche Wartezeiten.</li>
        <li>Fragen Sie nach den Gesamtkosten und möglichen Zuschüssen.</li>
    </ul>
</section>

<section class="detail-section content-faq" aria-labelledby="facility-faq-title">
    <h2 id="facility-faq-title">Häufige Fragen</h2>
    <dl>
        <div>
            <dt>Welche Unterlagen werden benötigt?</dt>
            <dd>Die benötigten Unterlagen hängen von der Einrichtungsart und dem vereinbarten Angebot ab. Fragen Sie die Einrichtung nach einer aktuellen Liste und halten Sie vorhandene Unterlagen der Pflegekasse bereit.</dd>
        </div>
        <div>
            <dt>Kann ich einen Besichtigungstermin vereinbaren?</dt>
            <dd>Ob und wann eine Besichtigung möglich ist, erfahren Sie direkt bei der Einrichtung. Vereinbaren Sie den Termin möglichst vor einer Entscheidung.</dd>
        </div>
        <div>
            <dt>Übernimmt die Pflegeversicherung Kosten?</dt>
            <dd>Die Pflegeversicherung kann sich abhängig von Pflegegrad, Versorgungsform und persönlichen Voraussetzungen an bestimmten Kosten beteiligen. Lassen Sie sich von Einrichtung und Pflegekasse einen individuellen Kostenüberblick geben.</dd>
        </div>
        <div>
            <dt>Wie finde ich einen Pflegeplatz?</dt>
            <dd>Vergleichen Sie passende Einrichtungen und fragen Sie freie Plätze direkt an. Verfügbarkeit, Aufnahmebedingungen und Wartezeiten können sich kurzfristig ändern.</dd>
        </div>
    </dl>
</section>

@if($contentLinks->isNotEmpty())
    <nav class="detail-section content-links" aria-labelledby="facility-information-title">
        <h2 id="facility-information-title">Weitere Informationen</h2>
        <div>
            @foreach($contentLinks as $slug)
                <a href="{{ route('lexicon.show', $slug) }}">{{ $availableLexiconTerms[$slug]['title'] }}</a>
            @endforeach
        </div>
    </nav>
@endif

<section class="detail-section content-cta" aria-labelledby="facility-questions-title">
    <div>
        <h2 id="facility-questions-title">Fragen?</h2>
        @if($hasDirectContact)
            <p>Kontaktieren Sie die Einrichtung direkt über die verfügbaren Kontaktdaten.</p>
        @else
            <p>Direkte Kontaktdaten werden derzeit ergänzt und geprüft.</p>
        @endif
    </div>
    @if($hasDirectContact)
        <div class="content-cta__actions" role="group" aria-label="Direkter Kontakt zur Einrichtung">
            @if($facility->phone)<a href="tel:{{ $facility->phone }}">Telefon</a>@endif
            @if($displayWebsite)<a href="{{ $displayWebsite }}" target="_blank" rel="noopener noreferrer">Website</a>@endif
            @if($facility->email)<a href="mailto:{{ $facility->email }}">E-Mail</a>@endif
        </div>
    @endif
</section>
