{{--
    Optional design brief for Smart AI bookings (guest and client forms). Options, validation and
    the matching Gemini prompt rules live in App\Support\EventDesignBrief.

    The AI reads these fields when it analyzes a photo, so they sit above the upload. Page scripts
    use window.RafloraDesignBrief.get(id):
      appendTo(formData)   add the brief to an analyze-temp-image request
      getState()/setState  save and restore drafts
      summaryRows()        [label, value] pairs for a review step
      markAnalyzed()       remember the brief a photo was analyzed with; later edits then show a
                           "Re-analyze" notice whose button dispatches `design-brief:reanalyze`
      resetAnalyzed()      forget it (photo removed, booking method changed)
--}}
@props(['id' => 'design-brief'])
@use('App\Support\EventDesignBrief')
@use('App\Support\DecorAreaBreakdown')

@php
$oldAreas = (array) old('decor_areas', []);
$oldNotes = (array) old('decor_area_notes', []);
$oldColors = array_pad(array_slice((array) old('color_palette', []), 0, EventDesignBrief::MAX_COLORS), EventDesignBrief::MAX_COLORS, '');
$labels = [
'photo_usage' => array_map(fn ($option) => $option['label'], EventDesignBrief::PHOTO_USAGE),
'decor_areas' => EventDesignBrief::DECOR_AREAS,
'theme' => EventDesignBrief::THEMES,
'flower_material' => EventDesignBrief::MATERIALS,
'budget_range' => EventDesignBrief::BUDGET_RANGES,
];
$areaColors = DecorAreaBreakdown::colors();
$input = 'w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm text-[#0B1E43] outline-none focus:ring-2 focus:ring-emerald-500';
$hasDetails = old('theme') || old('preferred_flowers') || old('avoid_flowers') || old('flower_material') || old('budget_range') || array_filter($oldColors);
@endphp

<section id="{{ $id }}" data-design-brief data-labels='@json($labels)' data-area-colors='@json($areaColors)'
    {{ $attributes->merge(['class' => 'space-y-3.5 rounded-2xl border border-slate-200/90 bg-white p-3.5 sm:p-4.5 shadow-2xs']) }}
    aria-labelledby="{{ $id }}-heading">
    <div class="pb-2 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5">
        <div>
            <h3 id="{{ $id }}-heading" class="text-sm sm:text-base font-bold text-[#0B1E43] font-serif tracking-tight">2. Design Preferences <span class="text-xs font-normal text-slate-400 font-sans">(Optional)</span></h3>
            <p class="text-xs text-slate-500">Guide how our AI and florists interpret your floral arrangements.</p>
        </div>
        <button type="button" data-brief-clear-usage class="hidden text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:underline self-start sm:self-auto">Reset choices</button>
    </div>

    <!-- PRIMARY ROW: Photo Usage & Scalable Decoration Areas -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-start">

        <!-- LEFT: Photo Usage (5 cols on desktop) -->
        <div class="lg:col-span-5 space-y-1.5">
            <fieldset>
                <legend class="text-xs font-semibold text-[#0B1E43] mb-1.5 flex items-center justify-between">
                    <span>How should we use your photo?</span>
                </legend>
                <div class="grid grid-cols-3 gap-1.5">
                    @foreach(EventDesignBrief::PHOTO_USAGE as $key => $option)
                    @php
                    $iconSvg = match($key) {
                    'exact' => '<svg class="w-3.5 h-3.5 mb-0.5 text-slate-400 peer-checked:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>',
                    'style' => '<svg class="w-3.5 h-3.5 mb-0.5 text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.286L13 21l-2.286-6.857L5 12l5.714-2.286L13 3z"></path>
                    </svg>',
                    'colors' => '<svg class="w-3.5 h-3.5 mb-0.5 text-slate-400 peer-checked:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                    </svg>',
                    default => '<svg class="w-3.5 h-3.5 mb-0.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                    </svg>'
                    };
                    @endphp
                    <label class="cursor-pointer group">
                        <input type="radio" name="photo_usage" value="{{ $key }}" class="peer sr-only" @checked(old('photo_usage')===$key)>
                        <span class="block h-full rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:border-slate-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/50 peer-checked:ring-1 peer-checked:ring-emerald-500 shadow-2xs">
                            <div class="flex flex-col items-center justify-center">
                                {!! $iconSvg !!}
                                <span class="block text-[11px] font-bold text-[#0B1E43] leading-tight">{{ $option['label'] }}</span>
                            </div>
                        </span>
                    </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <!-- RIGHT: Scalable Decoration Area Selection & Per-Area Preferences (7 cols on desktop) -->
        <div class="lg:col-span-7 space-y-1.5" data-decor-area-section>
            <fieldset>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <legend class="text-xs font-semibold text-[#0B1E43]">
                        Areas to decorate <span class="font-normal text-slate-400">(Optional)</span>
                    </legend>
                    <span class="text-[10px] text-slate-400">Centerpieces sized by table count</span>
                </div>

                <!-- Hidden form inputs for native form submission & draft serialization -->
                <div class="hidden" data-hidden-area-inputs>
                    @foreach(EventDesignBrief::DECOR_AREAS as $key => $label)
                    <input type="checkbox" name="decor_areas[]" value="{{ $key }}" data-area-checkbox="{{ $key }}" @checked(in_array($key, $oldAreas, true))>
                    <input type="hidden" name="decor_area_notes[{{ $key }}]" value="{{ $oldNotes[$key] ?? '' }}" data-area-note-input="{{ $key }}">
                    @endforeach
                </div>

                <!-- Wrapping Chips & "+ Add Area" Button Container -->
                <div class="min-h-[38px] p-2 bg-slate-50/70 border border-slate-200/80 rounded-xl flex flex-wrap items-center gap-1.5">
                    <!-- Selected Chips Container -->
                    <div class="flex flex-wrap items-center gap-1.5" data-selected-chips></div>

                    <!-- Add Area Dropdown Button -->
                    <div class="relative inline-block" data-add-area-container>
                        <button type="button" data-add-area-btn class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-dashed border-emerald-500 bg-white hover:bg-emerald-50 text-emerald-800 text-[11px] font-bold transition shadow-2xs">
                            <span class="text-emerald-600 font-bold leading-none">+</span>
                            <span>Add area</span>
                        </button>

                        <!-- Dropdown Menu with Instant Filter -->
                        {{-- Positioned on screen (fixed) when opened, so the dialog's scrolling body cannot clip it. --}}
                        <div data-add-area-dropdown class="hidden absolute left-0 top-full mt-1 z-[60] w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2 space-y-1">
                            <div class="relative">
                                <input type="text" data-area-search placeholder="Search areas..." class="w-full text-xs rounded-lg border border-slate-200 px-2 py-1 pl-6 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                <svg class="w-3 h-3 text-slate-400 absolute left-2 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <div data-unselected-list class="max-h-60 overflow-y-auto space-y-0.5 divide-y divide-slate-50 pt-0.5"></div>
                        </div>
                    </div>
                </div>

                <!-- Per-Area Note Editor (Expandable Inline Popover) -->
                <div data-area-note-editor class="hidden mt-2 p-3 bg-white border border-emerald-200 rounded-xl space-y-2 shadow-xs">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full" data-note-area-color aria-hidden="true"></span>
                            <span class="text-xs font-bold text-[#0B1E43]">Preferences: <span data-note-area-name class="text-emerald-800"></span></span>
                        </div>
                        <button type="button" data-note-cancel class="text-slate-400 hover:text-slate-600 text-xs font-bold px-1" title="Close editor">✕</button>
                    </div>
                    <div class="space-y-0.5">
                        <textarea data-note-textarea maxlength="500" rows="2" placeholder="e.g. Prefer white flowers and a soft blush background; keep arrangements low..." class="w-full text-xs p-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none resize-none bg-slate-50/50 text-slate-800 leading-relaxed"></textarea>
                        <div class="flex items-center justify-between text-[10px] text-slate-400">
                            <span data-note-char-count>0 / 500 characters</span>
                            <span>Optional florist guidance</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <button type="button" data-note-clear class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 transition">Clear Note</button>
                        <div class="flex items-center gap-1.5">
                            <button type="button" data-note-cancel-btn class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancel</button>
                            <button type="button" data-note-save-btn class="px-3 py-1 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition shadow-2xs">Save Note</button>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <!-- COLLAPSIBLE DETAILS: Theme, Flowers, Color Palette, Budget -->
    <div class="pt-2 border-t border-slate-100" data-brief-details-wrapper>
        <button type="button" data-brief-toggle-details class="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-[#0B1E43] transition">
            <span class="flex items-center gap-1.5">
                <span class="text-emerald-600 font-bold">✦</span>
                <span>Additional Details (Theme, Flowers, Colors, Budget)</span>
                <span data-brief-active-count class="{{ $hasDetails ? '' : 'hidden' }} px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Configured</span>
            </span>
            <span data-brief-toggle-arrow class="text-slate-400 text-xs font-bold transition-transform duration-200 {{ $hasDetails ? 'rotate-180' : '' }}">▼</span>
        </button>

        <div data-brief-details-panel class="{{ $hasDetails ? '' : 'hidden' }} mt-2.5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 animate-fade-in p-2.5 bg-slate-50/50 rounded-xl border border-slate-100">
            <!-- Theme / Style -->
            <div>
                <label for="{{ $id }}-theme" class="mb-1 block text-[11px] font-bold text-slate-700">Theme / Style</label>
                <div class="relative">
                    <select id="{{ $id }}-theme" name="theme" class="{{ $input }} text-xs py-1.5 pr-7 appearance-none cursor-pointer shadow-2xs">
                        <option value="">No preference</option>
                        @foreach(EventDesignBrief::THEMES as $key => $label)
                        <option value="{{ $key }}" @selected(old('theme')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
                <input type="text" id="{{ $id }}-theme-other" name="theme_other" maxlength="60" value="{{ old('theme_other') }}"
                    placeholder="Describe theme" aria-label="Describe your theme"
                    data-brief-theme-other class="{{ $input }} text-xs py-1.5 mt-1 {{ old('theme') === 'other' ? '' : 'hidden' }}">
            </div>

            <!-- Flower Material -->
            <div>
                <label for="{{ $id }}-material" class="mb-1 block text-[11px] font-bold text-slate-700">Flower Material</label>
                <div class="relative">
                    <select id="{{ $id }}-material" name="flower_material" class="{{ $input }} text-xs py-1.5 pr-7 appearance-none cursor-pointer shadow-2xs">
                        <option value="">No preference</option>
                        @foreach(EventDesignBrief::MATERIALS as $key => $label)
                        <option value="{{ $key }}" @selected(old('flower_material')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Preferred & Avoid Flowers -->
            <div>
                <label for="{{ $id }}-preferred" class="mb-1 block text-[11px] font-bold text-slate-700">Preferred Flowers</label>
                <input type="text" id="{{ $id }}-preferred" name="preferred_flowers" maxlength="255" value="{{ old('preferred_flowers') }}" placeholder="e.g. Peonies, white roses" class="{{ $input }} text-xs py-1.5 shadow-2xs">

                <label for="{{ $id }}-avoid" class="mt-1.5 mb-1 block text-[11px] font-bold text-slate-700">Flowers to Avoid</label>
                <input type="text" id="{{ $id }}-avoid" name="avoid_flowers" maxlength="255" value="{{ old('avoid_flowers') }}" placeholder="e.g. Lilies (allergies)" class="{{ $input }} text-xs py-1.5 shadow-2xs">
            </div>

            <!-- Color Palette & Budget Range -->
            <div>
                <label class="mb-1 block text-[11px] font-bold text-slate-700">Color Palette <span class="font-normal text-slate-400">(up to {{ EventDesignBrief::MAX_COLORS }})</span></label>
                <div class="grid grid-cols-3 gap-1 mb-1.5">
                    @php $sampleDots = ['bg-[#F4C2C2]', 'bg-[#FFFFF0] border border-slate-300', 'bg-[#9CAF88]']; @endphp
                    @foreach($oldColors as $i => $color)
                    <div class="relative">
                        <span class="absolute left-1.5 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full {{ $sampleDots[$i] ?? 'bg-slate-300' }} shrink-0 pointer-events-none" aria-hidden="true"></span>
                        <input type="text" name="color_palette[]" maxlength="40" value="{{ $color }}" placeholder="Color {{ $i+1 }}" aria-label="Color {{ $i + 1 }}" class="{{ $input }} pl-4.5 pr-1 py-1 text-[11px] shadow-2xs">
                    </div>
                    @endforeach
                </div>

                <label for="{{ $id }}-budget" class="mb-1 block text-[11px] font-bold text-slate-700">Budget Range</label>
                <div class="relative">
                    <select id="{{ $id }}-budget" name="budget_range" class="{{ $input }} text-xs py-1.5 pr-7 appearance-none cursor-pointer shadow-2xs">
                        <option value="">Prefer not to say</option>
                        @foreach(EventDesignBrief::BUDGET_RANGES as $key => $label)
                        <option value="{{ $key }}" @selected(old('budget_range')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stale Analysis Notice -->
    <div data-brief-stale class="hidden flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
        <span data-brief-stale-text>You changed the design brief after your photo was analyzed. Re-analyze so the suggestions use it.</span>
        <button type="button" data-brief-reanalyze class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1 font-semibold text-white transition hover:bg-amber-700 text-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span>Re-analyze</span>
        </button>
    </div>
</section>

<script>
    (function() {
        if (window.RafloraDesignBrief) return;

        const instances = {};
        const ROW_LABELS = {
            photo_usage: 'Use of photo',
            decor_areas: 'Areas to decorate',
            theme: 'Theme',
            color_palette: 'Color palette',
            preferred_flowers: 'Preferred flowers',
            avoid_flowers: 'Flowers to avoid',
            flower_material: 'Flower material',
            budget_range: 'Budget range',
        };

        const escapeHtml = function(str) {
            return String(str || '').replace(/[&<>"']/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[m];
            });
        };

        function create(root) {
            const labels = JSON.parse(root.dataset.labels || '{}');
            const areaColors = JSON.parse(root.dataset.areaColors || '{}');
            const one = function(selector) {
                return root.querySelector(selector);
            };
            const all = function(selector) {
                return Array.from(root.querySelectorAll(selector));
            };
            const themeSelect = one('[name="theme"]');
            const themeOther = one('[data-brief-theme-other]');
            const clearUsageBtn = one('[data-brief-clear-usage]');
            const stale = one('[data-brief-stale]');
            const chipsContainer = one('[data-selected-chips]');
            const addAreaBtn = one('[data-add-area-btn]');
            const addAreaDropdown = one('[data-add-area-dropdown]');
            const areaSearchInput = one('[data-area-search]');
            const unselectedList = one('[data-unselected-list]');
            const noteEditor = one('[data-area-note-editor]');
            const noteAreaColor = one('[data-note-area-color]');
            const noteAreaName = one('[data-note-area-name]');
            const noteTextarea = one('[data-note-textarea]');
            const noteCharCount = one('[data-note-char-count]');
            const noteCancelBtn = one('[data-note-cancel]');
            const noteCancelBtn2 = one('[data-note-cancel-btn]');
            const noteSaveBtn = one('[data-note-save-btn]');
            const noteClearBtn = one('[data-note-clear]');
            const toggleDetailsBtn = one('[data-brief-toggle-details]');
            const detailsPanel = one('[data-brief-details-panel]');
            const toggleArrow = one('[data-brief-toggle-arrow]');
            const activeCountBadge = one('[data-brief-active-count]');
            let activeNoteArea = null;
            let analyzedSignature = null;

            // Render selected area chips and unselected list
            function renderAreaSelectionUI() {
                if (!chipsContainer || !unselectedList) return;
                chipsContainer.innerHTML = '';
                unselectedList.innerHTML = '';

                const decorLabels = labels.decor_areas || {};
                const selectedKeys = all('[data-area-checkbox]:checked').map(function(el) {
                    return el.value;
                });
                const searchVal = (areaSearchInput?.value || '').trim().toLowerCase();

                // 1. Render selected chips
                selectedKeys.forEach(function(key) {
                    const label = decorLabels[key] || key;
                    const color = areaColors[key] || areaColors.default || '#059669';
                    const noteInput = one('[data-area-note-input="' + key + '"]');
                    const noteText = (noteInput?.value || '').trim();

                    const chip = document.createElement('div');
                    chip.className = 'inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white pl-2.5 pr-1.5 py-0.5 text-xs font-semibold text-slate-800 shadow-2xs group hover:border-slate-300 transition';

                    const dot = document.createElement('span');
                    dot.className = 'w-2 h-2 rounded-full shrink-0';
                    dot.style.backgroundColor = color;
                    dot.setAttribute('aria-hidden', 'true');
                    chip.appendChild(dot);

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'font-bold text-slate-800 text-[11px]';
                    titleSpan.textContent = label;
                    chip.appendChild(titleSpan);

                    // Note action
                    const noteBtn = document.createElement('button');
                    noteBtn.type = 'button';
                    if (noteText) {
                        noteBtn.className = 'inline-flex items-center gap-1 text-[10px] bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 px-1.5 py-0.5 rounded-full font-medium transition max-w-[120px] truncate';
                        noteBtn.innerHTML = '<span>📝</span><span class="truncate">' + escapeHtml(noteText) + '</span>';
                        noteBtn.title = 'Edit preference: ' + noteText;
                    } else {
                        noteBtn.className = 'text-[10px] text-slate-400 hover:text-emerald-700 px-1 py-0.5 rounded hover:bg-emerald-50 transition font-medium';
                        noteBtn.textContent = '+ Note';
                        noteBtn.title = 'Add note for ' + label;
                    }
                    noteBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        openNoteEditor(key);
                    });
                    chip.appendChild(noteBtn);

                    // Remove action
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'w-4 h-4 rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center text-xs transition font-bold';
                    removeBtn.textContent = '✕';
                    removeBtn.title = 'Remove ' + label;
                    removeBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        removeArea(key);
                    });
                    chip.appendChild(removeBtn);

                    chipsContainer.appendChild(chip);
                });

                if (selectedKeys.length === 0) {
                    const emptyText = document.createElement('span');
                    emptyText.className = 'text-[11px] text-slate-400 italic py-0.5';
                    emptyText.textContent = 'No areas selected yet.';
                    chipsContainer.appendChild(emptyText);
                }

                // 2. Render unselected items in dropdown
                let unselectedCount = 0;
                Object.keys(decorLabels).forEach(function(key) {
                    if (selectedKeys.includes(key)) return;
                    const label = decorLabels[key] || key;
                    if (searchVal && !label.toLowerCase().includes(searchVal)) return;

                    unselectedCount++;
                    const itemBtn = document.createElement('button');
                    itemBtn.type = 'button';
                    itemBtn.className = 'w-full flex items-center justify-between p-1.5 rounded-lg text-left text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 transition';

                    const left = document.createElement('div');
                    left.className = 'flex items-center gap-1.5';
                    const dot = document.createElement('span');
                    dot.className = 'w-2 h-2 rounded-full shrink-0';
                    dot.style.backgroundColor = areaColors[key] || areaColors.default || '#059669';
                    left.appendChild(dot);
                    const name = document.createElement('span');
                    name.textContent = label;
                    left.appendChild(name);
                    itemBtn.appendChild(left);

                    const plus = document.createElement('span');
                    plus.className = 'text-emerald-600 font-bold';
                    plus.textContent = '+';
                    itemBtn.appendChild(plus);

                    itemBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        addArea(key);
                    });
                    unselectedList.appendChild(itemBtn);
                });

                if (unselectedCount === 0) {
                    const emptyUnselected = document.createElement('p');
                    emptyUnselected.className = 'text-[11px] text-slate-400 p-2 text-center';
                    emptyUnselected.textContent = Object.keys(decorLabels).length === selectedKeys.length ?
                        'All available areas added.' :
                        'No matching areas found.';
                    unselectedList.appendChild(emptyUnselected);
                }
            }

            function addArea(key) {
                const cb = one('[data-area-checkbox="' + key + '"]');
                if (cb) {
                    cb.checked = true;
                    renderAreaSelectionUI();
                    root.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                    closeAddDropdown();
                }
            }

            function removeArea(key) {
                const cb = one('[data-area-checkbox="' + key + '"]');
                if (cb) {
                    cb.checked = false;
                    if (activeNoteArea === key) closeNoteEditor();
                    renderAreaSelectionUI();
                    root.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }

            function openNoteEditor(key) {
                activeNoteArea = key;
                const decorLabels = labels.decor_areas || {};
                const label = decorLabels[key] || key;
                const color = areaColors[key] || areaColors.default || '#059669';
                const noteInput = one('[data-area-note-input="' + key + '"]');

                if (noteAreaName) noteAreaName.textContent = label;
                if (noteAreaColor) noteAreaColor.style.backgroundColor = color;
                if (noteTextarea) {
                    noteTextarea.value = noteInput ? noteInput.value : '';
                    updateNoteCharCount();
                }

                noteEditor?.classList.remove('hidden');
                noteTextarea?.focus();
            }

            function closeNoteEditor() {
                activeNoteArea = null;
                noteEditor?.classList.add('hidden');
            }

            function updateNoteCharCount() {
                if (!noteTextarea || !noteCharCount) return;
                const len = noteTextarea.value.length;
                noteCharCount.textContent = len + ' / 500 characters';
            }

            function saveNote() {
                if (!activeNoteArea) return;
                const noteInput = one('[data-area-note-input="' + activeNoteArea + '"]');
                if (noteInput && noteTextarea) {
                    noteInput.value = noteTextarea.value.trim().slice(0, 500);
                    renderAreaSelectionUI();
                    root.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
                closeNoteEditor();
            }

            function clearNote() {
                if (!activeNoteArea) return;
                const noteInput = one('[data-area-note-input="' + activeNoteArea + '"]');
                if (noteInput) {
                    noteInput.value = '';
                    renderAreaSelectionUI();
                    root.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
                closeNoteEditor();
            }

            function toggleAddDropdown() {
                if (!addAreaDropdown) return;
                const isHidden = addAreaDropdown.classList.contains('hidden');
                if (isHidden) {
                    addAreaDropdown.classList.remove('hidden');
                    if (areaSearchInput) {
                        areaSearchInput.value = '';
                    }
                    renderAreaSelectionUI();
                    positionAddDropdown();
                    areaSearchInput?.focus({
                        preventScroll: true
                    });
                } else {
                    closeAddDropdown();
                }
            }

            // Float the menu on screen below the button (or above it when there is no room below),
            // so a scrolling container such as the preferences dialog body cannot cut it off.
            function positionAddDropdown() {
                if (!addAreaDropdown || !addAreaBtn || addAreaDropdown.classList.contains('hidden')) return;
                const gap = 4;
                const margin = 8;
                const btn = addAreaBtn.getBoundingClientRect();
                const style = addAreaDropdown.style;
                style.position = 'fixed';
                style.marginTop = '0';
                style.bottom = 'auto';
                style.maxHeight = '';
                if (unselectedList) unselectedList.style.maxHeight = '';

                const width = addAreaDropdown.offsetWidth;
                const height = addAreaDropdown.offsetHeight;
                const below = window.innerHeight - btn.bottom - gap - margin;
                const above = btn.top - gap - margin;
                const openUp = height > below && above > below;
                const room = openUp ? above : below;

                if (height > room && unselectedList) {
                    const fixedPart = height - unselectedList.offsetHeight;
                    unselectedList.style.maxHeight = Math.max(80, room - fixedPart) + 'px';
                }
                const finalHeight = Math.min(addAreaDropdown.offsetHeight, room);
                style.left = Math.max(margin, Math.min(btn.left, window.innerWidth - width - margin)) + 'px';
                style.top = (openUp ? btn.top - gap - finalHeight : btn.bottom + gap) + 'px';
            }

            function closeAddDropdown() {
                if (!addAreaDropdown) return;
                addAreaDropdown.classList.add('hidden');
                ['position', 'left', 'top', 'bottom', 'marginTop', 'maxHeight'].forEach(function(prop) {
                    addAreaDropdown.style[prop] = '';
                });
                if (unselectedList) unselectedList.style.maxHeight = '';
            }

            function getState() {
                const checked = one('[name="photo_usage"]:checked');
                const value = function(name) {
                    return (one('[name="' + name + '"]')?.value || '').trim();
                };
                const theme = value('theme');

                const notes = {};
                all('[data-area-note-input]').forEach(function(el) {
                    const key = el.dataset.areaNoteInput;
                    const isChecked = one('[data-area-checkbox="' + key + '"]:checked');
                    if (isChecked && el.value.trim()) {
                        notes[key] = el.value.trim().slice(0, 500);
                    }
                });

                return {
                    photo_usage: checked ? checked.value : '',
                    decor_areas: all('[data-area-checkbox]:checked').map(function(el) {
                        return el.value;
                    }),
                    decor_area_notes: notes,
                    theme: theme,
                    theme_other: theme === 'other' ? value('theme_other') : '',
                    color_palette: all('[name="color_palette[]"]').map(function(el) {
                        return el.value.trim();
                    }).filter(Boolean),
                    preferred_flowers: value('preferred_flowers'),
                    avoid_flowers: value('avoid_flowers'),
                    flower_material: value('flower_material'),
                    budget_range: value('budget_range'),
                };
            }

            function setState(state) {
                if (!state || typeof state !== 'object') return;
                all('[name="photo_usage"]').forEach(function(el) {
                    el.checked = el.value === state.photo_usage;
                });
                const selectedAreas = state.decor_areas || [];
                const notes = state.decor_area_notes || {};

                all('[data-area-checkbox]').forEach(function(el) {
                    el.checked = selectedAreas.includes(el.value);
                });
                all('[data-area-note-input]').forEach(function(el) {
                    const key = el.dataset.areaNoteInput;
                    el.value = notes[key] || '';
                });

                all('[name="color_palette[]"]').forEach(function(el, i) {
                    el.value = (state.color_palette || [])[i] || '';
                });
                ['theme', 'theme_other', 'preferred_flowers', 'avoid_flowers', 'flower_material', 'budget_range'].forEach(function(name) {
                    const el = one('[name="' + name + '"]');
                    if (el) el.value = state[name] || '';
                });

                renderAreaSelectionUI();
                syncControls();
            }

            function appendTo(formData) {
                const state = getState();
                Object.keys(state).forEach(function(key) {
                    if (key === 'decor_area_notes') {
                        Object.keys(state[key] || {}).forEach(function(area) {
                            formData.append('decor_area_notes[' + area + ']', state[key][area]);
                        });
                    } else if (Array.isArray(state[key])) {
                        state[key].forEach(function(item) {
                            formData.append(key + '[]', item);
                        });
                    } else if (state[key]) {
                        formData.append(key, state[key]);
                    }
                });
            }

            function summaryRows() {
                const s = getState();
                const pick = function(group, key) {
                    return key ? ((labels[group] || {})[key] || key) : '';
                };
                const theme = s.theme === 'other' && s.theme_other ? 'Other: ' + s.theme_other : pick('theme', s.theme);

                const decorSummary = s.decor_areas.map(function(a) {
                    const base = pick('decor_areas', a);
                    const note = (s.decor_area_notes || {})[a];
                    return note ? base + ' ("' + note + '")' : base;
                }).join(', ');

                return [
                    [ROW_LABELS.photo_usage, pick('photo_usage', s.photo_usage)],
                    [ROW_LABELS.decor_areas, decorSummary],
                    [ROW_LABELS.theme, theme],
                    [ROW_LABELS.color_palette, s.color_palette.join(', ')],
                    [ROW_LABELS.preferred_flowers, s.preferred_flowers],
                    [ROW_LABELS.avoid_flowers, s.avoid_flowers],
                    [ROW_LABELS.flower_material, pick('flower_material', s.flower_material)],
                    [ROW_LABELS.budget_range, pick('budget_range', s.budget_range)],
                ].filter(function(row) {
                    return row[1];
                });
            }

            function syncControls() {
                const isOther = themeSelect && themeSelect.value === 'other';
                themeOther?.classList.toggle('hidden', !isOther);
                clearUsageBtn?.classList.toggle('hidden', !one('[name="photo_usage"]:checked'));
                const isStale = analyzedSignature !== null && analyzedSignature !== JSON.stringify(getState());
                stale?.classList.toggle('hidden', !isStale);
                stale?.classList.toggle('flex', isStale);

                // Update active count for collapsible panel
                const s = getState();
                const count = [s.theme, s.flower_material, s.preferred_flowers, s.avoid_flowers, s.budget_range].filter(Boolean).length + s.color_palette.length;
                if (activeCountBadge) {
                    activeCountBadge.classList.toggle('hidden', count === 0);
                    if (count > 0) activeCountBadge.textContent = count + ' active';
                }
            }

            // Event Listeners
            root.addEventListener('input', syncControls);
            root.addEventListener('change', syncControls);
            clearUsageBtn?.addEventListener('click', function() {
                all('[name="photo_usage"]').forEach(function(el) {
                    el.checked = false;
                });
                all('[data-area-checkbox]').forEach(function(el) {
                    el.checked = false;
                });
                all('[data-area-note-input]').forEach(function(el) {
                    el.value = '';
                });
                renderAreaSelectionUI();
                closeNoteEditor();
                root.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            });
            one('[data-brief-reanalyze]')?.addEventListener('click', function() {
                root.dispatchEvent(new CustomEvent('design-brief:reanalyze', {
                    bubbles: true
                }));
            });

            addAreaBtn?.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleAddDropdown();
            });

            areaSearchInput?.addEventListener('input', function() {
                renderAreaSelectionUI();
                positionAddDropdown();
            });

            // Keep the floating menu attached to its button while the page or dialog scrolls or resizes,
            // and after an area is picked (the list gets shorter).
            unselectedList?.addEventListener('click', function() {
                window.requestAnimationFrame(positionAddDropdown);
            });
            window.addEventListener('resize', positionAddDropdown);
            document.addEventListener('scroll', function(e) {
                if (addAreaDropdown && !addAreaDropdown.contains(e.target)) positionAddDropdown();
            }, true);

            document.addEventListener('click', function(e) {
                if (addAreaDropdown && !addAreaDropdown.classList.contains('hidden') && !addAreaDropdown.contains(e.target) && e.target !== addAreaBtn) {
                    closeAddDropdown();
                }
            });

            noteTextarea?.addEventListener('input', updateNoteCharCount);
            noteSaveBtn?.addEventListener('click', saveNote);
            noteClearBtn?.addEventListener('click', clearNote);
            noteCancelBtn?.addEventListener('click', closeNoteEditor);
            noteCancelBtn2?.addEventListener('click', closeNoteEditor);

            toggleDetailsBtn?.addEventListener('click', function() {
                if (!detailsPanel) return;
                const isClosed = detailsPanel.classList.contains('hidden');
                detailsPanel.classList.toggle('hidden', !isClosed);
                toggleArrow?.classList.toggle('rotate-180', isClosed);
            });

            // Initial UI rendering
            renderAreaSelectionUI();
            syncControls();

            return {
                getState: getState,
                setState: setState,
                appendTo: appendTo,
                summaryRows: summaryRows,
                markAnalyzed: function() {
                    analyzedSignature = JSON.stringify(getState());
                    syncControls();
                },
                resetAnalyzed: function() {
                    analyzedSignature = null;
                    syncControls();
                },
                setStaleMessage: function(text) {
                    const el = one('[data-brief-stale-text]');
                    if (el) el.textContent = text;
                },
            };
        }

        window.RafloraDesignBrief = {
            get: function(id) {
                if (instances[id]) return instances[id];
                const root = document.getElementById(id);
                if (!root || !root.hasAttribute('data-design-brief')) return null;
                instances[id] = create(root);
                return instances[id];
            },
        };
    })();
</script>