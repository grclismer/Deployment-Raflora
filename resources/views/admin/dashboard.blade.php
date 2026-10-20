<x-admin-layout title="Dashboard" description="Bookings, revenue, inventory and follow-ups at a glance.">
    @php
        $peso = fn ($value) => '₱' . number_format((float) $value, 2);

        // Signed month-over-month change; the arrow keeps direction readable without relying on color.
        $delta = function ($growth) {
            $growth = (float) $growth;
            if ($growth > 0) {
                return ['class' => 'text-emerald-700 bg-emerald-50', 'icon' => 'fa-arrow-up', 'text' => '+' . rtrim(rtrim(number_format($growth, 1), '0'), '.') . '%'];
            }
            if ($growth < 0) {
                return ['class' => 'text-rose-700 bg-rose-50', 'icon' => 'fa-arrow-down', 'text' => rtrim(rtrim(number_format($growth, 1), '0'), '.') . '%'];
            }
            return ['class' => 'text-slate-600 bg-slate-100', 'icon' => null, 'text' => '0%'];
        };

        $formatTime = function ($time) {
            if (empty($time)) {
                return 'Time TBD';
            }
            try {
                return \Illuminate\Support\Carbon::parse($time)->format('g:i A');
            } catch (\Throwable $e) {
                return (string) $time;
            }
        };

        $kpis = [
            ['label' => 'Total Bookings', 'value' => number_format($bookingsThisMonth), 'note' => 'Bookings created this month', 'icon' => 'fa-regular fa-calendar-check', 'delta' => $delta($bookingsGrowth), 'compare' => 'vs last month'],
            ['label' => 'Collected Revenue', 'value' => $peso($revenueThisMonth), 'note' => 'Verified payments this month', 'icon' => 'fa-solid fa-peso-sign', 'delta' => $delta($revenueGrowth), 'compare' => 'vs last month'],
            ['label' => 'Active Packages', 'value' => number_format($activePackagesCount), 'note' => 'Published for public booking', 'icon' => 'fa-solid fa-gift', 'link' => route('admin.packages.index'), 'linkLabel' => 'Manage packages'],
            ['label' => 'Total Clients', 'value' => number_format($totalClientsCount), 'note' => 'Registered client profiles', 'icon' => 'fa-solid fa-users', 'delta' => $delta($clientsGrowth), 'compare' => 'new sign-ups vs last month'],
        ];

        $queues = [
            ['label' => 'Bookings to review', 'hint' => 'New, change & cancellation requests', 'count' => $attentionQueues['review'], 'icon' => 'fa-solid fa-magnifying-glass', 'href' => route('admin.bookings', ['stage' => 'review'])],
            ['label' => 'Awaiting approval', 'hint' => 'Accepted quotations to approve', 'count' => $attentionQueues['approval'], 'icon' => 'fa-solid fa-user-check', 'href' => route('admin.bookings', ['stage' => 'approval'])],
            ['label' => 'Payments to verify', 'hint' => 'Submitted proofs of payment', 'count' => $attentionQueues['payment'], 'icon' => 'fa-regular fa-credit-card', 'href' => route('admin.bookings', ['stage' => 'payment'])],
            ['label' => 'Returns to process', 'hint' => 'Inspections & damage approvals', 'count' => $attentionQueues['returns'], 'icon' => 'fa-solid fa-truck-ramp-box', 'href' => route('admin.return-tracking')],
            ['label' => 'Stock below minimum', 'hint' => 'Low or out-of-stock items', 'count' => $attentionQueues['stock'], 'icon' => 'fa-solid fa-boxes-stacked', 'href' => route('admin.inventory.index', ['stock_level' => 'low'])],
        ];
        // Queues mix bookings and stock items, so report how many queues are open rather than summing them.
        $openQueueCount = count(array_filter($queues, fn ($queue) => $queue['count'] > 0));

        $hasRevenueThisYear = array_sum($monthlyRevenueData) > 0;
        $maxEventTypeCount = max(1, ...array_map(fn ($et) => $et['count'], $eventTypes ?: [['count' => 1]]));

        $paymentTotal = $paymentSummary['paid'] + $paymentSummary['pending'] + $paymentSummary['balance_due'];
        $paymentRows = [
            ['label' => 'Paid (Verified)', 'amount' => $paymentSummary['paid'], 'pct' => $paymentSummary['paid_pct'], 'note' => 'of total obligation', 'swatch' => 'bg-[#0ca30c]', 'icon' => 'fa-circle-check text-[#0a8a0a]'],
            ['label' => 'Payment Pending', 'amount' => $paymentSummary['pending'], 'pct' => $paymentSummary['pending_pct'], 'note' => 'submitted, not yet verified', 'swatch' => 'bg-[#fab219]', 'icon' => 'fa-hourglass-half text-amber-600'],
            ['label' => 'Balance Due', 'amount' => $paymentSummary['balance_due'], 'pct' => $paymentSummary['balance_due_pct'], 'note' => 'outstanding on active bookings', 'swatch' => 'bg-slate-400', 'icon' => 'fa-circle-exclamation text-slate-500'],
        ];

        $inventoryRows = [
            ['label' => 'In Stock', 'count' => $inventoryStatus['in_stock'], 'pct' => $inventoryStatus['in_stock_pct'], 'swatch' => 'bg-[#0ca30c]', 'icon' => 'fa-circle-check text-[#0a8a0a]'],
            ['label' => 'Low Stock', 'count' => $inventoryStatus['low_stock'], 'pct' => $inventoryStatus['low_stock_pct'], 'swatch' => 'bg-[#fab219]', 'icon' => 'fa-triangle-exclamation text-amber-600'],
            ['label' => 'Out of Stock', 'count' => $inventoryStatus['out_of_stock'], 'pct' => $inventoryStatus['out_of_stock_pct'], 'swatch' => 'bg-[#d03b3b]', 'icon' => 'fa-circle-xmark text-[#d03b3b]'],
        ];

        $alertLabel = fn ($type) => match ($type) {
            'inventory_shortage' => 'Stock Shortage',
            'quotation_expired' => 'Quotation Expired',
            'meeting_requested' => 'Meeting Request',
            'meeting_cancelled' => 'Meeting Cancelled',
            default => \Illuminate\Support\Str::headline((string) $type),
        };

        $shortcuts = [
            ['label' => 'Review Bookings', 'hint' => 'Open the booking queue and verify payment references.', 'icon' => 'fa-solid fa-calendar-days', 'href' => route('admin.bookings')],
            ['label' => 'Quotations', 'hint' => 'Track issued quotations, revisions, and price validity.', 'icon' => 'fa-solid fa-file-invoice-dollar', 'href' => route('admin.quotations')],
            ['label' => 'Return Tracking', 'hint' => 'Review post-event asset returns, assess damages, and reconcile warehouse stock.', 'icon' => 'fa-solid fa-truck-ramp-box', 'href' => route('admin.return-tracking')],
            ['label' => 'Manage Inventory', 'hint' => 'Track physical floral assets, set reorder points, and replenish stock.', 'icon' => 'fa-solid fa-warehouse', 'href' => route('admin.inventory.index')],
            ['label' => 'AI Material Plan', 'hint' => 'See latest AI-suggested materials and update procurement plans.', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'href' => route('admin.ai-analysis')],
        ];
    @endphp

    <!-- Greeting -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand-700 mb-1">Operational Overview</p>
            <h2 class="text-xl sm:text-2xl font-semibold text-slate-900 tracking-tight">Welcome back, {{ auth()->user()->name ?? 'Administrator' }}</h2>
            <p class="text-sm text-slate-500 mt-1">Here's what's happening with your floral event business today.</p>
        </div>
        <p class="inline-flex items-center gap-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg px-3 h-8 self-start sm:self-auto shrink-0">
            <i class="fa-regular fa-calendar text-slate-400" aria-hidden="true"></i>
            <time datetime="{{ now()->toDateString() }}">{{ now()->format('l, M j, Y') }}</time>
        </p>
    </div>

    <!-- KPI tiles -->
    <section class="rf-stat-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6" aria-label="Key metrics">
        @foreach($kpis as $kpi)
            <div class="rf-admin-card p-5 flex flex-col">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="rf-admin-kpi-label">{{ $kpi['label'] }}</h3>
                    <span class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-100 text-slate-500 flex items-center justify-center shrink-0" aria-hidden="true">
                        <i class="{{ $kpi['icon'] }} text-[13px]"></i>
                    </span>
                </div>
                <p class="rf-admin-kpi-value mt-2 truncate">{{ $kpi['value'] }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $kpi['note'] }}</p>
                <div class="mt-auto pt-4"></div>
                <div class="pt-3 border-t border-slate-100 flex items-center gap-2 text-xs min-h-[2rem]">
                    @isset($kpi['delta'])
                        <span class="inline-flex items-center gap-1 font-semibold rounded-md px-1.5 py-0.5 shrink-0 tabular-nums {{ $kpi['delta']['class'] }}">
                            @if($kpi['delta']['icon'])
                                <i class="fa-solid {{ $kpi['delta']['icon'] }} text-[9px]" aria-hidden="true"></i>
                            @endif
                            {{ $kpi['delta']['text'] }}
                        </span>
                        <span class="text-slate-500 truncate">{{ $kpi['compare'] }}</span>
                    @else
                        <a href="{{ $kpi['link'] }}" class="rf-admin-card__link inline-flex items-center gap-1">{{ $kpi['linkLabel'] }} <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
                    @endisset
                </div>
            </div>
        @endforeach
    </section>

    <!-- Needs attention -->
    <section class="rf-admin-card mb-6" aria-labelledby="attention-heading">
        <div class="rf-admin-card__header">
            <div class="flex items-center gap-2">
                <h3 id="attention-heading" class="rf-admin-card__title">Needs your attention</h3>
                <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 tabular-nums {{ $openQueueCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $openQueueCount > 0 ? $openQueueCount . ' of ' . count($queues) . ' queues open' : 'All clear' }}
                </span>
            </div>
            <span class="hidden sm:inline text-xs text-slate-500">Counts match the filtered lists they open</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 divide-y sm:divide-y-0 lg:divide-x divide-slate-100">
            @foreach($queues as $queue)
                <a href="{{ $queue['href'] }}" class="group flex items-start gap-3 p-4 hover:bg-slate-50 transition focus:outline-none focus-visible:bg-slate-50">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $queue['count'] > 0 ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-slate-50 text-slate-400 ring-1 ring-slate-200' }}" aria-hidden="true">
                        <i class="{{ $queue['icon'] }} text-sm"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="flex items-baseline gap-2">
                            <span class="text-xl font-semibold tabular-nums {{ $queue['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ number_format($queue['count']) }}</span>
                            @if($queue['count'] === 0)
                                <i class="fa-solid fa-check text-[11px] text-emerald-600" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="block text-[13px] font-medium text-slate-800 group-hover:text-brand-800 transition">{{ $queue['label'] }}</span>
                        <span class="block text-xs text-slate-500 leading-snug">{{ $queue['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- Active Alerts (AdminAlert system) -->
    @if($activeAlerts->count() > 0)
    <section class="rf-admin-card mb-6 overflow-hidden" aria-labelledby="active-alerts-heading">
        <div class="rf-admin-card__header">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fa-solid fa-bell text-amber-500 text-sm" aria-hidden="true"></i>
                <h3 id="active-alerts-heading" class="rf-admin-card__title">Active Alerts</h3>
                <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800 tabular-nums">{{ $activeAlerts->count() }} unread</span>
            </div>
            <form method="POST" action="{{ route('admin.alerts.read-all') }}" data-confirm="Dismiss all alerts?" data-confirm-message="Alerts are marked as read. Stock count requests from Staff stay open until you approve or reject them in Notifications." data-confirm-label="Dismiss all">
                @csrf
                <button type="submit" class="text-xs font-semibold text-slate-600 hover:text-slate-900 border border-slate-200 bg-white rounded-lg px-3 h-8 hover:bg-slate-50 transition cursor-pointer">
                    Dismiss All
                </button>
            </form>
        </div>
        <ul class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
            @foreach($activeAlerts as $alert)
                @php $isShortage = $alert->type === 'inventory_shortage'; @endphp
                <li class="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50/70 transition group">
                    <span class="mt-0.5 shrink-0 w-8 h-8 rounded-lg flex items-center justify-center {{ $isShortage ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' }}" aria-hidden="true">
                        <i class="fa-solid {{ $isShortage ? 'fa-boxes-stacked' : 'fa-clock' }} text-xs"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-sm font-semibold text-slate-900">{{ $alert->title }}</p>
                            <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded-md {{ $isShortage ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800' }}">{{ $alertLabel($alert->type) }}</span>
                            <span class="text-xs text-slate-400">{{ $alert->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-[13px] text-slate-600 mt-0.5 leading-relaxed">{{ $alert->message }}</p>
                        <div class="flex items-center gap-4 mt-1.5">
                            @if($alert->booking_id)
                                <a href="{{ route('admin.bookings.show', $alert->booking_id) }}" class="rf-admin-card__link">View Booking #{{ $alert->booking_id }}</a>
                            @endif
                            @if($isShortage)
                                <a href="{{ route('admin.inventory.index') }}" class="rf-admin-card__link">Manage Inventory</a>
                            @endif
                        </div>
                    </div>
                    @if($alert->requiresDecision())
                        <a href="{{ route('admin.notifications') }}" class="rf-admin-card__link shrink-0 text-xs">Review</a>
                    @else
                    <form method="POST" action="{{ route('admin.alerts.read', $alert) }}" class="shrink-0">
                        @csrf
                        <button type="submit" title="Dismiss" aria-label="Dismiss alert" class="opacity-60 group-hover:opacity-100 focus:opacity-100 transition w-7 h-7 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center cursor-pointer">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    <!-- Revenue + Payment Summary -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">
        <section class="xl:col-span-8 rf-admin-card flex flex-col" aria-labelledby="revenue-heading">
            <div class="rf-admin-card__header">
                <h3 id="revenue-heading" class="rf-admin-card__title">Revenue Overview</h3>
                <span class="text-xs font-medium text-slate-500">Jan – Dec {{ now()->year }}</span>
            </div>
            <div class="px-5 pt-4">
                <p class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $peso($revenueThisYear) }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Verified payment collections this year, by month</p>
            </div>
            <div class="relative h-64 px-3 pb-3 pt-2">
                @if($hasRevenueThisYear)
                    <canvas id="revenueOverviewChart" role="img" aria-label="Monthly verified revenue for {{ now()->year }}"></canvas>
                @else
                    <div class="h-full flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-slate-200 bg-slate-50/60 mx-2">
                        <i class="fa-solid fa-chart-line text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                        <p class="text-sm font-medium text-slate-700">No verified payments in {{ now()->year }} yet</p>
                        <p class="text-xs text-slate-500 mt-1">The monthly trend appears once payments are verified.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="xl:col-span-4 rf-admin-card flex flex-col" aria-labelledby="payments-heading">
            <div class="rf-admin-card__header">
                <h3 id="payments-heading" class="rf-admin-card__title">Payment Summary</h3>
                <a href="{{ route('admin.bookings', ['stage' => 'payment']) }}" class="rf-admin-card__link">Verify payments</a>
            </div>
            <div class="p-5 flex-1 flex flex-col">
                <p class="text-xs text-slate-500">Total obligation across active bookings</p>
                <p class="text-2xl font-semibold text-slate-900 tracking-tight mt-0.5">{{ $peso($paymentTotal) }}</p>

                <div class="flex h-2.5 w-full gap-0.5 rounded-full overflow-hidden bg-slate-100 mt-4" role="img" aria-label="Paid {{ $paymentSummary['paid_pct'] }}%, pending {{ $paymentSummary['pending_pct'] }}%, balance due {{ $paymentSummary['balance_due_pct'] }}%">
                    @foreach($paymentRows as $row)
                        @if($row['pct'] > 0)
                            <span class="{{ $row['swatch'] }} h-full" style="width: {{ $row['pct'] }}%;"></span>
                        @endif
                    @endforeach
                </div>

                <dl class="mt-4 space-y-3">
                    @foreach($paymentRows as $row)
                        <div class="flex items-start justify-between gap-3">
                            <dt class="flex items-start gap-2 min-w-0">
                                <i class="fa-solid {{ $row['icon'] }} text-xs mt-0.5" aria-hidden="true"></i>
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-medium text-slate-800">{{ $row['label'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $row['pct'] }}% {{ $row['note'] }}</span>
                                </span>
                            </dt>
                            <dd class="text-[13px] font-semibold text-slate-900 tabular-nums whitespace-nowrap">{{ $peso($row['amount']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    </div>

    <!-- Event types + Inventory + Top packages -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <section class="rf-admin-card flex flex-col" aria-labelledby="event-types-heading">
            <div class="rf-admin-card__header">
                <h3 id="event-types-heading" class="rf-admin-card__title">Bookings by Event Type</h3>
                <span class="text-xs font-medium text-slate-500 tabular-nums">{{ number_format($totalEventTypeBookings) }} active</span>
            </div>
            @if(count($eventTypes) > 0)
                <ul class="p-5 space-y-3.5">
                    @foreach($eventTypes as $et)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <span class="font-medium text-slate-800 truncate">{{ $et['name'] }}</span>
                                <span class="text-slate-900 font-semibold tabular-nums shrink-0">{{ $et['count'] }} <span class="text-slate-400 font-normal">· {{ $et['percentage'] }}%</span></span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-brand-600" style="width: {{ round(($et['count'] / $maxEventTypeCount) * 100, 1) }}%;"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <i class="fa-solid fa-calendar-xmark text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                    <p class="text-sm font-medium text-slate-700">No active bookings recorded</p>
                    <p class="text-xs text-slate-500 mt-1">Bookings by event type will display once inquiries are created.</p>
                </div>
            @endif
        </section>

        <section class="rf-admin-card flex flex-col" aria-labelledby="inventory-heading">
            <div class="rf-admin-card__header">
                <h3 id="inventory-heading" class="rf-admin-card__title">Inventory Status</h3>
                <a href="{{ route('admin.inventory.index') }}" class="rf-admin-card__link">View All</a>
            </div>
            @if($inventoryStatus['total'] > 0)
                <div class="p-5">
                    <p class="text-2xl font-semibold text-slate-900 tracking-tight tabular-nums">{{ number_format($inventoryStatus['total']) }} <span class="text-sm font-medium text-slate-500">items tracked</span></p>
                    <div class="flex h-2.5 w-full gap-0.5 rounded-full overflow-hidden bg-slate-100 mt-4" role="img" aria-label="In stock {{ $inventoryStatus['in_stock_pct'] }}%, low stock {{ $inventoryStatus['low_stock_pct'] }}%, out of stock {{ $inventoryStatus['out_of_stock_pct'] }}%">
                        @foreach($inventoryRows as $row)
                            @if($row['pct'] > 0)
                                <span class="{{ $row['swatch'] }} h-full" style="width: {{ $row['pct'] }}%;"></span>
                            @endif
                        @endforeach
                    </div>
                    <dl class="mt-4 space-y-2.5">
                        @foreach($inventoryRows as $row)
                            <div class="flex items-center justify-between gap-3 text-[13px]">
                                <dt class="flex items-center gap-2 text-slate-700">
                                    <i class="fa-solid {{ $row['icon'] }} text-xs" aria-hidden="true"></i>
                                    {{ $row['label'] }}
                                </dt>
                                <dd class="font-semibold text-slate-900 tabular-nums">{{ number_format($row['count']) }} <span class="text-slate-400 font-normal">· {{ $row['pct'] }}%</span></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <i class="fa-solid fa-boxes-stacked text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                    <p class="text-sm font-medium text-slate-700">No inventory materials recorded</p>
                    <p class="text-xs text-slate-500 mt-1">Items added to inventory will be tracked here.</p>
                </div>
            @endif
        </section>

        <section class="rf-admin-card flex flex-col" aria-labelledby="top-packages-heading">
            <div class="rf-admin-card__header">
                <h3 id="top-packages-heading" class="rf-admin-card__title">Top Packages</h3>
                <a href="{{ route('admin.packages.index') }}" class="rf-admin-card__link">View All</a>
            </div>
            <ol class="divide-y divide-slate-100 flex-1">
                @forelse($topPackages as $index => $pkg)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="w-5 text-xs font-semibold text-slate-400 tabular-nums shrink-0">{{ $index + 1 }}</span>
                        @if(!empty($pkg->image_path))
                            <img src="{{ asset('storage/' . $pkg->image_path) }}" alt="" class="w-9 h-9 rounded-lg object-cover border border-slate-100 shrink-0">
                        @else
                            <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center text-xs shrink-0" aria-hidden="true">
                                <i class="fa-solid fa-gift"></i>
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-medium text-slate-900 truncate" title="{{ $pkg->title }}">{{ $pkg->title }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $pkg->category ?? 'Floral Bundle' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[13px] font-semibold text-slate-900 tabular-nums">{{ $pkg->bookings_count }}</p>
                            <p class="text-[11px] text-slate-500">bookings</p>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center">
                        <i class="fa-solid fa-gift text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                        <p class="text-sm text-slate-500">No package bookings recorded yet.</p>
                    </li>
                @endforelse
            </ol>
        </section>
    </div>

    <!-- Recent bookings + Upcoming events -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">
        <section class="xl:col-span-8 rf-admin-card overflow-hidden flex flex-col" aria-labelledby="recent-bookings-heading">
            <div class="rf-admin-card__header">
                <h3 id="recent-bookings-heading" class="rf-admin-card__title">Recent Bookings</h3>
                <a href="{{ route('admin.bookings') }}" class="rf-admin-card__link">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="rf-table--stack w-full text-left text-[13px] min-w-[640px]">
                    <thead class="rf-admin-thead">
                        <tr>
                            <th scope="col" class="!pl-5">Client</th>
                            <th scope="col">Event</th>
                            <th scope="col">Event Date</th>
                            <th scope="col" class="!text-right">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="!pr-5 !text-right"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($recentBookings as $booking)
                            @php $amt = (float) ($booking->final_quoted_price > 0 ? $booking->final_quoted_price : ($booking->total_quoted ?? 0)); @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="pl-5 pr-4 py-3">
                                    <p class="font-medium text-slate-900 truncate max-w-[14rem]">{{ $booking->client?->full_name ?? $booking->guest_name ?? 'Guest Client' }}</p>
                                    <p class="text-xs text-slate-500">#{{ $booking->id }}</p>
                                </td>
                                <td data-label="Event" class="px-4 py-3">{{ ucfirst($booking->event_type) }}</td>
                                <td data-label="Event Date" class="px-4 py-3 whitespace-nowrap text-slate-600">{{ optional($booking->event_date)->format('M j, Y') ?? 'N/A' }}</td>
                                <td data-label="Amount" class="px-4 py-3 text-right font-medium text-slate-900 tabular-nums whitespace-nowrap">
                                    @if($amt > 0)
                                        {{ $peso($amt) }}
                                    @else
                                        <span class="text-slate-400 font-normal">Not quoted</span>
                                    @endif
                                </td>
                                <td data-label="Status" class="px-4 py-3 whitespace-nowrap"><x-badge :status="$booking->status">{{ $booking->status_display_label }}</x-badge></td>
                                <td class="pl-4 pr-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" class="rf-admin-card__link">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500">No recent bookings recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="xl:col-span-4 rf-admin-card flex flex-col" aria-labelledby="upcoming-heading">
            <div class="rf-admin-card__header">
                <h3 id="upcoming-heading" class="rf-admin-card__title">Upcoming Events</h3>
                <a href="{{ route('admin.bookings', ['sort' => 'event_date_asc']) }}" class="rf-admin-card__link">View All</a>
            </div>
            <ul class="divide-y divide-slate-100 flex-1">
                @forelse($upcomingEvents as $event)
                    <li>
                        <a href="{{ route('admin.bookings.show', $event->id) }}" class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50/70 transition group">
                            <span class="w-11 shrink-0 rounded-lg border border-slate-200 bg-white text-center overflow-hidden" aria-hidden="true">
                                <span class="block bg-slate-50 text-[9px] font-semibold uppercase tracking-wider text-slate-500 py-0.5">{{ optional($event->event_date)->format('M') }}</span>
                                <span class="block text-base font-semibold text-slate-900 leading-6 tabular-nums">{{ optional($event->event_date)->format('j') }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-medium text-slate-900 truncate group-hover:text-brand-800 transition">
                                    {{ $event->client?->full_name ?? $event->guest_name ?? 'Client' }} — {{ ucfirst($event->event_type) }}
                                </span>
                                <span class="block text-xs text-slate-500 truncate mt-0.5">
                                    {{ $formatTime($event->event_time) }}@if($event->venue) · {{ $event->venue }}@endif
                                </span>
                                <span class="block mt-1.5"><x-badge :status="$event->status">{{ $event->status_display_label }}</x-badge></span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center">
                        <i class="fa-regular fa-calendar-check text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                        <p class="text-sm text-slate-500">No upcoming events scheduled.</p>
                    </li>
                @endforelse
            </ul>
        </section>
    </div>

    <!-- Low stock + Recent activity -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">
        <section class="xl:col-span-7 rf-admin-card overflow-hidden flex flex-col" aria-labelledby="low-stock-heading">
            <div class="rf-admin-card__header">
                <h3 id="low-stock-heading" class="rf-admin-card__title">Low Stock Items</h3>
                <a href="{{ route('admin.inventory.index', ['stock_level' => 'low']) }}" class="rf-admin-card__link">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="rf-table--stack w-full text-left text-[13px] min-w-[520px]">
                    <thead class="rf-admin-thead">
                        <tr>
                            <th scope="col" class="!pl-5">Item</th>
                            <th scope="col" class="!text-right">On Hand</th>
                            <th scope="col" class="!text-right">Minimum</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="!pr-5 !text-right"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($lowStockItems as $item)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="pl-5 pr-4 py-3 font-medium text-slate-900">{{ $item->name }}</td>
                                <td data-label="On Hand" class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium text-slate-900">{{ $item->current_stock }} <span class="text-slate-500 font-normal">{{ $item->unit ?? 'units' }}</span></td>
                                <td data-label="Minimum" class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-slate-500">{{ $item->min_stock }}</td>
                                <td data-label="Status" class="px-4 py-3 whitespace-nowrap">
                                    @if($item->current_stock <= 0)
                                        <x-badge variant="danger">Out of Stock</x-badge>
                                    @else
                                        <x-badge variant="warning">Low Stock</x-badge>
                                    @endif
                                </td>
                                <td class="pl-4 pr-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.inventory.edit', $item->id) }}" class="rf-admin-card__link">Update</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-slate-500">All inventory items are well-stocked!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="xl:col-span-5 rf-admin-card flex flex-col" aria-labelledby="activity-heading">
            <div class="rf-admin-card__header">
                <h3 id="activity-heading" class="rf-admin-card__title">Recent Activities</h3>
                <a href="{{ route('admin.settings') }}" class="rf-admin-card__link">Audit Trail</a>
            </div>
            <ol class="px-5 py-4 flex-1">
                @forelse($recentActivities as $activity)
                    <li class="relative flex gap-3 pb-4 last:pb-0">
                        @unless($loop->last)
                            <span class="absolute left-[7px] top-4 bottom-0 w-px bg-slate-200" aria-hidden="true"></span>
                        @endunless
                        <span class="relative mt-1 w-[15px] h-[15px] rounded-full bg-white ring-2 ring-brand-500 shrink-0" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] text-slate-700 leading-snug">
                                <span class="font-medium text-slate-900">{{ $activity->user?->name ?? 'System' }}</span>
                                <span class="text-slate-400">·</span>
                                @if(is_array($activity->details) && isset($activity->details['message']))
                                    {{ $activity->details['message'] }}
                                @elseif(is_string($activity->details))
                                    {{ $activity->details }}
                                @else
                                    {{ Str::title(str_replace('_', ' ', $activity->action ?? 'Event logged')) }}
                                @endif
                            </p>
                            <time class="text-xs text-slate-400" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center">
                        <i class="fa-solid fa-clock-rotate-left text-slate-300 text-2xl mb-2" aria-hidden="true"></i>
                        <p class="text-sm text-slate-500">No recent activity logged.</p>
                    </li>
                @endforelse
            </ol>
        </section>
    </div>

    <!-- Shortcuts -->
    <section aria-labelledby="shortcuts-heading">
        <h3 id="shortcuts-heading" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 mb-3">Shortcuts</h3>
        <div class="rf-stat-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach($shortcuts as $shortcut)
                <a href="{{ $shortcut['href'] }}" class="rf-admin-card group flex flex-col gap-2.5 p-4 hover:border-brand-300 hover:shadow-sm transition">
                    <span class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0" aria-hidden="true">
                            <i class="{{ $shortcut['icon'] }} text-[13px]"></i>
                        </span>
                        <i class="fa-solid fa-arrow-right text-xs text-slate-300 group-hover:text-brand-700 group-hover:translate-x-0.5 transition" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-[13px] font-semibold text-slate-900">{{ $shortcut['label'] }}</span>
                        <span class="block text-xs text-slate-500 leading-relaxed mt-0.5">{{ $shortcut['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    @if($hasRevenueThisYear)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('revenueOverviewChart');
                if (!canvas || typeof Chart === 'undefined') return;

                const css = getComputedStyle(document.documentElement);
                const brand = css.getPropertyValue('--color-brand-700').trim() || '#4a7940';
                const font = { family: 'Inter, system-ui, sans-serif', size: 11 };
                const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const compactPeso = (v) => v >= 1000000 ? '₱' + (v / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
                    : v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v;

                const ctx = canvas.getContext('2d');
                const fill = ctx.createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight);
                fill.addColorStop(0, 'rgba(74, 121, 64, 0.16)');
                fill.addColorStop(1, 'rgba(74, 121, 64, 0)');

                // Vertical hairline at the hovered month.
                const crosshair = {
                    id: 'crosshair',
                    afterDatasetsDraw(chart) {
                        const active = chart.tooltip?.getActiveElements?.() || [];
                        if (!active.length) return;
                        const { top, bottom } = chart.chartArea;
                        const x = active[0].element.x;
                        chart.ctx.save();
                        chart.ctx.strokeStyle = '#cbd5e1';
                        chart.ctx.lineWidth = 1;
                        chart.ctx.beginPath();
                        chart.ctx.moveTo(x, top);
                        chart.ctx.lineTo(x, bottom);
                        chart.ctx.stroke();
                        chart.ctx.restore();
                    },
                };

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($monthlyRevenueLabels),
                        datasets: [{
                            label: 'Collected revenue',
                            data: @json($monthlyRevenueData),
                            borderColor: brand,
                            borderWidth: 2,
                            backgroundColor: fill,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHitRadius: 16,
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: brand,
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                padding: 10,
                                cornerRadius: 8,
                                displayColors: false,
                                titleFont: { ...font, weight: '600' },
                                bodyFont: font,
                                callbacks: { label: (c) => peso(c.raw) },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: '#eef2f6' },
                                ticks: { color: '#94a3b8', font, maxTicksLimit: 5, callback: compactPeso },
                            },
                            x: {
                                grid: { display: false },
                                border: { color: '#e2e8f0' },
                                ticks: { color: '#94a3b8', font },
                            },
                        },
                    },
                    plugins: [crosshair],
                });
            });
        </script>
    @endif
</x-admin-layout>
