@extends('layouts.app')

@section('title', $term['title'].' einfach erklärt | PflegeIndex Pflegelexikon')
@section('description', $term['summary'].' Verständliche Erklärung, wichtige Hinweise und offizielle Quellen.')

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'DefinedTerm',
        'name' => $term['title'],
        'description' => $term['summary'],
        'url' => route('lexicon.show', $slug),
        'inDefinedTermSet' => route('lexicon.index'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    <section class="page-hero lexicon-term-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><a href="{{ route('lexicon.index') }}">Pflegelexikon</a><span>›</span><span>{{ $term['title'] }}</span></p>
            <p class="eyebrow">Pflegebegriff einfach erklärt</p>
            <h1>{{ $term['title'] }}</h1>
            <p class="page-hero__lead">{{ $term['summary'] }}</p>
        </div>
    </section>

    <div class="container detail-layout lexicon-detail-layout">
        <article class="detail-main">
            <section class="detail-section lexicon-definition">
                <h2>Was bedeutet {{ $term['title'] }}?</h2>
                <p>{{ $term['intro'] }}</p>
            </section>

            <section class="lexicon-at-a-glance" aria-labelledby="auf-einen-blick">
                <h2 id="auf-einen-blick">Auf einen Blick</h2>
                <p><strong>Kurz gesagt:</strong> {{ $term['summary'] }}</p>
                <p><strong><u>Wichtig:</u></strong> {{ $term['tip'] }}</p>
            </section>

            @foreach($term['sections'] as $section)
                <section class="detail-section">
                    <h2>{{ $section['title'] }}</h2>
                    <p>{{ $section['body'] }}</p>
                </section>
            @endforeach

            @if($example)
                <section class="detail-section lexicon-example">
                    <p class="lexicon-block-label">Beispiel aus dem Alltag</p>
                    <h2>So könnte es aussehen</h2>
                    <p><strong>Beispiel:</strong> {{ $example }}</p>
                    <p class="lexicon-example-note">Das Beispiel ist vereinfacht. Die persönliche Situation kann anders beurteilt werden.</p>
                </section>
            @endif

            @if($legalNote)
                <section class="detail-section lexicon-legal-note">
                    <p class="lexicon-block-label">Rechtlicher Hinweis</p>
                    <h2>{{ $legalNote['title'] }}</h2>
                    <p>{{ $legalNote['body'] }}</p>
                    <p class="lexicon-legal-disclaimer">Diese Informationen bieten eine erste Orientierung und ersetzen keine individuelle Rechts- oder Pflegeberatung.</p>
                </section>
            @endif

            <section class="detail-section lexicon-sources">
                <h2>Offizielle Quellen</h2>
                <ul>
                    @foreach($term['sources'] as $source)
                        <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener">{{ $source['label'] }} <span aria-hidden="true">↗</span></a></li>
                    @endforeach
                </ul>
                <p class="lexicon-reviewed">Redaktionell geprüft am {{ \Illuminate\Support\Carbon::parse($term['checked_at'])->format('d.m.Y') }}.</p>
            </section>
        </article>

        <aside class="lexicon-sidebar">
            <div class="lexicon-summary-card">
                <span class="contact-card__label">Kurz erklärt</span>
                <h2>{{ $term['title'] }}</h2>
                <p>{{ $term['summary'] }}</p>
            </div>

            @if($relatedTerms->isNotEmpty())
                <div class="lexicon-related-card">
                    <h2>Verwandte Begriffe</h2>
                    @foreach($relatedTerms as $related)
                        <a href="{{ route('lexicon.show', $related['slug']) }}"><strong>{{ $related['title'] }}</strong><span>{{ $related['summary'] }}</span></a>
                    @endforeach
                </div>
            @endif

            <a class="lexicon-back-link" href="{{ route('lexicon.index') }}">← Alle Begriffe von A bis Z</a>
        </aside>
    </div>
@endsection
