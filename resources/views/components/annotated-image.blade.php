@php
    // The AI never draws on the photo: it returns boxes ([ymin, xmin, ymax, xmax] on 0-1000,
    // cleaned by GeminiVisionService), and this page draws them as percent rectangles over the
    // whole photo. The stage always takes the photo's own (EXIF-upright) shape, so a box at
    // left 20% / top 10% sits on the same pixels the AI boxed, with no object-fit cropping.
    $maxHeight = $maxHeight ?? null;
    $activeImageId = (int) ($activeImageId ?? 1);
    $imageRatio = !empty($imageWidth) && !empty($imageHeight) ? (float) $imageWidth / (float) $imageHeight : null;
    $stageStyle = trim(($imageAspectRatio ? "aspect-ratio: {$imageAspectRatio};" : '')
        . ($maxHeight && $imageRatio ? ' width: min(100%, ' . round($maxHeight * $imageRatio, 2) . 'px); margin-inline: auto;' : ''));
    $percent = fn ($share) => rtrim(rtrim(number_format((float) $share * 100, 4, '.', ''), '0'), '.') . '%';

    // Only detected rows are drawn: recommendations and substitutes stay in the material lists.
    // Each drawn row gets a number per photo, shown on its boxes and in the legend.
    $numbers = [];
    $marked = [];
    foreach ($overlayItems as $item) {
        $location = $item['visual_location'] ?? null;
        $boxes = array_values(array_filter($item['boxes'] ?? [], 'is_array'));
        $point = $boxes === [] && is_array($location) && isset($location['x'], $location['y']) && !isset($location['width'], $location['height'])
            ? $location : null;
        if (($item['source_type'] ?? null) !== 'detected' || ($boxes === [] && $point === null)) {
            continue;
        }

        $imageId = (int) ($item['image_id'] ?? 1);
        $numbers[$imageId] = ($numbers[$imageId] ?? 0) + 1;
        $marked[] = $item + [
            'number' => $numbers[$imageId],
            'draw_boxes' => $boxes,
            'draw_point' => $point,
            'image' => $imageId,
            'possible' => $boxes !== [] && !in_array(false, array_column($boxes, 'possible'), true),
        ];
    }
@endphp
<div class="relative w-full max-w-full overflow-hidden bg-white" data-annotated-image-stage @if($maxHeight) data-max-height="{{ $maxHeight }}" @endif @if($stageStyle) style="{{ $stageStyle }}" @endif>
    <img src="{{ $previewImage }}" alt="Inspiration Image" class="block h-auto w-full object-contain transition duration-200" />

    @foreach($marked as $item)
        @php
            $name = trim((string) ($item['name'] ?? 'Detected element'));
            $areaName = trim((string) ($item['color_label'] ?? $item['area_label'] ?? ''));
            $color = $item['color'] ?? '#059669';
            $hiddenClass = $item['image'] === $activeImageId ? '' : ' hidden';
        @endphp
        @foreach($item['draw_boxes'] as $box)
            @php $label = $item['number'] . ' · ' . $name . ($areaName !== '' ? ' (' . $areaName . ')' : '') . (!empty($box['possible']) ? ' · possible match' : ''); @endphp
            <button type="button"
                class="absolute z-10 block rounded-[3px] border-2 bg-transparent p-0 transition-[box-shadow,opacity] duration-150 hover:shadow-[0_0_0_2px_rgba(255,255,255,0.85)] focus:outline-none focus-visible:shadow-[0_0_0_3px_rgba(255,255,255,0.95)] data-[dimmed]:opacity-30 data-[active]:shadow-[0_0_0_3px_rgba(255,255,255,0.95)]{{ $hiddenClass }}"
                style="left: {{ $percent($box['x']) }}; top: {{ $percent($box['y']) }}; width: {{ $percent($box['width']) }}; height: {{ $percent($box['height']) }}; border-color: {{ $color }}; border-style: {{ !empty($box['possible']) ? 'dashed' : 'solid' }};"
                data-annotation-item data-annotation-box data-line-id="{{ $item['line_id'] ?? '' }}" data-image-id="{{ $item['image'] }}" @if(!empty($box['possible'])) data-possible @endif
                title="{{ $label }}" aria-label="{{ $label }}">
                <span class="pointer-events-none absolute left-0 top-0 rounded-br-[3px] px-1 text-[10px] font-bold leading-4 text-white" style="background-color: {{ $color }};" aria-hidden="true">{{ $item['number'] }}{{ !empty($box['possible']) ? '?' : '' }}</span>
            </button>
        @endforeach
        @if($item['draw_point'])
            {{-- Analyses saved before boxes were requested only have a point. --}}
            <button type="button"
                class="absolute z-20 flex h-5 w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-[10px] font-bold text-white shadow data-[dimmed]:opacity-30{{ $hiddenClass }}"
                style="left: {{ $percent($item['draw_point']['x']) }}; top: {{ $percent($item['draw_point']['y']) }}; background-color: {{ $color }};"
                data-annotation-item data-annotation-point data-line-id="{{ $item['line_id'] ?? '' }}" data-image-id="{{ $item['image'] }}"
                title="{{ $item['number'] }} · {{ $name }}" aria-label="{{ $item['number'] }} · {{ $name }}">{{ $item['number'] }}</button>
        @endif
    @endforeach

    <button type="button" class="pointer-events-auto absolute bottom-4 right-4 z-30 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/95 px-3 py-2 text-sm font-semibold text-slate-700 shadow-lg transition hover:bg-white" data-image-lightbox-trigger data-image-src="{{ $previewImage }}" aria-label="View full inspiration image">
        <span>View full image</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h8l5 5v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm0 0v5h8M15 13l-6 6m0-6l6 6"/></svg>
    </button>
</div>

@if($marked !== [])
<div class="bg-white px-2 pb-2 pt-1.5">
    <ol class="grid grid-cols-1 gap-1 sm:grid-cols-2" data-annotation-legend aria-label="Materials marked on the photo">
        @foreach($marked as $item)
            <li class="{{ $item['image'] === $activeImageId ? '' : 'hidden' }}" data-annotation-item data-image-id="{{ $item['image'] }}">
                <button type="button" class="flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left text-[11px] text-slate-700 transition hover:bg-slate-100 data-[active]:bg-slate-100" data-annotation-legend-item data-line-id="{{ $item['line_id'] ?? '' }}">
                    <span class="flex h-4 min-w-4 shrink-0 items-center justify-center rounded-[3px] px-1 text-[10px] font-bold text-white" style="background-color: {{ $item['color'] ?? '#059669' }};">{{ $item['number'] }}</span>
                    <span class="min-w-0 truncate"><span class="font-semibold text-slate-800">{{ $item['name'] }}</span>@if(!empty($item['color_label'] ?? $item['area_label'])) <span class="text-slate-500">· {{ $item['color_label'] ?? $item['area_label'] }}</span>@endif</span>
                    @if(count($item['draw_boxes']) > 1)
                        <span class="shrink-0 text-slate-400">{{ count($item['draw_boxes']) }} places</span>
                    @endif
                    @if($item['possible'])
                        <span class="shrink-0 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800">possible</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ol>
    <p class="mt-1 text-[11px] text-slate-500">Boxes mark where the AI found each material in this photo; dashed boxes are possible matches for Raflora to confirm. Quantities come from your event details, not from the number of boxes.</p>
</div>
@endif

@once
<style>
    [data-lightbox-content] [data-annotated-image-stage] {
        width: fit-content !important;
        max-width: 100%;
        max-height: calc(90vh - 2rem);
        aspect-ratio: auto !important;
    }

    [data-lightbox-content] [data-annotated-image-stage] > img {
        width: auto;
        max-width: 100%;
        height: auto;
        max-height: calc(90vh - 2rem);
        object-fit: contain;
    }
</style>
<script>
    // Give the stage the photo's displayed shape (browsers apply EXIF turns), so percentage boxes
    // sit on the same pixels the AI boxed, including after the photo is swapped for another one.
    window.fitAnnotatedImageStage = (stage) => {
        const image = stage?.querySelector('img');
        if (!image || !image.naturalWidth || !image.naturalHeight) return;
        const ratio = image.naturalWidth / image.naturalHeight;
        stage.style.aspectRatio = `${image.naturalWidth} / ${image.naturalHeight}`;
        const maxHeight = Number(stage.dataset.maxHeight || 0);
        if (maxHeight > 0) {
            stage.style.width = `min(100%, ${Math.round(maxHeight * ratio)}px)`;
            stage.style.marginInline = 'auto';
        }
    };

    // Boxes are percent rectangles, so they follow the photo on their own; this only refits stages.
    window.positionAnnotatedImages = (root = document) => {
        const stages = root.matches?.('[data-annotated-image-stage]') ? [root] : [...root.querySelectorAll('[data-annotated-image-stage]')];
        stages.forEach((stage) => window.fitAnnotatedImageStage(stage));
    };

    // Shows only the boxes and legend entries made for one photo of a multi-photo booking.
    window.showAnnotationsForImage = (stage, imageId) => {
        if (!stage) return;
        const scope = stage.parentElement || document;
        scope.querySelectorAll('[data-annotation-item]').forEach((element) => {
            element.classList.toggle('hidden', Number(element.dataset.imageId || 1) !== Number(imageId));
        });
        window.positionAnnotatedImages(stage);
    };

    // Links a material line and its boxes by line ID: every box of the line is outlined, the
    // other boxes are dimmed, and the line's entries in the page's lists are marked.
    window.highlightAnnotationLine = (lineId, options = {}) => {
        const boxes = document.querySelectorAll('[data-annotation-box], [data-annotation-point]');
        const entries = document.querySelectorAll('[data-line-id]:not([data-annotation-box]):not([data-annotation-point])');
        boxes.forEach((box) => {
            const match = Boolean(lineId) && box.dataset.lineId === lineId;
            box.toggleAttribute('data-active', match);
            box.toggleAttribute('data-dimmed', Boolean(lineId) && !match);
        });
        let first = null;
        entries.forEach((entry) => {
            const match = Boolean(lineId) && entry.dataset.lineId === lineId;
            entry.toggleAttribute('data-active', match);
            entry.classList.toggle('ring-2', match && !entry.matches('[data-annotation-legend-item]'));
            entry.classList.toggle('ring-emerald-400', match && !entry.matches('[data-annotation-legend-item]'));
            if (match && !first && !entry.closest('[data-annotation-legend]') && entry.offsetParent !== null) first = entry;
        });
        if (options.scroll && first) first.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const initializeAnnotatedImages = () => {
        document.querySelectorAll('[data-annotated-image-stage] img').forEach((image) => {
            const stage = image.closest('[data-annotated-image-stage]');
            const refit = () => window.fitAnnotatedImageStage(stage);
            image.addEventListener('load', refit);
            if (image.complete) refit();
        });

        let pinned = null;
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-annotation-box], [data-annotation-point], [data-annotation-legend-item]');
            if (!trigger) return;
            event.preventDefault();
            pinned = pinned === trigger.dataset.lineId ? null : trigger.dataset.lineId;
            window.highlightAnnotationLine(pinned, { scroll: Boolean(pinned) && !trigger.matches('[data-annotation-legend-item]') });
        });
        // Pointing at a material anywhere on the page shows its boxes until the pointer leaves.
        document.addEventListener('mouseover', (event) => {
            const entry = event.target.closest('[data-line-id]');
            if (entry && entry.dataset.lineId && !pinned) window.highlightAnnotationLine(entry.dataset.lineId);
        });
        document.addEventListener('mouseout', (event) => {
            const entry = event.target.closest('[data-line-id]');
            if (entry && !pinned && !entry.contains(event.relatedTarget)) window.highlightAnnotationLine(null);
        });
    };

    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', initializeAnnotatedImages, { once: true });
    } else {
        initializeAnnotatedImages();
    }
</script>
@endonce
