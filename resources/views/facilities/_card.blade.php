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
        <div class="result-card__actions">
            @if(!empty($facility->website))
                <a class="card-action-btn" href="{{ $facility->website }}" target="_blank" rel="noopener" aria-label="Website von {{ $facility->name }} öffnen">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a8 8 0 1 1 0 16A8 8 0 0 1 10 2Zm0 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm0 1c.9 0 1.8.8 2.5 2H7.5c.7-1.2 1.6-2 2.5-2Zm3.9 2a5 5 0 0 1 .6 2H14a10 10 0 0 0-.5-2h.4Zm-8.2 0h.4A10 10 0 0 0 5.6 8H4.5a5 5 0 0 1 1.2-1.5ZM4.1 9H5.5a10.8 10.8 0 0 0 0 2H4.1a5 5 0 0 1 0-2Zm10.4 0h1.4a5 5 0 0 1 0 2h-1.4a10.8 10.8 0 0 0 0-2ZM5.6 12h.4a10 10 0 0 0 .5 2h-.4A5 5 0 0 1 5.6 12Zm8.4 0h.4a5 5 0 0 1-1.2 1.5h-.4a10 10 0 0 0 .5-1.5ZM7.5 14h5c-.7 1.2-1.6 2-2.5 2s-1.8-.8-2.5-2Z"/></svg>
                    Website
                </a>
            @endif
            @if(!empty($facility->email))
                <a class="card-action-btn" href="mailto:{{ $facility->email }}" aria-label="E-Mail an {{ $facility->name }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 4h16v12H2V4Zm1.5 1.5v.9l6.5 4.3 6.5-4.3v-.9H3.5Zm13 2.1-6.5 4.3L3.5 7.6V15h13V7.6Z"/></svg>
                    E-Mail
                </a>
            @endif
            <a href="{{ $facility->url ?? route('facilities.show', [$facility->city, $facility]) }}">Profil ansehen <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 6l4 4-4 4"/></svg></a>
        </div>
    </div>
</article>
