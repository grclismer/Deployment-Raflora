<x-staff-layout title="My Tasks">
    <div class="space-y-6">
        {{-- Task Overview Cards --}}
        <section class="grid grid-cols-2 gap-3.5 sm:grid-cols-4" aria-label="Task metrics">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Total Assigned</span>
                <p class="mt-1 text-2xl font-bold text-navy-900 tabular-nums">{{ $summary['tasks_total'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Completed Work</span>
                <p class="mt-1 text-2xl font-bold text-emerald-600 tabular-nums">{{ $summary['tasks_completed'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Due Today</span>
                <p class="mt-1 text-2xl font-bold text-rose-600 tabular-nums">{{ $summary['tasks_due_today'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Remaining</span>
                <p class="mt-1 text-2xl font-bold text-amber-600 tabular-nums">{{ $summary['tasks_open'] }}</p>
            </div>
        </section>

        {{-- Quick View Navigation Pills matching Admin --}}
        @php
            $curView = $filters['view'] ?? 'all';
            $hasActiveFilters = !empty($filters['search']) || !empty($filters['event']) || !empty($filters['type']) || !empty($filters['status']) || (!empty($filters['sort']) && $filters['sort'] !== 'due');
        @endphp
        <nav class="flex flex-wrap items-center gap-1.5" aria-label="Task status filters">
            @foreach(['all' => 'All', 'today' => 'Today', 'week' => 'This Week', 'completed' => 'Completed', 'pending' => 'Pending'] as $vKey => $vLabel)
                @php $isActive = ($curView === $vKey); @endphp
                <a href="{{ route('staff.tasks', array_merge($filters, ['view' => $vKey])) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition {{ $isActive ? 'bg-rose-500 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                    <span>{{ $vLabel }}</span>
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.2 text-[10px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }} tabular-nums">{{ $viewCounts[$vKey] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Admin-Style Search & Filter Toolbar --}}
        <form id="tasksFilterForm" method="GET" action="{{ route('staff.tasks') }}" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
            <input type="hidden" name="view" value="{{ $curView }}">
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Search (Full width on mobile, auto on desktop) --}}
                <div class="relative w-full md:min-w-[220px] md:flex-1 lg:max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input
                        type="text"
                        id="tasksSearchInput"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search tasks, clients, venues..."
                        autocomplete="off"
                        class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition"
                    >
                </div>

                {{-- Event Filter --}}
                <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[150px]">
                    <label for="tasks_event" class="sr-only">Event</label>
                    <select id="tasks_event" name="event" onchange="document.getElementById('tasksFilterForm').submit()"
                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                        <option value="">All Events</option>
                        @foreach($events as $evt)
                            <option value="{{ $evt->id }}" {{ ($filters['event'] ?? '') == $evt->id ? 'selected' : '' }}>#{{ $evt->id }} · {{ $evt->client?->full_name ?? $evt->guest_name ?? 'Client' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Task Type Filter --}}
                <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[140px]">
                    <label for="tasks_type" class="sr-only">Task Type</label>
                    <select id="tasks_type" name="type" onchange="document.getElementById('tasksFilterForm').submit()"
                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                        <option value="">All Task Types</option>
                        <option value="checklist" {{ ($filters['type'] ?? '') === 'checklist' ? 'selected' : '' }}>Checklist</option>
                        <option value="dispatch" {{ ($filters['type'] ?? '') === 'dispatch' ? 'selected' : '' }}>Dispatch</option>
                        <option value="return" {{ ($filters['type'] ?? '') === 'return' ? 'selected' : '' }}>Returns</option>
                        <option value="inspection" {{ ($filters['type'] ?? '') === 'inspection' ? 'selected' : '' }}>Inspection</option>
                    </select>
                </div>

                {{-- Status Filter --}}
                <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[130px]">
                    <label for="tasks_status" class="sr-only">Status</label>
                    <select id="tasks_status" name="status" onchange="document.getElementById('tasksFilterForm').submit()"
                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>

                {{-- Sort --}}
                <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[130px]">
                    <label for="tasks_sort" class="sr-only">Sort by</label>
                    <select id="tasks_sort" name="sort" onchange="document.getElementById('tasksFilterForm').submit()"
                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                        <option value="due" {{ ($filters['sort'] ?? '') === 'due' ? 'selected' : '' }}>Due Date</option>
                        <option value="urgency" {{ ($filters['sort'] ?? '') === 'urgency' ? 'selected' : '' }}>Urgency</option>
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
                @if($hasActiveFilters || $curView !== 'all')
                    <div class="w-full sm:w-auto flex gap-2">
                        <a href="{{ route('staff.tasks') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif
            </div>
        </form>

        {{-- Tasks List --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden" aria-label="Tasks list">
            @if($tasks->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 text-lg">
                        <i class="fa-solid fa-list-check"></i>
                    </span>
                    <h3 class="mt-3 text-sm font-bold text-navy-900">No tasks found</h3>
                    <p class="mt-1 text-xs text-slate-500">No tasks match your selected view or filter criteria.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100" role="list">
                    @foreach($tasks as $task)
                        @include('staff.partials.task-row', ['task' => $task, 'compact' => false])
                    @endforeach
                </ul>

                @if($tasks->hasPages())
                    <div class="border-t border-slate-100 px-5 py-3">
                        {{ $tasks->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-staff-layout>
