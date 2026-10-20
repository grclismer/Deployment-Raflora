<x-app-layout title="Dashboard">
    <x-client-layout active="dashboard">
        @php
            $user = auth()->user();
        @endphp

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center">
                    <div>
                        <h1 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">Welcome back, {{ $user->first_name ?? $user->name ?? 'Client' }}</h1>
                    </div>
                    <x-info-popover title="Dashboard">
                        Keep track of your event requests, updates, and account details from one place.
                    </x-info-popover>
                </div>
                <div class="shrink-0">
                    <a href="{{ route('booking.start') }}" class="rf-btn rf-btn-primary w-full sm:w-auto justify-center shadow-md">
                        <i class="fa-solid fa-plus mr-1.5" aria-hidden="true"></i>
                        Book New Event
                    </a>
                </div>
            </div>

            <div class="space-y-6">
                @php
                    $activeBookings = $bookings->filter(fn($b) => !in_array($b->status, ['completed', 'cancelled', 'declined', 'rejected']));
                @endphp

                <section class="rf-panel p-5 sm:p-6" aria-labelledby="active-bookings-heading">
                    <div class="rf-section-heading">
                        <div>
                            <h2 id="active-bookings-heading" class="rf-section-heading__title text-xl">Active Bookings</h2>
                            <p class="mt-1 text-sm text-slate-500">Your current ongoing event bookings and their statuses.</p>
                        </div>
                        <a href="{{ route('bookings') }}" class="rf-btn rf-btn-ghost text-sm">View all</a>
                    </div>
                    
                    @if($activeBookings->isEmpty())
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-500">
                            <p class="mb-4">You have no active bookings right now.</p>
                            <a href="{{ route('booking.start') }}" class="rf-btn rf-btn-primary">Start a New Booking</a>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($activeBookings->take(3) as $booking)
                                @php
                                    $statusClass = match ($booking->status) {
                                        'quotation_sent', 'payment_pending' => 'rf-badge--warning',
                                        'downpayment_received', 'confirmed', 'fully_paid' => 'rf-badge--success',
                                        default => 'rf-badge--primary',
                                    };
                                @endphp
                                <article class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
                                    <div>
                                        <div class="flex items-center gap-3 mb-1">
                                            <h3 class="font-bold text-slate-900">{{ ucfirst($booking->event_type ?? 'Event') }}</h3>
                                            <span class="rf-badge {{ $statusClass }} text-[10px]">{{ $booking->client_status_label }}</span>
                                        </div>
                                        <p class="text-sm text-slate-600">
                                            {{ optional($booking->event_date)->format('F j, Y') ?? 'TBD' }}
                                            @if($booking->venue) &bull; {{ $booking->venue }} @endif
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <a href="{{ route('bookings.analysis', $booking->id) }}" class="rf-btn rf-btn-outline text-sm w-full sm:w-auto justify-center">View Details</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rf-panel p-5 sm:p-6" aria-labelledby="updates-heading">
                    <div class="rf-section-heading">
                        <div>
                            <h2 id="updates-heading" class="rf-section-heading__title text-xl">Booking updates</h2>
                            <p class="mt-1 text-sm text-slate-500">The latest notes attached to your bookings.</p>
                        </div>
                        <a href="{{ route('client.notifications.index') }}" class="rf-btn rf-btn-ghost text-sm">All updates</a>
                    </div>
                    <div class="space-y-3">
                        @php
                            $hasUpdates = false;
                        @endphp
                        @foreach($bookings as $booking)
                            @if(!empty($booking->admin_notes))
                                @php $hasUpdates = true; @endphp
                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <p class="font-semibold text-slate-900">{{ $booking->event_type }}</p>
                                        <span class="rf-badge rf-badge--warning">{{ optional($booking->event_date)->format('M d, Y') }}</span>
                                    </div>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $booking->venue }}</p>
                                    <p class="mt-3 leading-6 text-slate-700">{{ $booking->admin_notes }}</p>
                                </article>
                            @endif
                        @endforeach
                        
                        @if(!$hasUpdates)
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-center text-slate-500">No booking updates yet.</div>
                        @endif
                    </div>
                </section>

            </div>
        </div>
    </x-client-layout>
</x-app-layout>
