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
    <div class="faq-accordion-list" style="margin-top: 18px;">
        <details class="faq-item">
            <summary class="faq-question">Welche Unterlagen werden benötigt?</summary>
            <div class="faq-answer">
                <p>Die benötigten Unterlagen hängen von der Einrichtungsart und dem vereinbarten Angebot ab. Fragen Sie die Einrichtung nach einer aktuellen Liste und halten Sie vorhandene Unterlagen der Pflegekasse bereit.</p>
            </div>
        </details>
        <details class="faq-item">
            <summary class="faq-question">Kann ich einen Besichtigungstermin vereinbaren?</summary>
            <div class="faq-answer">
                <p>Ob und wann eine Besichtigung möglich ist, erfahren Sie direkt bei der Einrichtung. Vereinbaren Sie den Termin möglichst vor einer Entscheidung.</p>
            </div>
        </details>
        <details class="faq-item">
            <summary class="faq-question">Übernimmt die Pflegeversicherung Kosten?</summary>
            <div class="faq-answer">
                <p>Die Pflegeversicherung kann sich abhängig von Pflegegrad, Versorgungsform und persönlichen Voraussetzungen an bestimmten Kosten beteiligen. Lassen Sie sich von Einrichtung und Pflegekasse einen individuellen Kostenüberblick geben.</p>
            </div>
        </details>
        <details class="faq-item">
            <summary class="faq-question">Wie finde ich einen Pflegeplatz?</summary>
            <div class="faq-answer">
                <p>Vergleichen Sie passende Einrichtungen und fragen Sie freie Plätze direkt an. Verfügbarkeit, Aufnahmebedingungen und Wartezeiten können sich kurzfristig ändern.</p>
            </div>
        </details>
    </div>
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
            @if($emailIsValid)<a href="mailto:{{ $facility->email }}">E-Mail</a>@endif
        </div>
    @endif
</section>
