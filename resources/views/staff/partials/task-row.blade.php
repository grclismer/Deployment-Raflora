{{-- One task row. Expects $task (from StaffWorkspaceService::tasks) and optional $compact. --}}
@php
    $compact = $compact ?? false;
    $booking = $task['booking'];
    $client = $booking->client?->full_name ?? $booking->guest_name ?? 'Client';
    $due = $task['due_at'];
    $hasTime = $due && $booking->event_time;

    $urgency = [
        'overdue' => ['Overdue', 'bg-rose-50 text-rose-700 ring-rose-200', 'fa-solid fa-circle-exclamation'],
        'today' => ['Due today', 'bg-amber-50 text-amber-800 ring-amber-200', 'fa-regular fa-clock'],
        'soon' => ['Due soon', 'bg-sky-50 text-sky-700 ring-sky-200', 'fa-regular fa-clock'],
        'later' => ['Upcoming', 'bg-slate-100 text-slate-600 ring-slate-200', 'fa-regular fa-calendar'],
        'none' => ['No deadline', 'bg-slate-100 text-slate-500 ring-slate-200', 'fa-regular fa-calendar'],
        'done' => ['Done', 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'fa-solid fa-check'],
    ][$task['urgency']];

    $status = [
        'pending' => ['Pending', 'bg-slate-100 text-slate-700'],
        'in_progress' => ['In progress', 'bg-amber-100 text-amber-800'],
        'completed' => ['Completed', 'bg-emerald-100 text-emerald-800'],
    ][$task['status']];

    $typeTone = [
        'checklist' => 'text-slate-600 bg-slate-100',
        'dispatch' => 'text-navy-700 bg-navy-50',
        'return' => 'text-brand-800 bg-brand-50',
        'inspection' => 'text-sky-700 bg-sky-50',
    ][$task['type']];

    $typeIcon = [
        'checklist' => 'fa-solid fa-list-check',
        'dispatch' => 'fa-solid fa-truck-fast',
        'return' => 'fa-solid fa-rotate-left',
        'inspection' => 'fa-solid fa-magnifying-glass',
    ][$task['type']];

    $dueText = $due ? ($due->isToday() ? 'Today' : ($due->isTomorrow() ? 'Tomorrow' : $due->format('M j'))) . ($hasTime ? ', ' . $due->format('g:i A') : '') : null;
@endphp
<li class="flex flex-col gap-3 px-4 py-3.5 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:px-5 {{ $task['urgency'] === 'overdue' ? 'bg-rose-50/30' : '' }}" data-task="{{ $task['key'] }}" data-urgency="{{ $task['urgency'] }}">
    <div class="flex min-w-0 flex-1 items-start gap-3">
        @if($task['type'] === 'checklist' && $task['checklist_item'])
            @php $checklistItem = $task['checklist_item']; @endphp
            <form method="POST" action="{{ route('staff.events.checklist.update', [$booking, $checklistItem]) }}" class="shrink-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="is_completed" value="{{ $checklistItem->is_completed ? '0' : '1' }}">
                <input type="hidden" name="notes" value="{{ $checklistItem->notes }}">
                <button type="submit"
                        class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-md border transition {{ $checklistItem->is_completed ? 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700' : 'border-slate-300 bg-white text-transparent hover:border-brand-600 hover:text-brand-600' }}"
                        aria-label="{{ $checklistItem->is_completed ? 'Reopen' : 'Mark complete' }}: {{ $task['title'] }} (Booking #{{ $booking->id }})"
                        title="{{ $checklistItem->is_completed ? 'Reopen task' : 'Mark complete' }}">
                    <i class="fa-solid fa-check text-[11px]" aria-hidden="true"></i>
                </button>
            </form>
        @else
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md {{ $typeTone }}" aria-hidden="true">
                <i class="{{ $typeIcon }} text-[11px]"></i>
            </span>
        @endif

        <div class="min-w-0">
            <p class="text-sm font-medium {{ $task['status'] === 'completed' ? 'text-slate-500 line-through decoration-slate-300' : 'text-slate-900' }}">{{ $task['title'] }}</p>
            <p class="mt-0.5 truncate text-xs text-slate-500">
                {{ $client }} · {{ ucfirst((string) $booking->event_type) }} · #{{ $booking->id }}@if($booking->venue && !$compact) · {{ $booking->venue }}@endif
            </p>
            @if($task['detail'] && !$compact)
                <p class="mt-0.5 text-xs text-slate-400">{{ $task['detail'] }}</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 pl-9 sm:shrink-0 sm:pl-0">
        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $typeTone }}">{{ $task['type_label'] }}</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $urgency[1] }}" title="{{ $due ? 'Due ' . $due->format('M j, Y' . ($hasTime ? ' g:i A' : '')) : 'No deadline recorded' }}">
            <i class="{{ $urgency[2] }} text-[9px]" aria-hidden="true"></i>{{ $task['urgency'] === 'done' ? $urgency[0] : ($dueText ?? $urgency[0]) }}
            @if(in_array($task['urgency'], ['overdue', 'today'], true))<span class="sr-only">({{ $urgency[0] }})</span>@endif
        </span>
        @unless($compact)
            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
        @endunless
        <a href="{{ $task['url'] }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900">
            {{ $task['type'] === 'checklist' ? 'Details' : ($task['status'] === 'completed' ? 'View' : 'Open') }}
            <i class="fa-solid fa-chevron-right text-[9px]" aria-hidden="true"></i>
        </a>
    </div>
</li>
