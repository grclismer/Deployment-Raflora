{{--
    What each area the client asked to decorate needs, from the AI material rows
    (App\Support\DecorAreaBreakdown). Renders nothing when no areas were picked.

    Prices never come from the AI. With a booking, what the viewer sees depends on `view`:
      admin   Raflora's raw line costs per area (the booking's materials)
      staff   quantities only
      client  quantities only until a quotation is issued, then each area's share of it
--}}
@props(['materials' => [], 'booking' => null, 'brief' => null, 'view' => 'client', 'compact' => false])
@use('App\Support\DecorAreaBreakdown')

@php
    $breakdown = $booking
        ? DecorAreaBreakdown::forBooking($booking, $materials, $view)
        : DecorAreaBreakdown::build($materials, is_array($brief) ? $brief : null);
    $mode = $breakdown['mode'] ?? DecorAreaBreakdown::MODE_QUANTITIES;
    $forClient = $view === 'client';
    $showCosts = $mode === DecorAreaBreakdown::MODE_COSTS && $view === 'admin';
    $showShare = $mode === DecorAreaBreakdown::MODE_SHARE && $forClient;
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $intro = match (true) {
        $showShare => 'Your quotation split by area: each area\'s share of the quoted price follows its share of the material cost.',
        $forClient => 'What the AI suggests for each area you asked us to decorate. Quantities are estimates; prices come with your quotation.',
        default => 'AI materials grouped by the areas the client asked to decorate, with the counting behind each quantity.',
    };
@endphp

@if($breakdown)
    <section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4']) }} aria-label="Materials per area" data-area-breakdown data-mode="{{ $mode }}">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Materials per area</h4>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ $intro }}</p>
            </div>
            @if($showCosts)
                <p class="shrink-0 text-xs text-slate-500">Raw total (Raflora prices) <span class="font-semibold tabular-nums text-slate-900">{{ $peso($breakdown['raw_total_php']) }}</span></p>
            @elseif($showShare)
                <p class="shrink-0 text-xs text-slate-500">Quoted price <span class="font-semibold tabular-nums text-slate-900">{{ $peso($breakdown['quote_total_php']) }}</span></p>
            @endif
        </div>

        @if($compact)
            {{-- Compact: pick an area, see only its materials. --}}
            <div class="mt-3 flex flex-wrap gap-2" role="tablist" aria-label="Areas">
                @foreach($breakdown['areas'] as $area)
                    <button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-area-pick="{{ $area['key'] }}"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 aria-selected:border-emerald-600 aria-selected:bg-emerald-50 aria-selected:text-emerald-900">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $area['color'] }};" aria-hidden="true"></span>
                        {{ $area['label'] }}
                        @unless($area['missing'])
                            <span class="font-normal text-slate-500">{{ $showShare ? $peso($area['share_php']) : $area['item_count'] }}</span>
                        @endunless
                    </button>
                @endforeach
            </div>
        @endif
        <div class="mt-3 grid grid-cols-1 gap-3 {{ $compact ? '' : 'lg:grid-cols-2' }}">
            @foreach($breakdown['areas'] as $area)
                <div class="min-w-0 rounded-lg border border-slate-200 bg-slate-50/60 p-3 {{ $compact && !$loop->first ? 'hidden' : '' }}" data-area="{{ $area['key'] }}" @if($compact) role="tabpanel" @endif @if($area['missing']) data-area-missing @endif>
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                        <div class="flex min-w-0 items-start gap-2">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $area['color'] }};" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <h5 class="text-sm font-semibold text-slate-900">{{ $area['label'] }}</h5>
                                @unless($area['missing'])
                                    <p class="text-[11px] text-slate-500">{{ $area['item_count'] }} {{ \Illuminate\Support\Str::plural('material', $area['item_count']) }}</p>
                                @endunless
                            </div>
                        </div>
                        @if($showCosts && !$area['missing'])
                            <p class="text-right text-[11px] leading-tight text-slate-500">
                                <span class="block font-semibold tabular-nums text-slate-900">{{ $peso($area['subtotal_php']) }}</span>
                                raw cost
                            </p>
                        @elseif($showShare && !$area['missing'])
                            <p class="text-right text-[11px] leading-tight text-slate-500">
                                <span class="block font-semibold tabular-nums text-slate-900">{{ $peso($area['share_php']) }}</span>
                                {{ rtrim(rtrim(number_format((float) $area['share_percent'], 1), '0'), '.') }}% of your quote
                            </p>
                        @endif
                    </div>

                    @if($area['missing'])
                        <p class="mt-2 rounded-md border border-dashed border-amber-300 bg-amber-50 px-2.5 py-2 text-xs text-amber-900">{{ DecorAreaBreakdown::MISSING_MESSAGE }}</p>
                    @else
                        <ul class="mt-2 {{ $compact ? 'grid grid-cols-1 gap-x-6 md:grid-cols-2 [&>li]:border-b [&>li]:border-slate-200/70' : 'divide-y divide-slate-200/70' }}">
                            @foreach($area['items'] as $item)
                                @php
                                    $changed = $item['ai_quantity'] !== null && $item['quantity'] !== null && abs($item['quantity'] - $item['ai_quantity']) > 0.001;
                                @endphp
                                <li class="py-1.5 text-xs" data-line-id="{{ $item['line_id'] }}">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="min-w-0 text-slate-800">
                                            <span class="font-semibold tabular-nums">{{ $item['quantity'] !== null ? trim($qty($item['quantity']) . ' ' . $item['unit_type']) : 'Qty to confirm' }}</span>
                                            · {{ $item['name'] }}
                                            @if(!$forClient && $item['needs_review'])
                                                <span class="ml-1 rounded bg-amber-100 px-1 py-px text-[10px] font-semibold text-amber-800">Review</span>
                                            @endif
                                        </span>
                                        @if($showCosts)
                                            <span class="shrink-0 tabular-nums text-slate-500">
                                                @if($item['amount'] !== null)
                                                    {{ $peso($item['unit_price']) }} → <span class="text-slate-800">{{ $peso($item['amount']) }}</span>
                                                @elseif($item['in_booking'] === false)
                                                    Not on the booking
                                                @else
                                                    Price pending
                                                @endif
                                            </span>
                                        @elseif($showShare && $item['in_booking'] === false)
                                            <span class="shrink-0 text-slate-500">Not in your quotation</span>
                                        @endif
                                    </div>
                                    @if($item['quantity_basis'] && !$changed)
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $item['quantity_basis'] }}</p>
                                    @elseif($changed)
                                        <p class="mt-0.5 text-[11px] text-slate-500">AI suggested {{ $qty($item['ai_quantity']) }} {{ $item['unit_type'] }}; {{ $forClient ? 'quoted' : 'booking' }} quantity shown.</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($showCosts && $area['pending_count'] > 0)
                            <p class="mt-1.5 text-[11px] text-amber-700">{{ $area['pending_count'] }} {{ \Illuminate\Support\Str::plural('line', $area['pending_count']) }} without a Raflora price {{ $area['pending_count'] === 1 ? 'is' : 'are' }} not in this subtotal.</p>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>

        @if($showCosts)
            <p class="mt-3 text-[11px] text-slate-500">Costs use Raflora's prices on the booking's materials (before markup). Each material line is split across the areas that use it; AI prices are never used here.</p>
        @elseif($forClient && !$showShare)
            <p class="mt-3 text-[11px] text-slate-500">Each area's share of the price appears here once Raflora sends your quotation.</p>
        @endif
    </section>

    @if($compact)
        @once
            <script>
                // Compact materials per area: the picked area's materials only.
                document.addEventListener('click', (event) => {
                    const pick = event.target.closest('[data-area-pick]');
                    if (!pick) return;
                    const root = pick.closest('[data-area-breakdown]');
                    root.querySelectorAll('[data-area-pick]').forEach((b) => {
                        const on = b === pick;
                        b.setAttribute('aria-selected', on ? 'true' : 'false');
                        b.tabIndex = on ? 0 : -1;
                    });
                    root.querySelectorAll('[data-area]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.area !== pick.dataset.areaPick));
                });
            </script>
        @endonce
    @endif
@endif
