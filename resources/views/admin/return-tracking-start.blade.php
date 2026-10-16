<x-admin-layout title="Return Audit">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Return Audit & Reconciliation</h1>
                <p class="text-sm text-slate-500 mt-1">Booking #{{ $booking->id }} · {{ ucfirst($booking->event_type ?? 'Event') }} · {{ $booking->client?->full_name ?? $booking->guest_name ?? 'Guest Client' }}</p>
            </div>
            <a href="{{ route('admin.return-tracking') }}" class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-50 transition shadow-2xs">
                <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                <span>Back to Return Tracking</span>
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-base font-bold text-slate-900">No return audit yet</h2>
            @if(empty($dispatchedQuantities))
                <p class="mt-1 text-sm text-slate-600">No reusable materials were dispatched for this booking. Start the audit to record that and close it.</p>
            @else
                <p class="mt-1 text-sm text-slate-600">{{ count($dispatchedQuantities) }} reusable item(s) were dispatched for this booking. Starting the audit lists them for counting.</p>
            @endif

            <form method="POST" action="{{ route('admin.return-tracking.start', $booking) }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 transition">
                    <i class="fa-solid fa-clipboard-list text-xs" aria-hidden="true"></i>
                    Start Return Audit
                </button>
            </form>
        </div>
    </div>
</x-admin-layout>
