{{--
    Loads Leaflet (OpenStreetMap maps) on demand for <x-location-finder> and <x-venue-map>.
    window.rafloraLoadLeaflet() resolves with the Leaflet global; the files are only fetched the
    first time a map is actually shown, and only once per page however many maps there are.
--}}
<script>
    window.rafloraLoadLeaflet = window.rafloraLoadLeaflet || (function () {
        const base = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/';
        let pending = null;

        function attach(tag, attrs) {
            const el = document.createElement(tag);
            Object.assign(el, attrs, { crossOrigin: 'anonymous', referrerPolicy: 'no-referrer' });
            document.head.appendChild(el);
            return el;
        }

        return function () {
            if (window.L) return Promise.resolve(window.L);
            if (pending) return pending;

            pending = new Promise(function (resolve, reject) {
                attach('link', {
                    rel: 'stylesheet',
                    href: base + 'leaflet.min.css',
                    integrity: 'sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw==',
                });
                const script = attach('script', {
                    src: base + 'leaflet.min.js',
                    integrity: 'sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g==',
                });
                script.onload = function () {
                    window.L ? resolve(window.L) : reject(new Error('Leaflet unavailable'));
                };
                script.onerror = function () {
                    pending = null; // allow a retry on the next attempt
                    script.remove();
                    reject(new Error('Leaflet failed to load'));
                };
            });

            return pending;
        };
    })();

    window.rafloraMapTiles = window.rafloraMapTiles || function (L) {
        return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        });
    };
</script>
