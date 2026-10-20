<x-staff-layout title="Search Results">
    <div class="space-y-6">
        {{-- Admin-Style Search Toolbar --}}
        <form method="GET" action="{{ route('staff.search') }}" id="staffSearchPageForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Search Box --}}
                <div class="relative w-full md:min-w-[260px] md:flex-1 lg:max-w-lg">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $term ?? '' }}"
                        placeholder="Search events, clients, venues, or tasks..."
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

                @if($term !== '')
                    <div class="w-full sm:w-auto flex gap-2">
                        <a href="{{ route('staff.search') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>

                    <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                        <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-700 tabular-nums">
                            {{ $bookings->count() }} Events · {{ $tasks->count() }} Tasks
                        </span>
                    </div>
                @endif
            </div>
        </form>

        {{-- Bookings Results --}}
        @if($bookings->isNotEmpty())
            <section class="space-y-3">
                <h4 class="text-sm font-bold text-navy-900">Matching Events ({{ $bookings->count() }})</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($bookings as $b)
                        @php
                            $cName = $b->client?->full_name ?? $b->guest_name ?? 'Client';
                        @endphp
                        <a href="{{ route('staff.events.show', $b) }}" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs hover:border-slate-300 hover:shadow-xs transition flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-rose-600 uppercase">Booking #{{ $b->id }}</span>
                                <h5 class="font-serif text-sm font-bold text-navy-900">{{ $cName }}</h5>
                                <p class="text-xs text-slate-500">{{ ucfirst((string) $b->event_type) }} · {{ optional($b->event_date)->format('M d, Y') ?? 'No date' }}@if($b->venue) · {{ $b->venue }}@endif</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                {{ $b->status_display_label }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Tasks Results --}}
        @if($tasks->isNotEmpty())
            <section class="space-y-3">
                <h4 class="text-sm font-bold text-navy-900">Matching Tasks ({{ $tasks->count() }})</h4>
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
                    <ul class="divide-y divide-slate-100" role="list">
                        @foreach($tasks as $task)
                            @include('staff.partials.task-row', ['task' => $task, 'compact' => false])
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if($term !== '' && $bookings->isEmpty() && $tasks->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No results found</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">No events or tasks matching "{{ $term }}" were found in your assigned workspace.</p>
            </div>
        @endif
    </div>
</x-staff-layout>
