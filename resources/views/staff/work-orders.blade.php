<x-staff-layout title="Work Orders">
    <div class="space-y-6">
        {{-- Work Orders Overview --}}
        <section class="grid grid-cols-2 gap-3.5 sm:grid-cols-3" aria-label="Work order metrics">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Active Work Orders</span>
                <p class="mt-1 text-2xl font-bold text-navy-900 tabular-nums">{{ $workOrders->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
                <span class="text-xs font-semibold text-slate-500">Materials Needed</span>
                <p class="mt-1 text-2xl font-bold text-amber-600 tabular-nums">{{ $summary['items_to_prepare'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs col-span-2 sm:col-span-1">
                <span class="text-xs font-semibold text-slate-500">Dispatch Ready</span>
                <p class="mt-1 text-2xl font-bold text-sky-600 tabular-nums">{{ $summary['dispatch_open'] }}</p>
            </div>
        </section>

        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch);
        @endphp
        <form method="GET" action="{{ route('staff.work-orders') }}" id="workOrdersFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Search Box --}}
                <div class="relative w-full md:min-w-[240px] md:flex-1 lg:max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input
                        type="text"
                        name="search"
                        value="{{ $currentSearch ?? '' }}"
                        placeholder="Search work order, client, event, or venue..."
                        autocomplete="off"
                        class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-rose-500 focus:border-rose-600 transition"
                    >
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
                        <a href="{{ route('staff.work-orders') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-600 tabular-nums">
                        {{ $workOrders->count() }} Active Work Orders
                    </span>
                </div>
            </div>
        </form>

        {{-- Work Orders Cards List --}}
        @if($workOrders->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No active work orders</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">You have no active work orders assigned at this time.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach($workOrders as $wo)
                    @php
                        $b = $wo['booking'];
                        $cName = $b->client?->full_name ?? $b->guest_name ?? 'Client';
                        $evtDate = optional($b->event_date)->format('l, F j, Y') ?? 'Date not set';
                        $evtTime = $b->event_time ? Carbon\Carbon::parse($b->event_time)->format('g:i A') : 'TBD';
                    @endphp
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                        {{-- Work Order Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">WO-{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}</span>
                                    <span class="rounded-full bg-pink-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">{{ $b->status_display_label }}</span>
                                </div>
                                <h3 class="mt-1 font-serif text-lg font-bold text-navy-900">{{ $cName }} · {{ ucfirst((string) $b->event_type) }}</h3>
                                <p class="text-xs text-slate-500">
                                    <i class="fa-regular fa-calendar text-slate-400 mr-1"></i>{{ $evtDate }} at {{ $evtTime }} · <i class="fa-solid fa-location-dot text-slate-400 ml-1 mr-1"></i>{{ $b->venue ?? 'Venue not specified' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('staff.events.show', $b) }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50">
                                    Open Workspace
                                </a>
                                <a href="{{ route('staff.events.show', $b) }}#checklist" class="rounded-xl bg-rose-500 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-rose-600">
                                    Checklist
                                </a>
                            </div>
                        </div>

                        {{-- Details Grid --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Checklists / Tasks --}}
                            <div class="rounded-xl bg-slate-50/70 p-4 border border-slate-100">
                                <h4 class="text-xs font-bold text-navy-900 mb-2.5 flex items-center justify-between">
                                    <span>Preparation Tasks</span>
                                    <span class="text-slate-500 font-normal tabular-nums">{{ $wo['completed_tasks'] }}/{{ $wo['total_tasks'] }}</span>
                                </h4>
                                @if($wo['tasks']->isEmpty())
                                    <p class="text-[11px] text-slate-400">No checklist items recorded.</p>
                                @else
                                    <ul class="space-y-1.5 text-xs" role="list">
                                        @foreach($wo['tasks']->take(4) as $t)
                                            <li class="flex items-center gap-2">
                                                <i class="fa-solid {{ $t['status'] === 'completed' ? 'fa-circle-check text-emerald-600' : 'fa-circle-dot text-slate-300' }} text-xs"></i>
                                                <span class="truncate {{ $t['status'] === 'completed' ? 'line-through text-slate-400' : 'text-slate-700' }}">{{ $t['title'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            {{-- Materials Requirements --}}
                            <div class="rounded-xl bg-slate-50/70 p-4 border border-slate-100">
                                <h4 class="text-xs font-bold text-navy-900 mb-2.5 flex items-center justify-between">
                                    <span>Material Requirements</span>
                                    <span class="text-slate-500 font-normal tabular-nums">{{ $wo['materials']->count() }} items</span>
                                </h4>
                                @if($wo['materials']->isEmpty())
                                    <p class="text-[11px] text-slate-400">No material requirements confirmed yet.</p>
                                @else
                                    <ul class="space-y-1.5 text-xs" role="list">
                                        @foreach($wo['materials']->take(4) as $m)
                                            <li class="flex items-center justify-between gap-2">
                                                <span class="truncate text-slate-700">{{ $m['name'] }} ({{ rtrim(rtrim(number_format($m['quantity'], 2), '0'), '.') }} {{ $m['unit'] }})</span>
                                                <span class="rounded px-1.5 py-0.2 text-[10px] font-semibold {{ ['ready' => 'bg-emerald-100 text-emerald-800', 'preparing' => 'bg-amber-100 text-amber-800', 'pending' => 'bg-slate-200 text-slate-700', 'blocked' => 'bg-rose-100 text-rose-800'][$m['state']] }}">
                                                    {{ $m['label'] }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-staff-layout>
