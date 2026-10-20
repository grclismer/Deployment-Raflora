{{--
    Step 2 of the guest and client booking forms for a Smart AI design: the design preferences, the
    inspiration photos with their AI analysis, and a compact booking summary. Details open in
    dialogs (preferences, one photo's analysis, one area, the price breakdown) so the step stays
    short. Both forms use this one component so they cannot drift apart; each page passes its own
    analysis route and event-details callbacks:

      const photos = window.RafloraInspirationPhotos.create(document.getElementById('{prefix}-inspiration-photos'), {
          contextFingerprint: () => '...',      // event details the analysis depends on
          appendContext: (formData) => {...},   // the same details for the analysis request
          isActive: () => true,                 // false pauses the queue (e.g. while Step 1 is shown)
          onChange: () => {...},                // after any photo change (draft saving)
      });

    Truthful states only: a photo is waiting, analyzing, analyzed, failed, cancelled, out of date
    (event details or preferences changed after it was analyzed) or needs its areas chosen. Only
    analyzed photos that are still current are submitted (their session tokens), and the server
    checks each again (InspirationPhotos::analysisIsStale). Progress is indeterminate: nothing here
    pretends to know how far an analysis has got. No price is worked out in the browser: the area
    ranges come from bookings.analysis-estimate (App\Services\AiEstimateService).
--}}
@props(['prefix', 'analyzeUrl'])
@use('App\Support\EventDesignBrief')
@use('App\Support\DecorAreaBreakdown')
@use('App\Support\InspirationPhotos')

@php
    $maxMb = InspirationPhotos::MAX_KILOBYTES / 1024;
    $config = [
        'prefix' => $prefix,
        'analyzeUrl' => $analyzeUrl,
        'estimateUrl' => route('bookings.analysis-estimate'),
        'restoredImageUrl' => url('/guest/bookings/analysis-image'),
        // Shown when a photo preview cannot be loaded (e.g. an analysis the session no longer has).
        'placeholderUrl' => 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#f1f5f9"/><circle cx="24" cy="24" r="5" fill="#cbd5e1"/><path d="M14 46l11-13 8 9 6-7 11 11z" fill="#cbd5e1"/></svg>'),
        'maxPhotos' => InspirationPhotos::MAX_PHOTOS,
        'maxBytes' => InspirationPhotos::MAX_KILOBYTES * 1024,
        'maxMb' => $maxMb,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'areaLabels' => EventDesignBrief::DECOR_AREAS,
        'areaColors' => DecorAreaBreakdown::colors(),
        'missingMessage' => DecorAreaBreakdown::MISSING_MESSAGE,
        'sharedKey' => DecorAreaBreakdown::SHARED,
        'usageLabels' => array_map(fn ($option) => $option['label'], EventDesignBrief::PHOTO_USAGE),
        'themeLabels' => EventDesignBrief::THEMES,
    ];
    // m-0: the dialogs sit inside space-y containers, whose child margin would shorten a full-screen dialog.
    $dialog = 'fixed inset-0 z-50 m-0 hidden items-stretch justify-center bg-slate-900/60 sm:items-center sm:p-4';
    $panel = 'relative flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:rounded-2xl sm:border sm:border-slate-200';
    $closeBtn = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500';
    $pagerBtn = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500';
    $ghostBtn = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500';
    $primaryBtn = 'inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-1';
@endphp

<div id="{{ $prefix }}-inspiration-photos" data-inspiration-photos data-config='@json($config)' class="space-y-3">
    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-12">
        {{-- ============================== Preferences and photos ============================== --}}
        <div class="space-y-3 lg:col-span-8">
            {{-- Design preferences: a short summary; every field is edited in the dialog --}}
            <section id="design-brief-summary-card" class="rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-2xs" aria-labelledby="{{ $prefix }}-prefs-heading">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 id="{{ $prefix }}-prefs-heading" class="text-xs font-bold uppercase tracking-wider text-[#0B1E43] sm:text-sm">Design preferences <span class="font-sans text-[11px] font-normal normal-case text-slate-400">(optional)</span></h3>
                        <p class="text-[11px] text-slate-500">Areas to decorate, notes per area, theme, colors, flowers and budget.</p>
                    </div>
                    <button type="button" data-action="open-preferences" id="btn-open-design-modal" class="{{ $primaryBtn }}">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit preferences
                    </button>
                </div>
                <div id="design-brief-summary-content" class="mt-2 space-y-1.5" aria-live="polite">
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span id="summary-area-count-badge" class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">No areas selected</span>
                        <span id="summary-usage-badge" class="hidden rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-600"></span>
                        <span id="summary-theme-badge" class="hidden rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-800"></span>
                        <span id="summary-palette-badge" class="hidden items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-700"></span>
                        <span id="summary-more-badge" class="hidden rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-600"></span>
                    </div>
                    <div id="summary-selected-chips-preview" class="flex min-h-[22px] flex-wrap items-center gap-1"></div>
                </div>
            </section>

            {{-- ============================== Dialog: design preferences ============================== --}}
            <div id="design-preferences-modal" data-dialog="preferences" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="design-preferences-modal-title">
                <div class="{{ $panel }} sm:max-w-3xl">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                        <div class="min-w-0">
                            <h3 id="design-preferences-modal-title" class="text-sm font-bold text-[#0B1E43]">Design preferences</h3>
                            <p class="text-[11px] text-slate-500">Optional. The AI uses these with your photos; Raflora's florists see them too.</p>
                        </div>
                        <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Cancel and close design preferences">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
                        <x-design-brief-fields id="design-brief" class="!border-0 !p-0 !shadow-none" />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
                        <button type="button" data-action="clear-preferences" class="text-xs font-semibold text-rose-600 hover:text-rose-700 focus:outline-none focus-visible:underline">Clear all preferences</button>
                        <div class="flex items-center gap-2">
                            <button type="button" data-dialog-close class="{{ $ghostBtn }}">Cancel</button>
                            <button type="button" data-action="save-preferences" class="{{ $primaryBtn }}">Save preferences</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inspiration photos --}}
            <section id="uploaded-images-section" class="rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-2xs" aria-labelledby="{{ $prefix }}-photos-heading">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 id="{{ $prefix }}-photos-heading" class="text-xs font-bold uppercase tracking-wider text-[#0B1E43] sm:text-sm">Inspiration photos <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></h3>
                        <p class="text-[11px] text-slate-500"><span id="uploaded-count-text"><span id="uploaded-count-badge">0</span> of {{ InspirationPhotos::MAX_PHOTOS }} added</span> · JPG, PNG or WEBP, up to {{ $maxMb }} MB each</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-action="open-tips" class="{{ $ghostBtn }} !px-2.5 !py-1.5">
                            <span class="font-bold text-emerald-600" aria-hidden="true">ⓘ</span> Tips
                        </button>
                        <button type="button" data-action="remove-all" id="btn-clear-all" class="hidden rounded-lg px-2 py-1.5 text-[11px] font-bold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400">Remove all</button>
                    </div>
                </div>

                <div id="dropzone_container" role="button" tabindex="0" aria-describedby="{{ $prefix }}-photos-heading" aria-label="Add inspiration photos: click, press Enter, or drop photos here"
                     class="mt-2.5 flex cursor-pointer select-none items-center justify-center gap-2.5 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 px-3 py-3 text-center transition hover:bg-emerald-50/50 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600" aria-hidden="true">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"></path></svg>
                    </span>
                    <span class="text-left">
                        <span class="block text-xs font-bold text-[#0B1E43]">Add photos — click or drag here</span>
                        <span class="block text-[10px] text-slate-500">Each photo is analyzed on its own for the areas it is for.</span>
                    </span>
                    {{-- Photos are uploaded one by one for analysis; the form submits only their analysis tokens. --}}
                    <input type="file" id="inspiration_image_input" accept="image/png,image/jpeg,image/webp" multiple class="hidden" tabindex="-1">
                </div>

                <p id="{{ $prefix }}-photo-status" data-photos-status class="mt-2 hidden rounded-lg border px-3 py-2 text-xs font-medium" role="status" aria-live="polite"></p>

                {{-- The one analysis in flight. Indeterminate: the AI service reports no progress. --}}
                <div id="ai-progress-card" class="mt-2 hidden rounded-xl border border-emerald-200 bg-white p-3 shadow-xs" role="status" aria-live="polite">
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <img id="progress-img-thumb" src="" alt="" class="h-full w-full object-cover">
                        </div>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="flex items-center gap-1.5 text-xs font-bold text-[#0B1E43]">
                                    <svg class="h-3.5 w-3.5 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <span id="progress-card-title">Analyzing photo</span>
                                </p>
                                <button type="button" data-action="cancel-analysis" class="rounded-lg border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400">Cancel</button>
                            </div>
                            <p id="progress-status-desc" class="text-[11px] leading-snug text-slate-600">Analyzing your inspiration image. Gemini is identifying floral materials and preparing suggestions. This may take a moment.</p>
                            <div class="relative h-1.5 w-full overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                <div id="progress-indeterminate-bar" class="h-full w-2/5 animate-pulse rounded-full bg-emerald-600"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="uploaded-images-grid" class="mt-2.5 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3" aria-label="Your inspiration photos"></div>
                <p data-gallery-empty class="mt-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-4 text-center text-[11px] text-slate-500">No photos yet. Add up to {{ InspirationPhotos::MAX_PHOTOS }} floral or event photos you like.</p>

                <nav data-gallery-pager class="mt-2 hidden items-center justify-between gap-2" aria-label="Photo pages">
                    <p class="text-[11px] font-semibold text-slate-600" data-gallery-range aria-live="polite"></p>
                    <div class="flex items-center gap-1">
                        <button type="button" data-action="gallery-page" data-step="-1" class="{{ $pagerBtn }}" aria-label="Previous photos">‹</button>
                        <button type="button" data-action="gallery-page" data-step="1" class="{{ $pagerBtn }}" aria-label="Next photos">›</button>
                    </div>
                </nav>
            </section>
        </div>

        {{-- ============================== Booking summary ============================== --}}
        <aside class="space-y-3 lg:sticky lg:top-4 lg:col-span-4" aria-labelledby="{{ $prefix }}-summary-heading">
            <section id="{{ $prefix }}-step2-summary" class="rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-2xs">
                <h3 id="{{ $prefix }}-summary-heading" class="text-xs font-bold uppercase tracking-wider text-[#0B1E43] sm:text-sm">Design summary</h3>
                <dl class="mt-2 divide-y divide-slate-100 text-xs">
                    <div class="flex items-baseline justify-between gap-3 py-1.5">
                        <dt class="text-slate-500">Photos</dt>
                        <dd class="text-right font-semibold text-slate-800" data-summary="photos">None yet</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 py-1.5">
                        <dt class="text-slate-500">Areas to decorate</dt>
                        <dd class="text-right font-semibold text-slate-800" data-summary="areas">Not chosen</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 py-1.5">
                        <dt class="text-slate-500">Materials suggested</dt>
                        <dd class="text-right font-semibold text-slate-800" data-summary="materials">—</dd>
                    </div>
                </dl>

                <div class="mt-2 rounded-xl border border-violet-200 bg-violet-50/40 p-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-wide text-violet-900">AI estimate</h4>
                        <span class="rounded-full border border-violet-200 bg-white px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-violet-800">Indicative</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-snug text-slate-600" data-summary="estimate-status" aria-live="polite">Appears after your photos are analyzed.</p>
                    <ul class="mt-1.5 hidden space-y-1" data-summary="estimate-list" role="list"></ul>
                    <button type="button" data-action="retry-estimate" class="mt-1.5 hidden text-[11px] font-bold text-violet-800 underline underline-offset-2 hover:text-violet-950">Try again</button>
                </div>

                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    <button type="button" data-action="open-areas" class="{{ $ghostBtn }}" disabled>Area details</button>
                    <button type="button" data-action="open-prices" class="{{ $ghostBtn }}" disabled>Price breakdown</button>
                </div>
                <p class="mt-2 text-[10px] leading-snug text-slate-500" data-summary="buttons-hint">Details open once at least one photo is analyzed.</p>

                <p class="mt-2.5 border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-500">
                    AI suggestions are decision support. Individual flower prices are finalized upon Raflora review, and Raflora shares the price in your official quotation. Nothing here reserves stock.
                </p>
            </section>
        </aside>
    </div>

    {{-- Analysis results submitted with the booking (the server trusts only its own session copies) --}}
    <input type="hidden" name="analysis_tokens" id="{{ $prefix }}_analysis_tokens" value="{{ old('analysis_tokens') }}">
    <input type="hidden" name="analysis_token" id="{{ $prefix }}_analysis_token" value="{{ old('analysis_token') }}">
    <input type="hidden" name="analysis_temp_path" id="{{ $prefix }}_analysis_temp_path" value="{{ old('analysis_temp_path') }}">
    <input type="hidden" name="analysis_data" id="{{ $prefix }}_analysis_data" value="{{ old('analysis_data') }}">
    <input type="hidden" name="analysis_nonce" id="{{ $prefix }}_analysis_nonce" value="{{ old('analysis_nonce') }}">

    {{-- ============================== Dialog: note for one decoration area ============================== --}}
    {{-- Writes the design brief's decor_area_notes[area] field (stored with the booking) only on Save. --}}
    <div id="area-note-dialog" data-dialog="note" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="area-note-dialog-title" aria-describedby="area-note-dialog-help">
        <div class="{{ $panel }} sm:max-w-lg">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                <h3 id="area-note-dialog-title" class="flex items-center gap-2 text-sm font-bold text-[#0B1E43]">
                    <span data-note-dot class="h-2.5 w-2.5 shrink-0 rounded-full" aria-hidden="true"></span>
                    <span data-note-title>Note for this area</span>
                </h3>
                <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Cancel and close the note">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                <label for="area-note-text" class="block text-xs font-semibold text-slate-700">What would you like for this area?</label>
                <p id="area-note-dialog-help" class="text-[11px] leading-snug text-slate-500">Colors, placement, size or anything Raflora's florists should know, e.g. "Keep it low so guests can talk." The AI also reads this note.</p>
                <textarea id="area-note-text" data-dialog-focus rows="4" maxlength="500" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-[#0B1E43] outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"></textarea>
                <p class="text-right text-[11px] text-slate-500" aria-live="polite"><span data-note-count>0</span>/500</p>
                <p data-note-warning class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-900"></p>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
                <button type="button" data-dialog-close class="{{ $ghostBtn }}">Cancel</button>
                <button type="button" data-action="save-area-note" class="{{ $primaryBtn }}">Save note</button>
            </div>
        </div>
    </div>

    {{-- ============================== Dialog: upload tips ============================== --}}
    <div id="upload-tips-modal" data-dialog="tips" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="upload-tips-modal-title">
        <div class="{{ $panel }} sm:max-w-lg">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <h3 id="upload-tips-modal-title" class="text-sm font-bold text-[#0B1E43]">Upload Tips</h3>
                <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Close tips">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 text-xs text-slate-600">
                <p>For better AI analysis results, follow these guidelines:</p>
                <ul class="space-y-1.5">
                    <li class="flex gap-2"><span class="font-bold text-emerald-600" aria-hidden="true">✓</span>Use clear, well-lit, high-quality floral photos.</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-600" aria-hidden="true">✓</span>Include the full arrangement when possible.</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-600" aria-hidden="true">✓</span>Add several photos for different angles or areas (up to {{ InspirationPhotos::MAX_PHOTOS }}).</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-600" aria-hidden="true">✓</span>Pinterest, past events, magazines or your own photos all work.</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-600" aria-hidden="true">✓</span>AI results are suggestions and require Raflora florist validation.</li>
                </ul>
                <div class="space-y-1 rounded-xl border border-emerald-100 bg-emerald-50/70 p-3 text-[11px] text-slate-700">
                    <p><span class="font-bold text-emerald-800">Accepted:</span> floral arrangements, event setups, bouquets, venue decorations, greenery.</p>
                    <p><span class="font-bold text-rose-700">Not accepted:</span> electronics, random objects, memes, screenshots, or blurry photos.</p>
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-4 py-3">
                <button type="button" data-dialog-close class="{{ $primaryBtn }}">Got it</button>
            </div>
        </div>
    </div>

    {{-- ============================== Dialog: one photo's analysis ============================== --}}
    <div id="photo-analysis-dialog" data-dialog="photo" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="photo-analysis-dialog-title">
        <div class="{{ $panel }} sm:max-w-5xl">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5">
                <div class="min-w-0 flex-1">
                    <h3 id="photo-analysis-dialog-title" class="text-sm font-bold text-[#0B1E43]">Photo analysis — Image <span id="img-nav-current">1</span> of <span id="img-nav-total">1</span></h3>
                    <p id="viewer-photo-areas" class="truncate text-[11px] font-medium text-slate-600" aria-live="polite"></p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" id="btn-img-prev" data-action="photo-step" data-step="-1" class="{{ $pagerBtn }}" aria-label="Previous analyzed photo">‹</button>
                    <button type="button" id="btn-img-next" data-action="photo-step" data-step="1" class="{{ $pagerBtn }}" aria-label="Next analyzed photo">›</button>
                </div>
                <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Close photo analysis">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div id="ai-material-preview-card" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4">
                <p data-photo-dialog-notice class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900" role="status"></p>

                {{-- The photo's areas: changing them needs a new analysis --}}
                <div id="viewer-photo-area-selector" class="hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-700">This photo is for:</span>
                            <div id="viewer-photo-area-chips" class="flex flex-wrap items-center gap-1" role="group" aria-label="Areas this photo is for"></div>
                        </div>
                        <div id="viewer-photo-reanalyze-wrap" class="hidden"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <div class="flex items-center gap-1 rounded-xl border border-slate-200/80 bg-slate-100 p-1" role="group" aria-label="Photo view">
                            <button type="button" id="tab-img-original" data-action="image-mode" data-mode="original" aria-pressed="true" class="flex-1 rounded-lg px-2.5 py-1.5 text-xs font-bold transition">Original Photo</button>
                            <button type="button" id="tab-img-annotated" data-action="image-mode" data-mode="annotated" aria-pressed="false" class="flex-1 rounded-lg px-2.5 py-1.5 text-xs font-bold transition">AI Annotated</button>
                        </div>
                        <div class="relative w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            <div class="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden">
                                <img id="viewer-main-img" src="" alt="Selected inspiration photo" class="h-full w-full object-contain">
                                <div id="viewer-markers-container" class="pointer-events-auto absolute left-0 top-0 hidden h-full w-full"></div>
                                <button type="button" id="viewer-enlarge-btn" data-action="enlarge" class="absolute bottom-2.5 right-2.5 z-30 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-md transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Enlarge inspiration photo">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                                    <span>Enlarge</span>
                                </button>
                            </div>
                        </div>
                        <p id="viewer-markers-hint" class="hidden items-start gap-1.5 text-[10px] text-slate-500">
                            <span class="font-bold text-emerald-600" aria-hidden="true">ⓘ</span>
                            <span id="viewer-markers-hint-text">Boxes show where the AI detected materials. Click a box to inspect.</span>
                        </p>

                        <div class="space-y-2 rounded-xl border border-slate-200/80 bg-white p-3">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-[#0B1E43]">Analysis summary</h4>
                            <p id="viewer-summary-text" class="text-xs italic leading-snug text-slate-600"></p>
                            <div class="flex flex-wrap gap-1.5">
                                <span id="viewer-badge-mats" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800"><span id="viewer-badge-mats-count">0</span> materials suggested</span>
                                <span id="viewer-badge-style" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-800"><span id="viewer-badge-style-text"></span></span>
                                <span id="viewer-badge-conf" class="hidden items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-800"><span id="viewer-badge-conf-text"></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            <div class="flex items-center justify-between border-b border-slate-200/70 pb-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#0B1E43]">Material details</h4>
                                <div class="flex items-center gap-1">
                                    <button type="button" id="mat-btn-prev" data-action="material-step" data-step="-1" class="{{ $pagerBtn }} !h-7 !w-7" aria-label="Previous material">‹</button>
                                    <span class="px-1 text-xs font-bold text-[#0B1E43]" aria-live="polite"><span id="mat-current-idx">1</span> of <span id="mat-total-count">0</span></span>
                                    <button type="button" id="mat-btn-next" data-action="material-step" data-step="1" class="{{ $pagerBtn }} !h-7 !w-7" aria-label="Next material">›</button>
                                </div>
                            </div>
                            <div class="relative flex h-24 w-full items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <img id="mat-ref-img" src="" alt="Raflora reference photo of this material" class="hidden h-full w-full object-cover">
                                <div id="mat-ref-fallback" class="flex flex-col items-center justify-center p-2 text-center text-slate-400">
                                    <span class="text-[11px] font-bold text-slate-600">Reference image unavailable</span>
                                    <span class="text-[9px] text-slate-400">No verified photo on file</span>
                                </div>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span id="mat-source-badge" class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">AI Detected</span>
                                    <span id="mat-category-badge" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-700"></span>
                                    <span id="mat-confidence-badge" class="text-[10px] font-medium text-slate-500"></span>
                                </div>
                                <h5 id="mat-name" class="mt-1 truncate font-serif text-sm font-bold text-[#0B1E43]"></h5>
                            </div>
                            <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200/70 bg-white p-2.5 text-[11px]">
                                <div>
                                    <span class="block text-[9px] font-semibold uppercase text-slate-400">Est. quantity</span>
                                    <span class="font-bold text-slate-800"><span id="mat-qty">—</span> <span id="mat-unit"></span></span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-semibold uppercase text-slate-400">Used for</span>
                                    <span id="mat-area" class="block truncate font-medium text-slate-700"></span>
                                </div>
                            </div>
                            <p id="mat-basis" class="hidden text-[11px] leading-snug text-slate-600"></p>
                            <p id="mat-note" class="text-[11px] italic leading-snug text-slate-500"></p>
                            <div id="mat-seasonal-box" class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-2 text-[10px]">
                                <p class="mb-0.5 font-bold text-emerald-800">Seasonal information</p>
                                <p id="mat-seasonal-text" class="leading-snug text-slate-700"></p>
                            </div>
                            <div id="mat-alt-box" class="hidden rounded-xl border border-amber-200 bg-amber-50/70 p-2 text-[10px]">
                                <p class="mb-0.5 font-bold uppercase tracking-wider text-amber-900">Alternative (subject to validation)</p>
                                <p class="font-medium text-slate-800"><span id="mat-orig-name"></span> → <span id="mat-alt-name" class="font-bold text-emerald-700"></span></p>
                                <p id="mat-alt-reason" class="mt-0.5 leading-snug text-slate-600"></p>
                            </div>
                        </div>

                        <div class="space-y-1.5 rounded-xl border border-slate-200/80 bg-slate-50/80 p-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-[11px] font-bold uppercase tracking-wider text-[#0B1E43]">All materials in this photo</h4>
                                <span class="text-[10px] font-bold text-slate-500"><span id="mat-quick-count">0</span> items</span>
                            </div>
                            <div id="mat-quick-list" class="max-h-52 space-y-1 overflow-y-auto pr-1" role="list"></div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="border-t border-slate-100 bg-slate-50/80 px-4 py-2 text-[10px] leading-snug text-slate-500">"AI Detected" means the AI saw it in your photo; "Suggested" means it was added to complete the design. Quantities, availability and substitutes are confirmed by Raflora before your quotation.</p>
        </div>
    </div>

    {{-- ============================== Dialog: one decoration area ============================== --}}
    <div id="area-details-dialog" data-dialog="areas" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="area-details-dialog-title">
        <div class="{{ $panel }} sm:max-w-2xl">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5">
                <div class="min-w-0 flex-1">
                    <h3 id="area-details-dialog-title" class="flex items-center gap-2 text-sm font-bold text-[#0B1E43]">
                        <span data-area-dot class="h-2.5 w-2.5 shrink-0 rounded-full" aria-hidden="true"></span>
                        <span data-area-title>Area</span>
                    </h3>
                    <p class="text-[11px] text-slate-500" data-area-position aria-live="polite"></p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" data-action="area-step" data-step="-1" class="{{ $pagerBtn }}" aria-label="Previous area">‹</button>
                    <button type="button" data-action="area-step" data-step="1" class="{{ $pagerBtn }}" aria-label="Next area">›</button>
                </div>
                <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Close area details">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-4 py-2" data-area-tabs role="group" aria-label="Choose an area"></div>
            <div id="{{ $prefix }}-area-breakdown" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4" data-area-body></div>
            <p class="border-t border-slate-100 bg-slate-50/80 px-4 py-2 text-[10px] leading-snug text-slate-500">AI-suggested quantities, not yet reviewed by Raflora. Nothing is reserved until your booking is confirmed.</p>
        </div>
    </div>

    {{-- ============================== Dialog: price breakdown ============================== --}}
    <div id="price-breakdown-dialog" data-dialog="prices" class="{{ $dialog }}" role="dialog" aria-modal="true" aria-labelledby="price-breakdown-dialog-title">
        <div class="{{ $panel }} sm:max-w-xl">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                <div class="min-w-0">
                    <h3 id="price-breakdown-dialog-title" class="text-sm font-bold text-[#0B1E43]">AI price breakdown</h3>
                    <p class="text-[11px] text-slate-500">An indicative range, not an official quotation.</p>
                </div>
                <button type="button" data-dialog-close class="{{ $closeBtn }}" aria-label="Close price breakdown">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                <p class="hidden text-xs text-slate-600" data-price-status role="status"></p>
                <x-ai-estimate id="{{ $prefix }}-ai-estimate" />
                <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-600">
                    <p><strong class="text-slate-700">How it is worked out:</strong> from the materials and quantities the AI suggested for your analyzed photos, using Raflora's current estimate settings, with a ±15% range.</p>
                    <p><strong class="text-slate-700">Not included:</strong> materials the AI could not price (marked "Incomplete") and anything Raflora adds during review.</p>
                    <p><strong class="text-slate-700">Official price:</strong> Raflora reviews every material, checks availability and sends an official quotation. Only that quotation can be accepted or paid.</p>
                </div>
            </div>
        </div>
    </div>

    <x-annotation-viewer />
</div>

@once
<script>
    (function () {
        if (window.RafloraInspirationPhotos) return;

        // ------------------------------------------------------------------ Dialogs
        // One dialog at a time from this component (the enlarged photo viewer may sit above).
        // Escape or the close buttons cancel, focus stays inside while open and returns to the
        // control that opened it.
        const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const openDialogs = [];

        function focusables(dialog) {
            return Array.from(dialog.querySelectorAll(FOCUSABLE)).filter(function (node) { return node.offsetParent !== null; });
        }
        function isOpen(dialog) { return Boolean(dialog) && !dialog.classList.contains('hidden'); }
        function openDialog(dialog, opener) {
            if (!dialog || isOpen(dialog)) return;
            dialog._opener = opener || document.activeElement;
            dialog.classList.remove('hidden');
            dialog.classList.add('flex');
            openDialogs.push(dialog);
            document.body.classList.add('overflow-hidden');
            const target = dialog.querySelector('[data-dialog-focus]') || dialog.querySelector('[data-dialog-close]') || focusables(dialog)[0];
            if (target) target.focus({ preventScroll: true });
        }
        function closeDialog(dialog) {
            if (!isOpen(dialog)) return;
            dialog.classList.add('hidden');
            dialog.classList.remove('flex');
            openDialogs.splice(openDialogs.indexOf(dialog), 1);
            if (openDialogs.length === 0) document.body.classList.remove('overflow-hidden');
            const opener = dialog._opener;
            dialog._opener = null;
            if (opener && document.contains(opener) && opener.offsetParent !== null) opener.focus({ preventScroll: true });
        }
        // backdrop: whether a click outside the panel cancels (not for forms, where it would lose edits).
        function wireDialog(dialog, onCancel, backdrop) {
            if (!dialog) return;
            dialog.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    onCancel();
                    return;
                }
                if (event.key !== 'Tab') return;
                const items = focusables(dialog);
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            dialog.addEventListener('click', function (event) {
                if ((backdrop !== false && event.target === dialog) || event.target.closest('[data-dialog-close]')) onCancel();
            });
            // The enlarged photo viewer frees the page scroll when it closes; keep it locked here.
            dialog.addEventListener('focusin', function () { if (isOpen(dialog)) document.body.classList.add('overflow-hidden'); });
        }

        // ------------------------------------------------------------------ Helpers
        function make(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined && text !== null) node.textContent = text;
            return node;
        }
        function button(label, action, className, extra) {
            const node = make('button', className, label);
            node.type = 'button';
            node.dataset.action = action;
            Object.keys(extra || {}).forEach(function (key) { node.dataset[key] = extra[key]; });
            return node;
        }
        const SMALL_BTN = 'inline-flex items-center rounded-lg border px-2 py-1 text-[11px] font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50 ';
        const BTN_PRIMARY = SMALL_BTN + 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100';
        const BTN_PLAIN = SMALL_BTN + 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
        const BTN_DANGER = SMALL_BTN + 'border-rose-200 bg-white text-rose-700 hover:bg-rose-50';
        const amount = function (value) { return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 }); };
        // A preview that cannot be loaded shows a neutral placeholder instead of a broken image.
        function photoImage(className, src, placeholder) {
            const img = make('img', className);
            img.alt = '';
            img.addEventListener('error', function () { if (img.src !== placeholder) img.src = placeholder; });
            img.src = src || placeholder;
            return img;
        }

        // Small JPEG copy of a photo for the saved draft (a blob URL does not survive a reload).
        function generateThumbnail(file, callback) {
            if (!file || !file.type || !file.type.startsWith('image/')) return callback(null);
            const reader = new FileReader();
            reader.onerror = function () { callback(null); };
            reader.onload = function (event) {
                const img = new Image();
                img.onerror = function () { callback(null); };
                img.onload = function () {
                    try {
                        const scale = Math.min(1, 320 / Math.max(img.width, img.height));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(img.width * scale));
                        canvas.height = Math.max(1, Math.round(img.height * scale));
                        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                        callback(canvas.toDataURL('image/jpeg', 0.7));
                    } catch (error) {
                        callback(null);
                    }
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }

        // ------------------------------------------------------------------ Controller
        function create(root, options) {
            const config = Object.assign(JSON.parse(root.dataset.config || '{}'), {
                csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                contextFingerprint: function () { return ''; },
                appendContext: function () {},
                isActive: function () { return true; },
                onChange: function () {},
            }, options || {});
            const prefix = config.prefix;
            const AREA_LABELS = config.areaLabels || {};
            const AREA_COLORS = config.areaColors || {};
            const AREA_KEYS = Object.keys(AREA_LABELS);
            const GALLERY_PAGE = 3;
            const MATERIAL_PAGE = 6;
            const RESTORED_NO_FILE = 'This photo came back from your saved draft without its file, so it cannot be analyzed again. Remove it and add the photo again.';
            const one = function (selector) { return root.querySelector(selector); };
            const byId = function (id) { return document.getElementById(id); };
            const dialogs = {
                preferences: byId('design-preferences-modal'),
                tips: byId('upload-tips-modal'),
                photo: byId('photo-analysis-dialog'),
                areas: byId('area-details-dialog'),
                prices: byId('price-breakdown-dialog'),
                note: byId('area-note-dialog'),
            };
            const brief = function () { return window.RafloraDesignBrief?.get('design-brief') || null; };
            const areaColor = function (key) { return AREA_COLORS[key] || AREA_COLORS.default || '#059669'; };
            const areaLabel = function (key) { return key === config.sharedKey ? 'Shared Materials' : (AREA_LABELS[key] || key); };

            // What came back with the form (old input), read before the first render overwrites it.
            const initialInputs = {};
            ['tokens', 'token', 'temp_path', 'data', 'nonce'].forEach(function (name) { initialInputs[name] = byId(prefix + '_analysis_' + name)?.value || ''; });

            let photos = [];
            let galleryPage = 0;
            let viewingId = null;
            let materialIndex = 0;
            let imageViewMode = 'original';
            let activeAiAbortController = null;
            let activeRequest = null; // { photo, fingerprint }
            let estimate = { state: 'idle', data: null, message: '', key: null, request: 0, timer: null };
            let areaIndex = 0;
            let areaMaterialPage = 0;
            let preferencesSnapshot = null;
            let noteArea = null;

            // ---------------------------------------------------------- Photo state
            const pickedAreas = function () { return brief()?.getState()?.decor_areas || []; };
            const canonical = function (areas) { return AREA_KEYS.filter(function (key) { return areas.includes(key); }); };

            // The brief a photo is analyzed with: only its own areas and their notes, as the server
            // narrows it (EventDesignBrief::forPhoto). A change to it means a new analysis.
            function briefKeyFor(photoAreas) {
                const state = brief()?.getState() || {};
                const areas = photoAreas.length ? canonical(photoAreas) : (state.decor_areas || []);
                const notes = {};
                Object.keys(state.decor_area_notes || {}).forEach(function (key) { if (areas.includes(key)) notes[key] = state.decor_area_notes[key]; });
                return JSON.stringify(Object.assign({}, state, { decor_areas: areas, decor_area_notes: notes }));
            }
            function photoAreasInvalid(photo) {
                if (photo.areasUnknown) return false;
                const picked = pickedAreas();
                if (picked.length === 0) return photo.photoAreas.length > 0;
                return photo.photoAreas.length === 0 || photo.photoAreas.some(function (area) { return !picked.includes(area); });
            }
            function contextChanged(photo) {
                return Boolean(photo.contextFingerprint) && photo.contextFingerprint !== config.contextFingerprint();
            }
            function briefChanged(photo) {
                return !photo.areasUnknown && Boolean(photo.briefKey) && photo.briefKey !== briefKeyFor(photo.photoAreas);
            }
            // analyzed | stale | areas_changed | needs_areas | pending | uploading | failed | cancelled
            function viewState(photo) {
                if (photo.status !== 'completed') return photo.status;
                if (photo.serverRejected || contextChanged(photo) || briefChanged(photo)) return 'stale';
                if (photoAreasInvalid(photo)) return 'areas_changed';
                return 'analyzed';
            }
            function staleReason(photo) {
                if (photo.serverRejected) return photo.serverRejected;
                if (contextChanged(photo)) return 'Your event details changed after this photo was analyzed, so its suggestions are out of date.';
                return 'Your design preferences changed after this photo was analyzed, so its suggestions are out of date.';
            }
            const isReady = function (photo) { return viewState(photo) === 'analyzed' && Boolean(photo.token); };
            const readyPhotos = function () { return photos.filter(isReady); };
            const withResults = function () { return photos.filter(function (photo) { return photo.status === 'completed' && photo.analysis; }); };
            function resetResult(photo) {
                Object.assign(photo, { token: null, tempPath: null, nonce: null, analysisData: null, analysis: null, suggestedMaterials: [], visualAnalysis: null, contextFingerprint: null, briefKey: null, serverRejected: null, error: null, errorType: null });
            }
            // A photo chosen now (file) or one coming back without its file (null).
            function newPhoto(file) {
                const picked = pickedAreas();
                return {
                    id: 'p' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7),
                    file: file, filename: file ? file.name : '', previewUrl: file ? URL.createObjectURL(file) : null, thumbnailUrl: null,
                    photoAreas: picked.length === 1 ? [picked[0]] : [], areasUnknown: false,
                    status: picked.length > 1 ? 'needs_areas' : 'pending',
                    token: null, tempPath: null, nonce: null, analysisData: null, analysis: null, suggestedMaterials: [], visualAnalysis: null,
                    contextFingerprint: null, briefKey: null, serverRejected: null, error: null, errorType: null,
                };
            }

            function changed() {
                syncHiddenInputs();
                try { config.onChange(); } catch (error) { /* page hook */ }
            }
            function announce(text, tone) {
                const box = one('[data-photos-status]');
                if (!box) return;
                box.className = 'mt-2 rounded-lg border px-3 py-2 text-xs font-medium ' + ({
                    error: 'border-rose-200 bg-rose-50 text-rose-800',
                    warning: 'border-amber-200 bg-amber-50 text-amber-900',
                    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
                }[tone] || 'border-slate-200 bg-slate-50 text-slate-700');
                box.textContent = text || '';
                box.classList.toggle('hidden', !text);
            }

            // ---------------------------------------------------------- Adding and analyzing
            function addFiles(fileList) {
                const files = Array.from(fileList || []);
                if (files.length === 0) return;
                const room = config.maxPhotos - photos.length;
                if (room <= 0) {
                    announce('You can add up to ' + config.maxPhotos + ' photos. Remove one to add another.', 'error');
                    return;
                }
                const problems = [];
                let added = 0;
                let overflow = 0;
                files.forEach(function (file) {
                    if (!config.mimes.includes(file.type)) return problems.push(file.name + ': only JPG, PNG or WEBP photos can be analyzed.');
                    if (file.size > config.maxBytes) return problems.push(file.name + ': larger than ' + config.maxMb + ' MB.');
                    if (photos.some(function (photo) { return photo.file && photo.file.name === file.name && photo.file.size === file.size && photo.file.lastModified === file.lastModified; })) {
                        return problems.push(file.name + ': already added.');
                    }
                    if (added >= room) { overflow++; return; }
                    const photo = newPhoto(file);
                    photos.push(photo);
                    added++;
                    generateThumbnail(file, function (thumb) { if (thumb) { photo.thumbnailUrl = thumb; changed(); } });
                });
                if (overflow > 0) problems.push(overflow + ' photo' + (overflow === 1 ? ' was' : 's were') + ' not added: the limit is ' + config.maxPhotos + '.');
                if (added > 0) galleryPage = Math.floor((photos.length - 1) / GALLERY_PAGE);
                if (problems.length) announce('Not added — ' + problems.join(' '), 'error');
                else if (added > 0 && pickedAreas().length > 1) announce('Choose which of your selected areas each new photo is for, then press Analyze.', 'warning');
                else announce('', null);
                render();
                changed();
                processQueue();
            }

            function processQueue() {
                if (activeAiAbortController || !config.isActive()) return renderProgress();
                const next = photos.find(function (photo) { return photo.status === 'pending'; });
                if (!next) return renderProgress();
                if (!next.file) {
                    next.status = 'failed';
                    next.errorType = 'restored';
                    next.error = RESTORED_NO_FILE;
                    render();
                    changed();
                    return processQueue();
                }
                analyze(next);
            }

            function failure(status, data) {
                if (status === 429) return ['rate_limit', 'Too many photos were analyzed in the last minute. Wait about a minute, then press Retry.'];
                if (status === 419) return ['session', 'Your session has expired. Reload the page, then add this photo again.'];
                if (status === 413) return ['too_large', 'This photo is too large to upload. Use a photo under ' + config.maxMb + ' MB.'];
                if (status === 422 && data && data.errors) {
                    const first = Object.values(data.errors).flat()[0];
                    return ['validation', first || 'This photo could not be checked. Please try another photo.'];
                }
                if (data && data.message) return [data.is_quota_error ? 'service' : (data.error_type || 'analysis'), data.message];
                return ['server', 'The analysis could not be completed (error ' + status + '). Please press Retry.'];
            }

            function analyze(photo) {
                const controller = new AbortController();
                const fingerprint = config.contextFingerprint();
                const requestBriefKey = briefKeyFor(photo.photoAreas);
                activeAiAbortController = controller;
                activeRequest = { photo: photo, fingerprint: fingerprint };
                photo.status = 'uploading';
                resetResult(photo);

                const formData = new FormData();
                formData.append('inspiration_image', photo.file);
                config.appendContext(formData);
                brief()?.appendTo(formData);
                photo.photoAreas.forEach(function (area) { formData.append('photo_areas[]', area); });
                render();
                renderProgress();
                changed();

                fetch(config.analyzeUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json' },
                    body: formData,
                    signal: controller.signal,
                })
                    .then(function (res) {
                        return res.json().catch(function () { return null; }).then(function (data) { return { res: res, data: data }; });
                    })
                    .then(function (result) {
                        // Cancelled, superseded or removed meanwhile: the result is not used.
                        if (activeAiAbortController !== controller || !photos.includes(photo)) return;
                        if (config.contextFingerprint() !== fingerprint) {
                            photo.status = 'cancelled';
                            photo.error = 'Your event details changed while this photo was being analyzed. Analyze it again so the AI uses the updated details.';
                            return;
                        }
                        const data = result.data;
                        if (result.res.ok && data && data.success && data.analysis_token) {
                            photo.status = 'completed';
                            photo.token = data.analysis_token;
                            photo.tempPath = data.analysis_temp_path || null;
                            photo.analysisData = data.analysis_data || null;
                            photo.nonce = data.analysis_nonce || null;
                            photo.analysis = data.analysis || {};
                            photo.suggestedMaterials = Array.isArray(photo.analysis.suggested_materials) ? photo.analysis.suggested_materials : [];
                            photo.visualAnalysis = photo.analysis.visual_analysis || null;
                            if (Array.isArray(data.photo_areas)) photo.photoAreas = data.photo_areas;
                            photo.contextFingerprint = fingerprint;
                            photo.briefKey = requestBriefKey;
                            if (!photos.some(function (other) { return other !== photo && viewState(other) === 'stale'; })) brief()?.markAnalyzed();
                            return;
                        }
                        const [type, message] = failure(result.res.status, data);
                        photo.status = 'failed';
                        photo.errorType = type;
                        photo.error = message;
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') return; // the canceller set the state
                        if (activeAiAbortController !== controller || !photos.includes(photo)) return;
                        photo.status = 'failed';
                        photo.errorType = 'connection';
                        photo.error = 'The photo could not reach Raflora because of a connection problem. Check your connection, then press Retry.';
                    })
                    .finally(function () {
                        if (activeAiAbortController === controller) {
                            activeAiAbortController = null;
                            activeRequest = null;
                        }
                        render();
                        changed();
                        scheduleEstimate();
                        processQueue();
                    });
            }

            function cancel(reason) {
                const controller = activeAiAbortController;
                const request = activeRequest;
                if (!controller) return false;
                activeAiAbortController = null;
                activeRequest = null;
                controller.abort();
                if (request && photos.includes(request.photo)) {
                    request.photo.status = 'cancelled';
                    request.photo.error = reason || 'You cancelled the analysis of this photo.';
                }
                render();
                changed();
                processQueue();
                return true;
            }

            function analyzeNow(photo) {
                if (!photo || ['pending', 'uploading'].includes(photo.status)) return;
                if (!photo.file) return announce(RESTORED_NO_FILE, 'error');
                photo.photoAreas = pickedAreas().filter(function (area) { return photo.photoAreas.includes(area); });
                if (pickedAreas().length > 0 && photo.photoAreas.length === 0) {
                    photo.status = 'needs_areas';
                    resetResult(photo);
                    render();
                    return announce('Choose which of your selected areas this photo is for, then analyze it.', 'warning');
                }
                photo.status = 'pending';
                resetResult(photo);
                announce('', null);
                render();
                changed();
                processQueue();
            }

            function toggleArea(photo, area) {
                if (!photo || !photo.file || ['pending', 'uploading'].includes(photo.status)) return;
                const chosen = new Set(photo.photoAreas);
                chosen.has(area) ? chosen.delete(area) : chosen.add(area);
                photo.photoAreas = pickedAreas().filter(function (key) { return chosen.has(key); });
                photo.areasUnknown = false;
                if (photo.status !== 'needs_areas') {
                    // Analyzed for other areas: the chosen ones need a new analysis.
                    photo.status = 'needs_areas';
                    resetResult(photo);
                }
                render();
                changed();
                scheduleEstimate();
            }

            function remove(photo) {
                if (!photo) return;
                if (activeRequest && activeRequest.photo === photo) {
                    const controller = activeAiAbortController;
                    activeAiAbortController = null;
                    activeRequest = null;
                    controller.abort();
                }
                photos = photos.filter(function (other) { return other !== photo; });
                if (photo.previewUrl && photo.previewUrl.startsWith('blob:')) URL.revokeObjectURL(photo.previewUrl);
                if (photos.length === 0) brief()?.resetAnalyzed();
                if (viewingId === photo.id) {
                    const next = withResults()[0];
                    if (next) viewingId = next.id; else closeDialog(dialogs.photo);
                }
                announce('', null);
                render();
                changed();
                scheduleEstimate();
                processQueue();
            }

            // ---------------------------------------------------------- Hidden inputs and gates
            function syncHiddenInputs() {
                const ready = readyPhotos();
                const set = function (name, value) { const input = byId(prefix + '_analysis_' + name); if (input) input.value = value || ''; };
                set('tokens', ready.length ? JSON.stringify(ready.map(function (photo) { return photo.token; })) : '');
                const primary = ready[0];
                set('token', primary && primary.token);
                set('temp_path', primary && primary.tempPath);
                set('data', primary && primary.analysisData);
                set('nonce', primary && primary.nonce);
            }

            // Why Step 2 cannot be left yet, or null.
            function blockingIssue() {
                if (photos.length === 0) return 'Add at least one inspiration photo and wait for its analysis to finish.';
                const states = photos.map(viewState);
                if (states.includes('pending') || states.includes('uploading')) return 'Please wait until every photo has finished analyzing.';
                if (states.includes('needs_areas') || states.includes('areas_changed')) return 'Choose the areas for each photo marked "Choose areas" or "Areas changed", then analyze it.';
                if (states.includes('stale')) return 'Some photos are out of date because your event details or design preferences changed. Re-analyze or remove them before continuing.';
                if (readyPhotos().length === 0) return 'None of your photos could be analyzed. Retry them or add a clearer photo.';
                return null;
            }

            // ---------------------------------------------------------- Rendering: gallery
            const BADGES = {
                analyzed: ['✓ Analyzed', 'bg-emerald-100 text-emerald-800'],
                uploading: ['Analyzing…', 'bg-sky-100 text-sky-800'],
                pending: ['Waiting', 'bg-slate-100 text-slate-700'],
                needs_areas: ['! Choose areas', 'bg-amber-100 text-amber-900'],
                areas_changed: ['! Areas changed', 'bg-amber-100 text-amber-900'],
                stale: ['↻ Out of date', 'bg-amber-100 text-amber-900'],
                failed: ['✕ Failed', 'bg-rose-100 text-rose-800'],
                cancelled: ['■ Cancelled', 'bg-slate-200 text-slate-700'],
            };

            function photoCard(photo, number) {
                const state = viewState(photo);
                const card = make('article', 'flex min-w-0 gap-2.5 rounded-xl border bg-white p-2 shadow-2xs ' + (state === 'analyzed' ? 'border-slate-200' : (state === 'failed' ? 'border-rose-200' : (['stale', 'needs_areas', 'areas_changed'].includes(state) ? 'border-amber-200' : 'border-slate-200'))));
                card.dataset.photoId = photo.id;
                card.setAttribute('aria-label', 'Photo ' + number);

                const thumbButton = button('', state === 'analyzed' || (photo.status === 'completed' && photo.analysis) ? 'view-photo' : 'enlarge-photo', 'relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', { photoId: photo.id });
                thumbButton.setAttribute('aria-label', (thumbButton.dataset.action === 'view-photo' ? 'View the analysis of photo ' : 'Enlarge photo ') + number);
                const img = photoImage('h-full w-full object-cover', photo.thumbnailUrl || photo.previewUrl, config.placeholderUrl);
                const tag = make('span', 'absolute bottom-0.5 left-0.5 rounded bg-slate-900/75 px-1 text-[9px] font-bold leading-4 text-white', '#' + number);
                thumbButton.append(img, tag);
                card.appendChild(thumbButton);

                const body = make('div', 'min-w-0 flex-1 space-y-1');
                const head = make('div', 'flex flex-wrap items-center gap-1.5');
                head.appendChild(make('span', 'text-xs font-bold text-slate-800', 'Photo ' + number));
                const [badgeText, badgeClass] = BADGES[state] || [state, 'bg-slate-100 text-slate-700'];
                const badge = make('span', 'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold ' + badgeClass, badgeText);
                if (state === 'uploading') badge.prepend(make('span', 'h-1.5 w-1.5 animate-pulse rounded-full bg-sky-600'));
                head.appendChild(badge);
                body.appendChild(head);

                let message = '';
                if (state === 'analyzed') message = photo.areasUnknown && !photo.analysis ? 'Analyzed earlier in this session.' : photo.suggestedMaterials.length + ' material' + (photo.suggestedMaterials.length === 1 ? '' : 's') + ' suggested';
                else if (state === 'pending') message = config.isActive() ? 'In the queue to be analyzed.' : 'Will be analyzed when you return to this step.';
                else if (state === 'uploading') message = 'The AI is reading this photo.';
                else if (state === 'needs_areas') message = 'Choose the areas this photo is for, then analyze it.';
                else if (state === 'areas_changed') message = 'Your selected areas changed. Choose this photo\'s areas and analyze it again.';
                else if (state === 'stale') message = staleReason(photo);
                else message = photo.error || (state === 'failed' ? 'This photo could not be analyzed.' : 'Analysis cancelled.');
                body.appendChild(make('p', 'line-clamp-3 text-[11px] leading-snug ' + (['failed'].includes(state) ? 'text-rose-700' : (['stale', 'needs_areas', 'areas_changed'].includes(state) ? 'text-amber-900' : 'text-slate-500')), message));

                const picked = pickedAreas();
                if (picked.length > 0) {
                    const canEdit = Boolean(photo.file) && !['pending', 'uploading'].includes(photo.status);
                    const group = make('div', 'flex flex-wrap gap-1');
                    group.setAttribute('role', 'group');
                    group.setAttribute('aria-label', 'Areas photo ' + number + ' is for');
                    picked.forEach(function (area) {
                        const on = photo.photoAreas.includes(area);
                        const chip = button('', 'toggle-area', 'inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold transition disabled:cursor-not-allowed ' + (on ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'), { photoId: photo.id, area: area });
                        chip.disabled = !canEdit;
                        chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                        const dot = make('span', 'h-1.5 w-1.5 rounded-full');
                        dot.style.backgroundColor = areaColor(area);
                        dot.setAttribute('aria-hidden', 'true');
                        chip.append(dot, document.createTextNode((on ? '✓ ' : '') + areaLabel(area)));
                        group.appendChild(chip);
                    });
                    body.appendChild(group);
                } else if (photo.photoAreas.length > 0 && state === 'areas_changed') {
                    body.appendChild(make('p', 'text-[10px] text-slate-500', 'No areas are selected now, so this photo is analyzed for the whole design.'));
                }

                const actions = make('div', 'flex flex-wrap gap-1 pt-0.5');
                if (state === 'analyzed' && photo.analysis) actions.appendChild(button('View details', 'view-photo', BTN_PRIMARY, { photoId: photo.id }));
                if (['needs_areas', 'areas_changed'].includes(state) && photo.file) {
                    const analyzeButton = button('Analyze', 'analyze-photo', BTN_PRIMARY, { photoId: photo.id });
                    analyzeButton.disabled = picked.length > 0 && photo.photoAreas.length === 0;
                    actions.appendChild(analyzeButton);
                }
                if (['stale', 'cancelled'].includes(state) && photo.file) actions.appendChild(button('↻ Re-analyze', 'analyze-photo', BTN_PRIMARY, { photoId: photo.id }));
                if (state === 'failed' && photo.file) actions.appendChild(button('↻ Retry', 'analyze-photo', BTN_PRIMARY, { photoId: photo.id }));
                if (['stale', 'cancelled', 'needs_areas', 'areas_changed'].includes(state) && !photo.file) body.appendChild(make('p', 'text-[10px] text-slate-500', 'Remove this photo and add it again to analyze it.'));
                if (state === 'uploading') actions.appendChild(button('Cancel', 'cancel-analysis', BTN_PLAIN));
                else actions.appendChild(button('Remove', 'remove-photo', BTN_DANGER, { photoId: photo.id }));
                body.appendChild(actions);
                card.appendChild(body);
                return card;
            }

            function renderGallery() {
                const grid = byId('uploaded-images-grid');
                if (!grid) return;
                const pages = Math.max(1, Math.ceil(photos.length / GALLERY_PAGE));
                galleryPage = Math.min(Math.max(0, galleryPage), pages - 1);
                const start = galleryPage * GALLERY_PAGE;
                // Only the current page is in the DOM.
                grid.replaceChildren.apply(grid, photos.slice(start, start + GALLERY_PAGE).map(function (photo, offset) { return photoCard(photo, start + offset + 1); }));
                one('[data-gallery-empty]')?.classList.toggle('hidden', photos.length > 0);

                const pager = one('[data-gallery-pager]');
                if (pager) {
                    pager.classList.toggle('hidden', photos.length <= GALLERY_PAGE);
                    pager.classList.toggle('flex', photos.length > GALLERY_PAGE);
                    one('[data-gallery-range]').textContent = 'Photos ' + (start + 1) + '–' + Math.min(start + GALLERY_PAGE, photos.length) + ' of ' + photos.length;
                    pager.querySelector('[data-step="-1"]').disabled = galleryPage === 0;
                    pager.querySelector('[data-step="1"]').disabled = galleryPage >= pages - 1;
                }
                byId('uploaded-count-badge').textContent = photos.length;
                const dropzone = byId('dropzone_container');
                dropzone?.classList.toggle('hidden', photos.length >= config.maxPhotos);
                byId('btn-clear-all')?.classList.toggle('hidden', photos.length < 2);
            }

            function renderProgress() {
                const card = byId('ai-progress-card');
                if (!card) return;
                const photo = activeRequest && activeRequest.photo;
                card.classList.toggle('hidden', !photo);
                if (!photo) return;
                const number = photos.indexOf(photo) + 1;
                const queued = photos.filter(function (other) { return other.status === 'pending'; }).length;
                byId('progress-card-title').textContent = 'Analyzing photo ' + number + ' of ' + photos.length + (queued ? ' · ' + queued + ' waiting' : '');
                byId('progress-img-thumb').src = photo.thumbnailUrl || photo.previewUrl || '';
            }

            // ---------------------------------------------------------- Rendering: preferences summary
            function renderPreferencesSummary() {
                const state = brief()?.getState();
                if (!state) return;
                const areas = state.decor_areas || [];
                const countBadge = byId('summary-area-count-badge');
                if (countBadge) {
                    countBadge.textContent = areas.length ? areas.length + ' area' + (areas.length === 1 ? '' : 's') + ' selected' : 'No areas selected — the AI plans the whole photo';
                    countBadge.className = 'rounded-full border px-2 py-0.5 font-semibold ' + (areas.length ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-100 text-slate-700');
                }
                // Each picked area with its note preview and an Add/Edit note action (area-note dialog).
                const chips = byId('summary-selected-chips-preview');
                if (chips) {
                    chips.replaceChildren.apply(chips, areas.map(function (key) {
                        const note = (state.decor_area_notes || {})[key] || '';
                        const chip = make('span', 'inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white py-0.5 pl-2 pr-0.5 text-[11px] text-slate-800 shadow-2xs');
                        chip.dataset.areaChip = key;
                        const dot = make('span', 'h-2 w-2 shrink-0 rounded-full');
                        dot.style.backgroundColor = areaColor(key);
                        dot.setAttribute('aria-hidden', 'true');
                        chip.append(dot, make('span', 'shrink-0 font-semibold', areaLabel(key)));
                        if (note) chip.appendChild(make('span', 'min-w-0 max-w-[12rem] truncate italic text-slate-500', '“' + note + '”'));
                        const edit = button(note ? 'Edit note' : '+ Add note', 'edit-area-note', 'shrink-0 rounded-md px-1.5 py-0.5 font-bold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', { area: key });
                        edit.setAttribute('aria-label', (note ? 'Edit the note for ' : 'Add a note for ') + areaLabel(key));
                        chip.appendChild(edit);
                        return chip;
                    }));
                }
                const show = function (id, text) { const el = byId(id); if (!el) return; el.textContent = text || ''; el.classList.toggle('hidden', !text); };
                show('summary-usage-badge', state.photo_usage ? 'Photo: ' + (config.usageLabels[state.photo_usage] || state.photo_usage) : '');
                show('summary-theme-badge', state.theme ? 'Theme: ' + (state.theme === 'other' && state.theme_other ? state.theme_other : (config.themeLabels[state.theme] || state.theme)) : '');
                const palette = byId('summary-palette-badge');
                if (palette) {
                    palette.replaceChildren();
                    const colors = state.color_palette || [];
                    palette.classList.toggle('hidden', colors.length === 0);
                    palette.classList.toggle('inline-flex', colors.length > 0);
                    if (colors.length) {
                        palette.appendChild(make('span', 'mr-0.5 font-semibold text-slate-500', 'Colors:'));
                        colors.forEach(function (color) { palette.appendChild(make('span', 'text-slate-700', color)); });
                    }
                }
                const extras = [state.preferred_flowers && 'preferred flowers', state.avoid_flowers && 'flowers to avoid', state.flower_material && 'flower material', state.budget_range && 'budget'].filter(Boolean);
                show('summary-more-badge', extras.length ? '+ ' + extras.join(', ') : '');
            }

            // ---------------------------------------------------------- Rendering: summary card
            function renderSummary() {
                const states = photos.map(viewState);
                const analyzed = states.filter(function (state) { return state === 'analyzed'; }).length;
                const attention = states.filter(function (state) { return ['stale', 'needs_areas', 'areas_changed', 'failed', 'cancelled'].includes(state); }).length;
                const working = states.filter(function (state) { return ['pending', 'uploading'].includes(state); }).length;
                const parts = photos.length ? [photos.length + ' added', analyzed + ' analyzed'] : [];
                if (working) parts.push(working + ' in progress');
                if (attention) parts.push(attention + ' need' + (attention === 1 ? 's' : '') + ' attention');
                const set = function (key, text) { const el = one('[data-summary="' + key + '"]'); if (el) el.textContent = text; };
                set('photos', parts.length ? parts.join(' · ') : 'None yet');
                const picked = pickedAreas();
                set('areas', picked.length ? picked.map(areaLabel).join(', ') : 'Not chosen (whole design)');
                const materialCount = estimate.state === 'ready' && estimate.data && Number.isInteger(estimate.data.material_count) ? estimate.data.material_count : null;
                set('materials', materialCount !== null ? materialCount + ' (combined, AI-suggested)' : (estimate.state === 'loading' ? 'Updating…' : '—'));
                renderEstimateSummary();

                const hasEstimate = estimate.state === 'ready' && estimate.data;
                const areasButton = one('[data-action="open-areas"]');
                const pricesButton = one('[data-action="open-prices"]');
                const areaList = hasEstimate && estimate.data.area_breakdown && Array.isArray(estimate.data.area_breakdown.areas) ? estimate.data.area_breakdown.areas : [];
                if (areasButton) {
                    areasButton.disabled = areaList.length === 0;
                    // inline-flex outranks hidden in the built CSS, so both are toggled.
                    areasButton.classList.toggle('hidden', picked.length === 0);
                    areasButton.classList.toggle('inline-flex', picked.length > 0);
                }
                if (pricesButton) pricesButton.disabled = !hasEstimate;
                set('buttons-hint', hasEstimate
                    ? (picked.length ? 'Open an area to see its materials, notes and range.' : 'Pick areas in your preferences to see a range per area.')
                    : 'Details open once at least one photo is analyzed.');
            }

            function renderEstimateSummary() {
                const status = one('[data-summary="estimate-status"]');
                const list = one('[data-summary="estimate-list"]');
                const retry = one('[data-action="retry-estimate"]');
                if (!status || !list) return;
                list.replaceChildren();
                retry?.classList.toggle('hidden', estimate.state !== 'error');
                const groups = estimate.state === 'ready' && estimate.data && estimate.data.estimate && Array.isArray(estimate.data.estimate.groups) ? estimate.data.estimate.groups : [];
                if (estimate.state === 'idle') {
                    status.textContent = photos.some(function (photo) { return photo.status === 'completed'; })
                        ? 'Re-analyze the photos that need attention to update the estimate.'
                        : 'Appears after your photos are analyzed.';
                }
                else if (estimate.state === 'loading') status.textContent = 'Updating the estimate for your analyzed photos…';
                else if (estimate.state === 'error') status.textContent = estimate.message;
                else if (groups.length === 0) status.textContent = 'No estimate is available for these photos. Raflora prices your design in the official quotation.';
                else status.textContent = (estimate.data.estimate.mode === 'areas' ? 'Per area' : 'For your whole design') + (estimate.data.estimate.labour_included ? ', including labour & setup:' : ' (labour & setup not included):');
                list.classList.toggle('hidden', groups.length === 0);
                groups.slice(0, 3).forEach(function (group) {
                    const li = make('li', 'flex items-baseline justify-between gap-2 text-[11px]');
                    const label = make('span', 'flex min-w-0 items-center gap-1.5 text-slate-700');
                    const dot = make('span', 'h-2 w-2 shrink-0 rounded-full');
                    dot.style.backgroundColor = group.color || '#7c3aed';
                    dot.setAttribute('aria-hidden', 'true');
                    label.append(dot, make('span', 'truncate', group.label));
                    const value = make('span', 'shrink-0 font-semibold tabular-nums ' + (group.display ? 'text-slate-900' : 'text-slate-400'), group.display || 'Unavailable');
                    if (group.status === 'incomplete') value.appendChild(make('span', 'ml-1 text-[9px] font-bold uppercase text-amber-800', 'partial'));
                    li.append(label, value);
                    list.appendChild(li);
                });
                if (groups.length > 3) list.appendChild(make('li', 'text-[10px] text-slate-500', '+ ' + (groups.length - 3) + ' more in the price breakdown'));
            }

            // ---------------------------------------------------------- Estimate (server only)
            function scheduleEstimate(force) {
                clearTimeout(estimate.timer);
                const ready = readyPhotos();
                const key = JSON.stringify([ready.map(function (photo) { return photo.token; }), brief()?.getState() || null]);
                if (ready.length === 0) {
                    estimate.request++;
                    Object.assign(estimate, { state: 'idle', data: null, message: '', key: key });
                    return renderEstimateViews();
                }
                if (!force && key === estimate.key && estimate.state !== 'error') return;
                estimate.key = key;
                estimate.state = 'loading';
                renderEstimateViews();
                estimate.timer = setTimeout(function () { fetchEstimate(ready, key); }, 350);
            }
            function fetchEstimate(ready, key) {
                const requestId = ++estimate.request;
                const formData = new FormData();
                ready.forEach(function (photo) { formData.append('analysis_tokens[]', photo.token); });
                brief()?.appendTo(formData);
                fetch(config.estimateUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json' }, body: formData })
                    .then(function (res) { return res.json().catch(function () { return null; }).then(function (data) { return { res: res, data: data }; }); })
                    .then(function (result) {
                        if (requestId !== estimate.request) return;
                        if (result.res.ok && result.data) {
                            Object.assign(estimate, { state: 'ready', data: result.data, message: '' });
                        } else {
                            Object.assign(estimate, { state: 'error', data: null, message: result.res.status === 429
                                ? 'The estimate was updated too often. Press "Try again" in a moment.'
                                : ((result.data && result.data.message) || 'The estimate is unavailable right now. Raflora prices your design in the official quotation.') });
                        }
                        renderEstimateViews();
                    })
                    .catch(function () {
                        if (requestId !== estimate.request) return;
                        Object.assign(estimate, { state: 'error', data: null, message: 'The estimate could not be loaded because of a connection problem.' });
                        renderEstimateViews();
                    });
            }
            function renderEstimateViews() {
                renderSummary();
                const data = estimate.state === 'ready' ? estimate.data : null;
                window.RafloraAiEstimate?.render(prefix + '-ai-estimate', data ? data.estimate : null);
                const status = dialogs.prices?.querySelector('[data-price-status]');
                if (status) {
                    const groups = data && data.estimate && Array.isArray(data.estimate.groups) ? data.estimate.groups : [];
                    status.textContent = estimate.state === 'loading' ? 'Updating…' : (estimate.state === 'error' ? estimate.message : (groups.length ? '' : 'No estimate is available yet.'));
                    status.classList.toggle('hidden', !status.textContent);
                }
                if (isOpen(dialogs.areas)) renderAreaDialog();
            }

            // ---------------------------------------------------------- Dialog: photo analysis
            function annotationBoxes(mat) {
                if (!mat || !mat.is_detected || mat.is_recommendation) return [];
                const boxes = (Array.isArray(mat.boxes) ? mat.boxes : []).map(function (entry) {
                    const box = Array.isArray(entry && entry.box_2d) ? entry.box_2d.map(Number) : null;
                    if (!box || box.length !== 4 || box.some(function (value) { return !Number.isFinite(value); })) return null;
                    const [ymin, xmin, ymax, xmax] = box;
                    if (ymin < 0 || xmin < 0 || ymax > 1000 || xmax > 1000 || ymax <= ymin || xmax <= xmin) return null;
                    return { x: xmin / 1000, y: ymin / 1000, width: (xmax - xmin) / 1000, height: (ymax - ymin) / 1000, possible: Boolean(entry.possible) };
                }).filter(Boolean);
                if (boxes.length > 0 || (Array.isArray(mat.boxes) && mat.boxes.length > 0)) return boxes;
                const loc = mat.visual_location;
                const x = Number(loc && loc.x);
                const y = Number(loc && loc.y);
                if (!loc || !Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 1 || y < 0 || y > 1) return [];
                const width = Number(loc.width) > 0 ? Math.min(Number(loc.width), 1 - x) : 0;
                const height = Number(loc.height) > 0 ? Math.min(Number(loc.height), 1 - y) : 0;
                return [{ x: x, y: y, width: width, height: height, possible: false, point: !(width > 0 && height > 0) }];
            }
            const annotationColor = function (mat) { return AREA_COLORS[mat && mat.decor_area] || AREA_COLORS[mat && mat.area] || AREA_COLORS.default; };

            // Lays the box layer exactly over the photo as drawn (object-contain leaves bars).
            function fitViewerMarkers() {
                const img = byId('viewer-main-img');
                const layer = byId('viewer-markers-container');
                if (!img || !layer || !img.naturalWidth || !img.naturalHeight || !img.clientWidth) return;
                const scale = Math.min(img.clientWidth / img.naturalWidth, img.clientHeight / img.naturalHeight);
                const width = img.naturalWidth * scale;
                const height = img.naturalHeight * scale;
                layer.style.left = (img.offsetLeft + (img.clientWidth - width) / 2) + 'px';
                layer.style.top = (img.offsetTop + (img.clientHeight - height) / 2) + 'px';
                layer.style.width = width + 'px';
                layer.style.height = height + 'px';
            }

            const viewing = function () { return photos.find(function (photo) { return photo.id === viewingId; }) || null; };

            function selectMaterial(index) {
                materialIndex = index;
                const photo = viewing();
                if (!photo) return;
                renderMaterialCard(photo);
                renderMaterialList(photo);
                renderMarkers(photo);
            }

            function renderMarkers(photo) {
                const layer = byId('viewer-markers-container');
                if (!layer) return;
                layer.replaceChildren();
                const materials = photo.suggestedMaterials || [];
                let located = 0;
                let possible = 0;
                materials.forEach(function (mat, index) {
                    const boxes = annotationBoxes(mat);
                    if (boxes.length === 0) return;
                    located++;
                    const color = annotationColor(mat);
                    const selected = index === materialIndex;
                    boxes.forEach(function (region) {
                        if (region.possible) possible++;
                        const box = make('button');
                        box.type = 'button';
                        box.dataset.annotationBox = '';
                        box.dataset.lineId = mat.line_id || '';
                        const label = (index + 1) + ' · ' + (mat.item_name || 'Material') + (region.possible ? ' · possible match' : '');
                        box.title = label;
                        box.setAttribute('aria-label', 'Select material ' + label);
                        box.style.left = (region.x * 100) + '%';
                        box.style.top = (region.y * 100) + '%';
                        if (region.point) {
                            box.className = 'absolute flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-[10px] font-bold text-white shadow focus:outline-none';
                            box.style.backgroundColor = color;
                            box.textContent = String(index + 1);
                        } else {
                            box.className = 'absolute rounded-[3px] border-2 bg-transparent p-0 transition-opacity focus:outline-none';
                            box.style.width = (region.width * 100) + '%';
                            box.style.height = (region.height * 100) + '%';
                            box.style.borderColor = color;
                            box.style.borderStyle = region.possible ? 'dashed' : 'solid';
                            const tag = make('span', 'pointer-events-none absolute left-0 top-0 rounded-br-[3px] px-1 text-[10px] font-bold leading-4 text-white', (index + 1) + (region.possible ? '?' : ''));
                            tag.style.backgroundColor = color;
                            box.appendChild(tag);
                        }
                        box.style.zIndex = selected ? '20' : '10';
                        box.style.opacity = selected ? '1' : '0.45';
                        if (selected) box.style.boxShadow = '0 0 0 2px rgba(255,255,255,0.9)';
                        box.addEventListener('click', function (event) { event.stopPropagation(); selectMaterial(index); });
                        layer.appendChild(box);
                    });
                });
                const hint = byId('viewer-markers-hint-text');
                if (hint) {
                    hint.textContent = located === 0
                        ? 'The AI could not pinpoint any material in this photo, so no boxes are shown. The material list still applies.'
                        : 'Boxes show where the AI found ' + located + ' of ' + materials.length + ' materials, colored by area.' + (possible > 0 ? ' Dashed boxes are possible matches for Raflora to confirm.' : '') + ' Items without a box are suggestions or could not be pinpointed. Quantities come from your event details, not from the number of boxes.';
                }
                fitViewerMarkers();
            }

            function renderMaterialCard(photo) {
                const materials = photo.suggestedMaterials || [];
                const total = materials.length;
                materialIndex = Math.min(Math.max(0, materialIndex), Math.max(0, total - 1));
                const mat = materials[materialIndex] || {};
                const set = function (id, text) { const el = byId(id); if (el) el.textContent = text; };
                set('mat-current-idx', total ? materialIndex + 1 : 0);
                set('mat-total-count', total);
                set('mat-name', total ? (mat.item_name || 'Floral material') : 'No materials in this photo');
                set('mat-category-badge', (mat.category || 'material').toUpperCase());
                const detected = Boolean(mat.is_detected && !mat.is_recommendation);
                const source = byId('mat-source-badge');
                if (source) {
                    source.textContent = detected ? 'AI Detected' : 'Suggested';
                    source.className = 'rounded px-1.5 py-0.5 text-[10px] font-bold ' + (detected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900');
                }
                // Confidence only when the AI actually reported one.
                const confidence = Number(mat.confidence);
                set('mat-confidence-badge', confidence > 0 ? Math.round(confidence * 100) + '% confidence' : '');
                const quantity = mat.quantity ?? mat.estimated_quantity ?? null;
                set('mat-qty', quantity !== null && quantity !== undefined ? amount(quantity) : 'To be confirmed');
                set('mat-unit', quantity !== null && quantity !== undefined ? (mat.unit_type || 'pcs') : '');
                set('mat-area', mat.decor_area ? areaLabel(mat.decor_area) : (mat.area ? String(mat.area).replace(/_/g, ' ') : 'Arrangement component'));
                const basis = byId('mat-basis');
                if (basis) { basis.textContent = mat.quantity_basis ? 'How it was counted: ' + mat.quantity_basis : ''; basis.classList.toggle('hidden', !mat.quantity_basis); }
                set('mat-note', mat.note || '');
                set('mat-seasonal-text', mat.seasonal_notes ? mat.seasonal_notes + ' (Raflora confirms availability for your event date.)' : 'No seasonal information was given for this item. Raflora will confirm availability for your event date.');
                const refImg = byId('mat-ref-img');
                const refFallback = byId('mat-ref-fallback');
                const refUrl = mat.reference_image_url || null;
                if (refImg && refFallback) {
                    if (refUrl) refImg.src = refUrl;
                    refImg.classList.toggle('hidden', !refUrl);
                    refFallback.classList.toggle('hidden', Boolean(refUrl));
                }
                const alt = byId('mat-alt-box');
                if (alt) {
                    alt.classList.toggle('hidden', !mat.suggested_alternative);
                    set('mat-orig-name', mat.item_name || '');
                    set('mat-alt-name', mat.suggested_alternative || '');
                    set('mat-alt-reason', mat.alternative_reason || '');
                }
                byId('mat-btn-prev').disabled = materialIndex <= 0;
                byId('mat-btn-next').disabled = materialIndex >= total - 1;
            }

            function renderMaterialList(photo) {
                const list = byId('mat-quick-list');
                if (!list) return;
                const materials = photo.suggestedMaterials || [];
                byId('mat-quick-count').textContent = materials.length;
                list.replaceChildren.apply(list, materials.map(function (mat, index) {
                    const selected = index === materialIndex;
                    const detected = Boolean(mat.is_detected && !mat.is_recommendation);
                    const item = make('button', 'flex w-full items-center justify-between gap-2 rounded-xl border p-2 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ' + (selected ? 'border-emerald-500 bg-emerald-50/60' : 'border-slate-100 bg-white hover:bg-slate-50'));
                    item.type = 'button';
                    item.setAttribute('role', 'listitem');
                    item.setAttribute('aria-current', selected ? 'true' : 'false');
                    const left = make('span', 'flex min-w-0 items-center gap-2');
                    left.append(
                        make('span', 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold ' + (selected ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'), String(index + 1)),
                        make('span', 'truncate text-xs font-semibold text-slate-800', mat.item_name || 'Material ' + (index + 1))
                    );
                    const quantity = mat.quantity ?? mat.estimated_quantity ?? null;
                    const right = make('span', 'flex shrink-0 items-center gap-1');
                    right.append(
                        make('span', 'text-[10px] text-slate-500', quantity !== null && quantity !== undefined ? amount(quantity) + ' ' + (mat.unit_type || 'pcs') : 'qty to confirm'),
                        make('span', 'rounded px-1.5 py-0.5 text-[9px] font-bold ' + (detected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'), detected ? 'Detected' : 'Suggested')
                    );
                    item.append(left, right);
                    item.addEventListener('click', function () { selectMaterial(index); });
                    return item;
                }));
            }

            function setImageMode(mode) {
                imageViewMode = mode;
                [['tab-img-original', 'original'], ['tab-img-annotated', 'annotated']].forEach(function ([id, value]) {
                    const tab = byId(id);
                    if (!tab) return;
                    const active = value === mode;
                    tab.setAttribute('aria-pressed', active ? 'true' : 'false');
                    tab.className = 'flex-1 rounded-lg px-2.5 py-1.5 text-xs font-bold transition ' + (active ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-white hover:text-slate-900');
                });
                byId('viewer-markers-container')?.classList.toggle('hidden', mode !== 'annotated');
                const hint = byId('viewer-markers-hint');
                hint?.classList.toggle('hidden', mode !== 'annotated');
                hint?.classList.toggle('flex', mode === 'annotated');
                fitViewerMarkers();
            }

            function renderPhotoDialog() {
                const list = withResults();
                let photo = viewing();
                if (!photo || !list.includes(photo)) photo = list[0] || null;
                if (!photo) return closeDialog(dialogs.photo);
                viewingId = photo.id;
                const position = list.indexOf(photo);
                byId('img-nav-current').textContent = photos.indexOf(photo) + 1;
                byId('img-nav-total').textContent = photos.length;
                byId('btn-img-prev').disabled = position <= 0;
                byId('btn-img-next').disabled = position >= list.length - 1;
                const areaNames = photo.photoAreas.map(areaLabel).join(', ');
                byId('viewer-photo-areas').textContent = (areaNames ? 'Analyzed for: ' + areaNames + ' · ' : '') + (list.length > 1 ? 'Analyzed photo ' + (position + 1) + ' of ' + list.length : 'Your analyzed photo');

                const state = viewState(photo);
                const notice = dialogs.photo.querySelector('[data-photo-dialog-notice]');
                const noticeText = state === 'stale' ? staleReason(photo) + ' Re-analyze it before you continue.'
                    : (state === 'areas_changed' ? 'Your selected areas changed. Choose this photo\'s areas and analyze it again.' : '');
                notice.textContent = noticeText;
                notice.classList.toggle('hidden', !noticeText);

                const img = byId('viewer-main-img');
                if (img && img.getAttribute('src') !== photo.previewUrl) img.src = photo.previewUrl || '';
                const analysis = photo.analysis || {};
                const visual = photo.visualAnalysis || {};
                byId('viewer-summary-text').textContent = '“' + (analysis.overall_assessment || visual.composition_summary || 'Floral inspiration analyzed by the AI. Material suggestions prepared for Raflora review.') + '”';
                byId('viewer-badge-mats-count').textContent = photo.suggestedMaterials.length;
                const style = (visual.detected_arrangement && visual.detected_arrangement.style) || analysis.event_style || '';
                byId('viewer-badge-style').classList.toggle('hidden', !style);
                byId('viewer-badge-style').classList.toggle('inline-flex', Boolean(style));
                byId('viewer-badge-style-text').textContent = style ? style.charAt(0).toUpperCase() + style.slice(1) : '';
                const overall = Number(visual.composition_confidence);
                const conf = byId('viewer-badge-conf');
                conf.classList.toggle('hidden', !(overall > 0));
                conf.classList.toggle('inline-flex', overall > 0);
                byId('viewer-badge-conf-text').textContent = overall > 0 ? Math.round(overall * 100) + '% overall confidence' : '';

                // The photo's areas (changing them needs a new analysis).
                const picked = pickedAreas();
                const selector = byId('viewer-photo-area-selector');
                const chips = byId('viewer-photo-area-chips');
                const reanalyze = byId('viewer-photo-reanalyze-wrap');
                selector.classList.toggle('hidden', picked.length === 0);
                const canEdit = Boolean(photo.file) && !['pending', 'uploading'].includes(photo.status);
                chips.replaceChildren.apply(chips, picked.map(function (area) {
                    const on = photo.photoAreas.includes(area);
                    const chip = button('', 'toggle-area', 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ' + (on ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'), { photoId: photo.id, area: area });
                    chip.disabled = !canEdit;
                    chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                    const dot = make('span', 'h-2 w-2 shrink-0 rounded-full');
                    dot.style.backgroundColor = areaColor(area);
                    dot.setAttribute('aria-hidden', 'true');
                    chip.append(dot, document.createTextNode(areaLabel(area)));
                    return chip;
                }));
                reanalyze.replaceChildren();
                if (['stale', 'areas_changed'].includes(state) && photo.file) reanalyze.appendChild(button('↻ Re-analyze this photo', 'analyze-photo', BTN_PRIMARY, { photoId: photo.id }));
                reanalyze.classList.toggle('hidden', reanalyze.childElementCount === 0);

                setImageMode(imageViewMode);
                renderMaterialCard(photo);
                renderMaterialList(photo);
                renderMarkers(photo);
            }

            function openPhoto(photo, opener) {
                if (!photo) return;
                viewingId = photo.id;
                materialIndex = 0;
                openDialog(dialogs.photo, opener);
                renderPhotoDialog();
            }

            function openEnlarged(photo, withBoxes) {
                if (!photo || !window.RafloraAnnotationViewer) return;
                const stage = make('div');
                stage.dataset.annotatedImageStage = '';
                const img = make('img', 'block max-h-full max-w-full object-contain');
                img.src = photo.previewUrl || '';
                img.alt = 'Inspiration photo ' + (photos.indexOf(photo) + 1);
                stage.appendChild(img);
                let legend = null;
                if (withBoxes) {
                    // The box layer sits exactly on the photo, so its percentage boxes carry over unchanged.
                    document.querySelectorAll('#viewer-markers-container [data-annotation-box]').forEach(function (box) {
                        const clone = box.cloneNode(true);
                        clone.style.opacity = '1';
                        clone.style.boxShadow = '';
                        stage.appendChild(clone);
                    });
                    legend = make('ol', 'space-y-1');
                    (photo.suggestedMaterials || []).forEach(function (mat, index) {
                        if (annotationBoxes(mat).length === 0) return;
                        const entry = make('button', 'flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left text-[11px] text-slate-700 hover:bg-slate-100');
                        entry.type = 'button';
                        entry.dataset.lineId = mat.line_id || '';
                        const number = make('span', 'flex h-4 min-w-4 shrink-0 items-center justify-center rounded-[3px] px-1 text-[10px] font-bold text-white', String(index + 1));
                        number.style.backgroundColor = annotationColor(mat);
                        entry.append(number, make('span', 'min-w-0 truncate font-semibold text-slate-800', mat.item_name || 'Material'));
                        const item = make('li');
                        item.appendChild(entry);
                        legend.appendChild(item);
                    });
                }
                const areas = photo.photoAreas.map(areaLabel).join(', ');
                window.RafloraAnnotationViewer.open({
                    stage: stage,
                    legend: legend,
                    title: 'Inspiration Image ' + (photos.indexOf(photo) + 1) + ' of ' + photos.length + (areas ? ' — ' + areas : ''),
                    onSelect: withBoxes ? function (lineId) {
                        const index = (photo.suggestedMaterials || []).findIndex(function (mat) { return mat.line_id === lineId; });
                        if (index >= 0) selectMaterial(index);
                    } : null,
                });
            }

            // ---------------------------------------------------------- Dialog: areas
            function areaList() {
                const breakdown = estimate.state === 'ready' && estimate.data ? estimate.data.area_breakdown : null;
                return breakdown && Array.isArray(breakdown.areas) ? breakdown.areas : [];
            }
            function renderAreaDialog() {
                const areas = areaList();
                const body = dialogs.areas.querySelector('[data-area-body]');
                const tabs = dialogs.areas.querySelector('[data-area-tabs]');
                if (areas.length === 0) {
                    body.replaceChildren(make('p', 'text-xs text-slate-600', estimate.state === 'loading' ? 'Updating…' : 'Area details appear once photos for your selected areas are analyzed.'));
                    tabs.replaceChildren();
                    return;
                }
                areaIndex = Math.min(Math.max(0, areaIndex), areas.length - 1);
                const area = areas[areaIndex];
                dialogs.areas.querySelector('[data-area-title]').textContent = area.label;
                dialogs.areas.querySelector('[data-area-dot]').style.backgroundColor = area.color || areaColor(area.key);
                dialogs.areas.querySelector('[data-area-position]').textContent = 'Area ' + (areaIndex + 1) + ' of ' + areas.length;
                dialogs.areas.querySelector('[data-action="area-step"][data-step="-1"]').disabled = areaIndex === 0;
                dialogs.areas.querySelector('[data-action="area-step"][data-step="1"]').disabled = areaIndex >= areas.length - 1;
                tabs.replaceChildren.apply(tabs, areas.map(function (other, index) {
                    const tab = button('', 'area-tab', 'inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold transition ' + (index === areaIndex ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'), { index: String(index) });
                    tab.setAttribute('aria-pressed', index === areaIndex ? 'true' : 'false');
                    const dot = make('span', 'h-1.5 w-1.5 rounded-full');
                    dot.style.backgroundColor = other.color || areaColor(other.key);
                    dot.setAttribute('aria-hidden', 'true');
                    tab.append(dot, document.createTextNode(other.label));
                    return tab;
                }));

                const sections = [];
                // The client's own note for this area (stored with the booking and sent to the AI).
                if (area.key !== config.sharedKey) {
                    const note = (brief()?.getState()?.decor_area_notes || {})[area.key];
                    const noteBox = make('section', 'rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 shadow-2xs space-y-1.5');
                    const noteHead = make('div', 'flex items-center justify-between gap-2 pb-1.5 border-b border-slate-200/70');
                    const titleWrap = make('div', 'flex items-center gap-1.5');
                    titleWrap.append(make('span', 'text-slate-500', '📝'), make('h4', 'text-xs font-bold uppercase tracking-wide text-slate-700', 'Client-Specific Note'));
                    noteHead.append(titleWrap, button(note ? 'Edit note' : '+ Add note', 'edit-area-note', 'text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline', { area: area.key }));
                    noteBox.append(noteHead, make('p', 'text-xs leading-relaxed ' + (note ? 'text-slate-800' : 'italic text-slate-400'), note || 'No specific instructions added yet for this area (e.g. preferred flower colors, backdrop dimensions, open center for photos).'));
                    sections.push(noteBox);
                }

                // Photos analyzed for this area.
                const areaPhotos = photos.filter(function (photo) { return photo.photoAreas.includes(area.key); });
                if (areaPhotos.length) {
                    const strip = make('section', 'space-y-1.5');
                    strip.appendChild(make('h4', 'text-[11px] font-bold uppercase tracking-wide text-slate-600', 'Photos for this area'));
                    const row = make('div', 'flex flex-wrap gap-1.5');
                    areaPhotos.forEach(function (photo) {
                        const number = photos.indexOf(photo) + 1;
                        const thumb = button('', photo.analysis ? 'view-photo' : 'enlarge-photo', 'relative h-12 w-12 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', { photoId: photo.id });
                        thumb.setAttribute('aria-label', 'Photo ' + number + ' (' + (BADGES[viewState(photo)] || [''])[0] + ')');
                        const img = photoImage('h-full w-full object-cover', photo.thumbnailUrl || photo.previewUrl, config.placeholderUrl);
                        thumb.append(img, make('span', 'absolute bottom-0 left-0 rounded-tr bg-slate-900/70 px-1 text-[9px] font-bold text-white', '#' + number));
                        row.appendChild(thumb);
                    });
                    strip.appendChild(row);
                    sections.push(strip);
                }

                // This area's indicative range (server-calculated).
                const groups = estimate.data && estimate.data.estimate && Array.isArray(estimate.data.estimate.groups) ? estimate.data.estimate.groups : [];
                const group = groups.find(function (candidate) { return candidate.key === area.key; });
                const labourIncluded = Boolean(estimate.data && estimate.data.estimate && estimate.data.estimate.labour_included);
                const rangeBox = make('section', 'rounded-xl border border-violet-200 bg-violet-50/50 p-3.5 space-y-2');
                const rangeHead = make('div', 'flex flex-wrap items-center justify-between gap-2');
                const titleWrap = make('div', 'flex items-center gap-1.5');
                const dot = make('span', 'h-2 w-2 rounded-full bg-violet-600');
                dot.setAttribute('aria-hidden', 'true');
                titleWrap.append(dot, make('h4', 'text-xs font-bold uppercase tracking-wide text-violet-950', 'AI Estimate (' + area.label + ')'));
                rangeHead.appendChild(titleWrap);
                rangeHead.appendChild(make('span', 'rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800 shadow-2xs', 'Indicative · ±15%'));
                rangeBox.appendChild(rangeHead);

                const amountRow = make('div', 'flex items-baseline justify-between gap-4 pt-0.5');
                if (group && group.display) {
                    const amtBox = make('div', 'flex items-center gap-1.5');
                    amtBox.appendChild(make('span', 'text-base sm:text-lg font-extrabold text-[#0B1E43] tabular-nums tracking-tight', group.display));
                    if (group.status === 'incomplete') {
                        amtBox.appendChild(make('span', 'rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800 align-middle', 'Partial'));
                    }
                    amountRow.appendChild(amtBox);
                } else {
                    amountRow.appendChild(make('span', 'text-xs font-semibold text-slate-400', 'Price pending florist quotation'));
                }
                amountRow.appendChild(make('p', 'text-[11px] text-slate-500 text-right shrink-0', labourIncluded ? 'Includes florist labour & setup' : 'Labour & setup not included'));
                rangeBox.appendChild(amountRow);

                const noteText = (group && group.note ? group.note + ' ' : '')
                    + 'Indicative preliminary range. Raflora confirms the authoritative price in your official quotation.';
                rangeBox.appendChild(make('p', 'text-[11px] leading-relaxed text-slate-600 border-t border-violet-100/90 pt-1.5', noteText));
                sections.push(rangeBox);

                // Materials, a page at a time.
                const items = Array.isArray(area.items) ? area.items : [];
                const materials = make('section', 'space-y-1.5');
                const matHead = make('div', 'flex items-center justify-between gap-2');
                matHead.appendChild(make('h4', 'text-[11px] font-bold uppercase tracking-wide text-slate-600', 'AI-suggested materials (' + items.length + ')'));
                materials.appendChild(matHead);
                if (area.missing || items.length === 0) {
                    materials.appendChild(make('p', 'rounded-md border border-dashed border-amber-300 bg-amber-50 px-2.5 py-2 text-xs text-amber-900', config.missingMessage));
                } else {
                    const pages = Math.ceil(items.length / MATERIAL_PAGE);
                    areaMaterialPage = Math.min(Math.max(0, areaMaterialPage), pages - 1);
                    const start = areaMaterialPage * MATERIAL_PAGE;
                    const list = make('ul', 'divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white');
                    list.setAttribute('role', 'list');
                    items.slice(start, start + MATERIAL_PAGE).forEach(function (item) {
                        const li = make('li', 'px-3 py-2 text-xs text-slate-700');
                        const line = make('p', 'flex flex-wrap items-baseline gap-x-1.5');
                        line.append(
                            make('span', 'font-semibold text-slate-900', item.quantity !== null && item.quantity !== undefined ? amount(item.quantity) + ' ' + (item.unit_type || 'pcs') : 'Qty to confirm'),
                            make('span', '', '· ' + item.name),
                            make('span', 'rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider ' + (item.is_detected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'), item.is_detected ? 'Detected' : 'Suggested')
                        );
                        li.appendChild(line);
                        if (item.quantity_basis) li.appendChild(make('p', 'mt-0.5 text-[11px] text-slate-500 font-mono', item.quantity_basis));
                        if (item.needs_review) li.appendChild(make('p', 'mt-0.5 text-[11px] font-medium text-amber-800', 'Flagged for Raflora review.'));
                        list.appendChild(li);
                    });
                    materials.appendChild(list);
                    if (pages > 1) {
                        const pager = make('div', 'flex items-center justify-between gap-2');
                        pager.appendChild(make('span', 'text-[11px] text-slate-500', 'Materials ' + (start + 1) + '–' + Math.min(start + MATERIAL_PAGE, items.length) + ' of ' + items.length));
                        const controls = make('div', 'flex gap-1');
                        const prev = button('‹', 'area-materials-page', 'inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-700 disabled:opacity-40', { step: '-1' });
                        prev.disabled = areaMaterialPage === 0;
                        prev.setAttribute('aria-label', 'Previous materials');
                        const next = button('›', 'area-materials-page', 'inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-700 disabled:opacity-40', { step: '1' });
                        next.disabled = areaMaterialPage >= pages - 1;
                        next.setAttribute('aria-label', 'Next materials');
                        controls.append(prev, next);
                        pager.appendChild(controls);
                        materials.appendChild(pager);
                    }
                }
                sections.push(materials);
                body.replaceChildren.apply(body, sections);
            }

            // ---------------------------------------------------------- Dialog: preferences
            function openPreferences(opener) {
                preferencesSnapshot = brief()?.getState() || null;
                openDialog(dialogs.preferences, opener);
            }
            function closePreferences(save) {
                const briefRoot = byId('design-brief');
                if (!save && preferencesSnapshot) brief()?.setState(preferencesSnapshot);
                preferencesSnapshot = null;
                closeDialog(dialogs.preferences);
                briefRoot?.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // ---------------------------------------------------------- Dialog: note for one area
            let noteOpenedFromAreas = false;
            const noteInput = function (area) { return byId('design-brief')?.querySelector('[data-area-note-input="' + area + '"]') || null; };
            function openAreaNote(area, opener) {
                const input = noteInput(area);
                if (!input || !dialogs.note) return;
                noteArea = area;
                const text = byId('area-note-text');
                text.value = input.value;
                dialogs.note.querySelector('[data-note-title]').textContent = 'Note for ' + areaLabel(area);
                dialogs.note.querySelector('[data-note-dot]').style.backgroundColor = areaColor(area);
                dialogs.note.querySelector('[data-note-count]').textContent = text.value.length;
                // Saving a different note changes what those photos were analyzed with.
                const analyzed = photos.filter(function (photo) { return photo.status === 'completed' && photo.photoAreas.includes(area); }).length;
                const warning = dialogs.note.querySelector('[data-note-warning]');
                warning.textContent = analyzed ? '' + analyzed + ' analyzed photo' + (analyzed === 1 ? ' is' : 's are') + ' linked to this area. Saving a different note means analyzing ' + (analyzed === 1 ? 'it' : 'them') + ' again so the AI uses it.' : '';
                warning.classList.toggle('hidden', !analyzed);
                openDialog(dialogs.note, opener);
            }
            function saveAreaNote() {
                const area = noteArea;
                const input = area ? noteInput(area) : null;
                const value = byId('area-note-text').value.trim().slice(0, 500);
                const changedNote = Boolean(input) && input.value.trim() !== value;
                if (input) input.value = value;
                noteArea = null;
                closeDialog(dialogs.note);
                if (noteOpenedFromAreas) {
                    noteOpenedFromAreas = false;
                    openDialog(dialogs.areas);
                    renderAreaDialog();
                }
                if (!changedNote) return;
                // Redraw the preferences dialog's own chips from the updated fields, then let every listener know.
                const state = brief()?.getState();
                if (state) brief().setState(state);
                byId('design-brief')?.dispatchEvent(new Event('change', { bubbles: true }));
                announce(value ? 'Note saved for ' + areaLabel(area) + '.' : 'Note removed for ' + areaLabel(area) + '.', 'success');
                // The chip was redrawn: keep keyboard focus on its button if area dialog not open.
                if (!isOpen(dialogs.areas)) {
                    document.querySelector('[data-area-chip="' + area + '"] [data-action="edit-area-note"]')?.focus({ preventScroll: true });
                }
            }
            byId('area-note-text')?.addEventListener('input', function (event) {
                dialogs.note.querySelector('[data-note-count]').textContent = event.target.value.length;
            });

            // ---------------------------------------------------------- Rendering
            function render() {
                renderGallery();
                renderProgress();
                renderPreferencesSummary();
                renderSummary();
                syncHiddenInputs();
                if (isOpen(dialogs.photo)) renderPhotoDialog();
                if (isOpen(dialogs.areas)) renderAreaDialog();
            }

            // ---------------------------------------------------------- Events
            const fileInput = byId('inspiration_image_input');
            const dropzone = byId('dropzone_container');
            dropzone?.addEventListener('click', function () { fileInput?.click(); });
            dropzone?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput?.click(); }
            });
            ['dragenter', 'dragover'].forEach(function (name) {
                dropzone?.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.add('border-emerald-600', 'bg-emerald-50/60'); });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                dropzone?.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.remove('border-emerald-600', 'bg-emerald-50/60'); });
            });
            dropzone?.addEventListener('drop', function (event) { addFiles(event.dataTransfer && event.dataTransfer.files); });
            fileInput?.addEventListener('click', function (event) { event.stopPropagation(); });
            fileInput?.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });

            const find = function (id) { return photos.find(function (photo) { return photo.id === id; }) || null; };
            root.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action]');
                if (!trigger || !root.contains(trigger) || trigger.disabled) return;
                const photo = trigger.dataset.photoId ? find(trigger.dataset.photoId) : null;
                switch (trigger.dataset.action) {
                    case 'open-preferences': return openPreferences(trigger);
                    case 'save-preferences': return closePreferences(true);
                    case 'clear-preferences': brief()?.setState({}); byId('design-brief')?.dispatchEvent(new Event('change', { bubbles: true })); return;
                    case 'open-tips': return openDialog(dialogs.tips, trigger);
                    case 'remove-all': photos.slice().forEach(remove); return;
                    case 'gallery-page': galleryPage += Number(trigger.dataset.step); return renderGallery();
                    case 'analyze-photo': return analyzeNow(photo);
                    case 'toggle-area': return toggleArea(photo, trigger.dataset.area);
                    case 'remove-photo': return remove(photo);
                    case 'cancel-analysis': return cancel();
                    case 'view-photo': return openPhoto(photo, trigger);
                    case 'enlarge-photo': return openEnlarged(photo, false);
                    case 'enlarge': return openEnlarged(viewing(), imageViewMode === 'annotated');
                    case 'photo-step': {
                        const list = withResults();
                        const next = list[list.indexOf(viewing()) + Number(trigger.dataset.step)];
                        if (next) { viewingId = next.id; materialIndex = 0; renderPhotoDialog(); }
                        return;
                    }
                    case 'material-step': return selectMaterial(materialIndex + Number(trigger.dataset.step));
                    case 'image-mode': return setImageMode(trigger.dataset.mode);
                    case 'open-areas': {
                        const areas = areaList();
                        const target = trigger.dataset.area;
                        const found = target ? areas.findIndex(function (a) { return a.key === target; }) : -1;
                        areaIndex = found >= 0 ? found : 0;
                        areaMaterialPage = 0;
                        openDialog(dialogs.areas, trigger);
                        return renderAreaDialog();
                    }
                    case 'area-step': areaIndex += Number(trigger.dataset.step); areaMaterialPage = 0; return renderAreaDialog();
                    case 'area-tab': areaIndex = Number(trigger.dataset.index); areaMaterialPage = 0; return renderAreaDialog();
                    case 'area-materials-page': areaMaterialPage += Number(trigger.dataset.step); return renderAreaDialog();
                    case 'edit-area-note': {
                        noteOpenedFromAreas = isOpen(dialogs.areas);
                        if (noteOpenedFromAreas) closeDialog(dialogs.areas);
                        return openAreaNote(trigger.dataset.area, noteOpenedFromAreas ? dialogs.areas : trigger);
                    }
                    case 'save-area-note': return saveAreaNote();
                    case 'open-prices': openDialog(dialogs.prices, trigger); return renderEstimateViews();
                    case 'retry-estimate': return scheduleEstimate(true);
                }
            });

            wireDialog(dialogs.preferences, function () { closePreferences(false); }, false);
            wireDialog(dialogs.tips, function () { closeDialog(dialogs.tips); });
            wireDialog(dialogs.photo, function () { closeDialog(dialogs.photo); });
            wireDialog(dialogs.areas, function () { closeDialog(dialogs.areas); });
            wireDialog(dialogs.prices, function () { closeDialog(dialogs.prices); });
            wireDialog(dialogs.note, function () {
                const returnTo = noteOpenedFromAreas;
                noteOpenedFromAreas = false;
                noteArea = null;
                closeDialog(dialogs.note);
                if (returnTo) {
                    openDialog(dialogs.areas);
                    renderAreaDialog();
                }
            }, false);

            const viewerImage = byId('viewer-main-img');
            viewerImage?.addEventListener('load', fitViewerMarkers);
            if (viewerImage && typeof ResizeObserver !== 'undefined') new ResizeObserver(fitViewerMarkers).observe(viewerImage);

            // Picked areas, notes or other preferences changed: photos show what now needs attention.
            const briefRoot = byId('design-brief');
            briefRoot?.addEventListener('change', function () {
                if (isOpen(dialogs.preferences)) return renderPreferencesSummary();
                render();
                changed();
                scheduleEstimate();
            });
            // "Re-analyze" in the preferences: photos whose analysis no longer matches are analyzed again.
            briefRoot?.addEventListener('design-brief:reanalyze', function () {
                const rerun = photos.filter(function (photo) { return photo.file && ['stale', 'areas_changed', 'failed', 'cancelled'].includes(viewState(photo)); });
                if (rerun.length === 0) {
                    brief()?.setStaleMessage(photos.some(function (photo) { return !photo.file; })
                        ? 'Photos restored from your saved draft cannot be analyzed again. Remove them and add them again.'
                        : 'Your photos already match these preferences.');
                    return;
                }
                closePreferences(true);
                rerun.forEach(analyzeNow);
            });

            render();

            // ---------------------------------------------------------- Public API
            return {
                addFiles: addFiles,
                initialInputs: function () { return Object.assign({}, initialInputs); },
                photos: function () { return photos.slice(); },
                readyPhotos: readyPhotos,
                viewState: viewState,
                blockingIssue: blockingIssue,
                isAnalyzing: function () { return Boolean(activeAiAbortController); },
                cancel: cancel,
                resume: processQueue,
                refresh: function () { render(); scheduleEstimate(); },
                // Event details changed: an analysis in flight for the old details is cancelled;
                // finished photos show as out of date until re-analyzed (or the details change back).
                handleContextChanged: function () {
                    if (activeRequest && config.contextFingerprint() !== activeRequest.fingerprint) {
                        cancel('Your event details changed while this photo was being analyzed. Analyze it again so the AI uses the updated details.');
                    }
                    render();
                    changed();
                    scheduleEstimate();
                },
                // The server refused the analyzed photos when the booking was submitted.
                markServerRejected: function (message) {
                    photos.forEach(function (photo) { if (photo.status === 'completed') photo.serverRejected = message; });
                    render();
                    changed();
                },
                estimate: function () { return estimate.state === 'ready' ? estimate.data : null; },
                // Saved drafts (no file can be saved, so restored photos can be shown but not re-analyzed).
                exportState: function () {
                    return photos.map(function (photo) {
                        return {
                            id: photo.id, filename: photo.filename, thumbnailUrl: photo.thumbnailUrl,
                            previewUrl: photo.previewUrl && !photo.previewUrl.startsWith('blob:') ? photo.previewUrl : null,
                            status: photo.status, photoAreas: photo.photoAreas, areasUnknown: photo.areasUnknown,
                            token: photo.token, tempPath: photo.tempPath, nonce: photo.nonce, analysisData: photo.analysisData,
                            analysis: photo.analysis, suggestedMaterials: photo.suggestedMaterials, visualAnalysis: photo.visualAnalysis,
                            contextFingerprint: photo.contextFingerprint, briefKey: photo.briefKey, error: photo.error, errorType: photo.errorType,
                        };
                    });
                },
                importState: function (list) {
                    photos = (Array.isArray(list) ? list : []).slice(0, config.maxPhotos).map(function (raw) {
                        const token = raw.token || raw.analysisToken || null;
                        const photo = Object.assign(newPhoto(null), {
                            file: null,
                            filename: raw.filename || 'Restored photo',
                            thumbnailUrl: raw.thumbnailUrl || null,
                            previewUrl: raw.thumbnailUrl || (token ? config.restoredImageUrl + '/' + encodeURIComponent(token) : null) || raw.previewUrl || config.placeholderUrl,
                            photoAreas: Array.isArray(raw.photoAreas) ? raw.photoAreas : [],
                            areasUnknown: Boolean(raw.areasUnknown),
                            token: token,
                            tempPath: raw.tempPath || null, nonce: raw.nonce || null, analysisData: raw.analysisData || null,
                            analysis: raw.analysis || null,
                            suggestedMaterials: Array.isArray(raw.suggestedMaterials) ? raw.suggestedMaterials : [],
                            visualAnalysis: raw.visualAnalysis || null,
                            contextFingerprint: raw.contextFingerprint || null, briefKey: raw.briefKey || null,
                        });
                        if (raw.id) photo.id = raw.id;
                        if (raw.status === 'completed' && token) {
                            photo.status = 'completed';
                        } else {
                            // Not analyzed before the reload: without its file it cannot be analyzed now.
                            photo.status = 'failed';
                            photo.errorType = 'restored';
                            photo.error = RESTORED_NO_FILE;
                        }
                        return photo;
                    });
                    galleryPage = 0;
                    render();
                    syncHiddenInputs();
                    scheduleEstimate();
                },
                // Photos analyzed in this session that came back with the form (no file, areas
                // known only to the server).
                restoreTokens: function (tokens, restoreOptions) {
                    const list = (Array.isArray(tokens) ? tokens : []).filter(function (token) { return typeof token === 'string' && token; });
                    photos = list.slice(0, config.maxPhotos).map(function (token, index) {
                        const analysis = index === 0 && restoreOptions && restoreOptions.analysis ? restoreOptions.analysis : null;
                        return Object.assign(newPhoto(null), {
                            file: null, filename: 'Analyzed photo ' + (index + 1),
                            previewUrl: config.restoredImageUrl + '/' + encodeURIComponent(token),
                            photoAreas: [], areasUnknown: true, status: 'completed', token: token,
                            analysis: analysis, suggestedMaterials: analysis && Array.isArray(analysis.suggested_materials) ? analysis.suggested_materials : [],
                            visualAnalysis: analysis ? analysis.visual_analysis || null : null,
                            contextFingerprint: config.contextFingerprint(),
                            serverRejected: restoreOptions && restoreOptions.rejected ? restoreOptions.rejected : null,
                        });
                    });
                    render();
                    syncHiddenInputs();
                    scheduleEstimate();
                },
            };
        }

        window.RafloraInspirationPhotos = { create: create, generateThumbnail: generateThumbnail };
    })();
</script>
@endonce
