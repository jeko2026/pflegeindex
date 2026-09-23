@if(!empty($nearbyPlaces))
    <section class="detail-section nearby-places" aria-labelledby="nearby-places-title">
        <h2 id="nearby-places-title">Lage &amp; Umgebung</h2>
        <div class="nearby-places__grid">
            @foreach($nearbyPlaces as $place)
                <div class="nearby-places__item">
                    <strong>{{ $place['label'] }}</strong>
                    <span>{{ $place['name'] }}</span>
                    <small>{{ $place['distance'] }}</small>
                </div>
            @endforeach
        </div>
        <p class="nearby-places__hint">Entfernungen als Luftlinie auf Basis von OpenStreetMap-Daten.</p>
    </section>
@endif
