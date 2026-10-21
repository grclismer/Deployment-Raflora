<x-admin-layout title="Notifications" description="Review payment verification requests, inventory shortages, and other admin alerts in one place.">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.alerts.read-all') }}" data-confirm="Dismiss all alerts?" data-confirm-message="Alerts are marked as read. Stock count requests from Staff stay open until you approve or reject them, and payments still need verifying." data-confirm-label="Dismiss all">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 h-9 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <i class="fa-solid fa-check-double text-xs" aria-hidden="true"></i>
                Dismiss All
            </button>
        </form>
    </x-slot:actions>

    @php
        // Classify each alert once, so the filter counts, card styling, and modal contents agree.
        $categoryMeta = [
            'payments' => ['label' => 'Payments', 'icon' => 'fa-regular fa-credit-card'],
            'inventory' => ['label' => 'Inventory', 'icon' => 'fa-solid fa-boxes-stacked'],
            'meetings' => ['label' => 'Meetings', 'icon' => 'fa-regular fa-calendar'],
            'system' => ['label' => 'System', 'icon' => 'fa-regular fa-bell'],
        ];

        $cards = [];
        $renderedBookingIds = [];
        foreach ($alerts as $alert) {
            $alertType = strtolower((string) ($alert->type ?? ''));
            $isShortage = ($alert->type ?? '') === 'booking_shortage' || ($alert->type ?? '') === 'stock_alert';
            $bookingId = (int) ($alert->booking_id ?? 0);
            if ($isShortage) {
                if ($bookingId > 0 && in_array($bookingId, $renderedBookingIds, true)) {
                    continue;
                }
                if ($bookingId > 0) {
                    $renderedBookingIds[] = $bookingId;
                }
            }
            $alertTitle = strtolower((string) ($alert->title ?? ''));
            $isInventoryAlert = !empty($alert->inventory_item_id)
                || str_contains($alertTitle, 'stock alert')
                || str_contains($alertTitle, 'shortage')
                || str_contains($alertTitle, 'inventory')
                || str_contains($alertType, 'inventory')
                || str_contains($alertType, 'stock')
                || ($alertType === 'inventory_shortage');
            $isBookingShortageAlert = $alertType === 'booking_shortage' || $alertType === 'daily_shortage' || $alertType === 'weekly_shortage';
            $isPaymentAlert = $alertType === 'payment_pending';
            $isPhysicalVarianceAlert = $alertType === 'physical_count_variance';
            $isMeetingAlert = str_starts_with($alertType, 'meeting_');

            $category = $isPaymentAlert ? 'payments' : (($isInventoryAlert || $isPhysicalVarianceAlert) ? 'inventory' : ($isMeetingAlert ? 'meetings' : 'system'));

            [$typeLabel, $tone, $icon, $actionLabel] = match (true) {
                $isPhysicalVarianceAlert => ['Physical count variance', 'bg-sky-50 text-sky-700 ring-sky-200', 'fa-solid fa-clipboard-check', 'Review Count'],
                $isBookingShortageAlert => ['Booking shortage', 'bg-rose-50 text-rose-700 ring-rose-200', 'fa-solid fa-triangle-exclamation', 'Resolve Booking Shortages'],
                $alertType === 'general_low_stock' => ['Low stock', 'bg-amber-50 text-amber-800 ring-amber-200', 'fa-solid fa-boxes-stacked', 'Review Low Stock'],
                $isInventoryAlert => ['Stock alert', 'bg-amber-50 text-amber-800 ring-amber-200', 'fa-solid fa-boxes-stacked', 'Restock'],
                $isPaymentAlert => ['Payment verification', 'bg-navy-50 text-navy-700 ring-navy-100', 'fa-regular fa-credit-card', 'Review Payment Verification'],
                $isMeetingAlert => ['Client meeting', 'bg-brand-50 text-brand-800 ring-brand-200', 'fa-regular fa-calendar', 'View Details'],
                default => ['System alert', 'bg-slate-100 text-slate-700 ring-slate-200', 'fa-regular fa-bell', 'View Details'],
            };

            $cards[] = [
                'alert' => $alert,
                'alertType' => $alertType,
                'category' => $category,
                'isInventoryAlert' => $isInventoryAlert,
                'isBookingShortageAlert' => $isBookingShortageAlert,
                'isPaymentAlert' => $isPaymentAlert,
                'isPhysicalVarianceAlert' => $isPhysicalVarianceAlert,
                'typeLabel' => $typeLabel,
                'tone' => $tone,
                'icon' => $icon,
                'actionLabel' => $actionLabel,
                'modalId' => 'notification-action-' . ($alert->id ?? uniqid()),
                'modalTitle' => $isPhysicalVarianceAlert ? 'Physical Stock Count Review' : ($isInventoryAlert ? 'Stock Shortage Alert' : ($isPaymentAlert ? 'Payment Verification Review' : 'Notification Details')),
            ];
        }

        $categoryCounts = collect($cards)->countBy('category');
        $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition';
        $btnSecondary = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition';
        $btnDanger = 'inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 transition';
    @endphp

    @if(empty($cards))
        <div class="rf-admin-card flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true"><i class="fa-solid fa-check text-lg"></i></span>
            <p class="text-sm font-semibold text-slate-900">You're all caught up</p>
            <p class="text-sm text-slate-500">No active notifications at the moment.</p>
        </div>
    @else
        <section class="rf-admin-card overflow-hidden" aria-label="Notification inbox">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="-mb-px flex min-w-0 flex-wrap gap-1" role="tablist" aria-label="Filter notifications">
                    <button type="button" role="tab" aria-selected="true" data-filter="all" class="notif-filter inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-semibold bg-slate-900 text-white">
                        All <span class="rounded-full bg-white/20 px-1.5 text-[11px] tabular-nums">{{ count($cards) }}</span>
                    </button>
                    @foreach($categoryMeta as $key => $meta)
                        @if($categoryCounts->get($key, 0) > 0)
                            <button type="button" role="tab" aria-selected="false" data-filter="{{ $key }}" class="notif-filter inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                                <i class="{{ $meta['icon'] }} text-xs opacity-70" aria-hidden="true"></i>{{ $meta['label'] }}
                                <span class="rounded-full bg-slate-100 px-1.5 text-[11px] tabular-nums text-slate-600">{{ $categoryCounts->get($key) }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
                <p class="text-xs text-slate-500">Payment reviews clear automatically once the payment is verified or rejected.</p>
            </div>

            <ul class="divide-y divide-slate-100" role="list">
                @foreach($cards as $card)
                    @php $alert = $card['alert']; @endphp
                    <li class="notification-card group flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50/70 sm:flex-row sm:items-start" data-alert-id="{{ $alert->id ?? uniqid() }}" data-category="{{ $card['category'] }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 {{ $card['tone'] }}" aria-hidden="true">
                            <i class="{{ $card['icon'] }} text-sm"></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                <span class="font-semibold text-slate-700">{{ $card['typeLabel'] }}</span>
                                @if($alert->booking_id)
                                    <span class="text-slate-300" aria-hidden="true">•</span>
                                    <a href="{{ route('admin.bookings.show', ['booking' => $alert->booking_id]) }}" class="font-medium text-slate-500 hover:text-brand-700 hover:underline">Booking #{{ $alert->booking_id }}</a>
                                @endif
                                <span class="text-slate-300" aria-hidden="true">•</span>
                                <time class="text-slate-400" datetime="{{ optional($alert->created_at)->toIso8601String() }}" title="{{ optional($alert->created_at)->format('M j, Y g:i A') }}">{{ optional($alert->created_at)->diffForHumans() }}</time>
                            </div>
                            <button type="button" onclick="openNotificationModal('{{ $card['modalId'] }}')" class="mt-1 block text-left">
                                <h3 class="text-sm font-semibold text-slate-900 group-hover:text-brand-800">{{ $alert->title }}</h3>
                                <p class="mt-0.5 text-[13px] leading-relaxed text-slate-600">{{ $alert->message }}</p>
                            </button>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                            <button type="button" onclick="openNotificationModal('{{ $card['modalId'] }}')" class="{{ $btnSecondary }} py-1.5">
                                {{ $card['actionLabel'] }}
                            </button>
                            @if(!($alert->is_dynamic ?? false) && !$alert->requiresDecision())
                                <form method="POST" action="{{ route('admin.alerts.read', $alert) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition" title="Mark this alert as read">
                                        <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>Mark Read
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p id="notifFilterEmpty" class="hidden px-5 py-10 text-center text-sm text-slate-500">No notifications in this category.</p>
        </section>

        {{-- Detail / action modals --}}
        @foreach($cards as $card)
            @php
                $alert = $card['alert'];
                $modalId = $card['modalId'];
                $alertType = $card['alertType'];
                $isBookingShortageAlert = $card['isBookingShortageAlert'];
                $isInventoryAlert = $card['isInventoryAlert'];
                $isPaymentAlert = $card['isPaymentAlert'];
                $isPhysicalVarianceAlert = $card['isPhysicalVarianceAlert'];
            @endphp
            <div id="{{ $modalId }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-2 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" data-alert-id="{{ $alert->id ?? uniqid() }}" onclick="closeNotificationModal('{{ $modalId }}')">
                <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
                    <div class="flex shrink-0 items-start justify-between gap-2 border-b border-slate-200 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 {{ $card['tone'] }}" aria-hidden="true"><i class="{{ $card['icon'] }} text-sm"></i></span>
                            <div class="min-w-0">
                                <h3 id="{{ $modalId }}-title" class="text-base font-semibold text-slate-900">{{ $isBookingShortageAlert ? 'Resolve Inventory Shortages - Booking #' . ($alert->booking_id ?? 'N/A') : $card['modalTitle'] }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $alert->title }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if($isBookingShortageAlert && $alert->booking_id)
                                {{-- This booking's shortage list for canvassing / procurement (BookingShortageExportController). --}}
                                <a href="{{ route('admin.notifications.booking-shortages.export', ['booking' => $alert->booking_id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" data-shortage-export-csv title="Download this booking's shortage list as CSV" aria-label="Export shortage list as CSV">
                                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i><span class="hidden sm:inline">Export CSV</span>
                                </a>
                                <a href="{{ route('admin.notifications.booking-shortages.report', ['booking' => $alert->booking_id]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" data-shortage-report title="Open the printable shortage report (Save as PDF)" aria-label="Print shortage report">
                                    <i class="fa-solid fa-print" aria-hidden="true"></i><span class="hidden sm:inline">Print Report</span>
                                </a>
                            @endif
                            <button type="button" aria-label="Close notification details" class="close-modal-btn inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" onclick="closeNotificationModal('{{ $modalId }}')"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    @if($isBookingShortageAlert && $alert->booking_id)
                        <form method="POST" action="{{ route('admin.notifications.resolve-booking-shortages', ['booking' => $alert->booking_id]) }}" class="flex min-h-0 flex-1 flex-col" data-booking-shortage-form data-has-missing-items="{{ collect($alert->items ?? [])->contains(fn ($item) => $item['is_missing'] ?? false) ? '1' : '0' }}">
                            @csrf
                            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-3 sm:p-5">
                                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900 sm:p-4">
                                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600" aria-hidden="true"></i>
                                    <div>
                                        <p class="font-semibold">Booking Shortage Items</p>
                                        <p class="mt-0.5 text-slate-600">Add stock for catalog items. Items marked as not in current inventory must be linked or added to the catalog first.</p>
                                    </div>
                                </div>

                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                    <table class="booking-shortage-table w-full text-left text-sm">
                                        <thead class="rf-admin-thead">
                                            <tr>
                                                <th scope="col">Item</th>
                                                <th scope="col" class="!text-right">Physical</th>
                                                <th scope="col" class="!text-right" title="Stock already held for other confirmed bookings">Held for Other Bookings</th>
                                                <th scope="col" class="!text-right">Shortfall</th>
                                                <th scope="col" class="!text-right w-32">Add Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach(($alert->items ?? []) as $item)
                                                @php $inventoryItem = \App\Models\InventoryItem::find($item['inventory_item_id']); $itemIsShort = $item['is_shortage'] ?? true; @endphp
                                                <tr class="{{ $itemIsShort ? '' : 'bg-emerald-50/50' }}">
                                                    <td class="px-4 py-3" data-label="Item">
                                                        <p class="font-medium text-slate-900">{{ $inventoryItem?->name ?? $item['name'] }}</p>
                                                        <p class="text-xs text-slate-500">Required: <span class="font-semibold text-slate-800 tabular-nums">{{ number_format((float) ($item['required'] ?? 0), 0) }}</span></p>
                                                    </td>
                                                    <td class="px-4 py-3 text-right tabular-nums" data-label="Physical">{{ number_format((float) ($item['current_stock'] ?? 0), 0) }}</td>
                                                    <td class="px-4 py-3 text-right tabular-nums" data-label="Held for Other Bookings">{{ number_format((float) ($item['reserved_stock'] ?? 0), 0) }}</td>
                                                    <td class="px-4 py-3 text-right" data-label="Shortfall">
                                                        @if($itemIsShort)
                                                            <span class="font-semibold text-rose-700 tabular-nums">{{ number_format((float) ($item['shortfall'] ?? 0), 0) }}</span>
                                                        @else
                                                            <span class="text-xs font-semibold text-emerald-700">In Stock</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right" data-label="Add Stock">
                                                        @if(!$itemIsShort)
                                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Available</span>
                                                        @elseif($item['is_missing'] ?? false)
                                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Not in current inventory</span>
                                                        @else
                                                            <label class="sr-only" for="stock_{{ $alert->id }}_{{ $item['inventory_item_id'] }}">Add Stock for {{ $inventoryItem?->name ?? $item['name'] }}</label>
                                                            <input type="number"
                                                                   min="0"
                                                                   step="0.01"
                                                                   name="stock[{{ $item['inventory_item_id'] }}]"
                                                                   id="stock_{{ $alert->id }}_{{ $item['inventory_item_id'] }}"
                                                                   data-shortfall="{{ $item['shortfall'] ?? 0 }}"
                                                                   data-is-shortage="1"
                                                                   value=""
                                                                   placeholder="{{ number_format((float) ($item['shortfall'] ?? 0), 0) }}"
                                                                   class="add-stock-input w-24 rounded-md border border-slate-300 px-2.5 py-1.5 text-right text-sm tabular-nums focus:border-brand-600 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-slate-500">Resolve &amp; Restock unlocks once every shortfall is covered.</p>
                            </div>

                            @php
                                // Compact footer buttons, so the actions fit on one row.
                                $btnCompactPrimary = str_replace(['px-4 py-2 text-sm'], ['px-3 py-1.5 text-xs sm:whitespace-nowrap'], $btnPrimary);
                                $btnCompactSecondary = str_replace(['px-4 py-2 text-sm'], ['px-3 py-1.5 text-xs sm:whitespace-nowrap'], $btnSecondary);
                                $missingBookingItemIds = collect($alert->items ?? [])->where('is_missing', true)->pluck('booking_item_id')->filter()->values();
                            @endphp
                            <div class="grid shrink-0 grid-cols-2 items-start gap-2 border-t border-slate-200 bg-white px-3 py-3 sm:flex sm:items-center sm:justify-between sm:px-5" data-shortage-modal-footer>
                                <button type="button" class="{{ $btnCompactSecondary }} w-full min-w-0 text-center leading-tight sm:w-auto" onclick="closeNotificationModal('{{ $modalId }}')">Cancel</button>
                                <div class="contents sm:flex sm:flex-wrap sm:items-center sm:justify-end sm:gap-2">
                                    <button type="button" class="{{ $btnCompactSecondary }} w-full min-w-0 text-center leading-tight sm:w-auto" data-substitution-btn onclick="window.location.href='{{ route('admin.bookings.show', ['booking' => $alert->booking_id]) }}'">
                                        Request Substitution / Edit Booking
                                    </button>
                                    @if($missingBookingItemIds->isNotEmpty())
                                        <button type="submit" form="promote-missing-ai-items-{{ $modalId }}" class="{{ $btnCompactPrimary }} w-full min-w-0 text-center leading-tight sm:w-auto">
                                            Add Missing AI Items to Inventory
                                        </button>
                                    @endif
                                    <button type="submit" name="action" value="restock_and_resolve" class="{{ $btnCompactPrimary }} w-full min-w-0 text-center leading-tight sm:w-auto disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none" data-resolve-btn disabled>
                                        Resolve &amp; Restock
                                    </button>
                                </div>
                            </div>
                        </form>
                        @if($missingBookingItemIds->isNotEmpty())
                            <form id="promote-missing-ai-items-{{ $modalId }}" method="POST" action="{{ route('admin.notifications.promote-booking-items', ['booking' => $alert->booking_id]) }}" class="hidden">
                                @csrf
                                @foreach($missingBookingItemIds as $bookingItemId)
                                    <input type="hidden" name="booking_item_ids[]" value="{{ $bookingItemId }}">
                                @endforeach
                            </form>
                        @endif
                    @elseif($alertType === 'general_low_stock')
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-5">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-800">General low-stock items</p>
                                <p class="mt-1 text-[13px] text-amber-800">These catalog items are at or below their configured minimum stock level.</p>
                            </div>
                            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white" role="list">
                                @foreach(($alert->items ?? []) as $item)
                                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">{{ $item['name'] }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">Minimum: {{ number_format((float) ($item['required'] ?? 0), 0) }} · Available: {{ number_format((float) ($item['net_available'] ?? 0), 0) }}</p>
                                        </div>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Restock needed</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
                            <a href="{{ route('admin.inventory.index') }}" class="{{ $btnSecondary }}">Manage Inventory</a>
                            <button type="button" class="{{ $btnSecondary }}" onclick="closeNotificationModal('{{ $modalId }}')">Close</button>
                        </div>
                    {{-- A Staff stock count carries an inventory item but needs Approve/Reject, not a restock form. --}}
                    @elseif($isInventoryAlert && !$isPhysicalVarianceAlert)
                        @php
                            $inventoryItem = $alert->inventory_item_id ? \App\Models\InventoryItem::find($alert->inventory_item_id) : null;
                            $physicalStock = (float) ($inventoryItem?->current_stock ?? 0);
                            $reservedStock = (float) ($inventoryItem?->reserved_stock ?? 0);
                            $netAvailable = max(0.0, $physicalStock - $reservedStock);
                            $shortageAmount = max(0.0, (float) ($inventoryItem?->min_stock ?? 0) - $netAvailable);
                        @endphp
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50/60 p-5">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-800">Stock shortage alert</p>
                                <p class="mt-1 text-[13px] text-amber-800">Resolve the shortage directly from this modal by adding physical stock and closing the alert immediately.</p>
                            </div>

                            @if($inventoryItem)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <dl class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">Physical stock</dt>
                                            <dd class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($physicalStock, 0) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">Reserved</dt>
                                            <dd class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($reservedStock, 0) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">Shortage amount</dt>
                                            <dd class="mt-0.5 text-lg font-semibold text-amber-700 tabular-nums">{{ number_format($shortageAmount, 0) }}</dd>
                                        </div>
                                    </dl>

                                    <form method="POST" action="{{ route('admin.notifications.resolve-shortage', ['inventoryItem' => $inventoryItem->id]) }}" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-end" data-restock-form data-alert-id="{{ $alert->id ?? uniqid() }}">
                                        @csrf
                                        <div class="flex-1">
                                            <label class="mb-1 block text-xs font-semibold text-slate-600" for="additional_stock_{{ $inventoryItem->id }}">Add Stock Quantity</label>
                                            <input type="number" min="0" step="0.01" name="additional_stock" id="additional_stock_{{ $inventoryItem->id }}" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-brand-600 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                                        </div>
                                        <button type="submit" class="{{ $btnPrimary }}">Restock &amp; Resolve Alert</button>
                                    </form>
                                </div>
                            @else
                                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                    Inventory item details are unavailable for this alert.
                                </div>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
                            @php $bookingId = (int) ($alert->booking_id ?? 0); @endphp
                            @if($bookingId > 0)
                                <a href="{{ route('admin.bookings.show', ['booking' => $bookingId]) }}" class="{{ $btnSecondary }}">View Booking (#{{ $bookingId }})</a>
                            @endif
                            <a href="{{ route('admin.inventory.index') }}" class="{{ $btnSecondary }}">Manage Inventory</a>
                            <button type="button" class="{{ $btnSecondary }}" onclick="closeNotificationModal('{{ $modalId }}')">Close</button>
                        </div>
                    @else
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50/60 p-5">
                            @if($isPaymentAlert && $alert->booking_id)
                                @php $booking = \App\Models\Booking::with(['client', 'payments'])->find($alert->booking_id); $latestPayment = $booking?->payments->sortByDesc('created_at')->first(); @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                        <div><dt class="text-xs font-medium text-slate-500">Booking</dt><dd class="font-medium text-slate-900">#{{ $booking?->id ?? $alert->booking_id }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $booking?->client?->full_name ?? 'Guest' }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Event</dt><dd class="font-medium text-slate-900">{{ $booking?->event_type ? ucfirst($booking->event_type) : 'N/A' }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ optional($booking?->event_date)->format('F j, Y') }}</dd></div>
                                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-slate-500">Venue</dt><dd class="font-medium text-slate-900">{{ $booking?->venue ?? 'Not set' }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Method</dt><dd class="font-medium text-slate-900">{{ $latestPayment ? strtoupper(str_replace('_', ' ', $latestPayment->payment_type)) : 'N/A' }}</dd></div>
                                        <div><dt class="text-xs font-medium text-slate-500">Reference</dt><dd class="font-mono text-slate-900">{{ $latestPayment?->reference_number ?? 'N/A' }}</dd></div>
                                    </dl>
                                </div>
                            @elseif($isPhysicalVarianceAlert)
                                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                                    <p class="text-sm font-semibold text-sky-900">Physical Stock Count Discrepancy</p>
                                    <p class="mt-1 text-[13px] text-sky-900">{{ $alert->message }}</p>
                                </div>
                            @else
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm text-slate-700">{{ $alert->message }}</p>
                                    @if($alert->booking_id)
                                        <a href="{{ route('admin.bookings.show', ['booking' => $alert->booking_id]) }}" class="rf-admin-card__link mt-2 inline-block">Open Booking #{{ $alert->booking_id }}</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500">Additional actions are not available for this alert yet.</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
                            @if($isPaymentAlert && $alert->booking_id && $latestPayment)
                                <form id="reject-payment-alert-{{ $latestPayment->id }}" method="POST" action="{{ route('admin.payments.reject', ['payment' => $latestPayment->id]) }}" data-confirm="Reject this payment?" data-confirm-message="The client will be told the payment could not be verified. The booking stays unconfirmed until a valid payment is verified." data-confirm-label="Reject payment" data-confirm-tone="danger">
                                    @csrf
                                    <button type="submit" class="{{ $btnDanger }}">Reject</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payments.verify', ['payment' => $latestPayment->id]) }}" data-confirm="Verify this payment?" data-confirm-message="Only verify after checking the reference against Raflora's account. Verifying records the payment and can confirm the booking." data-confirm-label="Verify payment">
                                    @csrf
                                    <button type="submit" class="{{ $btnPrimary }}"><i class="fa-solid fa-check" aria-hidden="true"></i>Verify &amp; Lock Booking</button>
                                </form>
                            @elseif($isPhysicalVarianceAlert)
                                <form method="POST" action="{{ route('admin.inventory.adjustments.reject', $alert) }}" data-confirm="Reject this stock count?" data-confirm-message="System stock stays as it is and the request is closed." data-confirm-label="Reject count" data-confirm-tone="danger">
                                    @csrf
                                    <button type="submit" class="{{ $btnDanger }}">Reject</button>
                                </form>
                                <form method="POST" action="{{ route('admin.inventory.adjustments.approve', $alert) }}" data-confirm="Apply this stock count?" data-confirm-message="System stock is set to the count Staff observed and the change is recorded." data-confirm-label="Apply count">
                                    @csrf
                                    <button type="submit" class="{{ $btnPrimary }}">Approve &amp; Apply Adjustment</button>
                                </form>
                            @else
                                <button type="button" class="{{ $btnSecondary }}" onclick="closeNotificationModal('{{ $modalId }}')">Close</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    <script>
        function openNotificationModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeNotificationModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('[role="dialog"][id^="notification-action-"].flex').forEach(function (modal) {
                closeNotificationModal(modal.id);
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Category filter tabs
            const filters = document.querySelectorAll('.notif-filter');
            const cards = document.querySelectorAll('.notification-card');
            const emptyNote = document.getElementById('notifFilterEmpty');
            const ACTIVE = ['bg-slate-900', 'text-white', 'font-semibold'];
            const INACTIVE = ['text-slate-600', 'hover:bg-slate-100', 'hover:text-slate-900', 'font-medium'];

            filters.forEach(function (filter) {
                filter.addEventListener('click', function () {
                    const value = filter.dataset.filter;
                    let visible = 0;
                    cards.forEach(function (card) {
                        const show = value === 'all' || card.dataset.category === value;
                        card.classList.toggle('hidden', !show);
                        if (show) visible++;
                    });
                    filters.forEach(function (other) {
                        const isActive = other === filter;
                        other.classList.remove(...(isActive ? INACTIVE : ACTIVE));
                        other.classList.add(...(isActive ? ACTIVE : INACTIVE));
                        other.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                    if (emptyNote) emptyNote.classList.toggle('hidden', visible > 0);
                });
            });

            // Booking shortage modal: Resolve & Restock unlocks only when every shortfall is covered.
            document.querySelectorAll('[data-booking-shortage-form]').forEach(function (form) {
                const resolveButton = form.querySelector('[data-resolve-btn]');
                const substitutionButton = form.querySelector('[data-substitution-btn]');
                const hasMissingItems = form.getAttribute('data-has-missing-items') === '1';

                function updateButtonState() {
                    const inputs = form.querySelectorAll('.add-stock-input');

                    const satisfiesAllShortfalls = !hasMissingItems && inputs.length > 0 && Array.from(inputs).every(function (input) {
                        const enteredValue = parseFloat(input.value) || 0;
                        const requiredShortfall = parseFloat(input.getAttribute('data-shortfall')) || 0;
                        return enteredValue >= requiredShortfall && requiredShortfall > 0;
                    });

                    if (resolveButton) {
                        resolveButton.disabled = !satisfiesAllShortfalls;
                    }

                    if (substitutionButton) {
                        substitutionButton.disabled = satisfiesAllShortfalls;
                        substitutionButton.classList.toggle('opacity-50', satisfiesAllShortfalls);
                        substitutionButton.classList.toggle('cursor-not-allowed', satisfiesAllShortfalls);
                    }
                }

                form.querySelectorAll('.add-stock-input').forEach(function (input) {
                    input.addEventListener('input', updateButtonState);
                    input.addEventListener('change', updateButtonState);
                });

                updateButtonState();
            });
        });
    </script>
</x-admin-layout>
