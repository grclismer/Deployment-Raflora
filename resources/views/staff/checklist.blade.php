<x-staff-layout title="Event Checklist">
    <div class="space-y-6">
        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch);
        @endphp
        <form method="GET" action="{{ route('staff.checklist') }}" id="checklistFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
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
                        placeholder="Search client, venue, or booking ID..."
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
                        <a href="{{ route('staff.checklist') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-700 tabular-nums">
                        {{ $events->count() }} Active Event Checklists
                    </span>
                </div>
            </div>
        </form>

        @if($events->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No active checklists</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">You currently have no active event preparations assigned.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach($events as $event)
                    @php
                        $cName = $event->client?->full_name ?? $event->guest_name ?? 'Client';
                        $evtDate = optional($event->event_date)->format('M d, Y') ?? 'Date not set';
                        $items = $event->staffChecklistItems;
                        $done = $items->where('is_completed', true)->count();
                        $total = $items->count();
                        $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
                    @endphp
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Booking #{{ $event->id }}</span>
                                <h4 class="font-serif text-base font-bold text-navy-900">{{ $cName }} · {{ ucfirst((string) $event->event_type) }}</h4>
                                <p class="text-xs text-slate-500">{{ $evtDate }} · {{ $event->venue ?? 'No venue' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <span class="text-xs font-bold text-slate-700 tabular-nums">{{ $done }}/{{ $total }} done</span>
                                    <div class="w-24 h-1.5 rounded-full bg-slate-100 overflow-hidden mt-1">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <a href="{{ route('staff.events.show', $event) }}#checklist" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Event Details
                                </a>
                            </div>
                        </div>

                        <ul class="divide-y divide-slate-100" role="list">
                            @foreach($items as $item)
                                <li class="py-2.5 flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <form method="POST" action="{{ route('staff.events.checklist.update', [$event, $item]) }}" class="shrink-0 mt-0.5">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="is_completed" value="{{ $item->is_completed ? '0' : '1' }}">
                                            <input type="hidden" name="notes" value="{{ $item->notes }}">
                                            <button type="submit"
                                                    class="flex h-5 w-5 items-center justify-center rounded-md border transition {{ $item->is_completed ? 'border-rose-500 bg-rose-500 text-white' : 'border-slate-300 bg-white text-transparent hover:border-rose-500 hover:text-rose-500' }}"
                                                    title="{{ $item->is_completed ? 'Reopen' : 'Mark completed' }}">
                                                <i class="fa-solid fa-check text-[10px]"></i>
                                            </button>
                                        </form>
                                        <div>
                                            <p class="text-xs font-semibold {{ $item->is_completed ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $item->title }}</p>
                                            @if($item->notes)
                                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $item->notes }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($item->is_completed && $item->completed_at)
                                        <span class="text-[10px] text-slate-400 shrink-0">{{ Carbon\Carbon::parse($item->completed_at)->format('M d, g:i A') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-staff-layout>
