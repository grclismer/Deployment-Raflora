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

@php
    $oldAreas = (array) old('decor_areas', []);
    $oldColors = array_pad(array_slice((array) old('color_palette', []), 0, EventDesignBrief::MAX_COLORS), EventDesignBrief::MAX_COLORS, '');
    $labels = [
        'photo_usage' => array_map(fn ($option) => $option['label'], EventDesignBrief::PHOTO_USAGE),
        'decor_areas' => EventDesignBrief::DECOR_AREAS,
        'theme' => EventDesignBrief::THEMES,
        'flower_material' => EventDesignBrief::MATERIALS,
        'budget_range' => EventDesignBrief::BUDGET_RANGES,
    ];
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm text-[#0B1E43] outline-none focus:ring-2 focus:ring-emerald-500';
    $chip = 'inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500';
@endphp

<section id="{{ $id }}" data-design-brief data-labels='@json($labels)'
         {{ $attributes->merge(['class' => 'space-y-4 rounded-xl border border-slate-200 bg-white p-3.5 sm:p-4']) }}
         aria-labelledby="{{ $id }}-heading">
    <div>
        <h3 id="{{ $id }}-heading" class="text-sm font-bold text-[#0B1E43] sm:text-base">Design brief <span class="text-xs font-normal text-slate-400">(optional)</span></h3>
        <p class="mt-0.5 text-xs text-slate-500">Fill these in <strong class="font-semibold text-slate-700">before</strong> uploading — the AI reads them when it analyzes your photo. The photo stays the main guide; prices are always confirmed by our florists.</p>
    </div>

    {{-- How to use the photo --}}
    <fieldset>
        <div class="mb-1.5 flex items-center justify-between gap-2">
            <legend class="text-xs font-semibold text-[#0B1E43]">How should we use your photo?</legend>
            <button type="button" data-brief-clear-usage class="hidden text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:underline">Clear choice</button>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach(EventDesignBrief::PHOTO_USAGE as $key => $option)
                <label class="cursor-pointer">
                    <input type="radio" name="photo_usage" value="{{ $key }}" class="peer sr-only" @checked(old('photo_usage') === $key)>
                    <span class="block h-full rounded-xl border border-slate-200 bg-white px-3 py-2 transition hover:border-slate-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">
                        <span class="block text-sm font-semibold text-[#0B1E43]">{{ $option['label'] }}</span>
                        <span class="block text-[11px] leading-snug text-slate-500">{{ $option['hint'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- Areas to decorate --}}
    <fieldset>
        <legend class="mb-1.5 text-xs font-semibold text-[#0B1E43]">Areas to decorate</legend>
        <div class="flex flex-wrap gap-2">
            @foreach(EventDesignBrief::DECOR_AREAS as $key => $label)
                <label class="cursor-pointer">
                    <input type="checkbox" name="decor_areas[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key, $oldAreas, true))>
                    <span class="{{ $chip }}">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-1 text-[11px] text-slate-500">Centerpieces are counted from your number of tables.</p>
    </fieldset>

    {{-- Theme + palette --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $id }}-theme" class="mb-1 block text-xs font-semibold text-[#0B1E43]">Theme</label>
            <select id="{{ $id }}-theme" name="theme" class="{{ $input }}">
                <option value="">No preference</option>
                @foreach(EventDesignBrief::THEMES as $key => $label)
                    <option value="{{ $key }}" @selected(old('theme') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" id="{{ $id }}-theme-other" name="theme_other" maxlength="60" value="{{ old('theme_other') }}"
                   placeholder="Describe your theme (e.g. Vintage Filipiniana)" aria-label="Describe your theme"
                   data-brief-theme-other class="{{ $input }} mt-2 {{ old('theme') === 'other' ? '' : 'hidden' }}">
        </div>
        <fieldset>
            <legend class="mb-1 block text-xs font-semibold text-[#0B1E43]">Color palette <span class="font-normal text-slate-400">(up to {{ EventDesignBrief::MAX_COLORS }})</span></legend>
            <div class="grid grid-cols-3 gap-2">
                @foreach($oldColors as $i => $color)
                    <input type="text" name="color_palette[]" maxlength="40" value="{{ $color }}"
                           placeholder="{{ ['e.g. Blush pink', 'Ivory', 'Sage green'][$i] ?? '' }}" aria-label="Color {{ $i + 1 }}" class="{{ $input }}">
                @endforeach
            </div>
        </fieldset>
    </div>

    {{-- Flower preferences --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $id }}-preferred" class="mb-1 block text-xs font-semibold text-[#0B1E43]">Preferred flowers</label>
            <input type="text" id="{{ $id }}-preferred" name="preferred_flowers" maxlength="255" value="{{ old('preferred_flowers') }}" placeholder="e.g. Peonies, white roses" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $id }}-avoid" class="mb-1 block text-xs font-semibold text-[#0B1E43]">Flowers to avoid</label>
            <input type="text" id="{{ $id }}-avoid" name="avoid_flowers" maxlength="255" value="{{ old('avoid_flowers') }}" placeholder="Allergies or preferences, e.g. lilies" class="{{ $input }}">
        </div>
    </div>

    {{-- Material + budget --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $id }}-material" class="mb-1 block text-xs font-semibold text-[#0B1E43]">Fresh or artificial flowers?</label>
            <select id="{{ $id }}-material" name="flower_material" class="{{ $input }}">
                <option value="">No preference</option>
                @foreach(EventDesignBrief::MATERIALS as $key => $label)
                    <option value="{{ $key }}" @selected(old('flower_material') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="{{ $id }}-budget" class="mb-1 block text-xs font-semibold text-[#0B1E43]">Budget range</label>
            <select id="{{ $id }}-budget" name="budget_range" class="{{ $input }}">
                <option value="">Prefer not to say</option>
                @foreach(EventDesignBrief::BUDGET_RANGES as $key => $label)
                    <option value="{{ $key }}" @selected(old('budget_range') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-500">Used only to suggest cheaper substitutes or a scaled-down version.</p>
        </div>
    </div>

    <div data-brief-stale class="hidden flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
        <span data-brief-stale-text>You changed the design brief after your photo was analyzed. Re-analyze so the suggestions use it.</span>
        <button type="button" data-brief-reanalyze class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white transition hover:bg-amber-700">
            <i class="fa-solid fa-rotate" aria-hidden="true"></i> Re-analyze
        </button>
    </div>
</section>

<script>
    (function () {
        if (window.RafloraDesignBrief) return;

        const instances = {};
        const ROW_LABELS = {
            photo_usage: 'Use of photo', decor_areas: 'Areas to decorate', theme: 'Theme', color_palette: 'Color palette',
            preferred_flowers: 'Preferred flowers', avoid_flowers: 'Flowers to avoid', flower_material: 'Flower material', budget_range: 'Budget range',
        };

        function create(root) {
            const labels = JSON.parse(root.dataset.labels || '{}');
            const one = function (selector) { return root.querySelector(selector); };
            const all = function (selector) { return Array.from(root.querySelectorAll(selector)); };
            const themeSelect = one('[name="theme"]');
            const themeOther = one('[data-brief-theme-other]');
            const clearUsageBtn = one('[data-brief-clear-usage]');
            const stale = one('[data-brief-stale]');
            let analyzedSignature = null;

            function getState() {
                const checked = one('[name="photo_usage"]:checked');
                const value = function (name) { return (one('[name="' + name + '"]')?.value || '').trim(); };
                const theme = value('theme');
                return {
                    photo_usage: checked ? checked.value : '',
                    decor_areas: all('[name="decor_areas[]"]:checked').map(function (el) { return el.value; }),
                    theme: theme,
                    theme_other: theme === 'other' ? value('theme_other') : '',
                    color_palette: all('[name="color_palette[]"]').map(function (el) { return el.value.trim(); }).filter(Boolean),
                    preferred_flowers: value('preferred_flowers'),
                    avoid_flowers: value('avoid_flowers'),
                    flower_material: value('flower_material'),
                    budget_range: value('budget_range'),
                };
            }

            function setState(state) {
                if (!state || typeof state !== 'object') return;
                all('[name="photo_usage"]').forEach(function (el) { el.checked = el.value === state.photo_usage; });
                all('[name="decor_areas[]"]').forEach(function (el) { el.checked = (state.decor_areas || []).includes(el.value); });
                all('[name="color_palette[]"]').forEach(function (el, i) { el.value = (state.color_palette || [])[i] || ''; });
                ['theme', 'theme_other', 'preferred_flowers', 'avoid_flowers', 'flower_material', 'budget_range'].forEach(function (name) {
                    const el = one('[name="' + name + '"]');
                    if (el) el.value = state[name] || '';
                });
                syncControls();
            }

            function appendTo(formData) {
                const state = getState();
                Object.keys(state).forEach(function (key) {
                    if (Array.isArray(state[key])) {
                        state[key].forEach(function (item) { formData.append(key + '[]', item); });
                    } else if (state[key]) {
                        formData.append(key, state[key]);
                    }
                });
            }

            function summaryRows() {
                const s = getState();
                const pick = function (group, key) { return key ? ((labels[group] || {})[key] || key) : ''; };
                const theme = s.theme === 'other' && s.theme_other ? 'Other: ' + s.theme_other : pick('theme', s.theme);
                return [
                    [ROW_LABELS.photo_usage, pick('photo_usage', s.photo_usage)],
                    [ROW_LABELS.decor_areas, s.decor_areas.map(function (a) { return pick('decor_areas', a); }).join(', ')],
                    [ROW_LABELS.theme, theme],
                    [ROW_LABELS.color_palette, s.color_palette.join(', ')],
                    [ROW_LABELS.preferred_flowers, s.preferred_flowers],
                    [ROW_LABELS.avoid_flowers, s.avoid_flowers],
                    [ROW_LABELS.flower_material, pick('flower_material', s.flower_material)],
                    [ROW_LABELS.budget_range, pick('budget_range', s.budget_range)],
                ].filter(function (row) { return row[1]; });
            }

            function syncControls() {
                const isOther = themeSelect && themeSelect.value === 'other';
                themeOther?.classList.toggle('hidden', !isOther);
                clearUsageBtn?.classList.toggle('hidden', !one('[name="photo_usage"]:checked'));
                const isStale = analyzedSignature !== null && analyzedSignature !== JSON.stringify(getState());
                stale.classList.toggle('hidden', !isStale);
                stale.classList.toggle('flex', isStale);
            }

            root.addEventListener('input', syncControls);
            root.addEventListener('change', syncControls);
            clearUsageBtn?.addEventListener('click', function () {
                all('[name="photo_usage"]').forEach(function (el) { el.checked = false; });
                root.dispatchEvent(new Event('change', { bubbles: true }));
            });
            one('[data-brief-reanalyze]').addEventListener('click', function () {
                root.dispatchEvent(new CustomEvent('design-brief:reanalyze', { bubbles: true }));
            });
            syncControls();

            return {
                getState: getState,
                setState: setState,
                appendTo: appendTo,
                summaryRows: summaryRows,
                markAnalyzed: function () { analyzedSignature = JSON.stringify(getState()); syncControls(); },
                resetAnalyzed: function () { analyzedSignature = null; syncControls(); },
                setStaleMessage: function (text) { const el = one('[data-brief-stale-text]'); if (el) el.textContent = text; },
            };
        }

        window.RafloraDesignBrief = {
            get: function (id) {
                if (instances[id]) return instances[id];
                const root = document.getElementById(id);
                if (!root || !root.hasAttribute('data-design-brief')) return null;
                instances[id] = create(root);
                return instances[id];
            },
        };
    })();
</script>
