<x-app-layout title="Booking Updates">
    <x-client-layout active="bookings">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rf-panel p-5 sm:p-8" aria-labelledby="booking-updates-title">
                <div class="mb-6 flex flex-col items-start justify-between gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#0F2E5B]">Booking Review</p>
                        <h1 id="booking-updates-title" class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
                            {{ ucfirst($booking->event_type ?? 'Event') }} Updates
                        </h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('bookings.analysis', ['booking' => $booking->id]) }}" class="rf-btn rf-btn-outline text-xs">
                            <i class="fa-solid fa-file-invoice mr-1.5" aria-hidden="true"></i>
                            View Booking
                        </a>
                        <a href="{{ route('bookings') }}" class="rf-btn rf-btn-ghost text-xs">
                            <i class="fa-solid fa-arrow-left mr-1.5" aria-hidden="true"></i>
                            Back to Bookings
                        </a>
                    </div>
                </div>

                @php
                    $statusClass = match ($booking->status) {
                        'quotation_sent', 'payment_pending' => 'rf-badge--warning',
                        'downpayment_received', 'completed', 'confirmed', 'fully_paid' => 'rf-badge--success',
                        'cancelled', 'declined', 'rejected' => 'rf-badge--danger',
                        default => 'rf-badge--primary',
                    };
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ $booking->getClientStatusLabelAttribute() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rf-badge {{ $statusClass }}">
                                <span aria-hidden="true">•</span>
                                {{ $booking->client_status_label }}
                            </span>
                            <span class="rf-badge rf-badge--primary">
                                <i class="fa-regular fa-calendar mr-1.5" aria-hidden="true"></i>
                                {{ optional($booking->event_date)->format('M d, Y') ?? 'Date TBD' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Venue</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $booking->venue ?? 'Venue TBD' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Quoted Total</p>
                            @if($booking->shared_quote_total !== null)
                                <p class="mt-1 text-base font-semibold text-slate-900">₱{{ number_format($booking->shared_quote_total, 2) }}</p>
                            @else
                                <p class="mt-1 text-base font-semibold text-slate-500">Not yet quoted</p>
                            @endif
                        </div>
                    </div>

                    @php
                        $latestSections = \App\Models\Booking::parseUpdateMessage($booking->admin_notes);
                    @endphp

                    @if(!empty($booking->admin_notes))
                        <div class="mt-5 rounded-xl border border-emerald-200 bg-white p-5 shadow-xs">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <i class="fa-solid fa-bullhorn text-emerald-700" aria-hidden="true"></i>
                                <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-700">Latest Admin Update</h2>
                            </div>

                            @if(!empty($latestSections['custom_note']))
                                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $latestSections['custom_note'] }}</p>
                            @endif

                            @if(!empty($latestSections['items']))
                                <div class="mt-3 space-y-1.5 rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">Items</p>
                                    @foreach($latestSections['items'] as $itemLine)
                                        <p class="text-sm leading-6 text-slate-800">{{ $itemLine }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($latestSections['removed_items']))
                                <div class="mt-3 space-y-1.5 rounded-lg border border-red-200 bg-red-50 p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-red-700">Removed items</p>
                                    @foreach($latestSections['removed_items'] as $itemLine)
                                        <p class="text-sm font-medium leading-6 text-red-700">{{ $itemLine }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($latestSections['price_changes']))
                                <div class="mt-3 space-y-1.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3.5">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Price changes</p>
                                    @foreach($latestSections['price_changes'] as $priceLine)
                                        <p class="text-sm font-medium leading-6 text-emerald-700">{{ $priceLine }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-8">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-slate-900">Update Timeline</h2>
                        <span class="text-xs font-semibold text-slate-500">
                            {{ $notifications->count() }} {{ \Illuminate\Support\Str::plural('entry', $notifications->count()) }}
                        </span>
                    </div>

                    <div class="space-y-4">
                        @forelse($notifications as $notification)
                            @php
                                $notificationSections = \App\Models\Booking::parseUpdateMessage($notification->message);
                            @endphp
                            <article class="rounded-2xl border {{ $notification->is_read ? 'border-slate-200 bg-white' : 'border-emerald-200 bg-emerald-50/30 ring-1 ring-emerald-100' }} p-5 shadow-xs transition hover:shadow-md">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-base font-bold text-slate-900">{{ $notification->title }}</h3>
                                        @if(!$notification->is_read)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800">New</span>
                                        @endif
                                    </div>
                                    <time class="text-xs font-medium text-slate-400" datetime="{{ $notification->created_at->toIso8601String() }}">
                                        {{ $notification->created_at->format('M d, Y • h:i A') }}
                                    </time>
                                </div>

                                @if(!empty($notificationSections['custom_note']))
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $notificationSections['custom_note'] }}</p>
                                @else
                                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $notification->message }}</p>
                                @endif

                                @if(!empty($notificationSections['items']))
                                    <div class="mt-3 space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Items</p>
                                        @foreach($notificationSections['items'] as $itemLine)
                                            <p class="text-sm leading-6 text-slate-800">{{ $itemLine }}</p>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!empty($notificationSections['removed_items']))
                                    <div class="mt-3 space-y-1.5 rounded-xl border border-red-200 bg-red-50 p-3">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-700">Removed items</p>
                                        @foreach($notificationSections['removed_items'] as $itemLine)
                                            <p class="text-sm font-medium leading-6 text-red-700">{{ $itemLine }}</p>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!empty($notificationSections['price_changes']))
                                    <div class="mt-3 space-y-1.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700">Price changes</p>
                                        @foreach($notificationSections['price_changes'] as $priceLine)
                                            <p class="text-sm font-medium leading-6 text-emerald-700">{{ $priceLine }}</p>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center text-slate-500">
                                <i class="fa-regular fa-bell-slash mx-auto mb-2 block text-2xl text-slate-400" aria-hidden="true"></i>
                                <p class="text-sm font-medium">No updates have been posted for this booking yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </x-client-layout>
</x-app-layout>

