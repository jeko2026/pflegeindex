@php
$cardPhone = \App\Support\PublicContact::phone($facility->phone);
$cardEmail = \App\Support\PublicContact::email($facility->email);
$cardWebsite = \App\Support\PublicContact::website($facility->website);
$cardUrl = $facility->url ?? route($config['facilityRoute'], [$city ?? $facility->city, $facility]);
$address = $config['addressFromRecord'] ? $facility->address : $facility->address.', '.$facility->postal_code.' '.$facility->city->name;
@endphp
<article class="result-card">
<div class="result-card__top"><span class="type-badge">{{ $facility->type }}</span><span class="source-badge">{{ $config['sourceLabel'] }}</span></div>
<h2><a href="{{ $cardUrl }}">{{ $facility->name }}</a></h2>
<p class="address"><span>{{ $address }}</span></p>
@if($cardPhone)
<p class="phone-line"><a class="phone-link" href="tel:{{ $cardPhone }}">{{ $facility->formattedPhone() }}</a></p>
@else
<p class="phone-line phone-line--pending">Telefon wird ergänzt</p>
@endif
<div class="result-card__footer"><span>{{ $config['sourceFooter'] }}</span><div class="result-card__actions">
@if($cardWebsite)<a class="card-action-btn" href="{{ $cardWebsite }}" target="_blank" rel="noopener noreferrer">Website</a>@endif
@if($cardEmail)<a class="card-action-btn" href="mailto:{{ $cardEmail }}">E-Mail</a>@endif
<a href="{{ $cardUrl }}">Profil ansehen</a>
</div></div></article>