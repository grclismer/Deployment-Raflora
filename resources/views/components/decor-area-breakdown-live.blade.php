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

        function render(id, breakdown) {
            const root = document.getElementById(id);
            if (!root) return;
            root.replaceChildren();
            const areas = breakdown && Array.isArray(breakdown.areas) ? breakdown.areas : [];
            root.classList.toggle('hidden', areas.length === 0);
            if (areas.length === 0) return;

            root.appendChild(make('h5', 'text-xs font-bold uppercase tracking-wider text-[#0B1E43]', 'Materials per area'));
            root.appendChild(make('p', 'mt-0.5 text-[11px] text-slate-500', 'What the AI suggests for each area you picked, with how each quantity was counted. Raflora confirms quantities and prices in your quotation.'));

            const grid = make('div', 'mt-3 grid grid-cols-1 gap-3 md:grid-cols-2');
            areas.forEach(function (area) { grid.appendChild(areaCard(area)); });
            root.appendChild(grid);
        }

        window.RafloraAreaBreakdown = { render: render };
    })();
</script>
