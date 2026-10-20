{{--
    Read-only venue map with directions links, for Admin and Staff booking pages.

    Uses the pin the client set with <x-location-finder> when there is one. Older bookings (or
    clients who skipped the pin) only have the venue text, so the map then looks that address up
    and labels the marker as approximate. The Google Maps links work either way and open the
    Maps app on phones.
--}}
@props([
    'booking',
    'height' => 'h-56',
])

@php
    $venueText = trim((string) ($booking->venue ?? ''));
    $hasPin = $booking->hasVenueCoordinates();
    $destination = $hasPin ? $booking->venue_latitude . ',' . $booking->venue_longitude : $venueText;
    $directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($destination);
    $openMapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($destination);
@endphp

@if($venueText !== '' || $hasPin)
    <div data-venue-map-root {{ $attributes->merge(['class' => 'space-y-2']) }}>
        <div data-venue-map
             data-lat="{{ $hasPin ? $booking->venue_latitude : '' }}"
             data-lng="{{ $hasPin ? $booking->venue_longitude : '' }}"
             data-venue="{{ $venueText }}"
             data-search-url="{{ route('location.search') }}"
             class="relative isolate z-0 {{ $height }} w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
             role="region" aria-label="Map of the event venue">
            <div class="flex h-full items-center justify-center gap-1.5 text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading map…
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p data-venue-map-note class="text-[11px] text-slate-500">
                {{ $hasPin ? 'Exact location pinned by the client.' : 'Locating the venue from its address…' }}
            </p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    <i class="fa-solid fa-route" aria-hidden="true"></i> Get directions
                </a>
                <a href="{{ $openMapUrl }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Open in Google Maps
                </a>
            </div>
        </div>
    </div>

    <x-leaflet-assets />

    <script>
        (function () {
            if (!window.rafloraInitVenueMaps) {
                // "Grand Ballroom, Shangri-La Fort, BGC" → that, then "Shangri-La Fort, BGC", then "BGC".
                function addressQueries(venue) {
                    const parts = venue.split(',').map(function (part) { return part.trim(); }).filter(Boolean);
                    const queries = [];
                    for (let i = 0; i < parts.length && queries.length < 3; i++) {
                        const query = parts.slice(i).join(', ');
                        if (query.length >= 3) queries.push(query);
                    }
                    return queries;
                }

                function lookup(searchUrl, queries, index) {
                    if (index >= queries.length) return Promise.resolve(null);
                    return fetch(searchUrl + '?q=' + encodeURIComponent(queries[index]), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }).then(function (res) {
                        if (!res.ok) throw new Error('Location lookup failed');
                        return res.json();
                    }).then(function (data) {
                        const place = data.results && data.results[0];
                        return place
                            ? Object.assign({}, place, { matchedQuery: queries[index], partial: index > 0 })
                            : lookup(searchUrl, queries, index + 1);
                    });
                }

                function show(el) {
                    const root = el.closest('[data-venue-map-root]');
                    const note = root.querySelector('[data-venue-map-note]');
                    const venue = el.dataset.venue || '';
                    const lat = parseFloat(el.dataset.lat);
                    const lng = parseFloat(el.dataset.lng);
                    const pinned = Number.isFinite(lat) && Number.isFinite(lng);

                    const placeLookup = pinned
                        ? Promise.resolve({ latitude: lat, longitude: lng })
                        : lookup(el.dataset.searchUrl, addressQueries(venue), 0);

                    Promise.all([window.rafloraLoadLeaflet(), placeLookup]).then(function (loaded) {
                        const L = loaded[0];
                        const place = loaded[1];
                        if (!place) {
                            el.classList.add('hidden');
                            note.textContent = 'This address could not be placed on the map automatically. Use Google Maps to find the venue.';
                            return;
                        }

                        el.innerHTML = '';
                        const zoom = pinned ? 17 : (place.partial ? 13 : 16);
                        const map = L.map(el, { scrollWheelZoom: false }).setView([place.latitude, place.longitude], zoom);
                        window.rafloraMapTiles(L).addTo(map);

                        const popup = document.createElement('div');
                        popup.className = 'text-xs';
                        popup.textContent = venue || 'Event venue';
                        L.marker([place.latitude, place.longitude], { title: venue || 'Event venue' }).addTo(map).bindPopup(popup);

                        if (pinned) {
                            note.textContent = 'Exact location pinned by the client.';
                        } else if (place.partial) {
                            note.textContent = 'Approximate area only (matched "' + place.matchedQuery + '"). Confirm the exact venue with the client.';
                        } else {
                            note.textContent = 'Approximate location found from the venue address. Confirm with the client before dispatch.';
                        }
                    }).catch(function () {
                        el.classList.add('hidden');
                        note.textContent = 'The map could not be loaded right now. Use the links to open the venue in Google Maps.';
                    });
                }

                // Maps are built only once they scroll into view (and are not inside a hidden tab),
                // because Leaflet cannot size a map whose container is not displayed.
                window.rafloraInitVenueMaps = function () {
                    const observer = 'IntersectionObserver' in window
                        ? new IntersectionObserver(function (entries) {
                            entries.forEach(function (entry) {
                                if (entry.isIntersecting) {
                                    observer.unobserve(entry.target);
                                    show(entry.target);
                                }
                            });
                        }, { rootMargin: '200px' })
                        : null;

                    document.querySelectorAll('[data-venue-map]:not([data-venue-map-ready])').forEach(function (el) {
                        el.setAttribute('data-venue-map-ready', '');
                        observer ? observer.observe(el) : show(el);
                    });
                };
            }

            window.rafloraInitVenueMaps();
        })();
    </script>
@endif
