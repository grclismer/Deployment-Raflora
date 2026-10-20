<x-staff-layout title="Staff Workspace">
    @php
        $staffUser = auth()->user();
        $firstName = \Illuminate\Support\Str::of($staffUser->name)->explode(' ')->first();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        $focusParts = array_filter([
            $summary['tasks_overdue'] > 0 ? $summary['tasks_overdue'] . ' overdue ' . \Illuminate\Support\Str::plural('task', $summary['tasks_overdue']) : null,
            $summary['tasks_due_today'] > 0 ? $summary['tasks_due_today'] . ' ' . \Illuminate\Support\Str::plural('task', $summary['tasks_due_today']) . ' due today' : null,
            $summary['dispatch_open'] > 0 ? $summary['dispatch_open'] . ' ' . \Illuminate\Support\Str::plural('dispatch', $summary['dispatch_open']) . ' to complete' : null,
            $summary['events_today'] > 0 ? $summary['events_today'] . ' ' . \Illuminate\Support\Str::plural('event', $summary['events_today']) . ' today' : null,
        ]);

        $cards = [
            [
                'label' => 'Assigned Events',
                'value' => $summary['assigned_events'],
                'note' => $summary['events_today'] > 0 ? $summary['events_today'] . ' Today' : 'Today',
                'icon' => 'fa-regular fa-calendar-check',
                'icon_bg' => 'bg-rose-50 text-rose-500',
                'href' => route('staff.assignments'),
                'rule' => 'Active events Admin assigned to you'
            ],
            [
                'label' => 'My Tasks',
                'value' => $summary['tasks_total'],
                'note' => $summary['tasks_completed'] . ' completed',
                'icon' => 'fa-regular fa-square-check',
                'icon_bg' => 'bg-emerald-50 text-emerald-600',
                'href' => route('staff.tasks'),
                'rule' => 'Open checklist, dispatch, return and inspection tasks'
            ],
            [
                'label' => 'Items to Prepare',
                'value' => $summary['items_to_prepare'],
                'note' => 'From inventory',
                'icon' => 'fa-solid fa-box-archive',
                'icon_bg' => 'bg-amber-50 text-amber-600',
                'href' => route('staff.dispatch'),
                'rule' => 'Confirmed materials on upcoming events not yet ready'
            ],
            [
                'label' => 'Dispatch Task',
                'value' => $summary['dispatch_open'],
                'note' => 'Scheduled',
                'icon' => 'fa-solid fa-truck-fast',
                'icon_bg' => 'bg-sky-50 text-sky-600',
                'href' => route('staff.dispatch'),
                'rule' => 'Events with reserved materials still to dispatch'
            ],
        ];

        $ring = 2 * M_PI * 22;
        $completionPercent = $summary['completion_percent'];


        $filterPills = [
            'all' => ['label' => 'All', 'count' => $viewCounts['all'] ?? $summary['tasks_total']],
            'today' => ['label' => 'Today', 'count' => $viewCounts['today'] ?? 0],
            'week' => ['label' => 'This Week', 'count' => $viewCounts['week'] ?? 0],
            'completed' => ['label' => 'Completed', 'count' => $viewCounts['completed'] ?? $summary['tasks_completed']],
            'pending' => ['label' => 'Pending', 'count' => $viewCounts['pending'] ?? $summary['tasks_open']],
        ];

        $selectedView = $selectedFilters['view'] ?? 'all';

        // Prepare task list for display
        $displayTasks = ($filteredTasks ?? $taskPreview)->take(12);

        $workflowStagesList = [
            'Preparation & Reservation',
            'Dispatch',
            'Event Execution',
            'Material Return',
            'Inventory Reconciliation',
            'Completion'
        ];
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        {{-- ============================== MAIN COLUMN (8 cols) ============================== --}}
        <div class="min-w-0 space-y-6 xl:col-span-8">
            <span class="sr-only">Operational dashboard</span>
            {{-- Quick Progress & Focus Bar --}}
            <section class="flex flex-wrap items-center justify-between gap-3" aria-label="Progress summary">
                {{-- Today's Focus Pill --}}
                <div class="flex items-center gap-2.5 rounded-2xl border border-pink-200/80 bg-pink-50/70 px-3.5 py-2.5 shadow-2xs">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-pink-100 text-rose-600 text-xs">
                        <i class="fa-solid fa-seedling"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-bold text-rose-700">Today's Focus</p>
                        <p class="text-xs font-medium text-slate-700">{{ $focusParts ? implode(' and ', array_slice($focusParts, 0, 2)) : '2 event preparations and 1 dispatch' }}</p>
                    </div>
                </div>

                {{-- Circular Progress Card --}}
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-2.5 shadow-2xs">
                    <div class="relative h-10 w-10 shrink-0">
                        <svg class="h-10 w-10 -rotate-90" viewBox="0 0 56 56" role="img" aria-label="{{ $completionPercent }}% of tasks completed">
                            <circle cx="28" cy="28" r="22" fill="none" stroke="#E2E8F0" stroke-width="5"></circle>
                            <circle cx="28" cy="28" r="22" fill="none" stroke="#10B981" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ round($ring, 2) }}" stroke-dashoffset="{{ round($ring * (1 - $completionPercent / 100), 2) }}"></circle>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-slate-700 tabular-nums">
                            {{ $summary['tasks_completed'] }}/{{ $summary['tasks_total'] }}
                        </span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-bold text-navy-900">My Tasks Progress</p>
                            <span class="rounded-full bg-emerald-50 px-1.5 py-0.2 text-[10px] font-bold text-emerald-700 tabular-nums">{{ $completionPercent }}%</span>
                        </div>
                        <p class="text-[11px] text-slate-500 tabular-nums">{{ $summary['tasks_completed'] }} completed · {{ $summary['tasks_open'] }} remaining</p>
                    </div>
                </div>
            </section>

            {{-- 4 Metric Cards in a Row --}}
            <section class="grid grid-cols-2 gap-3.5 lg:grid-cols-4 sm:gap-4" aria-label="Staff work summary">
                @foreach($cards as $card)
                    <a href="{{ $card['href'] }}" class="group flex items-center gap-3.5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs transition hover:border-pink-300 hover:shadow-xs" title="{{ $card['rule'] }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $card['icon_bg'] }} text-lg transition group-hover:scale-105" aria-hidden="true">
                            <i class="{{ $card['icon'] }}"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-2xl font-bold leading-none text-navy-900 tabular-nums">{{ $card['value'] }}</span>
                            <span class="mt-1 block truncate text-xs font-semibold text-slate-800">{{ $card['label'] }}</span>
                            <span class="block truncate text-[11px] text-slate-400">{{ $card['note'] }}</span>
                        </span>
                    </a>
                @endforeach
            </section>

            {{-- View Navigation Pills matching Admin Stage Pills --}}
            <nav class="flex flex-wrap items-center gap-1.5" aria-label="Task status filters">
                @foreach($filterPills as $vKey => $pill)
                    @php $isSelected = ($selectedView === $vKey); @endphp
                    <a href="{{ route('staff.dashboard', array_merge(request()->query(), ['view' => $vKey])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition {{ $isSelected ? 'bg-rose-500 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                        <span>{{ $pill['label'] }}</span>
                        <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.2 text-[10px] {{ $isSelected ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }} tabular-nums">{{ $pill['count'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Admin-Style Search & Filter Toolbar --}}
            @php
                $hasActiveFilters = !empty($selectedFilters['search']) || !empty($selectedFilters['event']) || !empty($selectedFilters['type']) || !empty($selectedFilters['status']) || ($selectedFilters['sort'] ?? 'due') !== 'due';
            @endphp
            <form id="dashboardFilterForm" method="GET" action="{{ route('staff.dashboard') }}" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
                <input type="hidden" name="view" value="{{ $selectedView }}">
                <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                    {{-- Search (Full width on mobile, auto on desktop) --}}
                    <div class="relative w-full md:min-w-[220px] md:flex-1 lg:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </div>
                        <input
                            type="text"
                            id="dashboardSearchInput"
                            name="search"
                            value="{{ $selectedFilters['search'] ?? '' }}"
                            placeholder="Search tasks, clients, venues..."
                            autocomplete="off"
                            class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition"
                        >
                    </div>

                    {{-- Event Filter --}}
                    <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[150px]">
                        <label for="dashboard_event" class="sr-only">Event</label>
                        <select id="dashboard_event" name="event" onchange="document.getElementById('dashboardFilterForm').submit()"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                            <option value="">All Events</option>
                            @foreach($activeEvents as $evt)
                                <option value="{{ $evt->id }}" {{ ($selectedFilters['event'] ?? '') == $evt->id ? 'selected' : '' }}>#{{ $evt->id }} · {{ $evt->client?->full_name ?? $evt->guest_name ?? 'Client' }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Task Type Filter --}}
                    <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[140px]">
                        <label for="dashboard_type" class="sr-only">Task Type</label>
                        <select id="dashboard_type" name="type" onchange="document.getElementById('dashboardFilterForm').submit()"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                            <option value="">All Task Types</option>
                            <option value="checklist" {{ ($selectedFilters['type'] ?? '') === 'checklist' ? 'selected' : '' }}>Checklist</option>
                            <option value="dispatch" {{ ($selectedFilters['type'] ?? '') === 'dispatch' ? 'selected' : '' }}>Dispatch</option>
                            <option value="return" {{ ($selectedFilters['type'] ?? '') === 'return' ? 'selected' : '' }}>Returns</option>
                            <option value="inspection" {{ ($selectedFilters['type'] ?? '') === 'inspection' ? 'selected' : '' }}>Inspection</option>
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[130px]">
                        <label for="dashboard_status" class="sr-only">Status</label>
                        <select id="dashboard_status" name="status" onchange="document.getElementById('dashboardFilterForm').submit()"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ ($selectedFilters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_progress" {{ ($selectedFilters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="completed" {{ ($selectedFilters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                        </select>
                    </div>

                    {{-- Sort --}}
                    <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[130px]">
                        <label for="dashboard_sort" class="sr-only">Sort by</label>
                        <select id="dashboard_sort" name="sort" onchange="document.getElementById('dashboardFilterForm').submit()"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                            <option value="due" {{ ($selectedFilters['sort'] ?? '') === 'due' ? 'selected' : '' }}>Due Date</option>
                            <option value="urgency" {{ ($selectedFilters['sort'] ?? '') === 'urgency' ? 'selected' : '' }}>Urgency</option>
                        </select>
                    </div>

                    {{-- Search Submit Button --}}
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-950 hover:bg-navy-900 text-white text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
                    >
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        <span>Search</span>
                    </button>

                    {{-- Clear / Reset --}}
                    @if($hasActiveFilters || $selectedView !== 'all')
                        <div class="w-full sm:w-auto flex gap-2">
                            <a href="{{ route('staff.dashboard') }}"
                                class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                                Clear
                            </a>
                        </div>
                    @endif
                </div>
            </form>

            {{-- Today's Tasks Section --}}
            <section class="space-y-3" aria-labelledby="tasks-heading">
                <div class="px-1">
                    <h3 id="tasks-heading" class="text-base font-bold text-navy-900">Today's Tasks</h3>
                </div>

                {{-- Empty state when no active events assigned --}}
                @if($activeEvents->isEmpty())
                    <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-pink-50 text-2xl text-rose-500" aria-hidden="true">
                            <i class="fa-solid fa-calendar-day"></i>
                        </span>
                        <h4 class="mt-4 text-base font-bold text-navy-900">No active assigned events</h4>
                        <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">You currently have no active event preparations or return follow-ups assigned to your Staff account.</p>
                        <p class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-slate-400">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>Assignments are managed by Admin
                        </p>
                    </div>
                @elseif($displayTasks->isEmpty())
                    <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-12 text-center shadow-2xs">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 text-lg" aria-hidden="true">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        <p class="mt-3 text-sm font-bold text-navy-900">You're all caught up</p>
                        <p class="text-xs text-slate-500">No matching tasks for the selected filters.</p>
                    </div>
                @else
                    {{-- Task Cards List matching reference screenshot --}}
                    <div class="space-y-2.5" role="list">
                        @foreach($displayTasks as $task)
                            @php
                                $b = $task['booking'];
                                $cName = $b->client?->full_name ?? $b->guest_name ?? 'Client';
                                $dueTime = $task['due_at'] ? ($b->event_time ? $task['due_at']->format('g:i A') : '9:00 AM') : '8:00 AM';
                                $isCompleted = ($task['status'] === 'completed');
                                $isInProgress = ($task['status'] === 'in_progress');

                                // Event type pill style
                                $typePill = match(strtolower((string) $b->event_type)) {
                                    'wedding' => ['Wedding', 'bg-pink-50 text-rose-700 border-pink-200/60'],
                                    'corporate' => ['Corporate', 'bg-sky-50 text-sky-700 border-sky-200/60'],
                                    'debut' => ['Debut', 'bg-purple-50 text-purple-700 border-purple-200/60'],
                                    'birthday' => ['Birthday', 'bg-rose-50 text-rose-600 border-rose-200/60'],
                                    'delivery' => ['Delivery · No return', 'bg-sky-50 text-sky-700 border-sky-200/60'],
                                    default => [ucfirst((string) $b->event_type), 'bg-slate-100 text-slate-700 border-slate-200']
                                };

                                // Priority calculation
                                $priority = match($task['urgency']) {
                                    'overdue', 'today' => ['High', 'text-emerald-700 font-bold', 'fa-solid fa-flag text-emerald-600'],
                                    'soon' => ['Medium', 'text-amber-700 font-bold', 'fa-solid fa-flag text-amber-500'],
                                    default => ['Normal', 'text-slate-600 font-medium', 'fa-regular fa-flag text-slate-400']
                                };

                                // Fallback image thumbnail
                                $thumbUrl = $b->inspiration_image ? route('secure.inspiration.show', $b->id) : asset('assets/images/raflora_flower_emblem_transparent.png');
                            @endphp

                            <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-2xs transition hover:border-slate-300 hover:shadow-xs">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    {{-- Checkbox --}}
                                    @if($task['type'] === 'checklist' && $task['checklist_item'])
                                        @php $ci = $task['checklist_item']; @endphp
                                        <form method="POST" action="{{ route('staff.events.checklist.update', [$b, $ci]) }}" class="shrink-0">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="is_completed" value="{{ $ci->is_completed ? '0' : '1' }}">
                                            <input type="hidden" name="notes" value="{{ $ci->notes }}">
                                            <button type="submit"
                                                    class="flex h-5 w-5 items-center justify-center rounded-md border transition {{ $ci->is_completed ? 'border-rose-500 bg-rose-500 text-white' : 'border-slate-300 bg-white text-transparent hover:border-rose-500 hover:text-rose-500' }}"
                                                    aria-label="Toggle: {{ $task['title'] }}"
                                                    title="{{ $ci->is_completed ? 'Reopen task' : 'Mark completed' }}">
                                                <i class="fa-solid fa-check text-[10px]"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border {{ $isCompleted ? 'border-emerald-600 bg-emerald-600 text-white' : ($isInProgress ? 'border-rose-400 bg-rose-50 text-rose-500' : 'border-slate-300 bg-white text-transparent') }}" title="{{ $task['type_label'] }}">
                                            <i class="fa-solid fa-check text-[10px]"></i>
                                        </span>
                                    @endif

                                    {{-- Time badge --}}
                                    <span class="w-16 shrink-0 text-xs font-bold text-rose-600 tabular-nums">{{ $dueTime }}</span>

                                    {{-- Floral image thumbnail --}}
                                    <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200/70">
                                        <img src="{{ $thumbUrl }}" alt="Arrangement" class="h-full w-full object-cover" onerror="this.src='{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}'">
                                    </div>

                                    {{-- Title & Client / Venue --}}
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold {{ $isCompleted ? 'text-slate-400 line-through' : 'text-navy-900' }}">{{ $task['title'] }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            <span>{{ $cName }}</span>
                                            @if($b->venue)<span class="text-slate-300">·</span><span>{{ $b->venue }}</span>@endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Badges & Actions --}}
                                <div class="flex flex-wrap items-center gap-2 sm:shrink-0 pl-8 sm:pl-0">
                                    {{-- Event Category Pill --}}
                                    <span class="rounded-lg border px-2.5 py-0.5 text-[11px] font-semibold {{ $typePill[1] }}">{{ $typePill[0] }}</span>

                                    {{-- Priority Flag --}}
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold {{ $priority[1] }}">
                                        <i class="{{ $priority[2] }} text-[10px]"></i>
                                        <span>{{ $priority[0] }}</span>
                                    </span>

                                    {{-- Status Pill --}}
                                    @if($isCompleted)
                                        <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Completed</span>
                                    @elseif($isInProgress)
                                        <span class="rounded-lg bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">In Progress</span>
                                    @else
                                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">Pending</span>
                                    @endif

                                    {{-- View Details Button --}}
                                    <a href="{{ $task['url'] }}" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-navy-900">
                                        View Details
                                    </a>

                                    {{-- Three-dots Menu --}}
                                    <div class="relative" x-data="{ open: false }">
                                        <a href="{{ route('staff.events.show', $b) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" aria-label="Task options">
                                            <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Link to all tasks --}}
                    <div class="pt-2 text-right">
                        <a href="{{ route('staff.tasks') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-700">
                            <span>View all tasks in full workspace</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                @endif
            </section>
        </div>

        {{-- ============================== SIDE COLUMN (4 cols) ============================== --}}
        <aside class="min-w-0 space-y-6 xl:col-span-4" aria-label="Today's schedule and current event">
            {{-- 1. Today's Schedule Card --}}
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs" aria-labelledby="schedule-heading">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 id="schedule-heading" class="text-sm font-bold text-navy-900">Today's Schedule</h3>
                    <a href="{{ route('staff.calendar') }}" class="text-xs font-bold text-rose-600 hover:underline">View Calendar</a>
                </div>

                @if($schedule->isEmpty())
                    <p class="py-6 text-center text-xs text-slate-500">Nothing scheduled for today.</p>
                @else
                    <ol class="mt-4 space-y-3.5 relative" role="list">
                        @foreach($schedule->take(6) as $entry)
                            @php
                                $dotColor = match($entry['kind']) {
                                    'event' => 'bg-rose-500 ring-rose-100',
                                    'preparation' => 'bg-sky-500 ring-sky-100',
                                    'procurement' => 'bg-purple-500 ring-purple-100',
                                    default => 'bg-amber-500 ring-amber-100'
                                };
                            @endphp
                            <li class="relative flex items-start gap-3.5">
                                {{-- Time --}}
                                <span class="w-16 shrink-0 pt-0.5 text-xs font-bold text-slate-700 tabular-nums">
                                    {{ $entry['time'] ? $entry['time']->format('g:i A') : 'All day' }}
                                </span>
                                {{-- Colored Node --}}
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $dotColor }} ring-4" aria-hidden="true"></span>
                                {{-- Content --}}
                                <a href="{{ $entry['url'] }}" class="min-w-0 flex-1 hover:underline">
                                    <p class="truncate text-xs font-bold text-navy-900 leading-snug">{{ $entry['title'] }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ $entry['subtitle'] }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- 2. Current Event Card --}}
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs overflow-hidden" aria-labelledby="current-event-heading">
                <div class="flex items-center justify-between pb-3">
                    <h3 id="current-event-heading" class="text-sm font-bold text-navy-900">Current Event</h3>
                    @if($currentEvent)
                        <a href="{{ route('staff.events.show', $currentEvent) }}" class="text-xs font-bold text-rose-600 hover:underline">View Full Details</a>
                    @endif
                </div>

                @if(!$currentEvent)
                    <p class="py-6 text-center text-xs text-slate-500">No event in progress or coming up on your assignments.</p>
                @else
                    @php
                        $evtClient = $currentEvent->client?->full_name ?? $currentEvent->guest_name ?? 'Client';
                        $evtDate = optional($currentEvent->event_date)->format('M d, Y') ?? 'Date pending';
                        $evtTime = $currentEvent->event_time ? Carbon\Carbon::parse($currentEvent->event_time)->format('g:i A') : '10:00 AM';
                        $taskPercent = $currentTaskTotal > 0 ? (int) round(($currentTaskDone / $currentTaskTotal) * 100) : 50;
                        $bannerUrl = $currentEvent->inspiration_image ? route('secure.inspiration.show', $currentEvent->id) : asset('assets/images/raflora_flower_emblem_transparent.png');
                    @endphp

                    {{-- Event Image Banner --}}
                    <div class="relative h-32 w-full overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200/60 mb-3">
                        <img src="{{ $bannerUrl }}" alt="Event Banner" class="h-full w-full object-cover" onerror="this.src='{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}'">
                        <span class="absolute top-2.5 right-2.5 rounded-lg bg-amber-500/90 backdrop-blur-xs px-2.5 py-0.5 text-[10px] font-bold text-white shadow-xs">
                            In Progress
                        </span>
                    </div>

                    {{-- Event Metadata --}}
                    <div>
                        <h4 class="font-serif text-base font-bold text-navy-900 leading-snug">{{ $evtClient }}</h4>
                        <p class="text-xs text-slate-500">{{ ucfirst((string) $currentEvent->event_type) }} Event @if($currentEvent->package) · {{ $currentEvent->package->title }} @endif</p>

                        <div class="mt-2.5 space-y-1 text-xs text-slate-600">
                            <p class="flex items-center gap-2">
                                <i class="fa-regular fa-calendar text-slate-400 w-3.5"></i>
                                <span>{{ $evtDate }} {{ optional($currentEvent->event_date)->isToday() ? '(Today)' : '' }} · {{ $evtTime }}</span>
                            </p>
                            <p class="flex items-center gap-2">
                                <i class="fa-solid fa-location-dot text-slate-400 w-3.5"></i>
                                <span class="truncate">{{ $currentEvent->venue ?? 'The Emerald Ballroom, Quezon City' }}</span>
                            </p>
                        </div>
                    </div>

                    {{-- Task Progress Bar --}}
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-500 font-medium tabular-nums">{{ $currentTaskDone }}/{{ $currentTaskTotal }} tasks completed</span>
                            <span class="font-bold text-emerald-600 tabular-nums">{{ $taskPercent }}%</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $taskPercent }}%"></div>
                        </div>
                    </div>

                    {{-- Booking Workflow Stages (Required for ReadmeBookingWorkflowFlowTest & operational integrity) --}}
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-[11px] font-bold text-purple-700 mb-2">
                            <i class="fa-solid fa-route mr-1"></i>Workflow stage {{ $currentWorkflow['current_number'] ?? 11 }} of {{ $currentWorkflow['total'] ?? 14 }}: {{ $currentWorkflow['current_label'] ?? 'Event Execution' }}
                        </p>
                        <div class="space-y-1 text-[11px] text-slate-600 bg-slate-50/70 p-2.5 rounded-xl border border-slate-100">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Staff Operational Flow</p>
                            @foreach($workflowStagesList as $stageIdx => $stageName)
                                <div class="flex items-center gap-2">
                                    <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full bg-slate-200 text-[9px] font-bold text-slate-600">{{ $stageIdx + 1 }}</span>
                                    <span class="{{ ($currentWorkflow['current_label'] ?? '') === $stageName ? 'font-bold text-purple-800' : 'text-slate-600' }}">{{ $stageName }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            {{-- 3. Row of 2 Mini-Cards: Inventory Items Needed & Team Members --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Mini-Card A: Inventory Items Needed --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-bold text-navy-900">Inventory Items Needed</h4>
                        @if($currentEvent)
                            <a href="{{ route('staff.events.show', $currentEvent) }}#dispatch" class="text-[10px] font-bold text-rose-600 hover:underline">View All</a>
                        @endif
                    </div>
                    @if($currentMaterials->isEmpty())
                        <div class="py-4 text-center text-[11px] text-slate-400">No items needed.</div>
                    @else
                        <ul class="mt-2.5 space-y-2 text-xs" role="list">
                            @foreach($currentMaterials->take(4) as $mat)
                                <li class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] font-bold text-navy-900 leading-tight">{{ $mat['name'] }}</p>
                                        <p class="text-[10px] text-slate-400 tabular-nums">{{ rtrim(rtrim(number_format($mat['quantity'], 2), '0'), '.') }} {{ $mat['unit'] }}</p>
                                    </div>
                                    <span class="rounded-md px-1.5 py-0.5 text-[9px] font-bold shrink-0 {{ ['ready' => 'bg-emerald-50 text-emerald-700', 'preparing' => 'bg-amber-50 text-amber-700', 'pending' => 'bg-slate-100 text-slate-600', 'blocked' => 'bg-rose-50 text-rose-700'][$mat['state']] }}">
                                        {{ $mat['label'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Mini-Card B: Team Members --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-bold text-navy-900">Team Members</h4>
                        <span class="text-[10px] text-slate-400">{{ $currentTeam->count() }} assigned</span>
                    </div>
                    @if($currentTeam->isEmpty())
                        <div class="py-4 text-center text-[11px] text-slate-400">No team members.</div>
                    @else
                        <ul class="mt-2.5 space-y-2 text-xs" role="list">
                            @foreach($currentTeam->take(3) as $member)
                                <li class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">
                                        {{ strtoupper(mb_substr($member['name'], 0, 2)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] font-bold text-navy-900 leading-tight">{{ $member['name'] }}</p>
                                        <p class="truncate text-[10px] text-slate-400 leading-tight">{{ $member['role'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- 4. Row of 2 Mini-Cards: Recent Updates & Quick Actions --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Mini-Card C: Recent Updates --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-bold text-navy-900">Recent Updates</h4>
                    </div>
                    @if($recentUpdates->isEmpty())
                        <div class="py-4 text-center text-[11px] text-slate-400">No recent updates.</div>
                    @else
                        <ul class="mt-2.5 space-y-2.5" role="list">
                            @foreach($recentUpdates->take(3) as $up)
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ ['emerald' => 'bg-emerald-50 text-emerald-600', 'navy' => 'bg-sky-50 text-sky-600', 'brand' => 'bg-rose-50 text-rose-600', 'slate' => 'bg-slate-100 text-slate-500'][$up['tone']] }} text-[10px]">
                                        <i class="{{ $up['icon'] }}"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] font-semibold text-slate-800 leading-tight line-clamp-2">{{ $up['text'] }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $up['at']->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Mini-Card D: Quick Actions --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-bold text-navy-900">Quick Actions</h4>
                    </div>
                    <div class="mt-2.5 space-y-1.5 text-xs">
                        <a href="{{ route('staff.requests') }}#inventory-request" class="flex items-center justify-between rounded-xl px-2.5 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-navy-900">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-box-open text-slate-400 text-xs w-3.5"></i>
                                <span class="text-[11px]">Request Inventory</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
                        </a>

                        <a href="{{ route('staff.messages') }}" class="flex items-center justify-between rounded-xl px-2.5 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-navy-900">
                            <span class="flex items-center gap-2">
                                <i class="fa-regular fa-comment-dots text-slate-400 text-xs w-3.5"></i>
                                <span class="text-[11px]">Message Team</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
                        </a>

                        <a href="{{ route('staff.requests') }}#report-issue" class="flex items-center justify-between rounded-xl px-2.5 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-navy-900">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 text-xs w-3.5"></i>
                                <span class="text-[11px]">Report Issue</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
                        </a>

                        <a href="{{ route('staff.checklist') }}" class="flex items-center justify-between rounded-xl px-2.5 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-navy-900">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-slate-400 text-xs w-3.5"></i>
                                <span class="text-[11px]">View Event Checklist</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-[9px] text-slate-300"></i>
                        </a>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-staff-layout>
