<x-staff-layout title="Team Messages">
    <div class="space-y-6">
        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch);
        @endphp
        <form method="GET" action="{{ route('staff.messages') }}" id="messagesFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
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
                        placeholder="Search message text, client, or booking ID..."
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
                        <a href="{{ route('staff.messages') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-700 tabular-nums">
                        {{ $threads->count() }} Active Threads
                    </span>
                </div>
            </div>
        </form>

        @if($threads->isEmpty() && $eventsWithoutMessages->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-regular fa-comments"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No message threads</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">You have no active message threads on your assigned events.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
                <ul class="divide-y divide-slate-100" role="list">
                    @foreach($threads as $t)
                        @php
                            $b = $t['booking'];
                            $latest = $t['latest'];
                            $cName = $b->client?->full_name ?? $b->guest_name ?? 'Client';
                        @endphp
                        <li>
                            <a href="{{ route('staff.events.show', $b) }}#discussion" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 sm:px-6 hover:bg-slate-50/70 transition">
                                <div class="flex items-start gap-3.5 min-w-0">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $t['unread'] > 0 ? 'bg-pink-100 text-rose-600' : 'bg-slate-100 text-slate-600' }} text-sm font-bold">
                                        <i class="fa-regular fa-comment-dots"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="truncate text-sm font-bold text-navy-900">{{ $cName }} · Booking #{{ $b->id }}</p>
                                            @if($t['unread'] > 0)
                                                <span class="rounded-full bg-rose-500 px-1.5 py-0.2 text-[10px] font-bold text-white tabular-nums">{{ $t['unread'] }} unread</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-600 truncate mt-0.5">
                                            <span class="font-semibold text-slate-800">{{ ucfirst($latest->sender_type) }}:</span> {{ $latest->message }}
                                        </p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $latest->created_at->diffForHumans() }} · {{ $t['count'] }} messages total</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 sm:shrink-0">
                                    <span class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs">
                                        Open Discussion
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach

                    @foreach($eventsWithoutMessages as $ew)
                        @php $cName = $ew->client?->full_name ?? $ew->guest_name ?? 'Client'; @endphp
                        <li>
                            <a href="{{ route('staff.events.show', $ew) }}#discussion" class="flex items-center justify-between p-4 sm:px-6 hover:bg-slate-50/50 transition">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 text-sm">
                                        <i class="fa-regular fa-comment"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ $cName }} · Booking #{{ $ew->id }}</p>
                                        <p class="text-xs text-slate-400">No messages yet. Start a conversation with team or client.</p>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold text-rose-600">Start Thread &rarr;</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-staff-layout>
