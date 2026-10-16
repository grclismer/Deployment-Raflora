<x-app-layout title="Booking Details">
    <x-client-layout active="bookings" plain="true">
        <div class="max-w-5xl mx-auto px-4 py-8 sm:px-6 lg:px-8">

            <div id="quotationState" class="space-y-6">

                @php
                    $activeQuotation  = $activeQuotation ?? $booking->activeQuotation;
                    $previewImage     = $booking->inspiration_image ? route('secure.inspiration.show', $booking->id) : null;
                    $imagePath        = $booking->inspiration_image ? storage_path('app/private/' . ltrim($booking->inspiration_image, '/')) : null;
                    $imageDimensions  = $imagePath ? \App\Support\ImageOrientation::displaySize($imagePath) : null;
                    $imageAspectRatio = is_array($imageDimensions) && !empty($imageDimensions[0]) && !empty($imageDimensions[1])
                                        ? $imageDimensions[0] . ' / ' . $imageDimensions[1]
                                        : null;
                    $analysisGroups   = app(\App\Services\GeminiVisionService::class)->normalizeAreaAnalysis(['suggested_materials' => $analysisMaterials]);
                    $hasAiAnalysis    = !empty($analysisGroups);
                    // Every photo (owner-checked secure URLs) and each photo's own boxes, without AI prices.
                    $storedAnalysis   = is_array($booking->ai_analysis_data) ? $booking->ai_analysis_data : null;
                    $galleryPhotos    = array_map(fn (array $photo) => $photo + [
                        'url' => route('secure.inspiration.show', ['bookingId' => $booking->id, 'image_index' => $photo['index']]),
                    ], \App\Support\InspirationPhotos::galleryPhotos($storedAnalysis));
                    $previewImage     = $previewImage ?? ($galleryPhotos[0]['url'] ?? null);
                    $overlayRows      = \App\Services\GeminiVisionService::withoutPrices(['suggested_materials' => \App\Support\InspirationPhotos::overlayRows($storedAnalysis, $analysisMaterials)])['suggested_materials'];
                    $overlayItems     = collect(app(\App\Services\GeminiVisionService::class)->normalizeAreaAnalysis(['suggested_materials' => $overlayRows]))->flatMap(fn($group) => $group['items'] ?? [])->values();

                    $latestPayment      = $booking->payments()->latest()->first();
                    $hasSubmittedPayment = $latestPayment && in_array($latestPayment->status, ['pending', 'verified', 'fully_paid', 'downpayment_received'], true);
                    $hasPendingPayment  = $booking->payments()->where('status', 'pending')->exists();
                    // Prices come only from the quotation Admin issued (null until then).
                    $sharedQuotation    = $sharedQuotation ?? $booking->sharedQuotation();
                    $displayedTotal     = $booking->shared_quote_total;
                    $damageCharge       = $displayedTotal !== null ? (float) $booking->damage_charges : 0.0;
                    $paidAmount         = (float) $booking->total_paid;
                    $remaining          = (float) ($booking->shared_remaining_balance ?? 0);
                    
                    $canShowPaymentForm = !$hasPendingPayment && (
                        $booking->status === 'admin_approved' || 
                        (in_array($booking->status, ['event_completed', 'pending_resolution'], true) && $remaining > 0)
                    );

                    $canRequestCancellation = in_array($booking->status, [
                        'pending',
                        'quotation_sent',
                        'change_requested',
                        'approved',
                        'admin_approved',
                        'payment_pending',
                        'payment_submitted',
                        'downpayment_received',
                        'confirmed',
                        'in_preparation',
                    ], true);

                    $presentations = isset($presentations) ? $presentations : $booking->presentations()->orderBy('created_at')->get();
                    $isTerminal = in_array($booking->status, ['cancelled', 'declined'], true);

                    // README end-to-end booking workflow (single source of truth)
                    $bookingWorkflow = app(\App\Services\BookingWorkflowService::class)->resolve($booking);
                @endphp

                    {{-- 1. Back Navigation --}}
                    <div>
                        <a href="{{ route('bookings') }}" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-emerald-700 transition">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Back to My Bookings
                        </a>
                    </div>

                    {{-- 2. Booking Header --}}
                    <section aria-labelledby="booking-summary-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col md:flex-row gap-5 items-start">
                        @if($previewImage)
                            <div class="w-full md:w-32 md:h-32 rounded-xl overflow-hidden shrink-0 bg-slate-100 border border-slate-200 hidden md:block">
                                <img src="{{ $previewImage }}" alt="Event Inspiration" class="w-full h-full object-cover">
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            {{-- The current status is shown once, in the status & action section below. --}}
                            <h1 id="booking-summary-heading" class="text-2xl font-semibold text-slate-800 leading-tight mb-2 truncate">
                                {{ ucfirst($booking->event_type ?? 'Event') }}
                                @if($booking->package)
                                    <span class="font-normal text-slate-500">— {{ $booking->package->title }}</span>
                                @endif
                            </h1>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-600 font-medium">
                                @if($booking->event_date)
                                    <span>{{ $booking->event_date->format('F j, Y') }} at {{ $booking->event_time ?? 'TBD' }}</span>
                                @endif
                                @if($booking->venue)
                                    <span class="text-slate-300" aria-hidden="true">|</span>
                                    <span class="truncate max-w-[200px] sm:max-w-xs">{{ $booking->venue }}</span>
                                @endif
                                @if($booking->event_size)
                                    <span class="text-slate-300" aria-hidden="true">|</span>
                                    <span>{{ number_format($booking->event_size) }} guests</span>
                                @endif
                                @if($booking->table_count)
                                    <span class="text-slate-300" aria-hidden="true">|</span>
                                    <span>{{ number_format($booking->table_count) }} tables</span>
                                @endif
                            </div>
                        </div>
                    </section>

                {{-- 3. Booking progress: client stages; event stages appear once the booking is confirmed --}}
                <x-booking-workflow :workflow="$bookingWorkflow" audience="client" :show-current="false" heading="Booking Progress" description="Where your booking is in Raflora's process." id="client-booking-workflow" />

                <div class="flex flex-col lg:flex-row gap-6 mb-8">

                    {{-- 4-10. Current Status / Action Area --}}
                    <div class="w-full lg:flex-1 space-y-6">
                        
                        <section aria-labelledby="status-action-heading" class="rounded-2xl border-2 {{ $isTerminal ? 'border-slate-300' : 'border-emerald-600' }} bg-white shadow-sm overflow-hidden h-full">
                            <div class="{{ $isTerminal ? 'bg-slate-50 border-slate-200' : 'bg-emerald-50/50 border-emerald-100' }} p-5 sm:p-6 sm:p-8 border-b flex flex-col sm:flex-row sm:items-start justify-between gap-8 h-full">
                                
                                {{-- Status Info --}}
                                <div class="flex-1 min-w-0">
                                    <h2 id="status-action-heading" class="text-xl sm:text-2xl font-bold text-slate-900 mb-3">
                                        @if($isTerminal)
                                            Booking {{ ucfirst($booking->status) }}
                                        @elseif($booking->status === 'pending')
                                            {{ $bookingWorkflow['current_label'] ?? 'Raflora Review' }}
                                        @elseif($booking->status === 'quotation_sent')
                                            Quotation Ready
                                        @elseif($booking->status === 'change_requested')
                                            Quotation Revision in Progress
                                        @elseif($booking->status === 'approved')
                                            Quotation Accepted — Awaiting Final Admin Approval
                                        @elseif($booking->status === 'admin_approved')
                                            Payment Required
                                        @elseif(in_array($booking->status, ['payment_pending', 'payment_submitted'], true))
                                            Payment Verification in Progress
                                        @elseif(in_array($booking->status, ['downpayment_received', 'confirmed'], true))
                                            Booking Confirmed
                                        @elseif($booking->status === 'in_preparation')
                                            Event in Preparation
                                        @elseif($booking->status === 'event_in_progress')
                                            Event in Progress
                                        @elseif($booking->status === 'event_completed')
                                            Event Completed
                                        @elseif($booking->status === 'pending_return')
                                            Material Return Pending
                                        @elseif($booking->status === 'pending_resolution')
                                            Pending Resolution
                                        @elseif($booking->status === 'completed')
                                            Completed
                                        @elseif($booking->status === 'fully_paid')
                                            Fully Paid
                                        @else
                                            {{ $booking->client_status_label }}
                                        @endif
                                    </h2>
                                    <div class="text-slate-600 text-sm sm:text-base space-y-3 leading-relaxed">
                                        @if($isTerminal)
                                            <p>This booking process has been stopped and is no longer active.</p>
                                        @elseif($booking->status === 'pending')
                                            <p>Your booking request has been received. {{ $bookingWorkflow['current_detail'] ?? 'Raflora is reviewing the event details before preparing the formal quotation.' }}</p>
                                            <div class="mt-4 pt-4 border-t border-emerald-200/60">
                                                <p class="font-semibold text-slate-800 mb-2">What happens next?</p>
                                                <ol class="list-decimal list-inside space-y-1.5 ml-1">
                                                    <li>Raflora reviews your booking details.</li>
                                                    <li>Raflora prepares and validates the materials for your event.</li>
                                                    <li>We prepare your formal quotation and notify you when it is ready for your review.</li>
                                                </ol>
                                            </div>
                                        @elseif($booking->status === 'quotation_sent')
                                            <p>Your formal quotation has been prepared and is ready for your review.</p>
                                            <p>Accepting this quotation means you agree to proceed with these details. Admin final approval will follow.</p>
                                        @elseif($booking->status === 'change_requested')
                                            <p>You have requested changes to your quotation. Raflora Administration is currently reviewing your requested adjustments and will issue an updated quotation.</p>
                                        @elseif($booking->status === 'approved')
                                            <p>Your quotation has been accepted. Raflora Administration must complete the final booking approval before payment submission becomes available.</p>
                                        @elseif($booking->status === 'admin_approved')
                                            <p>Your booking has received Admin final approval. Please submit your payment reference below to secure your booking.</p>
                                        @elseif(in_array($booking->status, ['payment_pending', 'payment_submitted'], true))
                                            <p>Your payment reference has been submitted. Raflora Administration is currently verifying your payment. Your booking will be confirmed once verification is complete.</p>
                                        @elseif(in_array($booking->status, ['downpayment_received', 'confirmed'], true))
                                            <p>Your downpayment has been received and verified. Your event preparation is now underway.</p>
                                        @elseif($booking->status === 'in_preparation')
                                            <p>Your event styling materials and arrangements are actively being prepared by the Raflora team.</p>
                                        @elseif($booking->status === 'event_in_progress')
                                            <p>Your event is currently taking place.</p>
                                        @elseif($booking->status === 'pending_return')
                                            <p>Your event has concluded. Material return reconciliation is currently in progress.</p>
                                        @elseif($booking->status === 'pending_resolution')
                                            <p>Material return inspection has noted items requiring resolution. Additional charges or adjustments are currently awaiting Admin review.</p>
                                            @if($hasPendingPayment)
                                                <p class="mt-2 text-emerald-700 font-medium">Your payment reference for resolution charges has been submitted and is currently being verified.</p>
                                            @endif
                                        @elseif($booking->status === 'event_completed')
                                            @if($hasPendingPayment)
                                                <p>Your final payment reference has been submitted and is currently being verified by Raflora Administration.</p>
                                            @elseif($remaining > 0)
                                                <p>Your event has concluded, and final payment remains outstanding. Please submit your payment reference below to settle your balance.</p>
                                            @else
                                                <p>Your event has concluded. Thank you for choosing Raflora Enterprises!</p>
                                            @endif
                                        @elseif($booking->status === 'completed')
                                            <p>Your event and booking settlement have been completed. Thank you for choosing Raflora Enterprises!</p>
                                        @elseif($booking->status === 'fully_paid')
                                            <p>Your booking is fully paid and confirmed.</p>
                                        @elseif($booking->status === 'cancellation_requested')
                                            <p>Your cancellation request has been submitted to the admin for review. You will be notified once a decision has been made.</p>
                                        @else
                                            <p>{{ $booking->client_status_label }}</p>
                                        @endif
                                    </div>

                                    @if($latestPayment && in_array($latestPayment->status, ['pending', 'verified', 'downpayment_received', 'fully_paid'], true))
                                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 text-sm">
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200/80 pb-2">
                                                <span class="font-bold text-emerald-950 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                                                    <i class="fa-solid fa-receipt text-emerald-700" aria-hidden="true"></i>
                                                    Submitted Payment Reference
                                                </span>
                                                <span class="rf-badge {{ $latestPayment->status === 'pending' ? 'rf-badge--warning' : 'rf-badge--success' }} text-xs">
                                                    {{ $latestPayment->status === 'pending' ? 'Verification Pending' : 'Verified' }}
                                                </span>
                                            </div>
                                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                                <div>
                                                    <span class="text-slate-500 font-medium">Reference #:</span>
                                                    <span class="font-bold text-slate-900 block mt-0.5">{{ $latestPayment->reference_number }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 font-medium">Payment Method:</span>
                                                    <span class="font-bold text-slate-900 block mt-0.5 uppercase">{{ strtoupper($latestPayment->payment_type) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 font-medium">Submitted At:</span>
                                                    <span class="font-bold text-slate-900 block mt-0.5">{{ optional($latestPayment->created_at)->format('M j, Y • g:i A') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @php
                                        $effectiveValidUntil = $quotationValidUntil ?? ($activeQuotation?->valid_until ?? $booking->price_valid_until);
                                    @endphp

                                    @if(isset($isExpired) && $isExpired && !$isTerminal)
                                        <div class="mt-5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 p-4 text-sm flex items-start gap-3">
                                            <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <div>
                                                <p class="font-semibold text-base">Quotation Expired</p>
                                                <p class="mt-1">This quotation expired on <strong>{{ optional($effectiveValidUntil)->format('F j, Y') }}</strong> due to floral market price changes. An updated quote will be issued shortly.</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Pricing Summary: the issued quotation only; before it, the public catalog price at most --}}
                                @if(!$isTerminal)
                                <div class="sm:text-right shrink-0 mt-2 sm:mt-0 bg-white sm:bg-transparent p-5 sm:p-0 rounded-xl sm:rounded-none border sm:border-0 border-emerald-100">
                                    @if($displayedTotal === null)
                                        @if($booking->package)
                                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Catalog Package Price</p>
                                            <p class="text-3xl lg:text-4xl font-bold text-slate-900">₱{{ number_format((float) $booking->package->price, 2) }}</p>
                                            <p class="text-sm text-slate-500 mt-2">Your official quotation confirms the final price.</p>
                                        @else
                                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Quotation</p>
                                            <p class="text-lg font-semibold text-slate-700">Not yet issued</p>
                                            <p class="text-sm text-slate-500 mt-2">Raflora shares the price in your official quotation.</p>
                                        @endif
                                    @else
                                        @if($damageCharge > 0)
                                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-1">Total Obligation</p>
                                        @else
                                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-1">Quotation</p>
                                        @endif

                                        <p class="text-3xl lg:text-4xl font-bold text-slate-900" aria-label="Amount: ₱{{ number_format($displayedTotal, 2) }}">
                                            ₱{{ number_format($displayedTotal, 2) }}
                                        </p>
                                        @if($damageCharge > 0)
                                            <p class="text-sm font-semibold text-rose-600 mt-1 mb-2">Includes ₱{{ number_format($damageCharge, 2) }} Damage/Loss Charges</p>
                                        @endif
                                    @endif
                                    
                                    @if(!(isset($isExpired) && $isExpired) && $effectiveValidUntil && $booking->status !== 'pending')
                                        <p class="text-sm text-slate-500 mt-2">Valid until {{ $effectiveValidUntil->format('M j, Y') }}</p>
                                    @endif

                                    @if(($activeQuotation && $activeQuotation->is_tentative) || $booking->hasTentativePricing())
                                        <div class="mt-2.5 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs text-amber-900 font-medium">
                                            <i class="fa-solid fa-clock text-amber-600" aria-hidden="true"></i>
                                            <span>Tentative Quotation (Subject to final price/availability reconfirmation {{ \App\Models\Setting::getPriceReconfirmationThresholdDays() }} days before event)</span>
                                        </div>
                                    @endif

                                    @if($booking->isPriceReconfirmationDue())
                                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-amber-400 bg-amber-100/90 px-2.5 py-1 text-xs text-amber-950 font-semibold">
                                            <i class="fa-solid fa-triangle-exclamation text-amber-700" aria-hidden="true"></i>
                                            <span>Price Validity Reconfirmation: Seasonal market pricing review in progress by Raflora administration.</span>
                                        </div>
                                    @endif

                                    @if($activeQuotation && $activeQuotation->version > 1)
                                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-800 font-medium">
                                            <i class="fa-solid fa-rotate text-emerald-600" aria-hidden="true"></i>
                                            <span>Revised Quotation (Version {{ $activeQuotation->version }})</span>
                                        </div>
                                    @endif

                                    @if($paidAmount > 0 && in_array($booking->status, ['downpayment_received', 'confirmed', 'in_preparation', 'event_in_progress', 'event_completed', 'completed', 'pending_return', 'pending_resolution', 'fully_paid'], true))
                                        <p class="text-sm font-medium text-emerald-700 mt-2">Paid: ₱{{ number_format($paidAmount, 2) }}</p>
                                    @endif

                                    @if(in_array($booking->status, ['downpayment_received', 'confirmed', 'in_preparation', 'event_in_progress', 'pending_return', 'pending_resolution', 'event_completed', 'payment_submitted', 'payment_pending'], true) && $remaining > 0)
                                        <p class="text-sm font-semibold text-amber-700 mt-3 bg-amber-50 rounded-lg px-3 py-1.5 inline-block border border-amber-200">Balance: ₱{{ number_format($remaining, 2) }}</p>
                                    @endif
                                </div>
                                @endif
                            </div>

                            {{-- Action Area & Modals --}}
                            @if(!$isTerminal && ($booking->status === 'quotation_sent' || $canShowPaymentForm || $canRequestCancellation))
                                <div class="p-5 sm:p-6 sm:p-8 bg-white border-t border-slate-100">
                                    @if($booking->status === 'quotation_sent')
                                        @if(isset($isExpired) && $isExpired)
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                <button disabled class="rf-btn w-full sm:w-auto cursor-not-allowed opacity-50">
                                                    Approval Locked — Awaiting Price Update
                                                </button>
                                                @if($canRequestCancellation)
                                                    <button type="button" onclick="document.getElementById('cancellationModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto px-4 !text-rose-600 !border-rose-200 hover:!bg-rose-50">Request Cancellation</button>
                                                @endif
                                            </div>
                                        @else
                                            <form method="POST" action="{{ route('bookings.accept', ['booking' => $booking->id]) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                @csrf
                                                <p class="text-sm text-slate-600 font-medium">Please review the quotation breakdown below before accepting.</p>
                                                <div class="flex gap-2 flex-wrap sm:flex-nowrap">
                                                    <button type="button" onclick="document.getElementById('cancellationModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto px-4 !text-rose-600 !border-rose-200 hover:!bg-rose-50">Request Cancellation</button>
                                                    <button type="button" onclick="document.getElementById('changeModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto px-4">Request Changes</button>
                                                    <button type="submit" class="rf-btn rf-btn-primary w-full sm:w-auto px-8">Accept Quotation</button>
                                                </div>
                                            </form>
                                        @endif
                                    
                                    @elseif($canShowPaymentForm)
                                        @if($latestPayment && $latestPayment->status === 'rejected')
                                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 mb-5 text-rose-900" role="alert">
                                                <div class="flex items-start gap-3">
                                                    <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    <div class="space-y-1 text-sm">
                                                        <p class="font-bold text-base text-rose-800">Previous Payment Submission Not Verified</p>
                                                        <p class="text-rose-700">
                                                            Your previous payment reference (<span class="font-semibold">{{ $latestPayment->reference_number }}</span>
                                                            via <span class="font-semibold uppercase">{{ strtoupper($latestPayment->payment_type) }}</span>)
                                                            could not be verified by Raflora Administration.
                                                        </p>
                                                        <p class="text-rose-600 text-xs">
                                                            Please double-check your payment receipt or transaction slip, verify the reference number, and submit a corrected reference below to {{ in_array($booking->status, ['event_completed', 'pending_resolution'], true) ? 'complete your final settlement' : 'secure your booking' }}.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        <form method="POST" action="{{ route('bookings.payment.reference', ['booking' => $booking->id]) }}" class="space-y-4">
                                            @csrf
                                            <div class="flex flex-col sm:flex-row gap-4 items-end">
                                                <div class="w-full sm:flex-1 grid gap-4 sm:grid-cols-3">
                                                    <div class="rf-field mb-0">
                                                        <label for="payment_type_{{ $booking->id }}" class="rf-label">Payment Method</label>
                                                        <select id="payment_type_{{ $booking->id }}" name="payment_type" class="rf-select">
                                                            <option value="gcash">GCash</option>
                                                            <option value="bank_transfer">Bank Transfer</option>
                                                        </select>
                                                    </div>
                                                    <div class="rf-field mb-0">
                                                        <label for="payment_option_{{ $booking->id }}" class="rf-label">Payment Option</label>
                                                        <select id="payment_option_{{ $booking->id }}" name="payment_option" class="rf-select">
                                                            @if(in_array($booking->status, ['event_completed', 'pending_resolution'], true) || $paidAmount > 0)
                                                                <option value="full_payment" selected>Final Balance Settlement (₱{{ number_format($remaining, 2) }})</option>
                                                            @else
                                                                <option value="downpayment">50% Downpayment</option>
                                                                <option value="full_payment">Full Payment (100%)</option>
                                                            @endif
                                                        </select>
                                                    </div>
                                                    <div class="rf-field mb-0">
                                                        <label for="reference_number_{{ $booking->id }}" class="rf-label">Reference Number</label>
                                                        <input id="reference_number_{{ $booking->id }}" type="text" name="reference_number" placeholder="Reference #" class="rf-input" required>
                                                    </div>
                                                </div>
                                                <button type="submit" class="rf-btn rf-btn-primary shrink-0 w-full sm:w-auto">
                                                    Submit Payment Reference
                                                </button>
                                            </div>
                                        </form>

                                        @if($canRequestCancellation)
                                            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-3">
                                                <p class="text-xs sm:text-sm text-slate-500">Need to cancel this booking instead?</p>
                                                <button type="button" onclick="document.getElementById('cancellationModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary px-4 py-1.5 text-xs sm:text-sm !text-rose-600 !border-rose-200 hover:!bg-rose-50 shrink-0">
                                                    Request Cancellation
                                                </button>
                                            </div>
                                        @endif

                                    @elseif($canRequestCancellation)
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                            <p class="text-sm text-slate-600">Need to cancel your event booking? You can submit a cancellation request for admin review.</p>
                                            <button type="button" onclick="document.getElementById('cancellationModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto px-4 shrink-0 !text-rose-600 !border-rose-200 hover:!bg-rose-50">
                                                Request Cancellation
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Real-time Communication Component --}}
                            <div class="p-5 sm:p-6 sm:p-8 bg-slate-50 border-t border-slate-200">
                                    <x-booking-conversation 
                                        :booking="$booking"
                                        role="client"
                                        :booking-messages="$bookingMessages ?? collect()"
                                        :unread-count="$unreadMessageCount ?? 0"
                                        :active-quotation="$activeQuotation"
                                    />
                                </div>
                            {{-- Meetings with Raflora (README Client Workflow: client and Raflora can meet about the booking) --}}
                            @php
                                $clientMeetings = $booking->meetings()->orderByDesc('scheduled_datetime')->get();
                                $meetingsAllowed = \App\Models\Meeting::bookingAllowsMeetings($booking);
                                $hasPendingMeetingRequest = $clientMeetings->contains(fn ($m) => $m->status === \App\Models\Meeting::STATUS_REQUESTED);
                            @endphp
                            @if($clientMeetings->isNotEmpty() || $meetingsAllowed)
                                <div id="client-meetings" class="p-5 sm:p-6 sm:p-8 bg-white border-t border-slate-200">
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-1">Meetings with Raflora</h3>
                                    <p class="text-sm text-slate-500 mb-4">Request an online or in-person meeting to discuss your booking. Raflora confirms the final schedule.</p>

                                    @if($clientMeetings->isNotEmpty())
                                        <ul class="space-y-3 mb-6" role="list">
                                            @foreach($clientMeetings as $meeting)
                                                <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-slate-800">{{ $meeting->type_label }} · {{ $meeting->scheduled_datetime->format('M j, Y g:i A') }}</p>
                                                            @if($meeting->status === \App\Models\Meeting::STATUS_REQUESTED)
                                                                <p class="text-xs text-slate-500">Your preferred time — awaiting Raflora confirmation.</p>
                                                            @endif
                                                            @if($meeting->status === \App\Models\Meeting::STATUS_SCHEDULED && $meeting->meeting_link)
                                                                <p class="mt-1 text-sm"><a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-700 underline break-all">Join meeting</a></p>
                                                            @endif
                                                            @if($meeting->status === \App\Models\Meeting::STATUS_SCHEDULED && $meeting->address)
                                                                <p class="mt-1 text-sm text-slate-600">Location: {{ $meeting->address }}</p>
                                                            @endif
                                                            @if($meeting->agenda)
                                                                <p class="mt-1 text-xs text-slate-500 whitespace-pre-line">Agenda: {{ $meeting->agenda }}</p>
                                                            @endif
                                                        </div>
                                                        <span class="w-fit shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold {{ match($meeting->status) { 'scheduled' => 'bg-emerald-100 text-emerald-800', 'completed' => 'bg-slate-200 text-slate-700', 'cancelled' => 'bg-rose-100 text-rose-800', default => 'bg-amber-100 text-amber-800' } }}">{{ $meeting->status_label }}</span>
                                                    </div>
                                                    @if($meeting->isOpen())
                                                        <form method="POST" action="{{ route('bookings.meetings.cancel', ['booking' => $booking->id, 'meeting' => $meeting->id]) }}" class="mt-3">
                                                            @csrf
                                                            <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 underline">Cancel this meeting</button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if($meetingsAllowed && !$hasPendingMeetingRequest)
                                        <form method="POST" action="{{ route('bookings.meetings.store', $booking->id) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            @csrf
                                            <div class="rf-field">
                                                <label class="rf-label" for="meeting_type">Meeting type</label>
                                                <select id="meeting_type" name="meeting_type" class="rf-input text-sm w-full" required>
                                                    @foreach(\App\Models\Meeting::TYPES as $typeValue => $typeLabel)
                                                        <option value="{{ $typeValue }}" @selected(old('meeting_type') === $typeValue)>{{ $typeLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="rf-field">
                                                <label class="rf-label" for="preferred_datetime">Preferred date &amp; time</label>
                                                <input id="preferred_datetime" type="datetime-local" name="preferred_datetime" value="{{ old('preferred_datetime') }}" min="{{ now()->format('Y-m-d\TH:i') }}" class="rf-input text-sm w-full" required>
                                                @error('preferred_datetime')
                                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="rf-field sm:col-span-2">
                                                <label class="rf-label" for="meeting_agenda">What would you like to discuss? (optional)</label>
                                                <textarea id="meeting_agenda" name="agenda" rows="2" maxlength="1000" class="rf-input text-sm w-full" placeholder="e.g., Review the arch design and table centerpieces">{{ old('agenda') }}</textarea>
                                            </div>
                                            <div class="sm:col-span-2 flex justify-end">
                                                <button type="submit" class="rf-btn bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-6 py-2">Request Meeting</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </section>
                        
                        {{-- Modals --}}
                        <div id="changeModal" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="changeModalTitle">
                            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
                                <div class="p-5 sm:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                    <h3 id="changeModalTitle" class="text-lg font-bold text-slate-800">Request Changes</h3>
                                    <button type="button" onclick="document.getElementById('changeModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600" aria-label="Close modal">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('bookings.request-changes', $booking->id) }}" class="p-5 sm:p-6 space-y-4">
                                    @csrf
                                    <div class="rf-field">
                                        <label class="rf-label" for="change_type">Change Type</label>
                                        <select name="change_type" id="change_type" class="rf-input" required>
                                            <option value="">Select a change type</option>
                                            <option value="material">Material / Item</option>
                                            <option value="schedule">Schedule</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="rf-field">
                                            <label class="rf-label" for="change_item">Item / Material / Topic</label>
                                            <input type="text" name="change_item" id="change_item" class="rf-input" required placeholder="e.g., Roses">
                                        </div>
                                        <div class="rf-field">
                                            <label class="rf-label" for="change_quantity">Quantity (if applicable)</label>
                                            <input type="number" name="change_quantity" id="change_quantity" class="rf-input" min="1" value="1" required>
                                        </div>
                                    </div>
                                    <div class="rf-field">
                                        <label class="rf-label" for="change_reason">Additional Details / Reason (Optional)</label>
                                        <textarea name="change_reason" id="change_reason" rows="3" class="rf-input resize-none" placeholder="e.g., Please add 15 red roses to the arrangement."></textarea>
                                    </div>
                                    <div class="flex justify-end gap-3 mt-6">
                                        <button type="button" onclick="document.getElementById('changeModal').classList.add('hidden')" class="rf-btn rf-btn-secondary">Cancel</button>
                                        <button type="submit" class="rf-btn rf-btn-primary">Send Request</button>
                                    </div>
                                </form>
                            </div>
                        </div>


                    </div>
                </div>

                {{-- 11. Supporting Information --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                            {{-- Selected Package Information (If preset) --}}
                            @if($booking->package_id && $booking->package)
                            <section aria-labelledby="package-info-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                <h2 id="package-info-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Selected Package</h2>
                                <div class="mb-4">
                                    <p class="text-lg font-bold text-slate-800">{{ $booking->package->title }}</p>
                                    @if($booking->package->description)
                                        <p class="text-sm text-slate-600 mt-1">{{ $booking->package->description }}</p>
                                    @endif
                                </div>
                                
                                @if(is_array($booking->package->included_items) && count($booking->package->included_items) > 0)
                                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Package Inclusions</p>
                                        <ul class="space-y-1.5">
                                            @foreach($booking->package->included_items as $inc)
                                                <li class="flex items-start gap-2 text-sm text-slate-700">
                                                    <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    {{ $inc }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </section>
                            @endif

                            {{-- Event Details --}}
                            <section aria-labelledby="event-details-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                <h2 id="event-details-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Event Details</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-slate-400 font-semibold uppercase">Event Type</p>
                                        <p class="text-sm text-slate-800 font-medium mt-0.5">{{ ucfirst($booking->event_type) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 font-semibold uppercase">Guest Count</p>
                                        <p class="text-sm text-slate-800 font-medium mt-0.5">{{ $booking->event_size ? number_format($booking->event_size) . ' pax' : 'Not specified' }}</p>
                                    </div>
                                    @if($booking->table_count)
                                    <div>
                                        <p class="text-xs text-slate-400 font-semibold uppercase">Table Count</p>
                                        <p class="text-sm text-slate-800 font-medium mt-0.5">{{ number_format($booking->table_count) }} tables</p>
                                    </div>
                                    @endif
                                    <div>
                                        <p class="text-xs text-slate-400 font-semibold uppercase">Date & Time</p>
                                        <p class="text-sm text-slate-800 font-medium mt-0.5">{{ optional($booking->event_date)->format('F j, Y') ?? 'TBD' }} @if($booking->event_time) at {{ $booking->event_time }} @endif</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 font-semibold uppercase">Venue</p>
                                        <p class="text-sm text-slate-800 font-medium mt-0.5">{{ $booking->venue ?? 'Not specified' }}</p>
                                    </div>
                                </div>
                                @if($booking->special_requests)
                                    <div class="mt-4 pt-4 border-t border-slate-100">
                                        <p class="text-xs text-slate-400 font-semibold uppercase mb-1">Special Requests</p>
                                        <p class="text-sm text-slate-700 italic">"{{ $booking->special_requests }}"</p>
                                    </div>
                                @endif
                            </section>

                            {{-- Quotation Breakdown --}}
                            <section aria-labelledby="breakdown-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                <h2 id="breakdown-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Quotation Breakdown</h2>
                                
                                @if(isset($quotationItems) && count($quotationItems) > 0)
                                    <div class="space-y-1 overflow-x-auto w-full">
                                        @foreach($quotationItems as $item)
                                            @php
                                                $qty = floatval($item['quantity'] ?? 0);
                                                if (isset($item['selling_unit_price'])) {
                                                    $unitPrice = floatval($item['selling_unit_price']);
                                                } else {
                                                    $internalCost = floatval($item['quoted_unit_price'] ?? 0);
                                                    $multiplier = floatval($sharedQuotation?->multiplier ?? 3.0);
                                                    $unitPrice = $internalCost * $multiplier;
                                                }
                                            @endphp
                                            <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-2 py-2 border-b border-slate-100 last:border-0">
                                                <span class="text-sm font-medium text-slate-700 flex-1 min-w-[140px]">{{ $item['item_name'] ?? 'Item' }}</span>
                                                <span class="text-sm text-slate-500 shrink-0 font-medium">{{ number_format($qty, 0) }} × ₱{{ number_format($unitPrice, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif(!$sharedQuotation)
                                    <p class="text-sm text-slate-500">Item prices appear here once Raflora issues your official quotation.</p>
                                @elseif($hasAiAnalysis)
                                    <p class="text-sm text-slate-500">Pricing is based on the reviewed event details. See AI Visual Analysis below for suggested material context.</p>
                                @elseif($booking->package_id)
                                    <p class="text-sm text-slate-500">Pricing is based on the selected package. See Package Inclusions above.</p>
                                @else
                                    <p class="text-sm text-slate-500">A detailed quotation breakdown is not available yet.</p>
                                @endif
                            </section>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Inspiration photos (every photo, the areas each is for, AI boxes, enlarged view) --}}
                                @if($previewImage)
                                <section aria-labelledby="concept-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                    <h2 id="concept-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Inspiration Image &amp; AI Recommendation</h2>
                                    <x-inspiration-gallery id="client-inspiration-gallery"
                                        :photos="$galleryPhotos"
                                        :preview-image="$previewImage"
                                        :overlay-items="$overlayItems"
                                        :max-height="320"
                                        :image-aspect-ratio="$imageAspectRatio"
                                        :image-width="$imageDimensions[0] ?? null"
                                        :image-height="$imageDimensions[1] ?? null" />
                                </section>
                                @endif

                                {{-- Proposals Card (If exists) --}}
                                @if($presentations && $presentations->count() > 0)
                                <section aria-labelledby="proposals-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                    <h2 id="proposals-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Proposals &amp; Feedback</h2>
                                    
                                    <div class="space-y-4">
                                    @foreach($presentations as $presentation)
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex items-start justify-between gap-4 mb-4">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-slate-800 leading-tight mb-1">Proposal {{ $presentation->version }}</p>
                                                    @if($presentation->file_name)
                                                        <p class="text-xs text-slate-500 truncate" title="{{ $presentation->file_name }}">{{ $presentation->file_name }}</p>
                                                    @endif
                                                </div>
                                                <a
                                                    href="{{ asset('storage/' . $presentation->file_path) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="rf-btn rf-btn-secondary text-xs shrink-0 py-1.5 px-3"
                                                >
                                                    View PDF
                                                </a>
                                            </div>
                                            <hr class="my-3 border-slate-200">
                                            <form method="POST" action="{{ route('bookings.proposals.feedback', ['booking' => $booking->id, 'presentation' => $presentation->id]) }}" class="space-y-3">
                                                @csrf
                                                <div class="flex flex-col xl:flex-row items-stretch xl:items-center gap-3">
                                                    <select name="approval_status" class="rf-select text-sm py-1.5">
                                                        <option value="approved" {{ $presentation->approval_status === 'approved' ? 'selected' : '' }}>Approve Proposal</option>
                                                        <option value="needs_revision" {{ $presentation->approval_status === 'needs_revision' ? 'selected' : '' }}>Needs Revision</option>
                                                    </select>
                                                    <input type="text" name="feedback_text" value="{{ old('feedback_text', $presentation->feedback_text) }}" placeholder="General Feedback (Optional)" class="rf-input text-sm py-1.5 flex-1 w-full">
                                                </div>
                                                <div class="flex justify-end">
                                                    <button type="submit" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Submit Proposal Feedback</button>
                                                </div>
                                            </form>
                                        </div>
                                    @endforeach
                                    </div>
                                </section>
                                @endif
                            </div>

                            {{-- AI Analysis (If exists) --}}
                            @if($hasAiAnalysis)
                            <section aria-labelledby="ai-heading" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                <h2 id="ai-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">AI Visual Analysis (Decision Support)</h2>
                                <p class="text-sm text-slate-500 mb-4">Suggested materials based on Gemini AI visual analysis. These suggestions serve as decision support for Raflora florist review and quotation preparation, not final pricing.</p>

                                {{-- Indicative AI ranges per area; the official quotation above is the price that applies --}}
                                <x-ai-estimate :estimate="$aiEstimate ?? null" :quotation-issued="(bool) $sharedQuotation" class="mb-5" />

                                <x-decor-area-breakdown :materials="$analysisMaterials" :booking="$booking" view="client" class="mb-5" />

                                {{-- With picked areas the panel above already groups every material; this list groups by where the AI saw them. --}}
                                @if(\App\Support\DecorAreaBreakdown::requestedAreas($booking->design_brief) === [])
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($analysisGroups as $group)
                                        <div>
                                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">{{ $group['area_label'] ?? 'Overall Design' }}</h3>
                                            <div class="space-y-2">
                                                @foreach($group['items'] ?? [] as $item)
                                                    @php
                                                        $categoryLabel = strtoupper($item['category']);
                                                        $displayQty = $item['quantity'] > 0 ? number_format((float) $item['quantity'], 0) : '~' . number_format((float) max(1, $item['quantity']), 0);
                                                    @endphp
                                                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3" data-line-id="{{ $item['line_id'] }}">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-semibold text-slate-800 leading-snug">{{ $item['name'] }}</p>
                                                                <p class="mt-0.5 text-xs text-slate-500">{{ $displayQty }} {{ $item['unit_type'] }}</p>
                                                            </div>
                                                            <span class="shrink-0 rounded bg-white border border-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ $categoryLabel }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </section>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- Cancellation Modal --}}
                @if($canRequestCancellation)
                    <div id="cancellationModal" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="cancellationModalTitle">
                        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
                            <div class="p-5 sm:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 id="cancellationModalTitle" class="text-lg font-bold text-rose-600">Request Cancellation</h3>
                                <button type="button" onclick="document.getElementById('cancellationModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600" aria-label="Close modal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <form method="POST" action="{{ route('bookings.request-cancellation', $booking->id) }}" class="p-5 sm:p-6">
                                @csrf
                                <div class="rf-field">
                                    <label for="cancellation_reason" class="rf-label">Please provide a reason for cancelling this booking request:</label>
                                    <textarea id="cancellation_reason" name="cancellation_reason" rows="4" class="rf-input resize-none" required placeholder="Reason for cancellation..."></textarea>
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" onclick="document.getElementById('cancellationModal').classList.add('hidden')" class="rf-btn rf-btn-secondary">Keep Booking</button>
                                    <button type="submit" class="rf-btn rf-btn-primary !bg-rose-600 hover:!bg-rose-700 !border-rose-600">Submit Cancellation Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

            </div>{{-- #quotationState --}}
        </div>
    </x-client-layout>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const hasFlash = {{ session('success') || session('error') ? 'true' : 'false' }};
            const bookingStatusUrl = @json(route('bookings.status', ['booking' => $booking->id]));
            const initialBookingUpdatedAt = @json($booking->updated_at?->toISOString());

            let lastBookingUpdatedAt = initialBookingUpdatedAt;
            const checkForBookingUpdates = async () => {
                try {
                    const response = await fetch(bookingStatusUrl, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) return;

                    const latest = await response.json();
                    if (lastBookingUpdatedAt && latest.updated_at && latest.updated_at !== lastBookingUpdatedAt) {
                        window.location.reload();
                        return;
                    }

                    lastBookingUpdatedAt = latest.updated_at || lastBookingUpdatedAt;
                } catch (error) {
                    // silent fail
                }
            };

            window.setInterval(checkForBookingUpdates, 10000);

            const changeModal = document.getElementById('changeModal');
            const cancellationModal = document.getElementById('cancellationModal');

            [changeModal, cancellationModal].forEach((m) => {
                if (!m) return;
                m.addEventListener('click', (event) => {
                    if (event.target === m) {
                        m.classList.add('hidden');
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (changeModal && !changeModal.classList.contains('hidden')) {
                        changeModal.classList.add('hidden');
                    }
                    if (cancellationModal && !cancellationModal.classList.contains('hidden')) {
                        cancellationModal.classList.add('hidden');
                    }
                }
            });
        });
    </script>

</x-app-layout>
