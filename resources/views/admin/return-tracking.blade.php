<x-admin-layout
    title="Return Tracking"
    description="Track post-event asset returns, condition assessments, staff responsibility, and required damage/loss decisions."
>
    <div class="space-y-6">

        @if(session('success'))
            <x-alert type="success" :inline="true">{{ session('success') }}</x-alert>
        @endif
        @if(session('info'))
            <x-alert type="info" :inline="true">{{ session('info') }}</x-alert>
        @endif
        @if($errors->any())
            <x-alert type="danger" :inline="true">
                <div class="font-semibold mb-1">Please fix the following errors:</div>
                <ul class="list-disc pl-5 text-xs space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <!-- Operational Summary Metric Cards -->
        <div class="rf-stat-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Card 1: Total Returns -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Returns</p>
                    <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $totalReturnsCount }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">All event returns</p>
                </div>
            </div>

            <!-- Card 2: Pending Inspection -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pending Inspection</p>
                    <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $pendingInspectionCount }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Awaiting inspection</p>
                </div>
            </div>

            <!-- Card 3: Needs Approval -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Needs Approval</p>
                    <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $forApprovalCount }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Damage/loss decision pending</p>
                </div>
            </div>

            <!-- Card 4: Completed -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completed</p>
                    <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $completedReturnsCount }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Returns fully reconciled</p>
                </div>
            </div>
        </div>

        <!-- Search & Filter Toolbar -->
        <div class="bg-white rounded-2xl shadow-xs border border-gray-100 p-4 sm:p-5">
            <form method="GET" action="{{ route('admin.return-tracking') }}" id="returnFilterForm" class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <!-- Compact Search Area -->
                    <div class="flex flex-wrap items-center gap-2.5 flex-1">
                        <!-- Search Box -->
                        <div class="relative flex-1 min-w-[260px] max-w-lg">
                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-search text-xs"></i>
                            </span>
                            <input
                                type="text"
                                name="search"
                                value="{{ $currentSearch ?? '' }}"
                                placeholder="Search return ID, booking, client, or event..."
                                class="w-full pl-9 pr-3.5 py-2.5 bg-gray-50/70 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs"
                            >
                        </div>

                        <!-- Show Filters Toggle Button -->
                        <button
                            type="button"
                            id="toggleFiltersBtn"
                            onclick="toggleFilterPanel()"
                            aria-expanded="{{ !empty($hasActiveFilters) ? 'true' : 'false' }}"
                            aria-controls="returnFilterPanel"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2.5 border rounded-xl text-sm font-medium transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer {{ !empty($hasActiveFilters) ? 'border-brand-300 bg-brand-50 text-brand-700 font-semibold' : 'border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800' }}"
                        >
                            <i class="fa-solid fa-sliders text-xs {{ !empty($hasActiveFilters) ? 'text-brand-700' : 'text-gray-500' }}"></i>
                            <span id="toggleFiltersText">{{ !empty($hasActiveFilters) ? 'Hide Filters' : 'Show Filters' }}</span>
                            @if(!empty($hasActiveFilters))
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-700 inline-block" title="Filters are active"></span>
                            @endif
                            <i id="filtersChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200 {{ !empty($hasActiveFilters) ? 'rotate-180' : '' }}"></i>
                        </button>

                        <!-- Search Submit Button -->
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                        >
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            <span>Search</span>
                        </button>

                        @if(!empty($currentSearch) || !empty($hasActiveFilters))
                            <a
                                href="{{ route('admin.return-tracking') }}"
                                class="px-3 py-2.5 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Secondary Filters Collapsible Panel -->
                <div id="returnFilterPanel" style="{{ !empty($hasActiveFilters) ? 'display: block;' : 'display: none;' }}" class="pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                        <!-- Status Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Status</label>
                            <div class="relative">
                                <select name="status" onchange="this.form.submit()" class="w-full py-2 pl-2.5 pr-7 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="all" {{ ($currentStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                                    <option value="Pending" {{ ($currentStatus ?? '') === 'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Partially Returned" {{ ($currentStatus ?? '') === 'Partially Returned' ? 'selected' : '' }}>Partially Returned</option>
                                    <option value="Completed" {{ ($currentStatus ?? '') === 'Completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                                <span class="absolute inset-y-0 right-2 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Staff Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Assigned Staff</label>
                            <div class="relative">
                                <select name="staff" onchange="this.form.submit()" class="w-full py-2 pl-2.5 pr-7 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="all" {{ ($currentStaff ?? 'all') === 'all' ? 'selected' : '' }}>All Staff</option>
                                    <option value="unassigned" {{ ($currentStaff ?? '') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                                    @foreach($eligibleStaff as $staff)
                                        <option value="{{ $staff->id }}" {{ ($currentStaff ?? '') == $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Inspector Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Inspector</label>
                            <div class="relative">
                                <select name="inspector" onchange="this.form.submit()" class="w-full py-2 pl-2.5 pr-7 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="all" {{ ($currentInspector ?? 'all') === 'all' ? 'selected' : '' }}>All Inspectors</option>
                                    <option value="unassigned" {{ ($currentInspector ?? '') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                                    @foreach($eligibleInspectors as $insp)
                                        <option value="{{ $insp->id }}" {{ ($currentInspector ?? '') == $insp->id ? 'selected' : '' }}>{{ $insp->name }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Approval Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Approval</label>
                            <div class="relative">
                                <select name="approval" onchange="this.form.submit()" class="w-full py-2 pl-2.5 pr-7 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="all" {{ ($currentApproval ?? 'all') === 'all' ? 'selected' : '' }}>All Approvals</option>
                                    <option value="not_required" {{ ($currentApproval ?? '') === 'not_required' ? 'selected' : '' }}>Not Required</option>
                                    <option value="pending" {{ ($currentApproval ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ ($currentApproval ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ ($currentApproval ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                                <span class="absolute inset-y-0 right-2 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Event Date Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Event Date</label>
                            <div class="relative">
                                <input
                                    type="date"
                                    name="event_date"
                                    value="{{ $currentEventDate ?? '' }}"
                                    onchange="this.form.submit()"
                                    class="w-full py-1.5 px-2.5 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs transition"
                                >
                            </div>
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Sort</label>
                            <div class="relative">
                                <select name="sort" onchange="this.form.submit()" class="w-full py-2 pl-2.5 pr-7 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="default" {{ ($currentSort ?? 'default') === 'default' ? 'selected' : '' }}>Status Priority</option>
                                    <option value="latest" {{ ($currentSort ?? '') === 'latest' ? 'selected' : '' }}>Latest Return</option>
                                    <option value="oldest" {{ ($currentSort ?? '') === 'oldest' ? 'selected' : '' }}>Oldest Return</option>
                                    <option value="event_date_desc" {{ ($currentSort ?? '') === 'event_date_desc' ? 'selected' : '' }}>Event Date (Newest)</option>
                                    <option value="event_date_asc" {{ ($currentSort ?? '') === 'event_date_asc' ? 'selected' : '' }}>Event Date (Oldest)</option>
                                    <option value="booking_id" {{ ($currentSort ?? '') === 'booking_id' ? 'selected' : '' }}>Booking #</option>
                                </select>
                                <span class="absolute inset-y-0 right-2 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    @if(!empty($hasActiveFilters))
                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>Active filters applied</span>
                            <a href="{{ route('admin.return-tracking') }}" class="font-semibold text-brand-700 hover:text-brand-800">
                                Reset All Filters
                            </a>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- Returns Operational Data Table -->
        <section class="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden" aria-labelledby="returns-table-heading">
            <h2 id="returns-table-heading" class="sr-only">Return tracking records</h2>
            <div class="overflow-x-auto w-full">
                <table class="rf-table--stack w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200">
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Return</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Client / Booking</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Event</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Staff</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Inspector</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Approval</th>
                            <th scope="col" class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm">
                        @forelse($returns as $return)
                            @php
                                $staffUser = $return->assignedStaff;
                                $inspectorUser = $return->inspector;

                                $staffInitials = $staffUser ? collect(explode(' ', $staffUser->name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') : 'UN';
                                $inspectorInitials = $inspectorUser ? collect(explode(' ', $inspectorUser->name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') : 'UN';
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <!-- RETURN -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $return->reference }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ optional($return->return_date ?? $return->created_at)->format('M d, Y · g:i A') }}
                                    </div>
                                </td>

                                <!-- CLIENT / BOOKING -->
                                <td data-label="Client / Booking" class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 truncate max-w-[170px]" title="{{ $return->booking?->client?->full_name ?? $return->booking?->guest_name ?? 'N/A' }}">
                                        {{ $return->booking?->client?->full_name ?? $return->booking?->guest_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-[11px] text-brand-700 font-semibold mt-0.5">
                                        Booking #{{ $return->booking_id }}
                                    </div>
                                </td>

                                <!-- EVENT -->
                                <td data-label="Event" class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-medium text-slate-800">
                                        {{ ucfirst($return->booking?->event_type ?? 'Event') }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ optional($return->booking?->event_date)->format('M d, Y') ?? '—' }}
                                    </div>
                                </td>

                                <!-- STATUS -->
                                <td data-label="Status" class="px-4 py-3.5 whitespace-nowrap">
                                    @if($return->status == 'Completed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-regular fa-circle-check text-[10px]"></i>
                                            Completed
                                        </span>
                                    @elseif($return->status == 'Partially Returned')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            <i class="fa-solid fa-clipboard-check text-[10px]"></i>
                                            Partially Returned
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fa-regular fa-clock text-[10px]"></i>
                                            Pending
                                        </span>
                                    @endif
                                </td>

                                <!-- STAFF -->
                                <td data-label="Staff" class="px-4 py-3.5 whitespace-nowrap">
                                    @if($staffUser)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                                {{ $staffInitials }}
                                            </div>
                                            <span class="font-medium text-slate-800 text-xs truncate max-w-[110px]" title="{{ $staffUser->name }}">
                                                {{ $staffUser->name }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Unassigned</span>
                                    @endif
                                </td>

                                <!-- INSPECTOR -->
                                <td data-label="Inspector" class="px-4 py-3.5 whitespace-nowrap">
                                    @if($inspectorUser)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                                {{ $inspectorInitials }}
                                            </div>
                                            <span class="font-medium text-slate-800 text-xs truncate max-w-[110px]" title="{{ $inspectorUser->name }}">
                                                {{ $inspectorUser->name }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Unassigned</span>
                                    @endif
                                </td>

                                <!-- APPROVAL -->
                                <td data-label="Approval" class="px-4 py-3.5 whitespace-nowrap">
                                    @if($return->approval_status === 'approved')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            Approved
                                        </span>
                                    @elseif($return->approval_status === 'rejected')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <i class="fa-solid fa-circle-xmark text-[10px]"></i>
                                            Rejected
                                        </span>
                                    @elseif($return->approval_status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fa-regular fa-clock text-[10px]"></i>
                                            Pending
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 font-medium">Not Required</span>
                                    @endif
                                </td>

                                <!-- ACTION -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                    <button
                                        type="button"
                                        onclick="openReturnDrawer({{ $return->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 hover:text-brand-800 border border-brand-200 text-xs font-bold rounded-xl transition shadow-2xs cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500"
                                        aria-label="View return details for {{ $return->reference }}"
                                    >
                                        <i class="fa-solid fa-eye text-brand-700 text-xs"></i>
                                        <span>View</span>
                                        <span class="sr-only">Review Return</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Hidden Drawer Template for this Return -->
                            <template id="return-drawer-template-{{ $return->id }}">
                                <div class="flex flex-col h-full bg-slate-50">
                                    <!-- DRAWER HEADER -->
                                    <div class="bg-white border-b border-slate-200 p-5 shrink-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-start gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 border border-brand-100 flex items-center justify-center text-lg shrink-0 mt-0.5">
                                                    <i class="fa-solid fa-boxes-stacked"></i>
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="text-lg font-black text-slate-900 tracking-tight">{{ $return->reference }}</h3>
                                                        @if($return->status == 'Completed')
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                Completed
                                                            </span>
                                                        @elseif($return->status == 'Partially Returned')
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                                Partially Returned
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                                Pending
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-slate-500 mt-1">
                                                        Booking #{{ $return->booking_id }} • {{ ucfirst($return->booking?->event_type ?? 'Event') }}
                                                        <span class="text-slate-300 mx-1">•</span>
                                                        {{ optional($return->return_date ?? $return->created_at)->format('M d, Y \a\t g:i A') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onclick="closeReturnDrawer()"
                                                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                                aria-label="Close detail drawer"
                                            >
                                                <i class="fa-solid fa-xmark text-sm"></i>
                                            </button>
                                        </div>

                                        <!-- Drawer Tabs Navigation -->
                                        <div class="flex items-center gap-1 mt-5 border-b border-slate-100 -mb-5 pb-0 text-xs font-bold overflow-x-auto">
                                            <button
                                                type="button"
                                                onclick="switchReturnTab('{{ $return->id }}', 'overview')"
                                                id="tab-btn-{{ $return->id }}-overview"
                                                class="tab-btn px-3.5 py-2.5 border-b-2 border-brand-700 text-brand-700 transition cursor-pointer"
                                            >
                                                Overview
                                            </button>
                                            <button
                                                type="button"
                                                onclick="switchReturnTab('{{ $return->id }}', 'items')"
                                                id="tab-btn-{{ $return->id }}-items"
                                                class="tab-btn px-3.5 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition cursor-pointer"
                                            >
                                                Items ({{ $return->returnItems->count() }})
                                            </button>
                                            <button
                                                type="button"
                                                onclick="switchReturnTab('{{ $return->id }}', 'accountability')"
                                                id="tab-btn-{{ $return->id }}-accountability"
                                                class="tab-btn px-3.5 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition cursor-pointer"
                                            >
                                                Staff & Accountability
                                            </button>
                                            <button
                                                type="button"
                                                onclick="switchReturnTab('{{ $return->id }}', 'history')"
                                                id="tab-btn-{{ $return->id }}-history"
                                                class="tab-btn px-3.5 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition cursor-pointer"
                                            >
                                                History ({{ $return->auditLogs ? $return->auditLogs->count() : 0 }})
                                            </button>
                                        </div>
                                    </div>

                                    <!-- DRAWER BODY (Scrollable) -->
                                    <div class="flex-1 overflow-y-auto p-5 space-y-5">
                                        <!-- TAB 1: OVERVIEW -->
                                        <div id="tab-content-{{ $return->id }}-overview" class="tab-pane space-y-4">
                                            <!-- Booking & Client Card -->
                                            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Booking & Client</span>
                                                    <a href="{{ route('admin.bookings') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800 flex items-center gap-1">
                                                        <span>Booking #{{ $return->booking_id }}</span>
                                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                                    </a>
                                                </div>
                                                <div class="mt-3 space-y-2.5 text-xs">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Client:</span>
                                                        <span class="font-bold text-slate-900">{{ $return->booking?->client?->full_name ?? $return->booking?->guest_name ?? 'N/A' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Email:</span>
                                                        <span class="text-slate-700 font-medium">{{ $return->booking?->client?->email ?? $return->booking?->guest_email ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Contact:</span>
                                                        <span class="text-slate-700 font-medium">{{ $return->booking?->client?->phone ?? $return->booking?->guest_phone ?? $return->booking?->client?->mobile_number ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Event Date:</span>
                                                        <span class="text-slate-700 font-medium">{{ optional($return->booking?->event_date)->format('M d, Y') ?? '—' }}</span>
                                                    </div>
                                                    @if($return->booking?->venue_address)
                                                        <div class="flex items-start justify-between">
                                                            <span class="text-slate-500">Venue:</span>
                                                            <span class="text-slate-700 font-medium text-right max-w-[200px]">{{ $return->booking->venue_address }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Return Information Card -->
                                            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
                                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Return Information</span>
                                                    <span class="text-xs font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded">{{ $return->reference }}</span>
                                                </div>
                                                <div class="mt-3 space-y-2.5 text-xs">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Return Date:</span>
                                                        <span class="text-slate-800 font-medium">{{ optional($return->return_date ?? $return->created_at)->format('M d, Y g:i A') }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Current Status:</span>
                                                        <span class="font-bold text-slate-900">{{ $return->status }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Decision Status:</span>
                                                        <span class="font-bold capitalize {{ $return->approval_status === 'approved' ? 'text-emerald-600' : ($return->approval_status === 'pending' ? 'text-amber-600' : 'text-slate-700') }}">
                                                            {{ $return->approval_status_display_label }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-slate-500">Damage/Loss Charge:</span>
                                                        <span class="font-bold text-rose-600">₱{{ number_format($return->total_damage_charge, 2) }}</span>
                                                    </div>
                                                    @if($return->notes)
                                                        <div class="pt-2 border-t border-slate-100">
                                                            <span class="text-slate-500 block mb-1">Operational Notes:</span>
                                                            <p class="text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200/60">{{ $return->notes }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 2: ITEMS -->
                                        <div id="tab-content-{{ $return->id }}-items" class="tab-pane hidden space-y-3">
                                            <div class="flex items-center justify-between pb-1">
                                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Non-Perishable Materials ({{ $return->returnItems->count() }})</h4>
                                                <a href="{{ route('admin.return-tracking.manage', $return->booking_id) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">
                                                    Audit Workspace <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                                </a>
                                            </div>

                                            @forelse($return->returnItems as $item)
                                                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-2">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div>
                                                            <p class="text-sm font-bold text-slate-900">{{ $item->inventoryItem?->name ?? 'Inventory Item' }}</p>
                                                            <p class="text-xs text-slate-400 capitalize">{{ $item->inventoryItem?->category ?? 'Hardware' }} • Unit: {{ $item->inventoryItem?->unit ?? 'piece' }}</p>
                                                        </div>
                                                        @if($item->condition === 'good')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                Good condition
                                                            </span>
                                                        @elseif($item->condition === 'damaged')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                                Damaged
                                                            </span>
                                                        @elseif($item->condition === 'lost')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                                                Lost
                                                            </span>
                                                        @elseif($item->condition === 'mixed')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-brand-50 text-brand-700 border border-brand-200">
                                                                Mixed condition
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                                Pending Review
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="grid grid-cols-4 gap-2 text-center text-xs pt-2 border-t border-slate-100">
                                                        <div class="bg-slate-50 p-2 rounded-xl">
                                                            <span class="text-[10px] text-slate-400 block uppercase">Dispatched</span>
                                                            <span class="font-bold text-slate-800">{{ (float) $item->accounted_quantity }}</span>
                                                        </div>
                                                        <div class="bg-emerald-50/50 p-2 rounded-xl">
                                                            <span class="text-[10px] text-emerald-600 block uppercase">Good</span>
                                                            <span class="font-bold text-emerald-700">{{ (float) $item->quantity_good }}</span>
                                                        </div>
                                                        <div class="bg-amber-50/50 p-2 rounded-xl">
                                                            <span class="text-[10px] text-amber-600 block uppercase">Damaged</span>
                                                            <span class="font-bold text-amber-700">{{ (float) $item->quantity_damaged }}</span>
                                                        </div>
                                                        <div class="bg-rose-50/50 p-2 rounded-xl">
                                                            <span class="text-[10px] text-rose-600 block uppercase">Lost</span>
                                                            <span class="font-bold text-rose-700">{{ (float) $item->quantity_lost }}</span>
                                                        </div>
                                                    </div>

                                                    @if((float) $item->damage_charge > 0)
                                                        <div class="flex items-center justify-between text-xs pt-1">
                                                            <span class="text-slate-500">Damage Charge:</span>
                                                            <span class="font-bold text-rose-600">₱{{ number_format($item->damage_charge, 2) }}</span>
                                                        </div>
                                                    @endif

                                                    @if($item->notes)
                                                        <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-200/50 mt-1">
                                                            <span class="font-semibold text-slate-500">Note:</span> {{ $item->notes }}
                                                        </p>
                                                    @endif
                                                </div>
                                            @empty
                                                <div class="p-4 bg-white rounded-2xl border border-slate-200 text-center text-slate-400 text-xs">
                                                    No return items listed.
                                                </div>
                                            @endforelse

                                            <!-- Inventory Reconciliation Guide -->
                                            <div class="bg-slate-100/70 rounded-2xl p-4 border border-slate-200/80 text-xs space-y-2 mt-4">
                                                <p class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Inventory Reconciliation Guide</p>
                                                <div class="space-y-1.5 text-slate-600 text-xs">
                                                    <div><strong class="text-emerald-700 font-bold">GOOD:</strong> Restores eligible physical stock to active inventory.</div>
                                                    <div><strong class="text-amber-700 font-bold">DAMAGED:</strong> Does not restore active stock. Recorded for damage review.</div>
                                                    <div><strong class="text-rose-700 font-bold">LOST:</strong> Does not restore stock. Requires loss decision.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 3: STAFF & ACCOUNTABILITY -->
                                        <div id="tab-content-{{ $return->id }}-accountability" class="tab-pane hidden space-y-4">
                                            <!-- Current Accountability Responsibilities -->
                                            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3">
                                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider pb-2 border-b border-slate-100">Operational Responsibility</h4>

                                                <!-- Assigned Staff -->
                                                <div class="flex items-center justify-between py-1.5">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                            {{ $staffInitials }}
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-bold text-slate-900 block">{{ $staffUser?->name ?? 'Unassigned' }}</span>
                                                            <span class="text-[11px] text-slate-400">Physical Return Logging</span>
                                                        </div>
                                                    </div>
                                                    @if($staffUser?->mobile_number)
                                                        <span class="text-xs text-slate-500">{{ $staffUser->mobile_number }}</span>
                                                    @endif
                                                </div>

                                                <!-- Inspector -->
                                                <div class="flex items-center justify-between py-1.5 border-t border-slate-100">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                            {{ $inspectorInitials }}
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-bold text-slate-900 block">{{ $inspectorUser?->name ?? 'Unassigned' }}</span>
                                                            <span class="text-[11px] text-slate-400">Condition Assessment</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Approver -->
                                                <div class="flex items-center justify-between py-1.5 border-t border-slate-100">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                            <i class="fa-solid fa-shield-halved text-emerald-600 text-xs"></i>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-bold text-slate-900 block">{{ $return->approver?->name ?? 'Administrator' }}</span>
                                                            <span class="text-[11px] text-slate-400">Damage/Loss Authorization</span>
                                                        </div>
                                                    </div>
                                                    <span class="text-xs font-bold capitalize {{ $return->approval_status === 'approved' ? 'text-emerald-600' : ($return->approval_status === 'pending' ? 'text-amber-600' : 'text-slate-400') }}">
                                                        {{ $return->approval_status_display_label }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Assignment Update Controls (Admin only) -->
                                            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Update Assignments</h4>
                                                    <span class="text-[10px] font-semibold text-brand-700 bg-brand-50 px-2 py-0.5 rounded">Admin Controls</span>
                                                </div>

                                                <form method="POST" action="{{ route('admin.return-tracking.assign', $return) }}" class="space-y-3">
                                                    @csrf
                                                    @method('PUT')

                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Assigned Staff</label>
                                                        <select name="assigned_staff_id" class="w-full text-xs py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-500">
                                                            <option value="">— Unassigned —</option>
                                                            @foreach($eligibleStaff as $stf)
                                                                <option value="{{ $stf->id }}" {{ (int) $return->assigned_staff_id === (int) $stf->id ? 'selected' : '' }}>
                                                                    {{ $stf->name }} ({{ ucfirst($stf->role) }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Assigned Inspector</label>
                                                        <select name="inspector_id" class="w-full text-xs py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-500">
                                                            <option value="">— Unassigned —</option>
                                                            @foreach($eligibleInspectors as $insp)
                                                                <option value="{{ $insp->id }}" {{ (int) $return->inspected_by === (int) $insp->id ? 'selected' : '' }}>
                                                                    {{ $insp->name }} ({{ ucfirst($insp->role) }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        class="w-full py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition shadow-2xs"
                                                    >
                                                        Save Assignments
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Damage / Loss Decision Section -->
                                            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Damage / Loss Decision</h4>
                                                    <span class="text-xs font-bold text-rose-600">Charge: ₱{{ number_format($return->total_damage_charge, 2) }}</span>
                                                </div>

                                                @if($return->approval_status === 'approved')
                                                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800">
                                                        <i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i>
                                                        Approved by <strong>{{ $return->approver?->name ?? 'Administrator' }}</strong> on {{ optional($return->approved_at)->format('M d, Y \a\t g:i A') }}
                                                    </div>
                                                @elseif($return->approval_status === 'rejected')
                                                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800">
                                                        <i class="fa-solid fa-circle-xmark text-rose-600 mr-1.5"></i>
                                                        Damage/loss decision was rejected by <strong>{{ $return->approver?->name ?? 'Administrator' }}</strong>.
                                                    </div>
                                                @elseif($return->approval_status === 'pending')
                                                    <div class="p-2.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 mb-2">
                                                        <i class="fa-regular fa-clock text-amber-600 mr-1"></i>
                                                        <strong>Pending approval:</strong> Items with damage or loss require administrator authorization.
                                                    </div>

                                                    <form method="POST" action="{{ route('admin.return-tracking.approve', $return) }}" class="space-y-3">
                                                        @csrf
                                                        @method('PUT')

                                                        <div>
                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Decision</label>
                                                            <select name="decision" class="w-full text-xs py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-500">
                                                                <option value="approved">Approve Damage/Loss Decision & Charge</option>
                                                                <option value="rejected">Reject Decision (Request Re-inspection)</option>
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Authorization Notes</label>
                                                            <input
                                                                type="text"
                                                                name="reason"
                                                                placeholder="Optional authorization notes..."
                                                                class="w-full text-xs py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-500"
                                                            >
                                                        </div>

                                                        <button
                                                            type="submit"
                                                            class="w-full py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition shadow-2xs"
                                                        >
                                                            Submit Authorization Decision
                                                        </button>
                                                    </form>
                                                @else
                                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600">
                                                        <i class="fa-solid fa-check text-slate-400 mr-1.5"></i>
                                                        Approval not required (All items returned in good condition or no damages reported).
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- TAB 4: HISTORY -->
                                        <div id="tab-content-{{ $return->id }}-history" class="tab-pane hidden space-y-3">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider pb-1">Return Action History</h4>

                                            <div class="space-y-3">
                                                @forelse($return->auditLogs as $log)
                                                    @php
                                                        $logUser = $log->user;
                                                        $logInitials = $logUser ? collect(explode(' ', $logUser->name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') : 'SY';
                                                        
                                                        // Format friendly action title using approved terminology
                                                        $actionTitle = match($log->action) {
                                                            'return_staff_assigned' => 'Staff assigned',
                                                            'return_inspector_assigned' => 'Inspector assigned',
                                                            'return_quantities_recorded' => 'Return quantities recorded',
                                                            'return_inspection_submitted' => 'Inspection submitted',
                                                            'return_damage_assessed' => 'Damage/loss assessed',
                                                            'return_damage_approved' => 'Damage/loss approved',
                                                            'return_damage_rejected' => 'Damage/loss rejected',
                                                            'return_reconciled' => 'Return reconciled',
                                                            'cancelled_booking_return_reconciled' => 'Cancelled return reconciled',
                                                            'status_changed' => 'Booking status changed',
                                                            default => ucwords(str_replace('_', ' ', $log->action))
                                                        };
                                                    @endphp
                                                    <div class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-2xs flex items-start gap-3">
                                                        <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 border border-brand-100 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                                                            {{ $logInitials }}
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-baseline justify-between gap-2">
                                                                <span class="text-xs font-bold text-slate-900">{{ $logUser?->name ?? 'System' }}</span>
                                                                <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ $log->created_at->format('d M Y · g:i A') }}</span>
                                                            </div>
                                                            <p class="text-xs font-semibold text-brand-700 mt-0.5">{{ $actionTitle }}</p>
                                                            <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $log->details }}</p>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="p-6 bg-white rounded-2xl border border-slate-200 text-center text-slate-400 text-xs">
                                                        <i class="fa-solid fa-clock-rotate-left text-xl mb-2 text-slate-300 block"></i>
                                                        No history recorded yet for this return.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DRAWER FOOTER -->
                                    <div class="bg-white border-t border-slate-200 p-4 shrink-0 flex items-center justify-between gap-3">
                                        <button
                                            type="button"
                                            onclick="closeReturnDrawer()"
                                            class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition"
                                        >
                                            Close
                                        </button>
                                        <a
                                            href="{{ route('admin.return-tracking.manage', $return->booking_id) }}"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition shadow-2xs"
                                        >
                                            <span>Open Return Audit →</span>
                                            <span class="sr-only">Full Return Workspace Review Return</span>
                                        </a>
                                    </div>
                                </div>
                            </template>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center">
                                    @if(!empty($currentSearch) || !empty($hasActiveFilters))
                                        <div class="max-w-md mx-auto text-slate-500">
                                            <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto mb-3">
                                                <i class="fa-solid fa-magnifying-glass text-xl"></i>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800">No matching returns found</p>
                                            <p class="text-xs text-slate-500 mt-1">No returns matched your current search query or active filter criteria.</p>
                                            <div class="mt-4">
                                                <a href="{{ route('admin.return-tracking') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-lg transition shadow-2xs">
                                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                                    <span>Clear Filters</span>
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="max-w-md mx-auto text-slate-500">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                                <i class="fa-solid fa-boxes-stacked text-xl"></i>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800">No pending returns</p>
                                            <p class="text-xs text-slate-500 mt-1">Completed events requiring asset return processing will appear here.</p>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($returns->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $returns->links() }}
                </div>
            @endif
        </section>
    </div>

    <!-- Return Detail Slide-Over Drawer Container -->
    <div id="returnDrawerContainer" class="fixed inset-0 z-50 overflow-hidden hidden" aria-labelledby="returnDrawerTitle" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div
            id="returnDrawerBackdrop"
            onclick="closeReturnDrawer()"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px] transition-opacity duration-300 opacity-0 cursor-pointer"
            aria-hidden="true"
        ></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <!-- Slide-over panel -->
            <aside
                id="returnDrawerPanel"
                class="w-screen max-w-md md:max-w-xl bg-white shadow-2xl border-l border-slate-200 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col justify-between"
            >
                <div id="returnDrawerContent" class="h-full flex flex-col justify-between overflow-hidden">
                    <!-- Populated dynamically from template -->
                </div>
            </aside>
        </div>
    </div>

    <!-- Interactive Scripts: Filters & Detail Drawer -->
    <script>
        function toggleFilterPanel() {
            const panel = document.getElementById('returnFilterPanel');
            const text = document.getElementById('toggleFiltersText');
            const chevron = document.getElementById('filtersChevron');
            const btn = document.getElementById('toggleFiltersBtn');
            if (!panel) return;

            const isHidden = panel.style.display === 'none' || panel.classList.contains('hidden');
            if (isHidden) {
                panel.style.display = 'block';
                panel.classList.remove('hidden');
                if (text) text.textContent = 'Hide Filters';
                if (chevron) chevron.classList.add('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            } else {
                panel.style.display = 'none';
                panel.classList.add('hidden');
                if (text) text.textContent = 'Show Filters';
                if (chevron) chevron.classList.remove('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }

        function openReturnDrawer(returnId) {
            const template = document.getElementById('return-drawer-template-' + returnId);
            const container = document.getElementById('returnDrawerContainer');
            const backdrop = document.getElementById('returnDrawerBackdrop');
            const panel = document.getElementById('returnDrawerPanel');
            const content = document.getElementById('returnDrawerContent');

            if (!template || !container || !panel || !content) return;

            content.innerHTML = template.innerHTML;
            container.classList.remove('hidden');

            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
            });

            document.body.style.overflow = 'hidden';
        }

        function closeReturnDrawer() {
            const container = document.getElementById('returnDrawerContainer');
            const backdrop = document.getElementById('returnDrawerBackdrop');
            const panel = document.getElementById('returnDrawerPanel');

            if (!container || !panel) return;

            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            panel.classList.remove('translate-x-0');
            panel.classList.add('translate-x-full');

            setTimeout(() => {
                container.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }

        function switchReturnTab(returnId, tabName) {
            const content = document.getElementById('returnDrawerContent');
            if (!content) return;

            const tabPanes = content.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => pane.classList.add('hidden'));

            const targetPane = content.querySelector('#tab-content-' + returnId + '-' + tabName);
            if (targetPane) {
                targetPane.classList.remove('hidden');
            }

            const tabBtns = content.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('border-brand-700', 'text-brand-700');
                btn.classList.add('border-transparent', 'text-slate-500');
            });

            const activeBtn = content.querySelector('#tab-btn-' + returnId + '-' + tabName);
            if (activeBtn) {
                activeBtn.classList.remove('border-transparent', 'text-slate-500');
                activeBtn.classList.add('border-brand-700', 'text-brand-700');
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReturnDrawer();
            }
        });
    </script>
</x-admin-layout>
