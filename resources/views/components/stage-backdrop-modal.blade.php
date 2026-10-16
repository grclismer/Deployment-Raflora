{{--
    Raflora Stage or Backdrop Area Modal (components.stage-backdrop-modal)
    Uses real contracts:
      - App\Support\EventDesignBrief (decor_areas['stage'], decor_area_notes['stage'])
      - App\Support\DecorAreaBreakdown (materials per area, detected vs suggested, quantity_basis)
      - App\Services\AiEstimateService (indicative ±15% range per area, display values only)
--}}
@props([
    'booking' => null,
    'materials' => null,
    'brief' => null,
    'estimate' => null,
    'id' => 'stage-backdrop-modal',
    'canEditNote' => true,
    'clientNote' => null,
    'view' => 'client',
    'photos' => [],
])

@use('App\Support\EventDesignBrief')
@use('App\Support\DecorAreaBreakdown')

@php
    $resolvedBrief = $brief ?? (is_array($booking->design_brief ?? null) ? $booking->design_brief : []);
    $resolvedClientNote = $clientNote ?? ($resolvedBrief['decor_area_notes']['stage'] ?? null);

    $resolvedMaterials = $materials ?? ($booking && method_exists($booking, 'bookingItems') ? $booking->bookingItems : []);
    $breakdown = $booking
        ? DecorAreaBreakdown::forBooking($booking, $resolvedMaterials, $view)
        : (is_array($resolvedMaterials) && $resolvedMaterials !== []
            ? DecorAreaBreakdown::build($resolvedMaterials, is_array($resolvedBrief) ? $resolvedBrief : null)
            : null);

    $stageArea = collect($breakdown['areas'] ?? [])->firstWhere('key', 'stage');
    $items = is_array($stageArea['items'] ?? null) ? $stageArea['items'] : [];
    $isMissing = (bool) ($stageArea['missing'] ?? ($items === []));

    $stageGroup = collect($estimate['groups'] ?? [])->firstWhere('key', 'stage');
    $estimateDisplay = $stageGroup['display'] ?? null;
    $estimateStatus = $stageGroup['status'] ?? 'unavailable';
    $estimateNote = $stageGroup['note'] ?? null;
    $labourIncluded = (bool) ($estimate['labour_included'] ?? false);

    $areaColor = DecorAreaBreakdown::color('stage');
    $areaLabel = EventDesignBrief::DECOR_AREAS['stage'] ?? 'Stage or backdrop';
    $missingMsg = DecorAreaBreakdown::MISSING_MESSAGE;

    $qtyFormat = fn ($v) => is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.') : null;
@endphp

<div id="{{ $id }}"
     class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title"
     aria-describedby="{{ $id }}-desc"
     data-stage-backdrop-modal
     data-area="stage"
     tabindex="-1">

    <div class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-2xl border border-slate-200/90 flex flex-col overflow-hidden animate-fade-in"
         data-dialog-panel>

        <!-- ==================== HEADER ==================== -->
        <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-100 bg-slate-50/80 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-xs" style="background-color: {{ $areaColor }};" aria-hidden="true"></span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 id="{{ $id }}-title" class="text-base sm:text-lg font-bold text-[#0B1E43] font-serif tracking-tight truncate">
                            {{ $areaLabel }}
                        </h3>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800">
                            Focal Area
                        </span>
                    </div>
                    <p id="{{ $id }}-desc" class="text-xs text-slate-500 mt-0.5 truncate">
                        AI material suggestions &amp; client preferences
                    </p>
                </div>
            </div>

            <button type="button"
                    data-action="close-modal"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 transition-colors focus:ring-2 focus:ring-emerald-500 outline-none"
                    aria-label="Close stage or backdrop modal">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </header>

        <!-- ==================== SCROLLABLE BODY ==================== -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 text-xs">

            <!-- 1. CLIENT-SPECIFIC NOTE -->
            <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 shadow-2xs space-y-2"
                     data-note-section>
                <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-slate-200/70">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Client-Specific Note</h4>
                    </div>
                    @if($canEditNote)
                        <button type="button"
                                data-action="toggle-note-edit"
                                class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                            {{ !empty($resolvedClientNote) ? 'Edit note' : '+ Add note' }}
                        </button>
                    @endif
                </div>

                <!-- Read View -->
                <div data-note-display class="{{ empty($resolvedClientNote) ? 'italic text-slate-400' : 'text-slate-800' }} leading-relaxed">
                    {{ $resolvedClientNote ?? 'No specific instructions added yet for the stage or backdrop. (e.g. preferred flower colors, arch dimensions, open center for photos).' }}
                </div>

                <!-- In-Place Editor (Hidden by default) -->
                @if($canEditNote)
                    <div data-note-editor class="hidden space-y-2 pt-1 border-t border-slate-200/60">
                        <label for="{{ $id }}-note-input" class="block text-[11px] font-semibold text-slate-700">
                            Notes for Raflora florists (max 500 characters):
                        </label>
                        <textarea id="{{ $id }}-note-input"
                                  data-note-textarea
                                  maxlength="500"
                                  rows="3"
                                  class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none resize-none bg-white text-slate-800 leading-relaxed shadow-2xs"
                                  placeholder="e.g. Keep the center arch opening wide so the couple is visible; blush and ivory flowers along the top-left curve.">{{ $resolvedClientNote ?? '' }}</textarea>
                        
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span data-note-char-count>{{ mb_strlen($resolvedClientNote ?? '') }} / 500 characters</span>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        data-action="cancel-note-edit"
                                        class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200/60 rounded-lg transition">
                                    Cancel
                                </button>
                                <button type="button"
                                        data-action="save-note"
                                        class="px-3 py-1 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition shadow-2xs">
                                    Save Note
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <!-- 2. AI PRICE ESTIMATE CARD (Strictly verified data only — NO hardcoded fallback prices) -->
            <section class="rounded-xl border border-violet-200 bg-violet-50/50 p-3.5 sm:p-4 shadow-2xs space-y-2"
                     data-estimate-section
                     data-estimate-status="{{ $estimateStatus }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-violet-600" aria-hidden="true"></span>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-violet-950">AI Price Estimate (Stage / Backdrop)</h4>
                    </div>
                    <span class="rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800 shadow-2xs">
                        Indicative · ±15%
                    </span>
                </div>

                <div class="flex items-baseline justify-between gap-4 pt-0.5">
                    <div>
                        @if($estimateDisplay)
                            <span class="text-base sm:text-lg font-extrabold text-[#0B1E43] tabular-nums tracking-tight" data-estimate-display>
                                {{ $estimateDisplay }}
                            </span>
                            @if($estimateStatus === 'incomplete')
                                <span class="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800 align-middle">
                                    Partial Estimate
                                </span>
                            @endif
                        @else
                            <span class="text-sm font-semibold text-slate-400" data-estimate-display>
                                Price pending florist quotation
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-500 text-right shrink-0">
                        {{ $labourIncluded ? 'Includes florist labour & setup' : 'Labour & setup not included' }}
                    </p>
                </div>

                <p class="text-[11px] leading-relaxed text-slate-600 border-t border-violet-100/90 pt-1.5">
                    @if($estimateNote)
                        {{ $estimateNote }}
                    @elseif($estimateDisplay)
                        Indicative range for materials suggested for the stage or backdrop.
                    @else
                        The AI could not benchmark prices for these materials.
                    @endif
                    Raflora confirms the authoritative price in your official quotation.
                </p>
            </section>

            <!-- 3. INSPIRATION PHOTOS FOR THIS AREA (IF ANY) -->
            @if(!empty($photos))
                <section class="space-y-1.5" data-photos-section>
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-600">Photos for Stage / Backdrop</h4>
                        <span class="text-[11px] text-slate-400">{{ count($photos) }} photo(s)</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($photos as $pIndex => $p)
                            @php
                                $thumb = is_array($p) ? ($p['thumbnailUrl'] ?? $p['previewUrl'] ?? '') : '';
                            @endphp
                            @if($thumb)
                                <div class="relative w-12 h-12 rounded-lg overflow-hidden border border-slate-200 bg-slate-100 shadow-2xs">
                                    <img src="{{ $thumb }}" alt="Stage photo #{{ $pIndex + 1 }}" class="w-full h-full object-cover">
                                    <span class="absolute bottom-0 left-0 bg-slate-900/70 text-white text-[9px] font-bold px-1 rounded-tr">#{{ $pIndex + 1 }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- 4. AI MATERIAL SUGGESTIONS & ESTIMATED QUANTITIES -->
            <section class="space-y-2" data-materials-section>
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">
                        AI-Suggested Materials ({{ count($items) }})
                    </h4>
                    @if(count($items) > 0)
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span class="text-emerald-700 font-semibold">{{ collect($items)->where('is_detected', true)->count() }} Detected</span>
                            <span class="text-slate-300">·</span>
                            <span class="text-amber-700 font-semibold">{{ collect($items)->where('is_detected', false)->count() }} Suggested</span>
                        </div>
                    @endif
                </div>

                @if($isMissing || count($items) === 0)
                    <div class="p-3.5 rounded-xl border border-dashed border-amber-300 bg-amber-50/70 text-center" data-missing-notice>
                        <p class="text-xs font-medium text-amber-900">
                            {{ $missingMsg }}
                        </p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs" role="list">
                        @foreach($items as $item)
                            <li class="p-3 text-xs text-slate-700 hover:bg-slate-50/80 transition-colors" data-material-row="{{ $item['line_id'] ?? '' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">
                                                {{ !empty($item['name']) ? $item['name'] : ($item['item_name'] ?? 'Unnamed item') }}
                                            </span>
                                            <span class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ !empty($item['is_detected']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                                {{ !empty($item['is_detected']) ? 'Detected' : 'Suggested' }}
                                            </span>
                                        </div>

                                        @if(!empty($item['quantity_basis']))
                                            <p class="text-[11px] text-slate-500 font-mono">
                                                {{ $item['quantity_basis'] }}
                                            </p>
                                        @endif

                                        @if(!empty($item['needs_review']))
                                            <p class="text-[11px] font-semibold text-amber-800 flex items-center gap-1 mt-0.5">
                                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                Flagged for Raflora review
                                            </p>
                                        @endif
                                    </div>

                                    <!-- Quantity Badge -->
                                    <div class="text-right shrink-0">
                                        <span class="inline-block px-2.5 py-1 rounded-lg bg-slate-100 font-bold text-slate-900 tabular-nums text-xs">
                                            @if(($q = $qtyFormat($item['quantity'] ?? $item['ai_quantity'] ?? null)) !== null)
                                                {{ $q }} <span class="text-[10px] font-normal text-slate-500 uppercase">{{ $item['unit_type'] ?? 'pcs' }}</span>
                                            @else
                                                Qty to confirm
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <!-- ==================== FOOTER ==================== -->
        <footer class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/80 text-[11px] text-slate-500 shrink-0">
            <span class="truncate">
                AI decision support. Official pricing confirmed in Raflora quotation.
            </span>
            <button type="button"
                    data-action="close-modal"
                    class="px-4 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 font-bold text-slate-700 text-xs transition">
                Close
            </button>
        </footer>
    </div>
</div>

@once
<script>
    (function () {
        if (window.RafloraStageBackdropModal) return;

        function initModal(root) {
            if (!root || root._initialized) return;
            root._initialized = true;

            const textarea = root.querySelector('[data-note-textarea]');
            const charCount = root.querySelector('[data-note-char-count]');
            const noteDisplay = root.querySelector('[data-note-display]');
            const noteEditor = root.querySelector('[data-note-editor]');
            let previousNote = textarea ? textarea.value : '';

            function updateCharCount() {
                if (textarea && charCount) {
                    charCount.textContent = textarea.value.length + ' / 500 characters';
                }
            }

            function close() {
                root.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                if (root._opener && typeof root._opener.focus === 'function') {
                    root._opener.focus();
                    root._opener = null;
                }
            }

            function open(opener) {
                root._opener = opener || document.activeElement;
                root.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                const focusable = root.querySelector('button, [data-note-textarea], [tabindex="0"]');
                if (focusable) focusable.focus();
            }

            // Keyboard Escape
            root.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    close();
                }
            });

            // Click outside panel or close buttons
            root.addEventListener('click', function (e) {
                if (e.target === root || e.target.closest('[data-action="close-modal"]')) {
                    close();
                }
            });

            // Note Editor Toggle
            root.querySelector('[data-action="toggle-note-edit"]')?.addEventListener('click', function () {
                if (!noteEditor) return;
                previousNote = textarea ? textarea.value : '';
                noteEditor.classList.remove('hidden');
                if (noteDisplay) noteDisplay.classList.add('hidden');
                if (textarea) {
                    textarea.focus();
                    updateCharCount();
                }
            });

            // Cancel Note Edit
            root.querySelector('[data-action="cancel-note-edit"]')?.addEventListener('click', function () {
                if (!noteEditor) return;
                if (textarea) textarea.value = previousNote;
                noteEditor.classList.add('hidden');
                if (noteDisplay) noteDisplay.classList.remove('hidden');
            });

            // Save Note
            root.querySelector('[data-action="save-note"]')?.addEventListener('click', function () {
                if (!textarea) return;
                const newNote = textarea.value.trim().slice(0, 500);
                textarea.value = newNote;
                previousNote = newNote;

                // Sync with form input if present on the page
                const hiddenInput = document.querySelector('[data-area-note-input="stage"]')
                    || document.querySelector('input[name="decor_area_notes[stage]"]');
                if (hiddenInput) {
                    hiddenInput.value = newNote;
                }

                // Notify design brief component
                const briefEl = document.getElementById('design-brief');
                if (briefEl) {
                    briefEl.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (noteDisplay) {
                    noteDisplay.textContent = newNote || 'No specific instructions added yet for the stage or backdrop.';
                    noteDisplay.className = newNote ? 'text-slate-800 leading-relaxed' : 'italic text-slate-400 leading-relaxed';
                    noteDisplay.classList.remove('hidden');
                }
                noteEditor?.classList.add('hidden');
            });

            textarea?.addEventListener('input', updateCharCount);

            return { open: open, close: close };
        }

        window.RafloraStageBackdropModal = {
            init: initModal,
            open: function (id, opener) {
                const el = document.getElementById(id || 'stage-backdrop-modal');
                if (el) initModal(el)?.open(opener);
            },
            close: function (id) {
                const el = document.getElementById(id || 'stage-backdrop-modal');
                if (el) initModal(el)?.close();
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-stage-backdrop-modal]').forEach(initModal);
            });
        } else {
            document.querySelectorAll('[data-stage-backdrop-modal]').forEach(initModal);
        }
    })();
</script>
@endonce
