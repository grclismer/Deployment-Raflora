<x-staff-layout title="Event Assignments">
    <div class="space-y-6">
        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch) || !empty($currentEventType);
        @endphp
        <form method="GET" action="{{ route('staff.assignments') }}" id="assignmentsFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Search Box --}}
                <div class="relative w-full md:min-w-[240px] md:flex-1 lg:max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input
                        type="text"
                        id="assignmentsSearchInput"
                        name="search"
                        value="{{ $currentSearch ?? '' }}"
                        placeholder="Search client, event type, venue, or booking ID..."
                        autocomplete="off"
                        class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition"
                    >
                </div>

                {{-- Event Type Filter --}}
                <div class="w-[calc(50%-5px)] sm:w-auto sm:min-w-[160px]">
                    <label for="assign_event_type" class="sr-only">Event Type</label>
                    <select id="assign_event_type" name="event_type" onchange="document.getElementById('assignmentsFilterForm').submit()"
                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition">
                        <option value="">All Event Types</option>
                        @foreach($availableEventTypes as $type)
                            <option value="{{ $type }}" {{ ($currentEventType ?? '') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
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

                @if($hasActiveFilters)
                    <div class="w-full sm:w-auto flex gap-2">
                        <a href="{{ route('staff.assignments') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-600 tabular-nums">
                        {{ $activeEvents->count() }} Active Assignments
                    </span>
                </div>
            </div>
        </form>

        {{-- Active Events Section --}}
        <section class="space-y-4" aria-label="Active events">

            @if($activeEvents->isEmpty())
                <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-pink-50 text-2xl text-rose-500">
                        <i class="fa-solid fa-calendar-day"></i>
                    </span>
                    <h4 class="mt-4 text-base font-bold text-navy-900">No active assigned events</h4>
                    <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">You currently have no active event preparations or return follow-ups assigned to your Staff account.</p>
                    <p class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-slate-400">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>Assignments are managed by Admin
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($activeEvents as $event)
                        @php
                            $cName = $event->client?->full_name ?? $event->guest_name ?? 'Client';
                            $evtDate = optional($event->event_date)->format('M d, Y') ?? 'Date not scheduled';
                            $workflow = app(\App\Services\BookingWorkflowService::class)->resolve($event);
                            $bTasks = $tasks->filter(fn ($t) => (int) $t['booking']->id === (int) $event->id);
                            $doneCount = $bTasks->where('status', 'completed')->count();
                            $totalCount = $bTasks->count();
                            $percent = $totalCount > 0 ? (int) round(($doneCount / $totalCount) * 100) : 0;
                            $bannerUrl = $event->inspiration_image ? route('secure.inspiration.show', $event->id) : asset('assets/images/raflora_flower_emblem_transparent.png');
                        @endphp
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition hover:border-slate-300 hover:shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-xl overflow-hidden bg-slate-100 ring-1 ring-slate-200 shrink-0">
                                            <img src="{{ $bannerUrl }}" alt="" class="h-full w-full object-cover" onerror="this.src='{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}'">
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Booking #{{ $event->id }}</span>
                                            <h4 class="font-serif text-base font-bold text-navy-900 leading-tight">{{ $cName }}</h4>
                                            <p class="text-xs text-slate-500">{{ ucfirst((string) $event->event_type) }} @if($event->package) · {{ $event->package->title }} @endif</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-pink-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 shrink-0">
                                        {{ $event->status_display_label }}
                                    </span>
                                </div>

                                <div class="space-y-1.5 text-xs text-slate-600 my-3 bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                                    <p class="flex items-center gap-2">
                                        <i class="fa-regular fa-calendar text-slate-400 w-3.5"></i>
                                        <span>{{ $evtDate }} {{ optional($event->event_date)->isToday() ? '(Today)' : '' }}</span>
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <i class="fa-solid fa-location-dot text-slate-400 w-3.5"></i>
                                        <span class="truncate">{{ $event->venue ?? 'Venue not specified' }}</span>
                                    </p>
                                    @if($workflow['current'])
                                        <p class="flex items-center gap-2 text-purple-700 font-semibold pt-1 border-t border-slate-200/60">
                                            <i class="fa-solid fa-route text-purple-600 w-3.5"></i>
                                            <span>Stage {{ $workflow['current_number'] }} of {{ $workflow['total'] }}: {{ $workflow['current_label'] }}</span>
                                        </p>
                                    @endif
                                </div>

                                {{-- Task completion bar --}}
                                <div class="mt-2">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-slate-500 font-medium">{{ $doneCount }}/{{ $totalCount }} tasks completed</span>
                                        <span class="font-bold text-emerald-600 tabular-nums">{{ $percent }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                <a href="{{ route('staff.events.show', $event) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-700">
                                    <span>Open Event Workspace</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                                <a href="{{ route('staff.events.show', $event) }}#checklist" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    Checklist
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Completed Events Section --}}
        @if($completedEvents->isNotEmpty())
            <section class="space-y-4 pt-6 border-t border-slate-200" aria-labelledby="completed-assignments-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="completed-assignments-heading" class="text-sm font-bold text-navy-900">Past & Completed Events</h3>
                        <p class="text-xs text-slate-500">Historical records of events you previously serviced.</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
                    <ul class="divide-y divide-slate-100" role="list">
                        @foreach($completedEvents as $ce)
                            <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 hover:bg-slate-50/70 transition">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-navy-900">{{ $ce->client?->full_name ?? $ce->guest_name ?? 'Client' }} · {{ ucfirst((string) $ce->event_type) }}</p>
                                    <p class="text-xs text-slate-500">{{ optional($ce->event_date)->format('M d, Y') ?? 'No date' }} · {{ $ce->venue ?? 'No venue' }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">Completed</span>
                                    <a href="{{ route('staff.events.show', $ce) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        View Archive
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>
</x-staff-layout>
