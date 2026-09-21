<section class="section section--white" aria-labelledby="auswahl-hilfe">
    <div class="container">
        <h2 id="auswahl-hilfe">Die nächsten Schritte in {{ $city->name }}</h2>
        @foreach($editorial['blocks'] as $block)
            <section class="detail-section">
                <h2>{{ $block['title'] }}</h2>
                <p>{{ $block['body'] }}</p>
            </section>
        @endforeach
        <p>Halten Sie beim ersten Kontakt den gewünschten Beginn, Ihren Unterstützungsbedarf und offene Fragen bereit. Die Kontakthinweise finden Sie in den oben verlinkten Einrichtungsprofilen.</p>
        <nav aria-label="Weiterführende Pflegeinformationen">
            <ul>
                <li><a href="{{ route('lexicon.show', $editorial['topic']) }}">{{ $category['label'] }} verständlich erklärt</a></li>
                <li><a href="{{ route('lexicon.show', 'pflegegrad') }}">Was der Pflegegrad bedeutet</a></li>
                <li><a href="{{ route('lexicon.show', 'pflegeberatung') }}">Fragen für eine Pflegeberatung</a></li>
                @if($category['slug'] === 'pflegeheime')
                    <li><a href="{{ route('guides.care-costs') }}">Eine Pflegeheim-Kostenaufstellung verstehen</a></li>
                @endif
                <li><a href="{{ route('cities.show', $city) }}">Weitere Pflegearten in {{ $city->name }}</a></li>
            </ul>
        </nav>
        <p>Redaktioneller Stand der Auswahlhinweise: {{ \Illuminate\Support\Carbon::parse($editorial['reviewed_at'])->format('d.m.Y') }}. Dies ist keine erneute Prüfung aller Kontaktdaten; deren dokumentierter Prüfstatus steht im jeweiligen Profil.</p>
    </div>
</section>
<section class="section section--faq" aria-labelledby="service-faq">
    <div class="container">
        <h2 id="service-faq">{{ $category['label'] }} in {{ $city->name }}: häufige Fragen</h2>
        <div class="faq-accordion-list">
            @foreach($editorial['faq'] as $faq)
                <details class="faq-item">
                    <summary class="faq-question">{{ $faq['question'] }}</summary>
                    <div class="faq-answer"><p>{{ $faq['answer'] }}</p></div>
                </details>
            @endforeach
        </div>
    </div>
</section>
