<x-admin-layout title="Return Audit">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Return Audit & Reconciliation</h1>
                <p class="text-sm text-slate-500 mt-1">Review returned assets, assess condition, and reconcile inventory stock adjustments.</p>
            </div>
            <a href="{{ route('admin.return-tracking') }}" class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-50 transition shadow-2xs">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Back to Return Tracking</span>
            </a>
        </div>

        @if(session('success'))
            <x-alert type="success" :inline="true">{{ session('success') }}</x-alert>
        @endif
        @if($errors->any())
            <x-alert type="danger" :inline="true">
                <div class="font-semibold mb-1">Please fix the following return errors:</div>
                <ul class="list-disc pl-5 text-xs space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @php
            $displayStatus = $booking->status_display_label;
            $statusBadgeClass = match ($booking->status) {
                'downpayment_received' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'payment_pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                'completed' => 'bg-green-100 text-green-800 border-green-200',
                'pending_resolution' => 'bg-orange-100 text-orange-800 border-orange-200',
                'pending_return' => 'bg-amber-100 text-amber-800 border-amber-200',
                'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                default => 'bg-slate-100 text-slate-800 border-slate-200',
            };
            $isArchivedAudit = ($booking->status === 'completed') || ($booking->status === 'cancelled' && $return->status === 'Completed');
        @endphp

        @if($isArchivedAudit)
            <div class="rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-sm text-blue-900 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-lock text-base"></i>
                </div>
                <div>
                    <p class="font-bold">Return Audit Completed &amp; Archived</p>
                    <p class="text-xs text-blue-700 mt-0.5">This return audit record is finalized and locked. Historical return quantities, condition observations, and damage charge adjudications are preserved in read-only mode.</p>
                </div>
            </div>
        @endif

        {{-- Booking strip + how-it-works (the reconciliation guide, folded so it does not crowd the page). --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-slate-900">Booking #{{ $booking->booking_number ?? $booking->id }}</span>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass }}">{{ $displayStatus }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-600">
                            {{ $booking->client?->full_name ?? $booking->guest_name ?? 'Guest Client' }}
                            <span class="mx-1 text-slate-300">•</span>{{ ucfirst($booking->event_type ?? 'N/A') }}
                            <span class="mx-1 text-slate-300">•</span>{{ optional($booking->event_date)->format('F j, Y') ?? 'N/A' }}
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $booking->venue ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-6">
                    <div class="text-right">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Damage Charge (saved)</span>
                        <span class="text-lg font-bold text-rose-600">₱{{ number_format($return->total_damage_charge ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
            <details class="group border-t border-slate-100">
                <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-circle-question text-brand-700" aria-hidden="true"></i>
                    How reconciliation works
                    <i class="fa-solid fa-chevron-down ml-auto text-xs text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                </summary>
                <div class="grid gap-3 px-5 pb-5 text-xs text-slate-700 md:grid-cols-3">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800"><i class="fa-solid fa-circle-check"></i> Good</span>
                        <p class="mt-1.5 leading-relaxed">Restores physical item stock to available inventory automatically upon saving.</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3">
                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800"><i class="fa-solid fa-triangle-exclamation"></i> Damaged</span>
                        <p class="mt-1.5 leading-relaxed">Does not restore active stock. Recorded in damage audit ledger. Decide the client charge in the item's details.</p>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50/60 p-3">
                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800"><i class="fa-solid fa-circle-xmark"></i> Lost</span>
                        <p class="mt-1.5 leading-relaxed">Item was not physically returned. Does not restore stock. Decide the client replacement fee in the item's details.</p>
                    </div>
                </div>
            </details>
        </div>

        @php
            // Damage & loss resolution: items with damaged or lost units and what still blocks completion.
            $issueItems = $return->returnItems->filter(fn ($item) => (float) $item->quantity_damaged > 0 || (float) $item->quantity_lost > 0 || in_array($item->condition, ['damaged', 'lost', 'mixed'], true));
            $resolutionBlockers = $resolutionBlockers ?? [];
            $issuesResolved = $return->status === 'Completed' && $return->approval_status === 'approved';
        @endphp
        @if($issueItems->isNotEmpty())
            <section class="rounded-2xl border {{ $issuesResolved ? 'border-emerald-200' : 'border-amber-200' }} bg-white shadow-xs" aria-labelledby="resolution-heading" id="damageLossResolution">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $issuesResolved ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}" aria-hidden="true"><i class="fa-solid {{ $issuesResolved ? 'fa-circle-check' : 'fa-scale-balanced' }}"></i></span>
                        <div>
                            <h2 id="resolution-heading" class="text-base font-bold text-slate-900">Damage &amp; Loss Resolution</h2>
                            <p class="text-sm text-slate-500">
                                @if($issuesResolved)
                                    Resolved — the issues are settled and the return audit is completed.
                                @else
                                    Decide each damaged or lost item, collect the charges, then mark the issues resolved to complete the booking.
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $issuesResolved ? 'bg-emerald-100 text-emerald-800' : (empty($resolutionBlockers) ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ $issuesResolved ? 'Resolved' : (empty($resolutionBlockers) ? 'Ready to resolve' : 'Action needed') }}
                    </span>
                </div>

                <ul class="divide-y divide-slate-100">
                    @foreach($issueItems as $issue)
                        @php
                            $decisionLabel = match ($issue->charge_decision) {
                                'charge', 'charge_client' => 'Charge Client',
                                'no_charge' => 'No Charge (Waived)',
                                default => 'Pending Decision',
                            };
                            $decisionTone = match ($issue->charge_decision) {
                                'charge', 'charge_client' => 'bg-rose-50 text-rose-700',
                                'no_charge' => 'bg-slate-100 text-slate-700',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <li class="flex flex-col gap-2 px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" data-issue-row="{{ $issue->id }}" data-saved-decision="{{ $issue->charge_decision === 'charge_client' ? 'charge' : ($issue->charge_decision ?: 'pending') }}" data-saved-charge="{{ number_format((float) $issue->damage_charge, 2, '.', '') }}">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $issue->inventoryItem?->name ?? 'Unknown item' }}</p>
                                <p class="text-xs text-slate-500">
                                    @if((float) $issue->quantity_damaged > 0)<span class="text-amber-700">Damaged {{ rtrim(rtrim(number_format((float) $issue->quantity_damaged, 2, '.', ''), '0'), '.') }}</span>@endif
                                    @if((float) $issue->quantity_damaged > 0 && (float) $issue->quantity_lost > 0)<span class="mx-1 text-slate-300">•</span>@endif
                                    @if((float) $issue->quantity_lost > 0)<span class="text-rose-700">Lost {{ rtrim(rtrim(number_format((float) $issue->quantity_lost, 2, '.', ''), '0'), '.') }}</span>@endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $decisionTone }}" data-issue-decision>{{ $decisionLabel }}</span>
                                <span class="w-24 text-right text-sm font-semibold tabular-nums text-slate-900" data-issue-charge>₱{{ number_format((float) $issue->damage_charge, 2) }}</span>
                                @unless($isArchivedAudit)
                                    <button type="button" data-open-return="{{ $issue->id }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>

                @unless($issuesResolved)
                    <div class="space-y-3 border-t border-slate-100 bg-slate-50/60 p-5">
                        @unless($isArchivedAudit)
                            {{-- Shown by the page script when items were edited here but not saved yet. --}}
                            <div class="hidden rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900" data-resolution-unsaved role="status">
                                <p class="flex items-start gap-2"><i class="fa-solid fa-floppy-disk mt-0.5 text-sky-700" aria-hidden="true"></i><span>You changed items on this page but the changes are not saved yet. Save them first: this checklist and the button below use the saved audit.</span></p>
                                <button type="submit" form="returnAuditForm" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-sky-700 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-800">
                                    <i class="fa-solid fa-boxes-packing" aria-hidden="true"></i> Save &amp; Reconcile now
                                </button>
                            </div>
                        @endunless
                        @if($resolutionBlockers)
                            <ul class="space-y-1.5 text-sm text-amber-900" role="list">
                                @foreach($resolutionBlockers as $blocker)
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-circle-exclamation mt-0.5 text-amber-600" aria-hidden="true"></i><span>{{ $blocker }}</span></li>
                                @endforeach
                            </ul>
                            @if(collect($resolutionBlockers)->contains(fn ($b) => str_contains($b, 'unpaid') || str_contains($b, 'verification')))
                                <a href="{{ route('admin.bookings.show', $booking) }}#operational-actions-heading" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
                                    <i class="fa-solid fa-money-bill" aria-hidden="true"></i> Go to the booking's payments (Log Final Payment)
                                </a>
                            @endif
                        @else
                            <p class="flex items-start gap-2 text-sm text-emerald-800"><i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i>Every damaged or lost item has a decision, all items are accounted and all charges are paid.</p>
                        @endif

                        @unless($isArchivedAudit)
                            <form method="POST" action="{{ route('admin.return-tracking.resolve', $return) }}" id="resolveIssuesForm"
                                  data-confirm="Mark the damage and loss issues resolved?"
                                  data-confirm-message="The return audit is completed and Booking #{{ $booking->id }} moves to Completed. The client is notified."
                                  data-confirm-label="Mark resolved">
                                @csrf
                                @method('PUT')
                                <button type="submit" data-resolve-button data-blocked="{{ empty($resolutionBlockers) ? '0' : '1' }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-2xs transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:bg-slate-300" @disabled(!empty($resolutionBlockers))>
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Mark Issues Resolved &amp; Complete Booking
                                </button>
                            </form>
                        @endunless
                    </div>
                @endunless
            </section>
        @endif

        <form method="POST" action="{{ route('admin.return-tracking.update', $return) }}" class="space-y-6" id="returnAuditForm" data-archived="{{ $isArchivedAudit ? '1' : '0' }}">
            @csrf
            @method('PUT')

            @if($return->returnItems->isEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900 shadow-xs flex items-center gap-3">
                    <i class="fa-solid fa-circle-info text-amber-600 text-lg"></i>
                    <div>
                        <p class="font-bold">Zero-Hardware Booking</p>
                        <p class="text-xs text-amber-800 mt-0.5">This booking contains no rental or returnable hardware (service or fresh flowers only). Click below to finalize the return audit.</p>
                    </div>
                </div>
            @else
                {{-- Live progress across every item (updated as quantities are entered). --}}
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-live="polite">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Items done</p>
                        <p class="mt-1 text-xl font-bold text-slate-900"><span data-summary="items-done">0</span> <span class="text-sm font-medium text-slate-400">/ {{ $return->returnItems->count() }}</span></p>
                        <div class="mt-2 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-brand-600 transition-all" data-summary="items-bar" style="width:0%"></div></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Units accounted</p>
                        <p class="mt-1 text-xl font-bold text-slate-900"><span data-summary="units-done">0</span> <span class="text-sm font-medium text-slate-400">/ <span data-summary="units-total">0</span></span></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Damaged / Lost</p>
                        <p class="mt-1 text-xl font-bold"><span class="text-amber-700" data-summary="damaged">0</span> <span class="text-slate-300">/</span> <span class="text-rose-700" data-summary="lost">0</span></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Charges entered</p>
                        <p class="mt-1 text-xl font-bold text-rose-600">₱<span data-summary="charges">0.00</span></p>
                    </div>
                </div>
            @endif

            @if($return->returnItems->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
                    {{-- Toolbar: search, filter, bulk fill. --}}
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="relative w-full lg:max-w-xs">
                            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                            <label for="returnItemSearch" class="sr-only">Search items</label>
                            <input id="returnItemSearch" type="search" autocomplete="off" placeholder="Search items…" class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter items">
                            @foreach(['all' => 'All', 'open' => 'Not done', 'done' => 'Done', 'issues' => 'Damaged / Lost'] as $filterKey => $filterLabel)
                                <button type="button" data-return-filter="{{ $filterKey }}" aria-pressed="{{ $filterKey === 'all' ? 'true' : 'false' }}" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $filterKey === 'all' ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                                    {{ $filterLabel }} <span class="ml-0.5 opacity-75" data-filter-count="{{ $filterKey }}"></span>
                                </button>
                            @endforeach
                            @unless($isArchivedAudit)
                                <button type="button" id="markAllGoodBtn" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                                    <i class="fa-solid fa-check-double" aria-hidden="true"></i> Mark remaining as Good
                                </button>
                            @endunless
                        </div>
                    </div>

                    {{-- One short row per item; full details open in a pop-up. --}}
                    <ul class="divide-y divide-slate-100" id="returnItemList">
                        @foreach($return->returnItems as $returnItem)
                            @php
                                $inventoryItem = $returnItem->inventoryItem;
                                $dispatched = (float) ($dispatchedQuantities[$returnItem->inventory_item_id] ?? 0);
                            @endphp
                            <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" data-return-row="{{ $returnItem->id }}" data-name="{{ strtolower($inventoryItem?->name ?? '') }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-slate-900">{{ $inventoryItem?->name ?? 'Unknown item' }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $inventoryItem?->item_code ?: 'No code' }}@if($inventoryItem?->category)<span class="mx-1 text-slate-300">•</span><span class="capitalize">{{ $inventoryItem->category }}</span>@endif
                                        <span class="mx-1 text-slate-300">•</span>Expected {{ rtrim(rtrim(number_format($dispatched, 2, '.', ''), '0'), '.') }} {{ $inventoryItem?->unit }}
                                    </p>
                                </div>
                                <div class="w-full sm:w-56">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-slate-500">Accounted <strong class="text-slate-800" data-row="accounted">0</strong> / {{ rtrim(rtrim(number_format($dispatched, 2, '.', ''), '0'), '.') }}</span>
                                        <span data-row="badge" class="rounded px-1.5 py-0.5 text-[11px] font-semibold"></span>
                                    </div>
                                    <div class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                        <div class="bg-emerald-500" data-row="bar-good" style="width:0%"></div>
                                        <div class="bg-amber-400" data-row="bar-damaged" style="width:0%"></div>
                                        <div class="bg-rose-500" data-row="bar-lost" style="width:0%"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500" data-row="decision"></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    @unless($isArchivedAudit)
                                        <button type="button" data-row-all-good="{{ $returnItem->id }}" class="rounded-lg border border-emerald-300 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50" title="Fill the rest of this item as returned in good condition">All good</button>
                                    @endunless
                                    <button type="button" data-open-return="{{ $returnItem->id }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">
                                        <i class="fa-solid {{ $isArchivedAudit ? 'fa-eye' : 'fa-pen-to-square' }}" aria-hidden="true"></i>{{ $isArchivedAudit ? 'View' : 'Record' }}
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p id="returnItemEmpty" class="hidden px-4 py-8 text-center text-sm text-slate-500">No items match this search or filter.</p>

                    {{-- Paging --}}
                    <div class="flex flex-col items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row">
                        <span id="returnPageInfo"></span>
                        <div class="flex items-center gap-1" id="returnPager"></div>
                    </div>
                </div>

                {{-- Per-item details: the original fields, shown one item at a time in a pop-up (they stay inside this form). --}}
                @foreach($return->returnItems as $returnItem)
                    @php
                        $inventoryItem = $returnItem->inventoryItem;
                        $dispatched = (float) ($dispatchedQuantities[$returnItem->inventory_item_id] ?? 0);
                        $good = old('items.' . $returnItem->id . '.quantity_good', $returnItem->quantity_good);
                        $damaged = old('items.' . $returnItem->id . '.quantity_damaged', $returnItem->quantity_damaged);
                        $lost = old('items.' . $returnItem->id . '.quantity_lost', $returnItem->quantity_lost);
                        $cond = strtolower((string) ($returnItem->condition ?? 'pending'));
                    @endphp
                    <div class="fixed inset-0 z-50 !m-0 hidden items-center justify-center bg-slate-950/50 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="return-detail-title-{{ $returnItem->id }}" data-return-detail="{{ $returnItem->id }}" data-dispatched="{{ $dispatched }}">
                        <div class="flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                                <div class="min-w-0">
                                    <h2 id="return-detail-title-{{ $returnItem->id }}" class="truncate text-base font-bold text-slate-900">{{ $inventoryItem?->name ?? 'Unknown item' }}</h2>
                                    <p class="text-xs text-slate-500">{{ $inventoryItem?->item_code ?: 'No code' }}@if($inventoryItem?->category) • <span class="capitalize">{{ $inventoryItem->category }}</span>@endif • Expected return <strong class="text-slate-800">{{ $dispatched }}</strong> {{ $inventoryItem?->unit }}</p>
                                    <input type="hidden" name="items[{{ $returnItem->id }}][inventory_item_id]" value="{{ $returnItem->inventory_item_id }}">
                                </div>
                                <button type="button" data-close-return class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close item details"><i class="fa-solid fa-xmark"></i></button>
                            </div>

                            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                                {{-- Returned quantities --}}
                                <section>
                                    <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Returned quantities</h3>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-emerald-800" for="good-{{ $returnItem->id }}"><i class="fa-solid fa-circle-check mr-0.5 text-[10px] text-emerald-600"></i> Good</label>
                                            <input id="good-{{ $returnItem->id }}" type="number" name="items[{{ $returnItem->id }}][quantity_good]" value="{{ $good }}" min="0" max="{{ $dispatched }}" step="1" data-qty="good" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-emerald-300 bg-emerald-50/30 px-2.5 py-2 text-sm font-semibold text-slate-900 focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-amber-800" for="damaged-{{ $returnItem->id }}"><i class="fa-solid fa-triangle-exclamation mr-0.5 text-[10px] text-amber-600"></i> Damaged</label>
                                            <input id="damaged-{{ $returnItem->id }}" type="number" name="items[{{ $returnItem->id }}][quantity_damaged]" value="{{ $damaged }}" min="0" max="{{ $dispatched }}" step="1" data-qty="damaged" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-amber-300 bg-amber-50/30 px-2.5 py-2 text-sm font-semibold text-slate-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 disabled:bg-slate-100 disabled:text-slate-500">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-rose-800" for="lost-{{ $returnItem->id }}"><i class="fa-solid fa-circle-xmark mr-0.5 text-[10px] text-rose-600"></i> Lost</label>
                                            <input id="lost-{{ $returnItem->id }}" type="number" name="items[{{ $returnItem->id }}][quantity_lost]" value="{{ $lost }}" min="0" max="{{ $dispatched }}" step="1" data-qty="lost" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-rose-300 bg-rose-50/30 px-2.5 py-2 text-sm font-semibold text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 disabled:bg-slate-100 disabled:text-slate-500">
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-xs text-slate-500">
                                        <span>Accounted: <strong class="text-slate-800" data-detail="accounted">0</strong> / {{ $dispatched }}</span>
                                        <span data-detail="badge" class="rounded px-2 py-0.5 text-xs font-semibold"></span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500"><i class="fa-solid fa-rotate mr-0.5 text-emerald-600"></i> Good quantity returns to inventory stock upon save.</p>
                                </section>

                                {{-- Condition & evidence --}}
                                <section>
                                    <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Condition &amp; staff evidence</h3>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($cond === 'good')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check text-[10px]"></i> Good</span>
                                        @elseif($cond === 'damaged')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Damaged</span>
                                        @elseif($cond === 'lost')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700"><i class="fa-solid fa-circle-xmark text-[10px]"></i> Lost</span>
                                        @elseif($cond === 'mixed')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700"><i class="fa-solid fa-cubes text-[10px]"></i> Mixed Condition</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"><i class="fa-regular fa-clock text-[10px]"></i> Pending Review</span>
                                        @endif
                                        @forelse($returnItem->evidences as $evidence)
                                            <a href="{{ route('secure.evidence.show', $evidence) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-brand-200 px-2 py-1 text-xs text-brand-700 transition hover:bg-brand-50" title="View Evidence">
                                                <i class="fa-solid fa-image text-brand-700"></i><span>View photo</span>
                                            </a>
                                        @empty
                                            <span class="text-xs italic text-slate-400">No staff evidence uploaded</span>
                                        @endforelse
                                    </div>
                                </section>

                                {{-- Decision & charge --}}
                                <section class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500" for="decision-{{ $returnItem->id }}">Charge decision</label>
                                        <select id="decision-{{ $returnItem->id }}" name="items[{{ $returnItem->id }}][charge_decision]" data-field="decision" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500">
                                            <option value="pending" {{ old('items.' . $returnItem->id . '.charge_decision', $returnItem->charge_decision) === 'pending' ? 'selected' : '' }}>Pending Decision</option>
                                            <option value="no_charge" {{ old('items.' . $returnItem->id . '.charge_decision', $returnItem->charge_decision) === 'no_charge' ? 'selected' : '' }}>No Charge (Waived)</option>
                                            <option value="charge" {{ in_array(old('items.' . $returnItem->id . '.charge_decision', $returnItem->charge_decision), ['charge', 'charge_client'], true) ? 'selected' : '' }}>Charge Client</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500" for="charge-{{ $returnItem->id }}">Charge amount (₱)</label>
                                        <input id="charge-{{ $returnItem->id }}" type="number" name="items[{{ $returnItem->id }}][damage_charge]" value="{{ old('items.' . $returnItem->id . '.damage_charge', $returnItem->damage_charge ?? 0) }}" min="0" step="0.01" data-field="charge" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500" placeholder="Charge amount">
                                    </div>
                                </section>

                                {{-- Notes --}}
                                <section class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500" for="notes-{{ $returnItem->id }}">Physical return notes</label>
                                        <textarea id="notes-{{ $returnItem->id }}" name="items[{{ $returnItem->id }}][notes]" rows="3" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500" placeholder="Physical return notes...">{{ old('items.' . $returnItem->id . '.notes', $returnItem->notes) }}</textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500" for="reason-{{ $returnItem->id }}">Reason for damage / loss fee</label>
                                        <textarea id="reason-{{ $returnItem->id }}" name="items[{{ $returnItem->id }}][charge_reason]" rows="3" {{ $isArchivedAudit ? 'disabled' : '' }} class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500" placeholder="Reason for damage/loss fee...">{{ old('items.' . $returnItem->id . '.charge_reason', $returnItem->charge_reason) }}</textarea>
                                    </div>
                                </section>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                                <div class="flex gap-2">
                                    <button type="button" data-step-return="-1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Previous</button>
                                    <button type="button" data-step-return="1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Next<i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i></button>
                                </div>
                                <div class="flex items-center gap-2">
                                    @unless($isArchivedAudit)
                                        <button type="button" data-detail-all-good class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50">All good</button>
                                    @endunless
                                    @if($isArchivedAudit)
                                        <button type="button" data-close-return class="rounded-lg bg-brand-700 px-4 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">Done</button>
                                    @else
                                        <button type="button" data-close-return title="Close and keep editing; nothing is saved until Save &amp; Reconcile" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                                        <button type="submit" class="rounded-lg bg-brand-700 px-4 py-1.5 text-xs font-semibold text-white hover:bg-brand-800"><i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>Save &amp; Reconcile</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <!-- Audit Notes Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <label class="mb-2 block text-sm font-bold text-slate-800" for="returnAuditNotes">Overall Audit Notes</label>
                <textarea
                    id="returnAuditNotes"
                    name="notes"
                    rows="3"
                    {{ $isArchivedAudit ? 'disabled' : '' }}
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-700 focus:border-brand-600 focus:ring-1 focus:ring-brand-500 disabled:bg-slate-100 disabled:text-slate-500"
                    placeholder="Enter overall notes about the return inspection and equipment condition..."
                >{{ old('notes', $return->notes) }}</textarea>
            </div>

            <!-- Footer Actions & Safety Notice (stays in view while scrolling) -->
            <div class="sticky bottom-3 z-30 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="flex items-center gap-1.5 text-xs text-slate-500">
                        <i class="fa-solid fa-shield-halved text-brand-700"></i>
                        <span>Saving updates the return record and reconciles eligible good stock back into active inventory.</span>
                    </span>
                    @if($return->returnItems->isNotEmpty() && !$isArchivedAudit)
                        <span class="mt-0.5 block text-xs font-semibold text-amber-700" data-summary="footer"></span>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.return-tracking') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ $isArchivedAudit ? 'Back to Return Tracking' : 'Cancel' }}
                    </a>
                    @if($isArchivedAudit)
                        <a href="{{ route('admin.bookings.show', $booking) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-900 transition">
                            Back to Booking Review
                        </a>
                    @else
                        @if($return->returnItems->isEmpty())
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 transition shadow-2xs cursor-pointer">
                                <i class="fa-solid fa-check"></i>
                                <span>Complete Zero-Hardware Audit</span>
                            </button>
                        @else
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 transition shadow-2xs cursor-pointer">
                                <i class="fa-solid fa-boxes-packing"></i>
                                <span>Save &amp; Reconcile Return</span>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </form>
    </div>

    @if($return->returnItems->isNotEmpty())
        <script>
            // Return audit screen: compact rows, search/filter/paging, and one pop-up per item. Every
            // field stays inside #returnAuditForm, so saving posts exactly what it did before.
            (() => {
                const form = document.getElementById('returnAuditForm');
                if (!form) return;
                const archived = form.dataset.archived === '1';
                const PAGE_SIZE = 10;
                const rows = [...document.querySelectorAll('[data-return-row]')];
                const detail = (id) => document.querySelector(`[data-return-detail="${id}"]`);
                const num = (value) => { const n = parseFloat(value); return Number.isFinite(n) ? n : 0; };
                const fmt = (n) => (Math.round(n * 100) / 100).toString();
                let filter = 'all';
                let page = 1;
                let openId = null;
                let lastFocus = null;

                const itemState = (id) => {
                    const panel = detail(id);
                    const dispatched = num(panel.dataset.dispatched);
                    const good = num(panel.querySelector('[data-qty="good"]').value);
                    const damaged = num(panel.querySelector('[data-qty="damaged"]').value);
                    const lost = num(panel.querySelector('[data-qty="lost"]').value);
                    const decision = panel.querySelector('[data-field="decision"]');
                    const charge = decision.value === 'charge' ? num(panel.querySelector('[data-field="charge"]').value) : 0;
                    const accounted = good + damaged + lost;
                    return { dispatched, good, damaged, lost, accounted, remaining: Math.max(0, dispatched - accounted), over: accounted > dispatched, decision: decision.value, decisionLabel: decision.selectedOptions[0]?.textContent.trim(), charge };
                };

                const badge = (el, s) => {
                    el.className = el.className.replace(/\b(bg|text)-(amber|emerald|rose)-\d+\b/g, '').trim();
                    if (s.over) { el.textContent = 'Over by ' + fmt(s.accounted - s.dispatched); el.classList.add('bg-rose-100', 'text-rose-800'); }
                    else if (s.remaining > 0) { el.textContent = fmt(s.remaining) + ' remaining'; el.classList.add('bg-amber-100', 'text-amber-800'); }
                    else { el.textContent = 'Fully accounted'; el.classList.add('bg-emerald-100', 'text-emerald-800'); }
                };

                const refresh = () => {
                    const totals = { done: 0, units: 0, accounted: 0, damaged: 0, lost: 0, charges: 0, open: 0, issues: 0 };
                    rows.forEach((row) => {
                        const id = row.dataset.returnRow;
                        const s = itemState(id);
                        const panel = detail(id);
                        const pct = (n) => (s.dispatched > 0 ? Math.min(100, (n / s.dispatched) * 100) : 0) + '%';
                        row.querySelector('[data-row="accounted"]').textContent = fmt(s.accounted);
                        row.querySelector('[data-row="bar-good"]').style.width = pct(s.good);
                        row.querySelector('[data-row="bar-damaged"]').style.width = pct(s.damaged);
                        row.querySelector('[data-row="bar-lost"]').style.width = pct(s.lost);
                        badge(row.querySelector('[data-row="badge"]'), s);
                        row.querySelector('[data-row="decision"]').textContent = (s.damaged + s.lost) > 0
                            ? `Damaged ${fmt(s.damaged)} · Lost ${fmt(s.lost)} · ${s.decisionLabel}${s.charge > 0 ? ' · ₱' + s.charge.toFixed(2) : ''}`
                            : '';
                        panel.querySelector('[data-detail="accounted"]').textContent = fmt(s.accounted);
                        badge(panel.querySelector('[data-detail="badge"]'), s);

                        const done = s.remaining === 0 && !s.over;
                        row.dataset.state = done ? 'done' : 'open';
                        row.dataset.issues = (s.damaged + s.lost) > 0 ? '1' : '0';
                        totals.done += done ? 1 : 0;
                        totals.open += done ? 0 : 1;
                        totals.issues += (s.damaged + s.lost) > 0 ? 1 : 0;
                        totals.units += s.dispatched;
                        totals.accounted += Math.min(s.accounted, s.dispatched);
                        totals.damaged += s.damaged;
                        totals.lost += s.lost;
                        totals.charges += s.charge;
                    });
                    const set = (key, value) => document.querySelectorAll(`[data-summary="${key}"]`).forEach((el) => { el.textContent = value; });
                    set('items-done', totals.done);
                    set('units-done', fmt(totals.accounted));
                    set('units-total', fmt(totals.units));
                    set('damaged', fmt(totals.damaged));
                    set('lost', fmt(totals.lost));
                    set('charges', totals.charges.toFixed(2));
                    set('footer', totals.open ? `${totals.open} item${totals.open === 1 ? '' : 's'} still not fully accounted.` : 'Every item is fully accounted.');
                    document.querySelector('[data-summary="items-bar"]').style.width = (rows.length ? (totals.done / rows.length) * 100 : 0) + '%';
                    const counts = { all: rows.length, open: totals.open, done: totals.done, issues: totals.issues };
                    document.querySelectorAll('[data-filter-count]').forEach((el) => { el.textContent = `(${counts[el.dataset.filterCount]})`; });
                };

                const visibleRows = () => {
                    const q = (document.getElementById('returnItemSearch').value || '').trim().toLowerCase();
                    return rows.filter((row) => (!q || row.dataset.name.includes(q))
                        && (filter === 'all' || (filter === 'issues' ? row.dataset.issues === '1' : row.dataset.state === filter)));
                };

                const render = () => {
                    const matches = visibleRows();
                    const pages = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));
                    page = Math.min(page, pages);
                    rows.forEach((row) => row.classList.add('hidden'));
                    matches.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).forEach((row) => row.classList.remove('hidden'));
                    document.getElementById('returnItemEmpty').classList.toggle('hidden', matches.length > 0);
                    const from = matches.length ? (page - 1) * PAGE_SIZE + 1 : 0;
                    document.getElementById('returnPageInfo').textContent = `Showing ${from}–${Math.min(page * PAGE_SIZE, matches.length)} of ${matches.length} item${matches.length === 1 ? '' : 's'}`;
                    const pager = document.getElementById('returnPager');
                    pager.replaceChildren();
                    const addButton = (label, target, disabled, current) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = label;
                        b.disabled = disabled;
                        if (current) b.setAttribute('aria-current', 'page');
                        b.className = 'min-w-[2rem] rounded-md border px-2 py-1 font-semibold ' + (current ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40');
                        b.addEventListener('click', () => { page = target; render(); });
                        pager.append(b);
                    };
                    if (pages > 1) {
                        addButton('‹', page - 1, page === 1, false);
                        for (let p = 1; p <= pages; p++) addButton(String(p), p, false, p === page);
                        addButton('›', page + 1, page === pages, false);
                    }
                };

                const fillGood = (id) => {
                    const panel = detail(id);
                    const s = itemState(id);
                    if (s.remaining <= 0) return;
                    panel.querySelector('[data-qty="good"]').value = fmt(s.good + s.remaining);
                };

                const openDetail = (id) => {
                    if (openId) detail(openId).classList.replace('flex', 'hidden');
                    openId = id;
                    lastFocus = lastFocus || document.activeElement;
                    const panel = detail(id);
                    panel.classList.replace('hidden', 'flex');
                    document.body.classList.add('overflow-hidden');
                    (panel.querySelector('input:not([type=hidden]):not([disabled])') || panel.querySelector('[data-close-return]')).focus();
                };
                const closeDetail = () => {
                    if (!openId) return;
                    detail(openId).classList.replace('flex', 'hidden');
                    openId = null;
                    document.body.classList.remove('overflow-hidden');
                    refresh();
                    render();
                    lastFocus?.focus();
                    lastFocus = null;
                };
                const step = (direction) => {
                    const list = visibleRows();
                    const index = list.findIndex((row) => row.dataset.returnRow === openId);
                    const next = list[index + direction];
                    if (next) {
                        page = Math.floor(list.indexOf(next) / PAGE_SIZE) + 1;
                        openDetail(next.dataset.returnRow);
                    }
                };

                document.querySelectorAll('[data-open-return]').forEach((b) => b.addEventListener('click', () => { lastFocus = b; openDetail(b.dataset.openReturn); }));
                document.querySelectorAll('[data-return-detail]').forEach((panel) => {
                    panel.addEventListener('click', (event) => { if (event.target === panel) closeDetail(); });
                    panel.querySelectorAll('[data-close-return]').forEach((b) => b.addEventListener('click', closeDetail));
                    panel.querySelectorAll('[data-step-return]').forEach((b) => b.addEventListener('click', () => step(Number(b.dataset.stepReturn))));
                    panel.querySelector('[data-detail-all-good]')?.addEventListener('click', () => { fillGood(panel.dataset.returnDetail); refresh(); });
                    panel.querySelectorAll('input, select').forEach((field) => field.addEventListener('input', refresh));
                    panel.querySelectorAll('input[type=number]').forEach((field) => field.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); }));
                });
                document.querySelectorAll('[data-row-all-good]').forEach((b) => b.addEventListener('click', () => { fillGood(b.dataset.rowAllGood); refresh(); render(); }));
                document.getElementById('markAllGoodBtn')?.addEventListener('click', (event) => {
                    const open = rows.filter((row) => itemState(row.dataset.returnRow).remaining > 0);
                    if (!open.length) return;
                    openConfirmAction(
                        'Mark remaining as Good?',
                        `The remaining quantity of ${open.length} item${open.length === 1 ? '' : 's'} will be recorded as returned in Good condition. You can still change any item before saving.`,
                        'Mark as Good',
                        'default',
                        () => {
                            open.forEach((row) => fillGood(row.dataset.returnRow));
                            refresh();
                            render();
                            markUnsaved();
                        },
                        event.currentTarget
                    );
                });
                document.querySelectorAll('[data-return-filter]').forEach((b) => b.addEventListener('click', () => {
                    filter = b.dataset.returnFilter;
                    page = 1;
                    document.querySelectorAll('[data-return-filter]').forEach((other) => {
                        const active = other === b;
                        other.setAttribute('aria-pressed', active ? 'true' : 'false');
                        other.classList.toggle('bg-brand-700', active);
                        other.classList.toggle('border-brand-700', active);
                        other.classList.toggle('text-white', active);
                        other.classList.toggle('bg-white', !active);
                        other.classList.toggle('border-slate-300', !active);
                        other.classList.toggle('text-slate-700', !active);
                    });
                    render();
                }));
                document.getElementById('returnItemSearch').addEventListener('input', () => { page = 1; render(); });
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && openId) closeDetail(); });

                // Resolving uses the saved audit. Once anything is edited here, the resolution panel shows
                // the edited decisions as "not saved", offers Save & Reconcile, and holds the resolve button.
                const markUnsaved = () => {
                    document.querySelector('[data-resolution-unsaved]')?.classList.remove('hidden');
                    const resolveButton = document.querySelector('[data-resolve-button]');
                    if (resolveButton) resolveButton.disabled = true;
                    document.querySelectorAll('[data-issue-row]').forEach((row) => {
                        if (!detail(row.dataset.issueRow)) return;
                        const s = itemState(row.dataset.issueRow);
                        const changed = s.decision !== row.dataset.savedDecision || s.charge.toFixed(2) !== row.dataset.savedCharge;
                        const tone = s.decision === 'charge' ? ['bg-rose-50', 'text-rose-700'] : (s.decision === 'no_charge' ? ['bg-slate-100', 'text-slate-700'] : ['bg-amber-100', 'text-amber-800']);
                        const badgeEl = row.querySelector('[data-issue-decision]');
                        badgeEl.className = 'rounded-full px-2.5 py-0.5 text-xs font-semibold ' + tone.join(' ');
                        badgeEl.textContent = s.decisionLabel + (changed ? ' (not saved)' : '');
                        row.querySelector('[data-issue-charge]').textContent = '₱' + s.charge.toFixed(2);
                    });
                };
                form.addEventListener('input', markUnsaved);
                form.addEventListener('change', markUnsaved);
                document.querySelectorAll('[data-row-all-good], [data-detail-all-good]').forEach((b) => b.addEventListener('click', markUnsaved));

                // After a failed save, open the first item that is over its dispatched quantity.
                refresh();
                render();
                const firstOver = rows.find((row) => itemState(row.dataset.returnRow).over);
                if (firstOver && !archived) openDetail(firstOver.dataset.returnRow);
            })();
        </script>
    @endif
</x-admin-layout>
