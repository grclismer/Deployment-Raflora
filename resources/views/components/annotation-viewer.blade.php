{{--
    Enlarged view of an annotated inspiration photo, shared by the booking pages and forms.

      window.RafloraAnnotationViewer.open({ stage, legend, title, onSelect })

    stage    the photo's annotation stage: an element holding the <img> and the boxes, positioned
             as percentages of the photo. A copy is enlarged as one unit, so every box stays on the
             pixels the AI marked at any zoom (the photo is never resized apart from its boxes).
    legend   optional list whose entries carry data-line-id (copied next to the photo)
    onSelect optional callback(lineId) when a box or legend entry is chosen in the viewer

    Fit-to-screen to 400% zoom with buttons, + / − / 0 keys, Ctrl+wheel or a two-finger pinch;
    drag, scroll or the arrow keys pan. Esc closes; focus stays in the viewer while it is open and
    returns to the control that opened it.
--}}
@once
<div id="raflora-annotation-viewer" class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-labelledby="raflora-annotation-viewer-title">
    <div class="absolute inset-0 bg-slate-950/85" data-viewer-close></div>
    <div class="relative z-10 flex h-full flex-col p-2 sm:p-4">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
            <div class="flex flex-wrap items-center gap-2 border-b border-white/10 px-3 py-2 text-white">
                <h2 id="raflora-annotation-viewer-title" class="min-w-0 flex-1 truncate text-sm font-semibold" data-viewer-title>Inspiration photo</h2>
                <div class="flex items-center gap-1" role="group" aria-label="Zoom">
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/15 bg-white/5 text-lg font-bold hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" data-viewer-zoom-out aria-label="Zoom out">−</button>
                    <output class="w-14 text-center text-xs font-semibold tabular-nums" data-viewer-zoom-level aria-live="polite">Fit</output>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/15 bg-white/5 text-lg font-bold hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" data-viewer-zoom-in aria-label="Zoom in">+</button>
                    <button type="button" class="ml-1 inline-flex h-9 items-center justify-center rounded-lg border border-white/15 bg-white/5 px-2.5 text-xs font-semibold hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" data-viewer-zoom-reset>Fit to screen</button>
                </div>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/5 hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" data-viewer-close aria-label="Close enlarged photo">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
                <div class="relative min-h-0 flex-1 cursor-grab overflow-auto bg-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-400" style="touch-action: pan-x pan-y;" tabindex="0" data-viewer-scroll aria-label="Enlarged photo. Use the arrow keys to move around.">
                    <div style="display: grid; place-items: center; min-width: 100%; min-height: 100%; width: max-content; height: max-content; padding: 0.75rem;" data-viewer-canvas></div>
                </div>
                <div class="max-h-44 shrink-0 overflow-y-auto border-t border-white/10 bg-white p-2 lg:max-h-none lg:w-72 lg:border-l lg:border-t-0" data-viewer-legend>
                    <p class="px-1.5 pb-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Marked materials</p>
                    <div data-viewer-legend-list></div>
                </div>
            </div>
            <p class="border-t border-white/10 px-3 py-1.5 text-[11px] text-white/70">Drag, scroll or use the arrow keys to move around. Pinch, Ctrl + scroll, or + and − to zoom. Esc closes.</p>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.RafloraAnnotationViewer) return;

        const ZOOM_MIN = 1;
        const ZOOM_MAX = 4;
        const ZOOM_STEP = 1.25;
        const root = document.getElementById('raflora-annotation-viewer');
        const scroll = root.querySelector('[data-viewer-scroll]');
        const canvas = root.querySelector('[data-viewer-canvas]');
        const legendBox = root.querySelector('[data-viewer-legend]');
        const legendList = root.querySelector('[data-viewer-legend-list]');
        const title = root.querySelector('[data-viewer-title]');
        const level = root.querySelector('[data-viewer-zoom-level]');
        let stage = null;
        let zoom = ZOOM_MIN;
        let ratio = 1;
        let options = {};
        let returnFocus = null;
        let pinned = null;

        // The width that shows the whole photo inside the visible area, at its own shape.
        function fitWidth() {
            const style = getComputedStyle(canvas);
            const padX = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
            const padY = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
            const width = Math.max(80, scroll.clientWidth - padX);
            const height = Math.max(80, scroll.clientHeight - padY);
            return Math.min(width, height * ratio);
        }

        // Scales photo and boxes together; the point at the middle of the view stays in the middle.
        function applyZoom(next) {
            if (!stage) return;
            const centreX = (scroll.scrollLeft + scroll.clientWidth / 2) / Math.max(1, scroll.scrollWidth);
            const centreY = (scroll.scrollTop + scroll.clientHeight / 2) / Math.max(1, scroll.scrollHeight);
            zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, next));
            stage.style.width = Math.round(fitWidth() * zoom) + 'px';
            level.textContent = zoom === ZOOM_MIN ? 'Fit' : Math.round(zoom * 100) + '%';
            scroll.scrollLeft = centreX * scroll.scrollWidth - scroll.clientWidth / 2;
            scroll.scrollTop = centreY * scroll.scrollHeight - scroll.clientHeight / 2;
        }

        function highlight(lineId) {
            root.querySelectorAll('[data-annotation-box], [data-annotation-point]').forEach(function (box) {
                const match = Boolean(lineId) && box.dataset.lineId === lineId;
                box.toggleAttribute('data-active', match);
                box.toggleAttribute('data-dimmed', Boolean(lineId) && !match);
                box.style.opacity = lineId && !match ? '0.35' : '';
            });
            legendList.querySelectorAll('[data-line-id]').forEach(function (entry) {
                const match = Boolean(lineId) && entry.dataset.lineId === lineId;
                entry.toggleAttribute('data-active', match);
                entry.classList.toggle('bg-emerald-50', match);
                if (match) entry.scrollIntoView({ block: 'nearest' });
            });
        }

        function open(config) {
            if (!config || !config.stage) return;
            // Lives directly under <body>, so no transformed or clipped parent can trap the overlay.
            if (root.parentElement !== document.body) document.body.appendChild(root);
            options = config;
            returnFocus = document.activeElement;
            pinned = null;
            title.textContent = config.title || 'Inspiration photo';

            stage = config.stage.cloneNode(true);
            stage.querySelectorAll('[data-image-lightbox-trigger], [data-viewer-ignore]').forEach(function (el) { el.remove(); });
            stage.removeAttribute('id');
            stage.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
            stage.style.margin = '0';
            stage.style.maxWidth = 'none';
            stage.style.position = 'relative';
            stage.style.overflow = 'hidden';
            stage.classList.remove('w-full', 'h-full', 'max-w-full');
            const image = stage.querySelector('img');
            if (image) {
                image.style.width = '100%';
                image.style.height = 'auto';
                image.style.maxWidth = 'none';
                image.style.maxHeight = 'none';
                image.style.objectFit = 'fill';
                image.draggable = false;
            }
            const source = config.stage.querySelector('img');
            ratio = source && source.naturalWidth && source.naturalHeight ? source.naturalWidth / source.naturalHeight : 4 / 3;
            stage.style.aspectRatio = source && source.naturalWidth ? source.naturalWidth + ' / ' + source.naturalHeight : '';
            canvas.replaceChildren(stage);

            legendList.replaceChildren();
            if (config.legend) {
                const legend = config.legend.cloneNode(true);
                legend.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
                legendList.appendChild(legend);
            }
            legendBox.classList.remove('hidden');
            const hasLegend = Array.from(legendList.querySelectorAll('[data-line-id]')).some(function (entry) { return !entry.closest('.hidden'); });
            legendBox.classList.toggle('hidden', !hasLegend);

            root.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            requestAnimationFrame(function () {
                applyZoom(ZOOM_MIN);
                root.querySelector('[data-viewer-close][aria-label]').focus();
            });
            if (image && !image.complete) image.addEventListener('load', function () { applyZoom(zoom); }, { once: true });
        }

        function close() {
            if (root.classList.contains('hidden')) return;
            root.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            canvas.replaceChildren();
            legendList.replaceChildren();
            stage = null;
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
        }

        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-viewer-close]')) {
                close();
                return;
            }
            if (event.target.closest('[data-viewer-zoom-in]')) return applyZoom(zoom * ZOOM_STEP);
            if (event.target.closest('[data-viewer-zoom-out]')) return applyZoom(zoom / ZOOM_STEP);
            if (event.target.closest('[data-viewer-zoom-reset]')) return applyZoom(ZOOM_MIN);

            const trigger = event.target.closest('[data-annotation-box], [data-annotation-point], [data-line-id]');
            if (!trigger || !trigger.dataset.lineId || dragged) return;
            // Handled here (not by the page's own listeners) so the viewer and the page stay in step.
            event.preventDefault();
            event.stopPropagation();
            pinned = pinned === trigger.dataset.lineId ? null : trigger.dataset.lineId;
            highlight(pinned);
            if (typeof window.highlightAnnotationLine === 'function') window.highlightAnnotationLine(pinned);
            if (pinned && typeof options.onSelect === 'function') options.onSelect(pinned);
        });

        document.addEventListener('keydown', function (event) {
            if (root.classList.contains('hidden')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            } else if (event.key === '+' || event.key === '=') {
                event.preventDefault();
                applyZoom(zoom * ZOOM_STEP);
            } else if (event.key === '-' || event.key === '_') {
                event.preventDefault();
                applyZoom(zoom / ZOOM_STEP);
            } else if (event.key === '0') {
                event.preventDefault();
                applyZoom(ZOOM_MIN);
            } else if (event.key === 'Tab') {
                // Keep keyboard focus inside the viewer while it is open.
                const focusable = Array.from(root.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])'))
                    .filter(function (el) { return !el.disabled && el.offsetParent !== null; });
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

        scroll.addEventListener('wheel', function (event) {
            if (!event.ctrlKey) return;
            event.preventDefault();
            applyZoom(event.deltaY < 0 ? zoom * 1.1 : zoom / 1.1);
        }, { passive: false });

        // Mouse drag pans; two touch points pinch-zoom (one finger scrolls natively).
        const pointers = new Map();
        let dragged = false;
        let dragStart = null;
        let pinchStart = null;
        scroll.addEventListener('pointerdown', function (event) {
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            dragged = false;
            if (event.pointerType === 'mouse' && event.button === 0) {
                dragStart = { x: event.clientX, y: event.clientY, left: scroll.scrollLeft, top: scroll.scrollTop };
            }
            if (pointers.size === 2) {
                const [a, b] = Array.from(pointers.values());
                pinchStart = { distance: Math.hypot(a.x - b.x, a.y - b.y), zoom: zoom };
            }
        });
        scroll.addEventListener('pointermove', function (event) {
            if (!pointers.has(event.pointerId)) return;
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (pinchStart && pointers.size === 2) {
                const [a, b] = Array.from(pointers.values());
                const distance = Math.hypot(a.x - b.x, a.y - b.y);
                if (pinchStart.distance > 0) applyZoom(pinchStart.zoom * distance / pinchStart.distance);
                dragged = true;
            } else if (dragStart) {
                const dx = event.clientX - dragStart.x;
                const dy = event.clientY - dragStart.y;
                if (Math.abs(dx) + Math.abs(dy) > 4) {
                    dragged = true;
                    scroll.classList.add('cursor-grabbing');
                    scroll.scrollLeft = dragStart.left - dx;
                    scroll.scrollTop = dragStart.top - dy;
                }
            }
        });
        const endPointer = function (event) {
            pointers.delete(event.pointerId);
            if (pointers.size < 2) pinchStart = null;
            if (pointers.size === 0) {
                dragStart = null;
                scroll.classList.remove('cursor-grabbing');
                setTimeout(function () { dragged = false; }, 0);
            }
        };
        scroll.addEventListener('pointerup', endPointer);
        scroll.addEventListener('pointercancel', endPointer);
        scroll.addEventListener('pointerleave', endPointer);

        window.addEventListener('resize', function () {
            if (!root.classList.contains('hidden')) applyZoom(zoom);
        });

        window.RafloraAnnotationViewer = { open: open, close: close };
    })();
</script>
@endonce
