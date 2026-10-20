<x-staff-layout title="Event Calendar">
    @php
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');
        $startOfWeek = $month->copy()->startOfWeek();
        $endOfWeek = $month->copy()->endOfMonth()->endOfWeek();
        $daysInCalendar = [];
        $curr = $startOfWeek->copy();
        while ($curr->lte($endOfWeek)) {
            $daysInCalendar[] = $curr->copy();
            $curr->addDay();
        }
    @endphp

    <div class="space-y-6">
        {{-- Month Header & Nav Controls --}}
        <section class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-2xs">
            <div>
                <h3 class="font-serif text-xl font-bold text-navy-900 leading-tight">{{ $month->format('F Y') }}</h3>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('staff.calendar', ['month' => $prevMonth]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 shadow-2xs transition" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <a href="{{ route('staff.calendar', ['month' => now()->format('Y-m')]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs transition">
                    Current Month
                </a>
                <a href="{{ route('staff.calendar', ['month' => $nextMonth]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 shadow-2xs transition" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </a>
            </div>
        </section>

        {{-- Monthly Calendar Grid --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
            {{-- Day Header --}}
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/70 text-center text-xs font-bold uppercase tracking-wider text-slate-500 py-3">
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
                <span>Sun</span>
            </div>

            {{-- Day Cells --}}
            <div class="grid grid-cols-7 divide-x divide-y divide-slate-100">
                @foreach($daysInCalendar as $day)
                    @php
                        $dayStr = $day->toDateString();
                        $isCurrentMonth = $day->isSameMonth($month);
                        $isToday = $day->isToday();
                        $dayEntries = $entriesByDay->get($dayStr, collect());
                    @endphp
                    <div class="min-h-[110px] p-2 transition {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50/40 text-slate-300' }} {{ $isToday ? 'ring-2 ring-inset ring-pink-400 bg-pink-50/20' : '' }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold tabular-nums {{ $isToday ? 'text-rose-600' : ($isCurrentMonth ? 'text-slate-800' : 'text-slate-400') }}">
                                {{ $day->day }}
                            </span>
                            @if($isToday)
                                <span class="rounded bg-rose-500 text-white text-[9px] font-bold px-1 py-0.2">Today</span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            @foreach($dayEntries as $entry)
                                @php
                                    $b = $entry['booking'];
                                    $pillColor = match($entry['kind']) {
                                        'event' => 'bg-pink-50 text-rose-700 border-pink-200',
                                        'preparation' => 'bg-sky-50 text-sky-700 border-sky-200',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200'
                                    };
                                @endphp
                                <a href="{{ route('staff.events.show', $b) }}" class="block truncate rounded-md border px-1.5 py-0.5 text-[10px] font-semibold {{ $pillColor }} hover:shadow-xs transition" title="{{ $entry['label'] }}">
                                    @if($entry['time'])
                                        <span class="font-normal">{{ Carbon\Carbon::parse($entry['time'])->format('g:i A') }}</span>
                                    @endif
                                    {{ $entry['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-staff-layout>
