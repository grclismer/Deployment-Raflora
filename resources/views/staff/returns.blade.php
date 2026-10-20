<x-staff-layout title="Material Returns & Inspection">
    <div class="space-y-6">
        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch);
        @endphp
        <form method="GET" action="{{ route('staff.returns') }}" id="returnsFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
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
                        placeholder="Search return task, client, venue, or booking..."
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
                        <a href="{{ route('staff.returns') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-bold text-rose-700 tabular-nums">
                        {{ $tasks->count() }} Return & Inspection Tasks
                    </span>
                </div>
            </div>
        </form>

        @if($tasks->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-solid fa-rotate-left"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No pending returns or inspections</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">There are no post-event return assessments currently pending for your assigned events.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
                <ul class="divide-y divide-slate-100" role="list">
                    @foreach($tasks as $task)
                        @include('staff.partials.task-row', ['task' => $task, 'compact' => false])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-staff-layout>
