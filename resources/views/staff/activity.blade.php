<x-staff-layout title="Activity Logs">
    <div class="space-y-6">
        {{-- Admin-Style Search & Filter Toolbar --}}
        @php
            $hasActiveFilters = !empty($currentSearch);
        @endphp
        <form method="GET" action="{{ route('staff.activity') }}" id="activityFilterForm" class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4 shadow-2xs">
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
                        placeholder="Search action, module, or details..."
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
                        <a href="{{ route('staff.activity') }}"
                            class="inline-flex w-full items-center justify-center px-3.5 py-2 border border-slate-200 text-xs font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </a>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2 pt-1 sm:pt-0">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 tabular-nums">
                        {{ $logs->total() }} Recorded Actions
                    </span>
                </div>
            </div>
        </form>

        @if($logs->isEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-2xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-400">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <h4 class="mt-4 text-base font-bold text-navy-900">No activity recorded</h4>
                <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">No operations or checklist updates have been logged for your account yet.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs overflow-hidden">
                <ul class="divide-y divide-slate-100" role="list">
                    @foreach($logs as $log)
                        @php
                            $actionText = $workspace->auditText($log);
                        @endphp
                        <li class="flex items-start gap-4 p-4 sm:px-6 hover:bg-slate-50/50 transition">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-xs">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ $log->module ?? 'Operations' }}</span>
                                    <span class="text-[11px] font-semibold text-slate-400">{{ $log->created_at->format('M d, Y · g:i A') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-navy-900 mt-1">{{ $actionText }}</p>
                                @if($log->details && is_string($log->details) && $log->details !== $actionText)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $log->details }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($logs->hasPages())
                    <div class="border-t border-slate-100 px-5 py-3">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-staff-layout>
