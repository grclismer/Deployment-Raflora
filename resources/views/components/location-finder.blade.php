{{--
    Venue location finder for the booking forms.

    Lets the client search for the venue, use their current location, or click/drag a pin on an
    OpenStreetMap map. The pin is submitted as venue_latitude / venue_longitude (hidden inputs, so
    the component must sit inside the <form>). The finder never edits the page's address fields
    itself — it dispatches events on its root element and the page decides what to fill:

      location-finder:select  detail = { specific, city, label, name, latitude, longitude }
                              (specific/city are '' when no address was found at the pin)
      location-finder:clear   the pin was removed

    window.RafloraLocationFinder.get(id) returns { open(), setCoordinates(lat, lng), clear() }
    for scripts that restore saved drafts.
--}}
@props([
    'id' => 'venue-finder',
    'latitude' => null,
    'longitude' => null,
    'embedded' => false,
    'open' => false,
])

<div id="{{ $id }}"
     data-location-finder
     data-finder-embedded="{{ $embedded ? 'true' : 'false' }}"
     data-search-url="{{ route('location.search') }}"
     data-reverse-url="{{ route('location.reverse') }}"
     {{ $attributes->merge(['class' => $embedded ? 'rounded-xl border border-emerald-100 bg-emerald-50/20 p-2 sm:p-2.5 space-y-2' : 'rounded-xl border border-slate-200 bg-slate-50/60']) }}>
    <input type="hidden" name="venue_latitude" value="{{ $latitude }}" data-finder-lat>
    <input type="hidden" name="venue_longitude" value="{{ $longitude }}" data-finder-lng>

    @if($embedded)
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <h3 class="text-xs sm:text-sm font-bold text-emerald-950">Venue Location</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-finder-locate class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs">
                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0013 3.06V1h-2v2.06A8.994 8.994 0 003.06 11H1v2h2.06A8.994 8.994 0 0011 20.94V23h2v-2.06A8.994 8.994 0 0020.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"></path></svg>
                    Use my location
                </button>
                <button type="button" data-finder-toggle aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $id }}-panel"
                        class="sm:hidden inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-800 transition hover:bg-emerald-50">
                    <span data-finder-toggle-label>{{ $open ? 'Hide map' : 'Select on Map' }}</span>
                </button>
            </div>
        </div>

        <div id="{{ $id }}-panel" data-finder-panel class="{{ $open ? 'block' : 'hidden sm:block' }} space-y-2">
            <div class="flex gap-2">
                <label for="{{ $id }}-query" class="sr-only">Search venue, landmark, or street</label>
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="search" id="{{ $id }}-query" data-finder-query autocomplete="off" maxlength="200"
                           placeholder="Search venue or landmark (e.g. Venice Grand Canal Mall)"
                           class="w-full pl-8 pr-2.5 py-1 rounded-lg border border-slate-200 bg-white text-xs text-[#0B1E43] outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="button" data-finder-search class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700 transition">
                    Search
                </button>
            </div>

            <p data-finder-status role="status" aria-live="polite" class="hidden text-[11px] text-slate-500"></p>
            <ul data-finder-results class="hidden max-h-40 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 bg-white" aria-label="Matching places"></ul>

            <div data-finder-map class="relative isolate z-0 h-32 sm:h-36 lg:h-36 w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100" role="application" aria-label="Venue map. Click the map to place the venue pin."></div>

            <div class="flex items-center justify-between gap-2 pt-0.5">
                <div class="flex items-center gap-1.5">
                    <span data-finder-badge class="hidden inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                        <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        <span data-finder-summary>Venue pinned on map</span>
                    </span>
                    <span data-finder-unpinned class="text-[11px] text-slate-500">
                        Click the map or search to place pin
                    </span>
                </div>
                <button type="button" data-finder-clear class="hidden text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline">
                    Remove pin
                </button>
            </div>

            <p class="text-[11px] text-slate-500 flex items-start gap-1">
                <span class="text-slate-400 font-bold shrink-0">ⓘ</span>
                <span><strong>Tip:</strong> Click the map or drag the pin to the exact spot. You can still edit the address.</span>
            </p>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
            <p class="flex min-w-0 items-center gap-2 text-xs">
                <i class="fa-solid fa-map-location-dot text-emerald-600" aria-hidden="true"></i>
                <span data-finder-summary class="text-slate-600">Find the venue on the map to pin its exact location (optional).</span>
            </p>
            <div class="flex items-center gap-2">
                <button type="button" data-finder-clear class="hidden text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline">Remove pin</button>
                <button type="button" data-finder-toggle aria-expanded="false" aria-controls="{{ $id }}-panel"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                    <span data-finder-toggle-label>Find on map</span>
                </button>
            </div>
        </div>

        <div id="{{ $id }}-panel" data-finder-panel class="hidden space-y-2.5 border-t border-slate-200 p-3">
            <div class="flex flex-col gap-2 sm:flex-row">
                <label for="{{ $id }}-query" class="sr-only">Search for the venue name, landmark, or address</label>
                <input type="search" id="{{ $id }}-query" data-finder-query autocomplete="off" maxlength="200"
                       placeholder="Search venue, landmark, or street (e.g. Manila Hotel)"
                       class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm text-[#0B1E43] outline-none focus:ring-2 focus:ring-emerald-500">
                <div class="flex gap-2">
                    <button type="button" data-finder-search
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60 sm:flex-none">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search
                    </button>
                    <button type="button" data-finder-locate
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 sm:flex-none">
                        <i class="fa-solid fa-location-arrow" aria-hidden="true"></i> Use my location
                    </button>
                </div>
            </div>

            <p data-finder-status role="status" aria-live="polite" class="hidden text-xs text-slate-500"></p>

            <ul data-finder-results class="hidden max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 bg-white" aria-label="Matching places"></ul>

            <div data-finder-map class="relative isolate z-0 h-64 w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 sm:h-72" role="application" aria-label="Venue map. Click the map to place the venue pin."></div>

            <p class="text-[11px] text-slate-500">Tip: click the map or drag the pin to the exact spot. Choosing a place fills in the venue address — you can still edit it.</p>
        </div>
    @endif
</div>

<x-leaflet-assets />

<script>
    (function () {
        if (window.RafloraLocationFinder) return;

        const DEFAULT_CENTER = [14.5995, 120.9842]; // Metro Manila
        const DEFAULT_ZOOM = 11;
        const PIN_ZOOM = 17;
        const instances = {};

        function create(root) {
            const q = function (selector) { return root.querySelector(selector); };
            const latInput = q('[data-finder-lat]');
            const lngInput = q('[data-finder-lng]');
            const toggleBtn = q('[data-finder-toggle]');
            const toggleLabel = q('[data-finder-toggle-label]');
            const panel = q('[data-finder-panel]');
            const queryInput = q('[data-finder-query]');
            const searchBtn = q('[data-finder-search]');
            const locateBtn = q('[data-finder-locate]');
            const clearBtn = q('[data-finder-clear]');
            const statusEl = q('[data-finder-status]');
            const resultsEl = q('[data-finder-results]');
            const summaryEl = q('[data-finder-summary]');
            const mapEl = q('[data-finder-map]');
            const searchUrl = root.dataset.searchUrl;
            const reverseUrl = root.dataset.reverseUrl;
            const defaultSummary = summaryEl.textContent;

            let L = null;
            let map = null;
            let marker = null;
            let mapPromise = null;
            let lookupSeq = 0; // ignores responses from lookups that a newer action superseded

            function setStatus(message, tone) {
                statusEl.textContent = message || '';
                statusEl.className = 'text-xs ' + (tone === 'error' ? 'font-semibold text-rose-600' : tone === 'success' ? 'font-semibold text-emerald-700' : 'text-slate-500');
                statusEl.classList.toggle('hidden', !message);
            }

            function currentCoords() {
                const lat = parseFloat(latInput.value);
                const lng = parseFloat(lngInput.value);
                return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
            }

            function isOpen() {
                return !panel.classList.contains('hidden');
            }

            function refreshSummary() {
                const coords = currentCoords();
                if (summaryEl) {
                    summaryEl.textContent = coords
                        ? 'Venue pinned on map (' + coords[0].toFixed(5) + ', ' + coords[1].toFixed(5) + ')'
                        : defaultSummary;
                    summaryEl.classList.toggle('text-emerald-700', !!coords);
                    summaryEl.classList.toggle('font-semibold', !!coords);
                    summaryEl.classList.toggle('text-slate-600', !coords);
                }
                const badge = root.querySelector('[data-finder-badge]');
                if (badge) badge.classList.toggle('hidden', !coords);
                const unpinned = root.querySelector('[data-finder-unpinned]');
                if (unpinned) unpinned.classList.toggle('hidden', !!coords);
                if (clearBtn) clearBtn.classList.toggle('hidden', !coords);
                if (toggleLabel) toggleLabel.textContent = isOpen() ? 'Hide map' : (coords ? 'Adjust pin' : 'Select on Map');
            }

            function emit(name, detail) {
                root.dispatchEvent(new CustomEvent('location-finder:' + name, { detail: detail, bubbles: true }));
            }

            function fetchJson(url) {
                return fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (body) {
                        if (!res.ok) {
                            throw new Error(res.status === 429
                                ? 'Too many location lookups. Please wait a minute and try again.'
                                : (body.message || 'The location service is unavailable right now.'));
                        }
                        return body;
                    });
                });
            }

            function placeMarker(lat, lng, zoom) {
                if (!map) return;
                if (!marker) {
                    const pinIcon = L.divIcon({
                        className: 'raflora-pin-marker',
                        html: '<div style="background-color:#059669; width:26px; height:26px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; border:2px solid #ffffff; box-shadow:0 3px 6px rgba(0,0,0,0.3);"><div style="width:7px; height:7px; background:white; border-radius:50%; transform:rotate(45deg);"></div></div>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 26],
                        popupAnchor: [0, -26]
                    });
                    marker = L.marker([lat, lng], { icon: pinIcon, draggable: true, autoPan: true, title: 'Venue location' }).addTo(map);
                    marker.on('dragend', function () {
                        const point = marker.getLatLng();
                        pinAt(point.lat, point.lng, false);
                    });
                } else {
                    marker.setLatLng([lat, lng]);
                }
                if (zoom) {
                    map.setView([lat, lng], Math.max(map.getZoom(), zoom));
                } else {
                    map.panTo([lat, lng]);
                }
            }

            function setCoordinates(lat, lng) {
                if (lat === null || lat === undefined || lat === '' || !Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) {
                    latInput.value = '';
                    lngInput.value = '';
                    if (marker) { marker.remove(); marker = null; }
                } else {
                    latInput.value = Number(lat).toFixed(7);
                    lngInput.value = Number(lng).toFixed(7);
                    placeMarker(Number(lat), Number(lng));
                }
                refreshSummary();
            }

            function hideResults() {
                resultsEl.innerHTML = '';
                resultsEl.classList.add('hidden');
            }

            function ensureMap() {
                if (mapPromise) return mapPromise;
                mapPromise = window.rafloraLoadLeaflet().then(function (leaflet) {
                    L = leaflet;
                    const coords = currentCoords();
                    map = L.map(mapEl, { scrollWheelZoom: false })
                        .setView(coords || DEFAULT_CENTER, coords ? PIN_ZOOM : DEFAULT_ZOOM);
                    window.rafloraMapTiles(L).addTo(map);
                    map.on('click', function (e) { pinAt(e.latlng.lat, e.latlng.lng, false); });
                    if (coords) placeMarker(coords[0], coords[1]);
                    return map;
                }).catch(function (err) {
                    mapPromise = null;
                    setStatus('The map could not be loaded. You can still search above or type the address manually.', 'error');
                    throw err;
                });
                return mapPromise;
            }

            // Pin dropped by map click, pin drag, or "Use my location": look up the address there.
            function pinAt(lat, lng, zoomIn) {
                const seq = ++lookupSeq;
                hideResults();
                setCoordinates(lat, lng);
                if (zoomIn) placeMarker(lat, lng, PIN_ZOOM);
                setStatus('Looking up the address at this spot…');

                const coordsOnly = { latitude: lat, longitude: lng, specific: '', city: '', label: '', name: '' };
                fetchJson(reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
                    .then(function (data) {
                        if (seq !== lookupSeq) return;
                        const place = data && data.result;
                        if (place) {
                            setStatus('Pinned: ' + place.label, 'success');
                            emit('select', Object.assign({}, place, { latitude: lat, longitude: lng }));
                        } else {
                            setStatus('Pin set. No street address was found at this spot — please type the venue address yourself.');
                            emit('select', coordsOnly);
                        }
                    })
                    .catch(function (err) {
                        if (seq !== lookupSeq) return;
                        setStatus('Pin set, but the address lookup failed: ' + err.message + ' Please type the venue address yourself.', 'error');
                        emit('select', coordsOnly);
                    });
            }

            function choose(place) {
                lookupSeq++;
                hideResults();
                setCoordinates(place.latitude, place.longitude);
                placeMarker(place.latitude, place.longitude, PIN_ZOOM);
                setStatus('Pinned: ' + place.label, 'success');
                emit('select', place);
            }

            function renderResults(results) {
                hideResults();
                results.forEach(function (place) {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'block w-full px-3 py-2 text-left transition hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none';
                    const title = document.createElement('span');
                    title.className = 'block text-sm font-semibold text-slate-800';
                    title.textContent = place.name || place.specific;
                    const detail = document.createElement('span');
                    detail.className = 'block text-xs text-slate-500';
                    detail.textContent = place.label;
                    button.append(title, detail);
                    button.addEventListener('click', function () { choose(place); });
                    item.appendChild(button);
                    resultsEl.appendChild(item);
                });
                resultsEl.classList.remove('hidden');
            }

            function search() {
                const term = queryInput.value.trim();
                if (term.length < 3) {
                    setStatus('Type at least 3 characters to search.', 'error');
                    queryInput.focus();
                    return;
                }

                const seq = ++lookupSeq;
                searchBtn.disabled = true;
                hideResults();
                setStatus('Searching…');

                fetchJson(searchUrl + '?q=' + encodeURIComponent(term))
                    .then(function (data) {
                        if (seq !== lookupSeq) return;
                        const results = (data && data.results) || [];
                        if (!results.length) {
                            setStatus('No matches found. Try a nearby landmark or the city name, or click the map to drop a pin.');
                            return;
                        }
                        setStatus(results.length === 1 ? '1 match found. Select it to pin the venue.' : results.length + ' matches found. Select the correct venue.');
                        renderResults(results);
                    })
                    .catch(function (err) {
                        if (seq === lookupSeq) setStatus(err.message, 'error');
                    })
                    .finally(function () { searchBtn.disabled = false; });
            }

            function locate() {
                if (!navigator.geolocation) {
                    setStatus('Your browser does not support location access. Please search for the venue instead.', 'error');
                    return;
                }
                locateBtn.disabled = true;
                setStatus('Getting your current location…');
                navigator.geolocation.getCurrentPosition(function (position) {
                    locateBtn.disabled = false;
                    ensureMap().catch(function () {}).then(function () {
                        pinAt(position.coords.latitude, position.coords.longitude, true);
                    });
                }, function (error) {
                    locateBtn.disabled = false;
                    setStatus(error.code === error.PERMISSION_DENIED
                        ? 'Location access was blocked. Allow it in your browser settings, or search for the venue instead.'
                        : 'We could not get your current location. Please search for the venue instead.', 'error');
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
            }

            function setOpen(open) {
                panel.classList.toggle('hidden', !open);
                toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                refreshSummary();
                if (!open) return;

                queryInput.focus({ preventScroll: true });
                ensureMap().then(function (m) {
                    m.invalidateSize(); // the container had no size while the panel was hidden
                    const coords = currentCoords();
                    if (coords) m.setView(coords, Math.max(m.getZoom(), PIN_ZOOM));
                }).catch(function () {});
            }

            function clear() {
                lookupSeq++;
                hideResults();
                setCoordinates(null, null);
                setStatus(isOpen() ? 'Pin removed.' : '');
                emit('clear', {});
            }

            toggleBtn.addEventListener('click', function () { setOpen(!isOpen()); });
            searchBtn.addEventListener('click', search);
            locateBtn.addEventListener('click', locate);
            clearBtn.addEventListener('click', clear);
            queryInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // never submit the booking form from the search box
                    search();
                }
            });

            refreshSummary();

            if (root.dataset.finderEmbedded === 'true' && (window.innerWidth >= 640 || currentCoords())) {
                setOpen(true);
            }

            return { open: function () { setOpen(true); }, setCoordinates: setCoordinates, clear: clear };
        }

        function get(id) {
            if (instances[id]) return instances[id];
            const root = document.getElementById(id);
            if (!root || !root.hasAttribute('data-location-finder')) return null;
            instances[id] = create(root);
            return instances[id];
        }

        window.RafloraLocationFinder = { get: get };

        function initAll() {
            document.querySelectorAll('[data-location-finder]').forEach(function (el) { get(el.id); });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    })();
</script>
