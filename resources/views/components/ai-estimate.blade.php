{{--
    Indicative AI price ranges (App\Services\AiEstimateService): one per area the client picked
    plus Shared Materials, or one overall range. Display values only — never item prices, costs,
    the markup or labour figures — and no combined total across areas.

      <x-ai-estimate :estimate="$aiEstimate" :quotation-issued="..." />   rendered on the server
      <x-ai-estimate id="review-ai-estimate" />                           empty mount for
      window.RafloraAiEstimate.render(id, estimate, { quotationIssued })  the booking forms
--}}
@props(['estimate' => null, 'id' => null, 'quotationIssued' => false])

@php
    $groups = is_array($estimate['groups'] ?? null) ? $estimate['groups'] : [];
    $intro = fn (bool $labour, bool $issued) => 'Indicative ranges (±15%) for the materials the AI suggested'
        . ($labour ? ', including labour & setup' : '; labour & setup are not included')
        . '. They can change when your photos or Raflora\'s estimate settings change. '
        . ($issued ? 'Your official quotation is the price that applies.' : 'Raflora confirms the price in your official quotation.');
@endphp

@if($estimate && $groups !== [])
    <section {{ $attributes->merge(['class' => 'rounded-xl border border-violet-200 bg-violet-50/40 p-4']) }} @if($id) id="{{ $id }}" @endif aria-label="AI price estimate" data-ai-estimate data-status="{{ $estimate['status'] }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-bold uppercase tracking-wide text-violet-900">AI estimate {{ ($estimate['mode'] ?? '') === 'areas' ? 'per area' : '' }}</h4>
            <span class="rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800">Indicative · not an official quotation</span>
        </div>
        <p class="mt-1 text-[11px] leading-relaxed text-slate-600">{{ $intro((bool) ($estimate['labour_included'] ?? false), (bool) $quotationIssued) }}</p>
        <ul class="mt-3 divide-y divide-violet-100 rounded-lg border border-violet-100 bg-white" role="list">
            @foreach($groups as $group)
                <li class="flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4" data-ai-estimate-group="{{ $group['key'] }}" data-ai-estimate-status="{{ $group['status'] }}">
                    <div class="flex min-w-0 items-start gap-2">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $group['color'] }};" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</p>
                            @if($group['note'])
                                <p class="text-[11px] leading-snug {{ $group['status'] === 'incomplete' ? 'text-amber-800' : 'text-slate-500' }}">{{ $group['note'] }}</p>
                            @endif
                        </div>
                    </div>
                    <p class="shrink-0 pl-4.5 text-sm font-semibold tabular-nums sm:pl-0 sm:text-right {{ $group['display'] ? 'text-slate-900' : 'text-slate-400' }}">
                        {{ $group['display'] ?? 'Unavailable' }}
                        @if($group['status'] === 'incomplete')
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-px align-middle text-[10px] font-bold uppercase text-amber-800">Incomplete</span>
                        @endif
                    </p>
                </li>
            @endforeach
            @if(!empty($estimate['combined']) && ($estimate['combined']['display'] || $estimate['combined']['status'] === 'incomplete'))
                <li class="flex flex-col gap-0.5 border-t-2 border-violet-200 bg-violet-50/60 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4" data-ai-estimate-combined>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-violet-950">Combined AI Estimate</p>
                        @if(!empty($estimate['combined']['note']))
                            <p class="text-[11px] leading-snug {{ $estimate['combined']['status'] === 'incomplete' ? 'text-amber-800' : 'text-slate-600' }}">{{ $estimate['combined']['note'] }}</p>
                        @endif
                    </div>
                    <p class="shrink-0 text-sm font-bold tabular-nums sm:text-right {{ $estimate['combined']['display'] ? 'text-violet-950' : 'text-slate-500' }}">
                        {{ $estimate['combined']['display'] ?? 'Unavailable' }}
                        @if($estimate['combined']['status'] === 'incomplete')
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-px align-middle text-[10px] font-bold uppercase text-amber-800">Partial</span>
                        @endif
                    </p>
                </li>
            @endif
        </ul>
    </section>
@elseif($id)
    <section {{ $attributes->merge(['class' => 'hidden rounded-xl border border-violet-200 bg-violet-50/40 p-4']) }} id="{{ $id }}" aria-label="AI price estimate" aria-live="polite" data-ai-estimate></section>
@endif

@once
<script>
    (function () {
        if (window.RafloraAiEstimate) return;

        const make = function (tag, className, text) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined && text !== null) el.textContent = text;
            return el;
        };
        const intro = function (labour, issued) {
            return 'Indicative ranges (±15%) for the materials the AI suggested'
                + (labour ? ', including labour & setup' : '; labour & setup are not included')
                + '. They can change when your photos or Raflora\'s estimate settings change. '
                + (issued ? 'Your official quotation is the price that applies.' : 'Raflora confirms the price in your official quotation.');
        };

        // Builds the same panel as the server-rendered component from an estimate-endpoint response.
        function render(id, estimate, options) {
            const root = document.getElementById(id);
            if (!root) return;
            root.replaceChildren();
            const groups = estimate && Array.isArray(estimate.groups) ? estimate.groups : [];
            root.classList.toggle('hidden', groups.length === 0);
            if (groups.length === 0) return;
            root.dataset.status = estimate.status || '';

            const head = make('div', 'flex flex-wrap items-center justify-between gap-2');
            head.appendChild(make('h4', 'text-xs font-bold uppercase tracking-wide text-violet-900', 'AI estimate ' + (estimate.mode === 'areas' ? 'per area' : '')));
            head.appendChild(make('span', 'rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800', 'Indicative · not an official quotation'));
            root.appendChild(head);
            root.appendChild(make('p', 'mt-1 text-[11px] leading-relaxed text-slate-600', intro(Boolean(estimate.labour_included), Boolean(options && options.quotationIssued))));

            const list = make('ul', 'mt-3 divide-y divide-violet-100 rounded-lg border border-violet-100 bg-white');
            list.setAttribute('role', 'list');
            groups.forEach(function (group) {
                const li = make('li', 'flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4');
                li.dataset.aiEstimateGroup = group.key;
                li.dataset.aiEstimateStatus = group.status;
                const left = make('div', 'flex min-w-0 items-start gap-2');
                const dot = make('span', 'mt-1 h-2.5 w-2.5 shrink-0 rounded-full');
                dot.style.backgroundColor = group.color || '#7c3aed';
                dot.setAttribute('aria-hidden', 'true');
                left.appendChild(dot);
                const text = make('div', 'min-w-0');
                text.appendChild(make('p', 'text-sm font-semibold text-slate-900', group.label));
                if (group.note) text.appendChild(make('p', 'text-[11px] leading-snug ' + (group.status === 'incomplete' ? 'text-amber-800' : 'text-slate-500'), group.note));
                left.appendChild(text);
                li.appendChild(left);
                const amount = make('p', 'shrink-0 pl-4.5 text-sm font-semibold tabular-nums sm:pl-0 sm:text-right ' + (group.display ? 'text-slate-900' : 'text-slate-400'), group.display || 'Unavailable');
                if (group.status === 'incomplete') amount.appendChild(make('span', 'ml-1 rounded bg-amber-100 px-1.5 py-px align-middle text-[10px] font-bold uppercase text-amber-800', 'Incomplete'));
                li.appendChild(amount);
                list.appendChild(li);
            });

            if (estimate.combined && (estimate.combined.display || estimate.combined.status === 'incomplete')) {
                const combinedLi = make('li', 'flex flex-col gap-0.5 border-t-2 border-violet-200 bg-violet-50/60 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4');
                combinedLi.dataset.aiEstimateCombined = 'true';
                const cLeft = make('div', 'min-w-0');
                cLeft.appendChild(make('p', 'text-sm font-bold text-violet-950', 'Combined AI Estimate'));
                if (estimate.combined.note) {
                    cLeft.appendChild(make('p', 'text-[11px] leading-snug ' + (estimate.combined.status === 'incomplete' ? 'text-amber-800' : 'text-slate-600'), estimate.combined.note));
                }
                combinedLi.appendChild(cLeft);
                const cAmt = make('p', 'shrink-0 text-sm font-bold tabular-nums sm:text-right ' + (estimate.combined.display ? 'text-violet-950' : 'text-slate-500'), estimate.combined.display || 'Unavailable');
                if (estimate.combined.status === 'incomplete') {
                    cAmt.appendChild(make('span', 'ml-1 rounded bg-amber-100 px-1.5 py-px align-middle text-[10px] font-bold uppercase text-amber-800', 'Partial'));
                }
                combinedLi.appendChild(cAmt);
                list.appendChild(combinedLi);
            }

            root.appendChild(list);
        }

        window.RafloraAiEstimate = { render: render };
    })();
</script>
@endonce
