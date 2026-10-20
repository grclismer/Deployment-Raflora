{{--
    "Materials per area" panel for the booking forms, filled from an analyze-temp-image response's
    analysis.area_breakdown (built by App\Support\DecorAreaBreakdown; absent when no areas were picked).

      window.RafloraAreaBreakdown.render(id, breakdown)   show it, or hide it for null

    Quantities and the counting behind them only: area prices come from Raflora's own prices
    once the booking exists, never from the AI.
--}}
@props(['id'])

<section id="{{ $id }}" {{ $attributes->merge(['class' => 'hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs']) }} aria-label="Materials per area" aria-live="polite" data-area-breakdown></section>

<script>
    (function () {
        if (window.RafloraAreaBreakdown) return;

        const missingMessage = @json(\App\Support\DecorAreaBreakdown::MISSING_MESSAGE);
        const amount = function (value) { return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 }); };
        const make = function (tag, className, text) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined && text !== null) el.textContent = text;
            return el;
        };

        function compactAreaCard(area, estimate) {
            const card = make('div', 'min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition flex flex-col justify-between gap-3');
            card.dataset.area = area.key;

            const topRow = make('div', 'flex items-start justify-between gap-2');
            const titleRow = make('div', 'flex items-center gap-2.5 min-w-0');
            const dot = make('span', 'h-3 w-3 shrink-0 rounded-full shadow-xs');
            dot.style.backgroundColor = area.color || '#7c3aed';
            dot.setAttribute('aria-hidden', 'true');
            titleRow.appendChild(dot);
            titleRow.appendChild(make('h5', 'text-sm font-bold text-[#0B1E43] truncate font-serif', area.label));
            topRow.appendChild(titleRow);

            const badge = make('span', 'rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-violet-800 shrink-0 hidden sm:inline-block', 'Indicative');
            topRow.appendChild(badge);
            card.appendChild(topRow);

            const estGroup = (estimate && Array.isArray(estimate.groups)) ? estimate.groups.find(function (g) { return g.key === area.key; }) : null;
            const priceText = estGroup && estGroup.display ? estGroup.display : (area.missing ? 'No estimate' : 'Price pending florist quotation');

            const midRow = make('div', 'space-y-1');
            const priceEl = make('div', 'text-base sm:text-lg font-extrabold text-[#0B1E43] font-mono tabular-nums tracking-tight', priceText);
            midRow.appendChild(priceEl);

            if (area.missing) {
                card.dataset.areaMissing = '';
                midRow.appendChild(make('p', 'text-xs text-amber-800 font-medium', missingMessage));
            } else {
                midRow.appendChild(make('p', 'text-xs text-slate-500', area.item_count + ' suggested material' + (area.item_count === 1 ? '' : 's')));
            }
            card.appendChild(midRow);

            const btmRow = make('div', 'pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2');
            const helperText = make('span', 'text-[11px] text-slate-400 truncate hidden sm:inline', 'AI estimate · click to view breakdown');
            btmRow.appendChild(helperText);

            const btn = make('button', 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 shadow-2xs transition cursor-pointer');
            btn.type = 'button';
            btn.setAttribute('aria-label', 'View materials for ' + area.label);
            btn.innerHTML = '<svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg><span>View materials</span>';
            btn.addEventListener('click', function () {
                if (typeof window.openAreaDetailsModal === 'function') {
                    window.openAreaDetailsModal(area.key, btn);
                }
            });
            btmRow.appendChild(btn);
            card.appendChild(btmRow);

            return card;
        }

        function areaCard(area) {
            const card = make('div', 'min-w-0 rounded-xl border border-slate-200 bg-slate-50/60 p-3');
            card.dataset.area = area.key;

            const head = make('div', 'flex items-start gap-2');
            const dot = make('span', 'mt-1 h-2.5 w-2.5 shrink-0 rounded-full');
            dot.style.backgroundColor = area.color || '#059669';
            dot.setAttribute('aria-hidden', 'true');
            head.appendChild(dot);
            const titleBox = make('div', 'min-w-0');
            titleBox.appendChild(make('h6', 'text-sm font-bold text-[#0B1E43]', area.label));
            if (!area.missing) {
                titleBox.appendChild(make('p', 'text-[11px] text-slate-500', area.item_count + ' material' + (area.item_count === 1 ? '' : 's')));
            }
            head.appendChild(titleBox);
            card.appendChild(head);

            if (area.missing) {
                card.dataset.areaMissing = '';
                card.appendChild(make('p', 'mt-2 rounded-md border border-dashed border-amber-300 bg-amber-50 px-2.5 py-2 text-xs text-amber-900', missingMessage));
                return card;
            }

            const list = make('ul', 'mt-2 divide-y divide-slate-200/70');
            (area.items || []).forEach(function (item) {
                const li = make('li', 'py-1.5 text-xs text-slate-700');
                if (item.line_id) li.dataset.lineId = item.line_id;
                const line = make('p');
                line.appendChild(make('span', 'font-semibold text-slate-900', item.quantity !== null && item.quantity !== undefined ? amount(item.quantity) + ' ' + (item.unit_type || 'pcs') : 'Qty to confirm'));
                line.appendChild(document.createTextNode(' · ' + item.name));
                li.appendChild(line);
                if (item.quantity_basis) li.appendChild(make('p', 'mt-0.5 text-[11px] text-slate-500', item.quantity_basis));
                list.appendChild(li);
            });
            card.appendChild(list);

            return card;
        }

        function render(id, breakdown, estimate) {
            const root = document.getElementById(id);
            if (!root) return;
            root.replaceChildren();
            const areas = breakdown && Array.isArray(breakdown.areas) ? breakdown.areas : [];
            root.classList.toggle('hidden', areas.length === 0);
            if (areas.length === 0) return;

            const isReviewStep = id === 'review-area-breakdown' || id === 'client-review-area-breakdown' || id.includes('review');

            if (!isReviewStep) {
                root.appendChild(make('h5', 'text-xs font-bold uppercase tracking-wider text-[#0B1E43]', 'Materials per area'));
                root.appendChild(make('p', 'mt-0.5 text-[11px] text-slate-500', 'What the AI suggests for each area you picked, with how each quantity was counted. Raflora confirms quantities and prices in your quotation.'));
            }

            const grid = make('div', isReviewStep ? 'grid grid-cols-1 gap-3 sm:grid-cols-2' : 'mt-3 grid grid-cols-1 gap-3 md:grid-cols-2');
            areas.forEach(function (area) {
                grid.appendChild(isReviewStep ? compactAreaCard(area, estimate) : areaCard(area));
            });
            root.appendChild(grid);
        }

        window.RafloraAreaBreakdown = { render: render };
    })();
</script>
