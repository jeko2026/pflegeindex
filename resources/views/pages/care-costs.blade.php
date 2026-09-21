@extends('layouts.app')
@section('title', 'Pflegeheimkosten verstehen – Eigenanteil & Angebot | PflegeIndex')
@section('description', 'Pflegeheimkosten Schritt für Schritt prüfen: Eigenanteil, Unterkunft, Verpflegung, Investitionskosten und Zuschüsse im persönlichen Angebot unterscheiden.')
@section('canonical', route('guides.care-costs'))
@php
    $crumbs = [
        ['name' => 'Startseite', 'item' => route('home')],
        ['name' => 'Pflegeheimkosten', 'item' => route('guides.care-costs')],
    ];
    $faq = [
        ['question' => 'Kann ich hier schon meine Heimkosten berechnen?', 'answer' => 'Nein. PflegeIndex bietet auf dieser Seite noch keinen Kostenrechner an. Für eine belastbare Berechnung fehlen geprüfte individuelle Kostenpositionen und ein fachlich validiertes Regelwerk. Die Checkliste hilft, ein Angebot vorzubereiten.'],
        ['question' => 'Welche Angaben brauche ich vom Pflegeheim?', 'answer' => 'Bitten Sie um eine aktuelle schriftliche Aufstellung der Kostenbestandteile, des Abrechnungszeitraums, vereinbarter Zusatzleistungen und bereits berücksichtigter Zuschüsse. Lassen Sie unklare Positionen erklären.'],
        ['question' => 'Warum reicht der Pflegegrad allein nicht aus?', 'answer' => 'Die konkrete Rechnung hängt auch vom Angebot der Einrichtung und der persönlichen Situation ab. Ein einzelner Pflegegrad oder ein Landesdurchschnitt bildet nicht alle Kostenbestandteile ab.'],
    ];
@endphp
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@graph'=>[
    ['@type'=>'WebPage','name'=>'Pflegeheimkosten verstehen','url'=>route('guides.care-costs')],
    ['@type'=>'BreadcrumbList','itemListElement'=>collect($crumbs)->map(fn($c,$i)=>['@type'=>'ListItem','position'=>$i+1,...$c])->all()],
    ['@type'=>'FAQPage','mainEntity'=>collect($faq)->map(fn($f)=>['@type'=>'Question','name'=>$f['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f['answer']]])->all()],
]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
@endpush
@section('content')
<section class="page-hero"><div class="container">
    <nav aria-label="Breadcrumb"><ol class="breadcrumbs"><li><a href="{{ route('home') }}">Startseite</a></li><li aria-current="page"><span aria-hidden="true">›</span>Pflegeheimkosten</li></ol></nav>
    <h1>Pflegeheimkosten verstehen und ein Angebot prüfen</h1>
    <p class="page-hero__lead">Bevor Sie Angebote vergleichen, sollten Sie wissen, welche Beträge enthalten sind. Diese Übersicht hilft Ihnen, Unterlagen zu ordnen und gezielte Fragen an die Einrichtung oder Pflegekasse zu stellen.</p>
    <div class="notice">Noch kein Kostenrechner: Hier werden keine persönlichen Kosten oder Leistungsansprüche berechnet. Nutzen Sie ein aktuelles schriftliches Angebot und eine individuelle Beratung.</div>
</div></section>
<section class="section"><div class="container">
    <h2>Welche Kostenpositionen gehören in die Aufstellung?</h2>
    <p>Pflegebezogene Kosten, Unterkunft und Verpflegung sowie Investitionskosten sind getrennt zu betrachten. Hinzukommen können vereinbarte Zusatzleistungen. Die Beträge unterscheiden sich zwischen Einrichtungen. Lassen Sie sich erklären, welche Leistungen der Pflegekasse oder weiteren Zuschüsse bereits im Angebot berücksichtigt wurden.</p>
    <h2>Checkliste für vergleichbare Angebote</h2>
    <ul>
        <li>Auf welche Person, welchen Pflegegrad und welchen Zeitraum bezieht sich das Angebot?</li>
        <li>Sind die Positionen täglich oder monatlich angegeben? Wie werden Teilmonate behandelt?</li>
        <li>Welche Beträge sind vor, welche nach berücksichtigten Zuschüssen ausgewiesen?</li>
        <li>Welche Zusatzleistungen sind freiwillig und ausdrücklich vereinbart?</li>
        <li>Ab wann gilt die Aufstellung, und wer erläutert spätere Änderungen?</li>
    </ul>
    <h2>Eigenanteil und Zuschüsse nicht doppelt verrechnen</h2>
    <p>Übernehmen Sie nicht ungeprüft Zahlen aus unterschiedlichen Angeboten in eine Rechnung. Ein bereits reduzierter Eigenanteil und ein nochmals abgezogener Zuschuss können ein falsches Ergebnis ergeben. Markieren Sie unklare Positionen und lassen Sie die Berechnungsgrundlage bestätigen.</p>
    <h2>Einrichtung und Beratung finden</h2>
    <ul>
        <li><a href="{{ route('region.show') }}">Pflegeeinrichtungen nach Ort in Brandenburg suchen</a></li>
        <li><a href="{{ route('lexicon.show', 'pflegeheim') }}">Pflegeheim: Versorgung und Auswahl</a></li>
        <li><a href="{{ route('lexicon.show', 'pflegegrad') }}">Pflegegrad verstehen</a></li>
        <li><a href="{{ route('lexicon.show', 'pflegegeld') }}">Pflegegeld einordnen</a> – nicht pauschal als Abzug von der Heimrechnung behandeln.</li>
        <li><a href="{{ route('lexicon.show', 'pflegeberatung') }}">Eine Pflegeberatung vorbereiten</a></li>
    </ul>
    <h2>Häufige Fragen</h2>
    <div class="faq-accordion-list">@foreach($faq as $item)<details class="faq-item"><summary class="faq-question">{{ $item['question'] }}</summary><div class="faq-answer"><p>{{ $item['answer'] }}</p></div></details>@endforeach</div>
    <h2>Quellen und Grenzen</h2>
    <p><a href="https://www.bundesgesundheitsministerium.de/themen/pflege/online-ratgeber-pflege/leistungen-der-pflegeversicherung/vollstationaere-pflege-im-heim" rel="noopener" target="_blank">Bundesgesundheitsministerium: Vollstationäre Pflege im Heim</a> · <a href="https://gesund.bund.de/vollstationaere-pflege-im-heim" rel="noopener" target="_blank">gesund.bund.de: Pflege im Heim</a></p>
    <p>Redaktioneller Stand: 19.09.2026. Diese Orientierung ersetzt weder ein individuelles Angebot noch eine persönliche Rechts- oder Pflegeberatung. Es werden keine aktuellen Einrichtungspreise oder pauschalen Leistungsbeträge behauptet.</p>
</div></section>
@endsection
