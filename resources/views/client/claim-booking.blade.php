<x-app-layout title="Claim Booking Request">
    <x-client-layout active="bookings">
        <div class="mx-auto max-w-3xl space-y-6">
            <section class="rf-panel p-6 sm:p-10" aria-labelledby="claim-booking-heading">
                {{-- Category Pill and Icon --}}
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 shadow-xs">
                        <i class="fa-solid fa-calendar-check text-2xl" aria-hidden="true"></i>
                    </div>

                    <span class="rf-badge rf-badge--primary inline-flex items-center gap-1.5 text-xs">
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                        Guest Booking Claim
                    </span>

                    <h1 id="claim-booking-heading" class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Claim Your Booking Request
                    </h1>

                    <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-slate-600 sm:text-base">
                        You're about to link this <strong class="font-semibold text-slate-800">{{ ucfirst($booking->event_type ?? 'event') }}</strong> request to your registered account (<strong>{{ auth()->user()->email }}</strong>).
                        @if($booking instanceof \App\Models\Booking)
                            Once claimed, you can continue this booking from your client portal.
                        @else
                            Once claimed, this request will be submitted to the Raflora admin team for formal quotation and review.
                        @endif
                    </p>
                </div>

                {{-- Account Match Banner --}}
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>
                        </div>
                        <div class="text-sm">
                            <p class="font-bold text-emerald-900">Verified Account Match</p>
                            <p class="mt-0.5 text-emerald-800">
                                This guest inquiry was created with <strong class="font-semibold">{{ $booking->guest_email }}</strong> and matches your authenticated account.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Request Overview Card --}}
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-5 sm:p-6">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Request Details</h2>
                        <span class="text-xs font-semibold text-slate-500">{{ $booking instanceof \App\Models\Booking ? 'Booking ID' : 'Temporary ID' }}: #{{ $booking->id }}</span>
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Event Type</dt>
                            <dd class="mt-1 font-bold text-slate-900">{{ ucfirst($booking->event_type ?? 'Event') }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Event Date</dt>
                            <dd class="mt-1 font-bold text-slate-900">{{ \Carbon\Carbon::parse($booking->event_date)->format('M d, Y') }}</dd>
                        </div>
                        @if($booking->event_time)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Event Time</dt>
                                <dd class="mt-1 font-bold text-slate-900">{{ \Carbon\Carbon::parse($booking->event_time)->format('h:i A') }}</dd>
                            </div>
                        @endif
                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Type</dt>
                            <dd class="mt-1 font-bold text-slate-900">{{ ucfirst($booking->booking_type ?? 'Custom') }}</dd>
                        </div>
                        @if($booking->venue)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Venue</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $booking->venue }}</dd>
                            </div>
                        @endif
                        @if($booking->guest_count)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Estimated Guests</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $booking->guest_count }} guests</dd>
                            </div>
                        @endif
                        @if($booking->table_count)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Table Count</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ $booking->table_count }} tables</dd>
                            </div>
                        @endif
                    </dl>

                    @if($booking->special_requests)
                        <div class="mt-4 rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Special Requests / Notes</p>
                            <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $booking->special_requests }}</p>
                        </div>
                    @endif
                </div>

                {{-- What Happens Next --}}
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/40 p-5">
                    <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">What Happens Next</h2>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200/70 bg-white p-3.5 text-center shadow-xs">
                            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">1</div>
                            <h3 class="mt-2 text-xs font-bold text-slate-900">Account Linked</h3>
                            <p class="mt-1 text-[11px] leading-normal text-slate-500">Request attaches permanently to your client portal.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/70 bg-white p-3.5 text-center shadow-xs">
                            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">2</div>
                            <h3 class="mt-2 text-xs font-bold text-slate-900">Admin Quotation</h3>
                            <p class="mt-1 text-[11px] leading-normal text-slate-500">Our styling team reviews materials and prepares a quote.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/70 bg-white p-3.5 text-center shadow-xs">
                            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">3</div>
                            <h3 class="mt-2 text-xs font-bold text-slate-900">Review & Payment</h3>
                            <p class="mt-1 text-[11px] leading-normal text-slate-500">Accept quote, track updates, and submit payment securely.</p>
                        </div>
                    </div>
                </div>

                {{-- 
                RAFLORA UI FUNCTION

                Function: Claim Guest Booking
                Actor: Authenticated Client
                Purpose: Transfers ownership of a temporary guest booking to the authenticated client's account.
                Current Phase: UI-FIRST
                Current Behavior: Provides a confirmation screen to link the booking to the logged-in user.
                Expected Backend Action: Updates the booking's user_id, nullifies the guest token, and associates it with the client account.
                Required Conditions: Valid token, authenticated client, booking not already claimed.
                Success Feedback: Redirect to client dashboard with success message.
                Error/Validation Feedback: Displays error if token is invalid or booking is already claimed.
                Next UI State: Client Dashboard (or Booking History).
                Allowed Next Actions: Cancel (returns to dashboard).
                Restrictions: Staff and Admins cannot claim bookings.
                Backend Dependency: Client\ClaimGuestBookingController@claim.
                Notes: 
                --}}
                <div class="mt-8 border-t border-slate-100 pt-6">
                    <form action="{{ route('client.claim-guest-booking.claim', ['token' => $token]) }}" method="POST">
                        @csrf
                        <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <button type="submit" class="rf-btn rf-btn-primary w-full justify-center px-8 py-3 text-sm font-semibold shadow-sm sm:w-auto">
                                <i class="fa-solid fa-circle-check mr-2" aria-hidden="true"></i>
                                Yes, Claim &amp; Submit Request
                            </button>
                            <a href="{{ route('client.dashboard') }}" class="rf-btn rf-btn-ghost w-full justify-center px-6 py-3 text-sm font-semibold sm:w-auto">
                                <i class="fa-solid fa-arrow-left mr-2" aria-hidden="true"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <div class="mt-6 text-center text-xs text-slate-400">
                    <p>Logged in as <span class="font-medium text-slate-600">{{ auth()->user()->email }}</span>. If this is not your request, please cancel and log in with the correct account.</p>
                </div>
            </section>
        </div>
    </x-client-layout>
</x-app-layout>
