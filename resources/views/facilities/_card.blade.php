<article class="result-card">
    <div class="result-card__top">
        <span class="type-badge">{{ $facility->type }}</span>
        <span class="source-badge">Offizieller Datensatz</span>
    </div>
    <h2><a href="{{ $facility->url ?? route('facilities.show', [$facility->city, $facility]) }}">{{ $facility->name }}</a></h2>
    <p class="address">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.3"/></svg>
        <span>{{ $facility->address }}, {{ $facility->postal_code }} {{ $facility->city->name }}</span>
    </p>
    @if ($facility->phone)
        <p class="phone-line">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.3 3.5 10 7.8 8.1 10a15.7 15.7 0 0 0 5.9 5.9l2.2-1.9 4.3 2.7-.7 3.2c-.2.8-.9 1.3-1.7 1.3A15.3 15.3 0 0 1 2.8 5.9c0-.8.5-1.5 1.3-1.7l3.2-.7Z"/></svg>
            <a class="phone-link" href="tel:{{ $facility->phone }}">{{ $facility->formattedPhone() }}</a>
        </p>
    @else
        <p class="phone-line phone-line--pending">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.3 3.5 10 7.8 8.1 10a15.7 15.7 0 0 0 5.9 5.9l2.2-1.9 4.3 2.7-.7 3.2c-.2.8-.9 1.3-1.7 1.3A15.3 15.3 0 0 1 2.8 5.9c0-.8.5-1.5 1.3-1.7l3.2-.7Z"/></svg>
            <span>Telefon wird ergänzt</span>
        </p>
    @endif
    <div class="result-card__footer">
        <span>Quelle: LASV Brandenburg · Stand 31.12.2025</span>
        <a href="{{ $facility->url ?? route('facilities.show', [$facility->city, $facility]) }}">Profil ansehen <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg></a>
    </div>
</article>
