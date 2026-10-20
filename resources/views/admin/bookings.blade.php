<x-admin-layout title="Bookings Management" description="Manage booking approvals, payment verification, and quotation follow-ups from this unified screen.">
    @php
        $pipelineStages = [
            ['key' => 'all', 'step' => null, 'label' => 'All Bookings', 'hint' => 'All bookings', 'icon' => 'fa-solid fa-list-ul'],
            ['key' => 'request', 'step' => 1, 'label' => 'Awaiting Claim', 'hint' => 'Unclaimed guest requests'],
            ['key' => 'review', 'step' => 2, 'label' => 'Review', 'hint' => 'Review & materials'],
            ['key' => 'quotation', 'step' => 3, 'label' => 'Quotation', 'hint' => 'Quote preparation'],
            ['key' => 'approval', 'step' => 4, 'label' => 'Approval', 'hint' => 'Awaiting approval'],
            ['key' => 'payment', 'step' => 5, 'label' => 'Payment', 'hint' => 'Payment verification'],
            ['key' => 'confirmed', 'step' => 6, 'label' => 'Confirmed', 'hint' => 'Confirmed bookings'],
            ['key' => 'cancelled', 'step' => 7, 'label' => 'Cancelled', 'hint' => 'Cancelled bookings'],
        ];
    @endphp

    <!-- Primary Booking Workflow Navigation -->
    <nav class="mb-5" aria-label="Booking workflow stages">
        <ol class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2">
            @foreach($pipelineStages as $stage)
                @php $isActiveStage = $activeStage === $stage['key']; @endphp
                <li class="min-w-0">
                    <a
                        href="{{ route('admin.bookings', ['stage' => $stage['key']]) }}"
                        @if($isActiveStage) aria-current="page" @endif
                        class="group flex h-full flex-col rounded-xl border px-3 py-2.5 transition {{ $isActiveStage ? 'border-brand-300 bg-brand-50 ring-1 ring-brand-200' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60' }}"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-semibold shrink-0 {{ $isActiveStage ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-600' }}" aria-hidden="true">
                                @if($stage['step'])
                                    {{ $stage['step'] }}
                                @else
                                    <i class="{{ $stage['icon'] }} text-[10px]"></i>
                                @endif
                            </span>
                            <span class="text-lg font-semibold tabular-nums leading-none {{ $isActiveStage ? 'text-brand-800' : 'text-slate-900' }}">{{ $stageCounts[$stage['key']] }}</span>
                        </span>
                        <span class="mt-2 block text-[13px] font-semibold leading-tight {{ $isActiveStage ? 'text-brand-900' : 'text-slate-800' }}">{{ $stage['label'] }}</span>
                        <span class="block text-[11px] text-slate-500 leading-snug">{{ $stage['hint'] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    <!-- Filter Controls: Status filter dropdown, Sort & Search -->
    <form id="filterForm" method="GET" action="{{ route('admin.bookings') }}" class="mb-4 bg-white px-4 py-3 rounded-xl border border-slate-200">
        <input type="hidden" name="stage" value="{{ $activeStage }}">
        <div class="flex flex-wrap items-center gap-2 md:gap-3">
            {{-- Search (Full width on mobile, auto on desktop) --}}
            <div class="relative w-full md:min-w-[240px] md:flex-1 lg:max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="bookingSearchInput" name="search" value="{{ $searchTerm }}"
                    placeholder="Search client, event, ID..."
                    autocomplete="off"
                    class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-brand-500 focus:border-brand-600 transition">
            </div>

            {{-- Status Filter (Half width on mobile) --}}
            <div class="w-[calc(50%-4px)] md:w-auto md:min-w-[200px]">
                <label for="status" class="sr-only">Status</label>
                <select id="status" name="status" onchange="document.getElementById('filterForm').submit()"
                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600 transition">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Awaiting Admin Approval</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="quotation_sent" {{ $statusFilter === 'quotation_sent' ? 'selected' : '' }}>Quotation Sent</option>
                    <option value="admin_approved" {{ $statusFilter === 'admin_approved' ? 'selected' : '' }}>Admin Approved (Payment Pending)</option>
                    <option value="payment_pending" {{ $statusFilter === 'payment_pending' ? 'selected' : '' }}>Payment Pending</option>
                    <option value="payment_submitted" {{ $statusFilter === 'payment_submitted' ? 'selected' : '' }}>Payment Submitted</option>
                    <option value="fully_paid" {{ $statusFilter === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                    <option value="downpayment_received" {{ $statusFilter === 'downpayment_received' ? 'selected' : '' }}>Downpayment Received</option>
                    <option value="confirmed" {{ $statusFilter === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="event_in_progress" {{ $statusFilter === 'event_in_progress' ? 'selected' : '' }}>Event In Progress</option>
                    <option value="event_completed" {{ $statusFilter === 'event_completed' ? 'selected' : '' }}>Event Completed</option>
                    <option value="pending_return" {{ $statusFilter === 'pending_return' ? 'selected' : '' }}>Pending Return</option>
                    <option value="pending_resolution" {{ $statusFilter === 'pending_resolution' ? 'selected' : '' }}>Pending Resolution</option>
                    <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="declined" {{ $statusFilter === 'declined' ? 'selected' : '' }}>Declined</option>
                    <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            {{-- Event Type Filter (Half width on mobile) --}}
            <div class="w-[calc(50%-4px)] md:w-auto md:min-w-[160px]">
                <label for="event_type" class="sr-only">Event Type</label>
                <select id="event_type" name="event_type" onchange="document.getElementById('filterForm').submit()"
                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600 transition">
                    <option value="all" {{ ($eventTypeFilter ?? 'all') === 'all' ? 'selected' : '' }}>All Event Types</option>
                    @foreach($availableEventTypes as $type)
                        <option value="{{ $type }}" {{ ($eventTypeFilter ?? '') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Event Date Range Filter --}}
            <div class="w-full md:w-auto flex items-center gap-2">
                <label for="event_date_range" class="sr-only">Event Date</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-regular fa-calendar text-slate-400"></i>
                    </div>
                    <input type="text" id="event_date_range" placeholder="Select Date Range..."
                        class="block w-full md:w-64 pl-9 pr-8 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600 transition cursor-pointer">
                    
                    @if($eventDateFrom || $eventDateTo)
                        <button type="button" onclick="clearDateRange()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    @endif
                </div>
                <input type="hidden" id="event_date_from" name="event_date_from" value="{{ $eventDateFrom ?? '' }}">
                <input type="hidden" id="event_date_to" name="event_date_to" value="{{ $eventDateTo ?? '' }}">
            </div>

            {{-- Sort (Half width on mobile) --}}
            <div class="flex-1 md:flex-none md:w-auto md:min-w-[160px]">
                <label for="sort" class="sr-only">Sort by</label>
                <select id="sort" name="sort" onchange="document.getElementById('filterForm').submit()"
                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600 transition">
                    <option value="newest" {{ ($sort ?? 'newest') === 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="oldest" {{ ($sort ?? 'newest') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                    <option value="event_date_asc" {{ ($sort ?? 'newest') === 'event_date_asc' ? 'selected' : '' }}>Event Date (Nearest)</option>
                    <option value="event_date_desc" {{ ($sort ?? 'newest') === 'event_date_desc' ? 'selected' : '' }}>Event Date (Furthest)</option>
                </select>
            </div>

            {{-- Clear (Full width on mobile, auto on desktop) --}}
            <div class="w-full sm:w-auto mt-2 sm:mt-0 flex gap-2">
                <a href="{{ route('admin.bookings', ['stage' => $activeStage]) }}"
                    class="inline-flex w-full items-center justify-center px-4 py-2 border border-slate-200 text-sm font-medium rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <!-- Bookings Table: Lists all bookings for admin review -->
    <section class="rf-admin-card overflow-hidden" aria-labelledby="bookings-queue-heading">
        <div class="rf-admin-card__header">
            <div class="min-w-0">
                <h2 id="bookings-queue-heading" class="rf-admin-card__title">Bookings queue</h2>
                <p class="text-xs text-slate-500 mt-0.5">Review client requests, quotations, payment states, and operational follow-up from one queue.</p>
            </div>
            <div class="text-xs font-medium text-slate-500 whitespace-nowrap tabular-nums">
                Showing {{ $bookings->total() }} {{ \Illuminate\Support\Str::plural('booking', $bookings->total()) }}
            </div>
        </div>
        <div class="overflow-x-auto">
            <div class="w-full md:min-w-[1040px]"><table class="rf-table rf-table--stack !border-0 !rounded-none">
                <thead>
                    <tr>
                        <th scope="col" class="min-w-[150px]">CLIENT &amp; EVENT</th>
                        <th scope="col" class="min-w-[150px]">EVENT DETAILS</th>
                        <th scope="col" class="min-w-[140px]">FINANCIALS</th>
                        <th scope="col" class="min-w-[150px]">STATUS (WORKFLOW)</th>
                        <th scope="col" class="min-w-[110px]">NEXT ACTION</th>
                        <th scope="col" class="min-w-[140px]">ACTIONS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        @if($booking instanceof \App\Models\TemporaryGuestBooking)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-800 text-sm">{{ $booking->guest_name ?? 'Guest' }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">ID: #{{ $booking->id ?? 'Req' }} • {{ ucfirst($booking->event_type) }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-slate-700"><i class="fa-regular fa-calendar mr-1.5 text-slate-400"></i>{{ optional($booking->event_date)->format('M j, Y') ?? 'TBA' }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5 truncate max-w-[200px]" title="{{ $booking->venue }}"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>{{ $booking->venue ?? 'TBA' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-xs text-slate-500 italic">No quote yet</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-slate-50 text-slate-700 border border-slate-200 mb-1">
                                        <div class="w-4 h-4 rounded-full bg-slate-200 flex items-center justify-center text-[9px] font-bold">{{ \App\Services\BookingWorkflowService::stageNumber('awaiting_claim') }}</div>
                                        <span class="text-xs font-semibold">Awaiting Claim</span>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-500">{{ $booking->isExpired() ? 'Guest request expired unclaimed' : 'Guest Workflow · Request Submitted' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 font-semibold">
                                        <i class="fa-solid fa-hand-pointer"></i>
                                        <span>Guest Must Claim</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="px-3 py-1.5 bg-slate-50 border border-slate-200 text-slate-500 rounded-md text-xs text-center cursor-not-allowed">
                                        Client Must Claim First
                                    </div>
                                </td>
                            </tr>
                        @else
                            @php
                                $latestPayment = $booking->payments->sortByDesc('created_at')->first();
                                $quoteReviewLabel = in_array($booking->status, ['event_in_progress', 'completed'], true)
                                    ? 'View Quote Details'
                                    : 'Review / Edit Quote';
                                $totalObligation = (float) $booking->total_obligation;
                                $paidAmount = (float) $booking->total_paid;
                                $remaining = (float) $booking->remaining_balance;
                                $hasDamageCharges = $totalObligation > (float) ($booking->final_quoted_price ?? $booking->total_quoted ?? 0);
                            @endphp
                            <tr>
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-800 text-sm">{{ $booking->client?->full_name ?? $booking->guest_name ?? 'Guest' }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">ID: #{{ $booking->id ?? 'Req' }} • {{ ucfirst($booking->event_type) }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-sm text-slate-700"><i class="fa-regular fa-calendar mr-1.5 text-slate-400"></i>{{ optional($booking->event_date)->format('M j, Y') ?? 'TBA' }}</div>
                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-[200px]" title="{{ $booking->venue }}"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>{{ $booking->venue ?? 'TBA' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-xs space-y-1 w-36">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-500">{{ $hasDamageCharges ? 'Obligation:' : 'Quote:' }}</span>
                                        <span class="font-semibold text-slate-800">₱{{ number_format($totalObligation, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-500">Paid:</span>
                                        <span class="font-medium text-emerald-600">₱{{ number_format($paidAmount, 2) }}</span>
                                    </div>
                                    @if($remaining > 0)
                                    <div class="flex justify-between gap-2 border-t border-slate-100 pt-1 mt-1">
                                        <span class="text-slate-500">Bal:</span>
                                        <span class="font-bold text-rose-600">₱{{ number_format($remaining, 2) }}</span>
                                    </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $hasReturnAudit = $booking->returns->isNotEmpty();
                                    
                                    $stageNum = 2; $stageName = 'Review'; $stageColor = 'navy';
                                    if (in_array($booking->status, ['quotation_sent'])) { $stageNum = 3; $stageName = 'Quotation'; $stageColor = 'amber'; }
                                    elseif (in_array($booking->status, ['approved', 'admin_approved'])) { $stageNum = 4; $stageName = 'Approval'; $stageColor = 'amber'; }
                                    elseif (in_array($booking->status, ['payment_submitted', 'payment_pending'])) { $stageNum = 5; $stageName = 'Payment'; $stageColor = 'amber'; }
                                    elseif (in_array($booking->status, ['downpayment_received', 'fully_paid', 'confirmed', 'event_in_progress', 'event_completed', 'pending_return', 'pending_resolution', 'completed'])) { $stageNum = 6; $stageName = 'Confirmed'; $stageColor = 'emerald'; }
                                    elseif ($booking->status === 'cancelled') { $stageNum = 7; $stageName = 'Cancelled'; $stageColor = 'red'; }
                                    elseif ($booking->status === 'declined') { $stageNum = 0; $stageName = 'Declined'; $stageColor = 'slate'; }
                                    
                                    $detailLabel = match($booking->status) {
                                        'approved' => 'Awaiting Admin Approval',
                                        'admin_approved' => 'Admin Approved',
                                        'event_completed' => $hasReturnAudit ? 'Items Returned' : 'Event Completed',
                                        default => $booking->status_display_label,
                                    };

                                    $stageClasses = [
                                        2 => ['bg' => 'bg-navy-50', 'text' => 'text-navy-700', 'border' => 'border-navy-100', 'circleBg' => 'bg-navy-100'],
                                        3 => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-100', 'circleBg' => 'bg-amber-100'],
                                        4 => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-100', 'circleBg' => 'bg-amber-100'],
                                        5 => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-100', 'circleBg' => 'bg-amber-100'],
                                        6 => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-100', 'circleBg' => 'bg-emerald-100'],
                                        7 => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-100', 'circleBg' => 'bg-red-100'],
                                        0 => ['bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'circleBg' => 'bg-slate-200'],
                                    ];
                                    $sClass = $stageClasses[$stageNum] ?? $stageClasses[2];

                                    // README end-to-end workflow stage (Guest → Client → Staff) for active bookings.
                                    $rowWorkflow = app(\App\Services\BookingWorkflowService::class)->resolve($booking);
                                    if (empty($rowWorkflow['terminal']) && $rowWorkflow['current']) {
                                        $stageNum = $rowWorkflow['current_number'];
                                        $stageName = $rowWorkflow['current_label'];
                                        $sClass = match ($rowWorkflow['current_phase']) {
                                            'guest' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'circleBg' => 'bg-slate-200'],
                                            'staff' => $stageClasses[6],
                                            default => $stageClasses[$stageNum >= 8 ? 6 : ($stageNum >= 5 ? 3 : 2)],
                                        };
                                    }
                                @endphp
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md {{ $sClass['bg'] }} {{ $sClass['text'] }} {{ $sClass['border'] }} mb-1 border">
                                    @if($stageNum > 0)
                                    <div class="w-4 h-4 rounded-full {{ $sClass['circleBg'] }} flex items-center justify-center text-[9px] font-bold">{{$stageNum}}</div>
                                    @endif
                                    <span class="text-xs font-semibold leading-snug">{{$stageName}}</span>
                                </div>
                                <div class="text-[11px] font-medium text-slate-600">
                                    {{ $detailLabel }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $showMarkInProgress = in_array($booking->status, ['downpayment_received', 'confirmed'], true);
                                    $showMarkEventCompleted = $booking->status === 'event_in_progress';
                                    $hasCompletedReturn = $booking->returns->where('status', 'Completed')->isNotEmpty();
                                    $hasCancellationRecovery = ($booking->status === 'cancelled') && ($booking->returns->isNotEmpty() || $booking->hasDispatchedReusableMaterials());
                                    
                                    $nextActionText = '-';
                                    $nextActionClass = 'text-slate-400';
                                    $nextActionIcon = 'fa-minus';

                                    if ($booking->status === 'approved') {
                                        $nextActionText = 'Final Approve';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-check-double';
                                    } elseif (in_array($booking->status, ['payment_submitted', 'pending_resolution'], true) && $latestPayment && $latestPayment->status === 'pending') {
                                        $nextActionText = 'Verify Payment';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-receipt';
                                    } elseif ($showMarkInProgress) {
                                        $nextActionText = 'Mark In Progress';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-play';
                                    } elseif ($showMarkEventCompleted) {
                                        $nextActionText = 'Mark Completed';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-flag-checkered';
                                    } elseif ($booking->status === 'event_completed' || in_array($booking->status, ['pending_return', 'pending_resolution'], true)) {
                                        if (!$hasCompletedReturn) {
                                            $nextActionText = 'Manage Return';
                                            $nextActionClass = 'text-brand-700 font-semibold';
                                            $nextActionIcon = 'fa-box-open';
                                        } elseif ($remaining > 0) {
                                            $nextActionText = 'Log Final Payment';
                                            $nextActionClass = 'text-brand-700 font-semibold';
                                            $nextActionIcon = 'fa-money-bill';
                                        } else {
                                            $nextActionText = 'None';
                                        }
                                    } elseif ($hasCancellationRecovery && !$hasCompletedReturn) {
                                        $nextActionText = 'Manage Return';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-box-open';
                                    } elseif (in_array($booking->status, ['pending', 'change_requested'])) {
                                        $nextActionText = 'Review / Edit Quote';
                                        $nextActionClass = 'text-brand-700 font-semibold';
                                        $nextActionIcon = 'fa-file-invoice';
                                    } elseif ($booking->status === 'cancellation_requested') {
                                        $nextActionText = 'Review Cancel';
                                        $nextActionClass = 'text-red-600 font-semibold';
                                        $nextActionIcon = 'fa-ban';
                                    } elseif ($booking->status === 'quotation_sent') {
                                        $nextActionText = 'Wait for Client';
                                        $nextActionClass = 'text-slate-500 font-medium';
                                        $nextActionIcon = 'fa-hourglass-half';
                                    } elseif (in_array($booking->status, ['admin_approved', 'payment_pending'])) {
                                        $nextActionText = 'Wait for Payment';
                                        $nextActionClass = 'text-slate-500 font-medium';
                                        $nextActionIcon = 'fa-hourglass-half';
                                    } elseif ($booking->status === 'completed' || $booking->status === 'cancelled') {
                                        $nextActionText = 'None';
                                    }
                                @endphp
                                <div class="flex items-center gap-1.5 text-[11px] {{ $nextActionClass }}">
                                    <i class="fa-solid {{ $nextActionIcon }} w-3 text-center"></i>
                                    <span>{{ $nextActionText }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-col gap-3">
                                    <a href="{{ route('admin.bookings.edit', ['booking' => $booking->id]) }}" class="rf-btn rf-btn-outline w-full text-center text-sm">
                                        {{ $quoteReviewLabel }}
                                    </a>

                                    @if($booking->status === 'approved')
                                        <form method="POST" action="{{ route('admin.bookings.final-approve', ['booking' => $booking->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn-primary w-full text-sm">
                                                Final Approve &amp; Enable Payment
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($booking->status, ['payment_submitted', 'pending_resolution'], true) && $latestPayment && $latestPayment->status === 'pending')
                                        <button type="button" aria-label="Verify payment for booking {{ $booking->id }}" onclick="openBookingModal('booking-payment-modal-{{ $booking->id }}')" class="rf-btn rf-btn-outline w-full text-sm">
                                            Verify Payment
                                        </button>
                                    @endif

                                    @php
                                        $showMarkInProgress = in_array($booking->status, ['downpayment_received', 'confirmed'], true);
                                        $showMarkEventCompleted = $booking->status === 'event_in_progress';
                                        $showCompletedBadge = $booking->status === 'completed';
                                        $hasCompletedReturn = $booking->returns->where('status', 'Completed')->isNotEmpty();
                                        $hasCancellationRecovery = ($booking->status === 'cancelled') && ($booking->returns->isNotEmpty() || $booking->hasDispatchedReusableMaterials());
                                        $showEventCompletedBadge = ($booking->status === 'event_completed') && ($remaining > 0);
                                        $showEventCompletedFullyPaidBadge = ($booking->status === 'event_completed') && ($remaining <= 0);
                                    @endphp

                                    @if($showMarkInProgress)
                                        <form method="POST" action="{{ route('admin.bookings.update', ['booking' => $booking->id]) }}" class="mt-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="event_type" value="{{ $booking->event_type }}">
                                            <input type="hidden" name="event_date" value="{{ optional($booking->event_date)->format('Y-m-d') }}">
                                            <input type="hidden" name="venue" value="{{ $booking->venue }}">
                                            <input type="hidden" name="status" value="{{ $booking->status }}">
                                            <button type="submit" name="action" value="mark_event_in_progress" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm text-xs inline-block text-center cursor-pointer mt-2 w-full">
                                                Mark Event In Progress
                                            </button>
                                        </form>
                                    @endif

                                    @if($showMarkEventCompleted)
                                        <form method="POST" action="{{ route('admin.bookings.update', ['booking' => $booking->id]) }}" class="mt-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="event_type" value="{{ $booking->event_type }}">
                                            <input type="hidden" name="event_date" value="{{ optional($booking->event_date)->format('Y-m-d') }}">
                                            <input type="hidden" name="venue" value="{{ $booking->venue }}">
                                            <input type="hidden" name="status" value="{{ $booking->status }}">
                                            <button type="submit" name="action" value="mark_event_completed" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm text-xs inline-block text-center cursor-pointer mt-2 w-full">
                                                Mark Event Completed
                                            </button>
                                        </form>
                                    @endif

                                    @if($showEventCompletedBadge)
                                        <span class="inline-flex items-center justify-center rounded-full bg-amber-100 px-3 py-2 text-xs font-semibold uppercase text-amber-800">
                                            Event Completed (Balance Pending)
                                        </span>
                                    @endif

                                    @if($showEventCompletedFullyPaidBadge)
                                        <span class="inline-flex items-center justify-center rounded-full bg-emerald-100 px-3 py-2 text-xs font-semibold uppercase text-emerald-800">
                                            Completed &amp; Fully Paid
                                        </span>
                                    @endif

                                    @if($booking->status === 'event_completed')
                                        @if(!$hasCompletedReturn)
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 font-medium rounded-md border border-brand-200 hover:bg-brand-100 inline-block text-center text-xs">
                                                Manage Return Audit
                                            </a>
                                        @else
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-slate-50 text-slate-700 font-medium rounded-md border border-slate-200 hover:bg-slate-100 inline-block text-center text-xs">
                                                View Return Audit
                                            </a>
                                            <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">
                                                Return Audit Completed
                                            </span>
                                        @endif
                                    @endif

                                    @if($showCompletedBadge)
                                        <span class="inline-flex items-center justify-center rounded-full bg-emerald-100 px-3 py-2 text-xs font-semibold uppercase text-emerald-800">
                                            Completed &amp; Fully Paid
                                        </span>
                                        <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-slate-50 text-slate-700 font-medium rounded-md border border-slate-200 hover:bg-slate-100 inline-block text-center text-xs">
                                            View Return Audit
                                        </a>
                                    @endif

                                    @if(in_array($booking->status, ['pending_return', 'pending_resolution'], true))
                                        @if(!$hasCompletedReturn)
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 font-medium rounded-md border border-brand-200 hover:bg-brand-100 inline-block text-center text-xs">
                                                Manage Return Audit
                                            </a>
                                        @else
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-slate-50 text-slate-700 font-medium rounded-md border border-slate-200 hover:bg-slate-100 inline-block text-center text-xs">
                                                View Return Audit
                                            </a>
                                            <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">
                                                Return Audit Completed
                                            </span>
                                        @endif
                                    @endif

                                    @if($hasCancellationRecovery)
                                        @if(!$hasCompletedReturn)
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 font-medium rounded-md border border-brand-200 hover:bg-brand-100 inline-block text-center text-xs">
                                                Manage Return Audit
                                            </a>
                                        @else
                                            <a href="{{ route('admin.return-tracking.manage', ['booking' => $booking->id]) }}" class="px-3 py-1.5 bg-slate-50 text-slate-700 font-medium rounded-md border border-slate-200 hover:bg-slate-100 inline-block text-center text-xs">
                                                View Return Audit
                                            </a>
                                            <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">
                                                Return Audit Completed
                                            </span>
                                        @endif
                                    @endif

                                    @php
                                        $canLogFinalPayment = in_array($booking->status, ['event_in_progress', 'event_completed', 'pending_return', 'pending_resolution'], true) && ($remaining > 0);
                                    @endphp
                                    @if($canLogFinalPayment)
                                        <button type="button" onclick="openBookingModal('booking-final-payment-modal-{{ $booking->id }}')" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold rounded-lg shadow-sm text-xs inline-block text-center cursor-pointer mt-2 w-full">
                                            Log Final Payment
                                        </button>
                                        <div id="booking-final-payment-modal-{{ $booking->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4">
                                            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
                                                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-slate-900">Log Final Payment</h3>
                                                        <p class="text-sm text-slate-500">#{{ $booking->id }} • {{ ucfirst($booking->event_type) }}</p>
                                                    </div>
                                                    <button type="button" onclick="closeBookingModal('booking-final-payment-modal-{{ $booking->id }}')" class="rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </div>

                                                <div class="space-y-6 p-6">
                                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 grid grid-cols-1 gap-3">
                                                        <div class="flex justify-between"><span class="text-sm text-slate-600">Total Obligation</span><span class="font-semibold">₱{{ number_format($totalObligation, 2) }}</span></div>
                                                        <div class="flex justify-between"><span class="text-sm text-slate-600">Already Paid</span><span class="font-semibold">₱{{ number_format($paidAmount, 2) }}</span></div>
                                                        <div class="flex justify-between"><span class="text-sm text-slate-600">Remaining Balance</span><span class="font-semibold">₱{{ number_format($remaining, 2) }}</span></div>
                                                    </div>

                                                    <form method="POST" action="{{ route('admin.bookings.final_payment', ['booking' => $booking->id]) }}" class="space-y-4">
                                                        @csrf
                                                        <div>
                                                            <label class="block text-sm font-semibold text-slate-700">Final Payment Amount Received</label>
                                                            <input type="number" step="0.01" name="amount_received" value="{{ number_format($remaining, 2, '.', '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                        </div>

                                                        <div>
                                                            <label class="block text-sm font-semibold text-slate-700">Payment Method</label>
                                                            <select name="payment_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                                <option value="gcash">GCash</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                                <option value="cash">Cash</option>
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <button type="submit" class="w-full rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 transition">
                                                                Confirm Final Payment
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>

                                                <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                                                    <button type="button" onclick="closeBookingModal('booking-final-payment-modal-{{ $booking->id }}')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if(in_array($booking->status, ['payment_submitted', 'pending_resolution'], true) && $latestPayment && $latestPayment->status === 'pending')
                            <tr>
                                <td colspan="6" class="p-0">
                            <div id="booking-payment-modal-{{ $booking->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4">
                                <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-900">Payment Verification</h3>
                                            <p class="text-sm text-slate-500">#{{ $booking->id }} • {{ ucfirst($booking->event_type) }}</p>
                                        </div>
                                        <button type="button" onclick="closeBookingModal('booking-payment-modal-{{ $booking->id }}')" class="rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>

                                    <div class="space-y-6 p-6">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Client Name</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $booking->client?->full_name ?? $booking->guest_name ?? ($booking->user?->name ?? 'Guest') }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Client Mobile</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $booking->client?->phone ?? $booking->guest_phone ?? 'Not provided' }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Client Email</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $booking->client?->email ?? $booking->guest_email ?? 'Not provided' }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Event Type</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ ucfirst($booking->event_type) }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Date &amp; Time</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ optional($booking->event_date)->format('F j, Y') }} • {{ $booking->event_time ?? 'Time not set' }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Venue</p>
                                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $booking->venue }}</p>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-brand-200 bg-brand-50 p-4">
                                            <div class="flex items-center justify-between gap-4">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-700">{{ $hasDamageCharges ? 'Total Obligation' : 'Total Quote' }}</p>
                                                    <p class="mt-2 text-2xl font-bold text-brand-700">₱{{ number_format($totalObligation, 2) }}</p>
                                                </div>
                                                <div class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-brand-700 shadow-sm">
                                                    {{ str_replace('_', ' ', ucfirst($booking->status)) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-circle-info text-amber-600"></i>
                                                <h4 class="text-sm font-semibold text-amber-900">Payment Details</h4>
                                            </div>
                                            <div class="mt-4 space-y-2 text-sm text-amber-900">
                                                <p><span class="font-semibold">Method:</span> {{ strtoupper(str_replace('_', ' ', $latestPayment->payment_method ?? $latestPayment->payment_type ?? 'N/A')) }}</p>
                                                <p><span class="font-semibold">Reference:</span> {{ $latestPayment->reference_number }}</p>
                                                @if(!empty($latestPayment->receipt_image))
                                                    <a href="{{ asset('storage/' . $latestPayment->receipt_image) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-semibold text-brand-700 hover:text-brand-900">
                                                        <i class="fa-solid fa-image mr-1"></i>View Receipt
                                                    </a>
                                                @endif
                                            </div>
                                            <form method="POST" action="{{ route('admin.payments.verify', ['payment' => $latestPayment->id]) }}" class="mt-4">
                                                @csrf
                                                <div class="grid grid-cols-1 gap-2">
                                                    <div>
                                                        <label class="block text-sm font-semibold text-slate-700">Amount Received (PHP)</label>
                                                        @php
                                                            $defaultAmount = (float) $latestPayment->amount;
                                                        @endphp
                                                        <input type="number" step="0.01" name="amount_received" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="{{ number_format($defaultAmount, 2, '.', '') }}">
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="flex-1 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 transition">
                                                            Verify &amp; Lock Booking
                                                        </button>
                                                        <button type="button" onclick="document.getElementById('reject-payment-form-{{ $latestPayment->id }}').submit();" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                                                            Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            <form id="reject-payment-form-{{ $latestPayment->id }}" method="POST" action="{{ route('admin.payments.reject', ['payment' => $latestPayment->id]) }}" class="hidden">
                                                @csrf
                                            </form>
                                        </div>
                                    </div>

                                    <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                                        <button type="button" onclick="closeBookingModal('booking-payment-modal-{{ $booking->id }}')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                                </td>
                            </tr>
                        @endif
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                @if($statusFilter === 'approved')
                                    No bookings are currently awaiting Admin final approval.
                                @elseif($statusFilter !== 'all' || !empty($searchTerm))
                                    No bookings found matching your search and filter criteria.
                                    <div class="mt-2">
                                        <a href="{{ route('admin.bookings') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800 underline">Clear filters</a>
                                    </div>
                                @else
                                    No bookings available.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>

        @if($bookings->hasPages())
            <div class="p-5 border-t border-slate-200">
                {{ $bookings->links() }}
            </div>
        @endif
    </section>

    <script>
        function openBookingModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeBookingModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            let debounceTimer;
            const searchInput = document.getElementById('bookingSearchInput');
            const filterForm = document.getElementById('filterForm');

            if (searchInput && filterForm) {
                // Focus at end of input to maintain typing flow after reload
                if (searchInput.value) {
                    searchInput.focus();
                    const len = searchInput.value.length;
                    searchInput.setSelectionRange(len, len);
                }

                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        filterForm.submit();
                    }, 600);
                });
                
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(debounceTimer);
                        filterForm.submit();
                    }
                });
            }
        });

        function clearDateRange() {
            document.getElementById('event_date_from').value = '';
            document.getElementById('event_date_to').value = '';
            document.getElementById('filterForm').submit();
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateFromInput = document.getElementById('event_date_from');
            const dateToInput = document.getElementById('event_date_to');
            const rangeInput = document.getElementById('event_date_range');
            
            let initialDates = [];
            if (dateFromInput && dateFromInput.value) initialDates.push(dateFromInput.value);
            if (dateToInput && dateToInput.value) initialDates.push(dateToInput.value);

            if (rangeInput) {
                flatpickr(rangeInput, {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    defaultDate: initialDates,
                    showMonths: window.innerWidth > 768 ? 2 : 1,
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            if (dateFromInput) dateFromInput.value = instance.formatDate(selectedDates[0], "Y-m-d");
                            if (dateToInput) dateToInput.value = instance.formatDate(selectedDates[1], "Y-m-d");
                            document.getElementById('filterForm').submit();
                        } else if (selectedDates.length === 0) {
                            if (dateFromInput) dateFromInput.value = "";
                            if (dateToInput) dateToInput.value = "";
                            document.getElementById('filterForm').submit();
                        }
                    }
                });
            }
        });
    </script>
</x-admin-layout>
