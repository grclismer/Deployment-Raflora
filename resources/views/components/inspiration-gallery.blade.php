{{--
    Every inspiration photo of a booking with its AI annotations (components.annotated-image),
    for the guest and client tracking pages. Each photo shows the areas it is for; with several
    photos, the area chips narrow the photo navigation to one area's photos.

    photos        App\Support\InspirationPhotos::galleryPhotos() entries plus a private 'url' each
                  (empty for a single-photo booking stored without images[])
    previewImage  URL of the first photo (and of the only one when photos is empty)
    overlayItems  every photo's rows from GeminiVisionService::normalizeAreaAnalysis (image_id set)

    Selecting a photo dispatches "inspiration-gallery:select" ({ imageId, index }) on the root, so
    the page can show that photo's materials.
--}}
@props([
    'id' => 'inspiration-gallery',
    'photos' => [],
    'previewImage',
    'overlayItems' => [],
    'imageWidth' => null,
    'imageHeight' => null,
    'imageAspectRatio' => null,
    'maxHeight' => null,
])

@php
    $photos = array_values($photos);
    $first = $photos[0] ?? null;
    $count = count($photos);
    $areas = collect($photos)->flatMap(fn ($photo) => $photo['areas'])->unique('key')->values();
    $titleFor = fn (?array $photo, int $number) => 'Inspiration photo ' . $number . ($count > 1 ? ' of ' . $count : '')
        . ($photo && $photo['areas'] !== [] ? ' — ' . implode(', ', array_column($photo['areas'], 'label')) : '');
@endphp

<div id="{{ $id }}" data-inspiration-gallery class="space-y-2">
    @if($count > 1)
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-bold text-slate-700" aria-live="polite">
                Photo <span data-gallery-current>1</span> of {{ $count }}
            </p>
            <div class="flex items-center gap-1">
                <button type="button" data-gallery-step="-1" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Previous photo">‹</button>
                <button type="button" data-gallery-step="1" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Next photo">›</button>
            </div>
        </div>
        @if($areas->count() > 1)
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Show photos for one area">
                <button type="button" data-gallery-area="" aria-pressed="true" class="rounded-full border px-2.5 py-1 text-[11px] font-semibold transition aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:text-emerald-800 border-slate-200 bg-white text-slate-600">All photos</button>
                @foreach($areas as $area)
                    <button type="button" data-gallery-area="{{ $area['key'] }}" aria-pressed="false" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold transition aria-pressed:border-emerald-600 aria-pressed:bg-emerald-50 aria-pressed:text-emerald-800 border-slate-200 bg-white text-slate-600">
                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $area['color'] }};" aria-hidden="true"></span>{{ $area['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
    @endif

    <div class="relative w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100" data-gallery-frame>
        @include('components.annotated-image', [
            'previewImage' => $first['url'] ?? $previewImage,
            'maxHeight' => $maxHeight,
            'imageAspectRatio' => $imageAspectRatio,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
            'overlayItems' => $overlayItems,
            'activeImageId' => $first['id'] ?? 1,
            'viewerTitle' => $titleFor($first, 1),
        ])
    </div>

    @if($first && $first['areas'] !== [])
        <p class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500" data-gallery-photo-areas>
            <span class="font-semibold text-slate-600">This photo is for:</span>
            @foreach($first['areas'] as $area)
                <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 font-semibold text-slate-700 ring-1 ring-slate-200"><span class="h-2 w-2 rounded-full" style="background-color: {{ $area['color'] }};" aria-hidden="true"></span>{{ $area['label'] }}</span>
            @endforeach
        </p>
    @endif

    @if($count > 1)
        <div class="flex items-center gap-1.5 overflow-x-auto py-1" role="list" aria-label="All inspiration photos">
            @foreach($photos as $position => $photo)
                <button type="button" role="listitem" data-gallery-thumb="{{ $position }}" aria-current="{{ $position === 0 ? 'true' : 'false' }}"
                        aria-label="{{ $titleFor($photo, $position + 1) }}"
                        class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 aria-[current=true]:border-emerald-600 aria-[current=false]:border-slate-200 aria-[current=false]:opacity-70 hover:opacity-100">
                    <img src="{{ $photo['url'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
                    <span class="absolute inset-x-0 bottom-0 bg-slate-900/60 text-center text-[9px] font-bold text-white">{{ $position + 1 }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>

@if($count > 1)
<script>
    (function () {
        const root = document.getElementById(@json($id));
        if (!root) return;
        const photos = @json($photos);
        const stage = root.querySelector('[data-annotated-image-stage]');
        const image = stage ? stage.querySelector('img') : null;
        const enlarge = stage ? stage.querySelector('[data-image-lightbox-trigger]') : null;
        const areasLine = root.querySelector('[data-gallery-photo-areas]');
        let current = 0;
        let areaFilter = '';

        const visible = function () {
            return photos.map(function (photo, index) { return index; })
                .filter(function (index) { return !areaFilter || photos[index].areas.some(function (area) { return area.key === areaFilter; }); });
        };
        const titleFor = function (photo, number) {
            return 'Inspiration photo ' + number + ' of ' + photos.length
                + (photo.areas.length ? ' — ' + photo.areas.map(function (area) { return area.label; }).join(', ') : '');
        };

        function select(index) {
            const photo = photos[index];
            if (!photo || !image) return;
            current = index;
            image.src = photo.url;
            if (enlarge) enlarge.dataset.imageSrc = photo.url;
            stage.dataset.viewerTitle = titleFor(photo, index + 1);
            // Only this photo's boxes and legend entries are shown.
            window.showAnnotationsForImage?.(stage, photo.id);
            root.querySelector('[data-gallery-current]').textContent = index + 1;
            root.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
                thumb.setAttribute('aria-current', Number(thumb.dataset.galleryThumb) === index ? 'true' : 'false');
            });
            if (areasLine) {
                areasLine.classList.toggle('hidden', photo.areas.length === 0);
                areasLine.querySelectorAll('span.inline-flex').forEach(function (chip) { chip.remove(); });
                photo.areas.forEach(function (area) {
                    const chip = document.createElement('span');
                    chip.className = 'inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 font-semibold text-slate-700 ring-1 ring-slate-200';
                    const dot = document.createElement('span');
                    dot.className = 'h-2 w-2 rounded-full';
                    dot.style.backgroundColor = area.color;
                    dot.setAttribute('aria-hidden', 'true');
                    chip.append(dot, document.createTextNode(area.label));
                    areasLine.appendChild(chip);
                });
            }
            const order = visible();
            const position = order.indexOf(index);
            root.querySelector('[data-gallery-step="-1"]').disabled = position <= 0;
            root.querySelector('[data-gallery-step="1"]').disabled = position === -1 || position >= order.length - 1;
            root.dispatchEvent(new CustomEvent('inspiration-gallery:select', { bubbles: true, detail: { imageId: photo.id, index: index } }));
        }

        root.addEventListener('click', function (event) {
            const thumb = event.target.closest('[data-gallery-thumb]');
            if (thumb) return select(Number(thumb.dataset.galleryThumb));
            const step = event.target.closest('[data-gallery-step]');
            if (step) {
                const order = visible();
                const next = order[order.indexOf(current) + Number(step.dataset.galleryStep)];
                if (next !== undefined) select(next);
                return;
            }
            const filter = event.target.closest('[data-gallery-area]');
            if (filter) {
                areaFilter = filter.dataset.galleryArea;
                root.querySelectorAll('[data-gallery-area]').forEach(function (button) {
                    button.setAttribute('aria-pressed', button === filter ? 'true' : 'false');
                });
                root.querySelectorAll('[data-gallery-thumb]').forEach(function (thumbButton) {
                    const index = Number(thumbButton.dataset.galleryThumb);
                    thumbButton.classList.toggle('hidden', !visible().includes(index));
                });
                const order = visible();
                select(order.includes(current) ? current : order[0]);
            }
        });

        select(0);
    })();
</script>
@endif
