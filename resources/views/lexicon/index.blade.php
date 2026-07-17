@extends('layouts.app')

@section('title', 'Pflegelexikon – Pflegebegriffe einfach erklärt | PflegeIndex')
@section('description', 'Wichtige Begriffe rund um Pflegegrad, Pflegegeld, ambulante Pflege und Pflegeleistungen einfach und verständlich erklärt.')

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'DefinedTermSet',
        'name' => 'Pflegelexikon von PflegeIndex',
        'url' => route('lexicon.index'),
        'hasDefinedTerm' => $terms->map(fn ($term, $slug) => [
            '@type' => 'DefinedTerm',
            'name' => $term['title'],
            'description' => $term['summary'],
            'url' => route('lexicon.show', $slug),
        ])->values(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Pflegelexikon</span></p>
            <p class="eyebrow">Begriffe einfach erklärt</p>
            <h1>Pflegelexikon</h1>
            <p class="page-hero__lead">Verständliche Erklärungen zu Pflegeformen, Leistungen und wichtigen Begriffen – für Pflegebedürftige und Angehörige.</p>
        </div>
    </section>

    <div class="container lexicon-page">
        <nav class="lexicon-alphabet" aria-label="Pflegelexikon nach Anfangsbuchstaben">
            @foreach(range('A', 'Z') as $letter)
                @if($groups->has($letter))
                    <a href="#buchstabe-{{ strtolower($letter) }}">{{ $letter }}</a>
                @else
                    <span aria-hidden="true">{{ $letter }}</span>
                @endif
            @endforeach
        </nav>

        <div class="lexicon-intro-note">
            <strong>Einfach und sorgfältig erklärt</strong>
            <p>Die Inhalte geben einen ersten Überblick. Leistungsansprüche hängen von der persönlichen Situation ab und sollten mit der Pflegekasse oder einer Pflegeberatung geklärt werden.</p>
        </div>

        <div class="lexicon-groups">
            @foreach($groups as $letter => $letterTerms)
                <section class="lexicon-group" id="buchstabe-{{ strtolower($letter) }}">
                    <h2>{{ $letter }}</h2>
                    <div class="lexicon-grid">
                        @foreach($letterTerms as $slug => $term)
                            <a class="lexicon-card" href="{{ route('lexicon.show', $slug) }}">
                                <strong>{{ $term['title'] }}</strong>
                                <span>{{ $term['summary'] }}</span>
                                <small>Einfach erklärt <span aria-hidden="true">→</span></small>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection
