@if($currentPage === 1 && $seoAction)
<section class="section section--white" aria-labelledby="pflegeart-waehlen">
    <div class="container">
        <h2 id="pflegeart-waehlen">Die passende Pflegeart in {{ $city->name }} auswählen</h2>
        <p>Geht es um Hilfe in der Wohnung, Betreuung während des Tages oder einen dauerhaften Aufenthalt? Diese Unterscheidung grenzt die Suche stärker ein als der Name einer Einrichtung. Die verlinkten Kategorien enthalten nur passend zugeordnete Einträge.</p>
        <h2>Ein Gespräch vorbereiten</h2>
        <p>Notieren Sie den gewünschten Beginn, den persönlichen Unterstützungsbedarf und wichtige Zeiten. Fragen Sie nach einem schriftlichen Angebot und den nächsten Schritten. Kontaktdaten und deren Prüfstatus stehen im einzelnen Profil; die Stadtübersicht bestätigt keine Aufnahme oder laufende Belegung.</p>
        <ul>
            @foreach($carePageLinks as $link)
                <li><a href="{{ $link['url'] }}">{{ $link['label'] }} in {{ $city->name }} ansehen</a></li>
            @endforeach
            <li><a href="{{ route('lexicon.show', 'pflegeberatung') }}">Unterstützung bei der Pflegeentscheidung</a></li>
            <li><a href="{{ route('lexicon.show', 'pflegegrad') }}">Pflegegrad: eine erste Orientierung</a></li>
        </ul>
        <p>Auswahlhinweise redaktionell bearbeitet am {{ \Illuminate\Support\Carbon::parse($seoAction['reviewed_at'])->format('d.m.Y') }}. Basisdaten und ergänzende Kontaktprüfungen haben eigene, im Verzeichnis ausgewiesene Datenstände.</p>
    </div>
</section>
@endif
