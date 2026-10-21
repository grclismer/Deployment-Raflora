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
                    
                    // First payment (downpayment or full) after Admin approval: the inline form below.
                    $canShowPaymentForm = !$hasPendingPayment && $booking->status === 'admin_approved';

                    // Remaining balance, from the event day on: the "Pay Remaining Balance" button and dialog.
                    $isBalanceStage = in_array($booking->status, \App\Http\Controllers\BookingController::BALANCE_PAYMENT_STATUSES, true);
                    $canPayRemainingBalance = $isBalanceStage && $remaining > 0 && $displayedTotal !== null && !$hasPendingPayment;
                    $balancePaymentWaiting = $isBalanceStage && $hasPendingPayment;
                    $balancePaymentRejected = $canPayRemainingBalance && $latestPayment && $latestPayment->status === 'rejected';

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

                    // Changes can be requested any time before the event starts. An open quotation has its own
                    // button next to Accept Quotation; every other stage gets the separate row below.
                    $canRequestChanges = in_array($booking->status, \App\Services\ScheduleChangeService::OPEN_STATUSES, true);
                    $showChangeRequestRow = $canRequestChanges && !($booking->status === 'quotation_sent' && !(isset($isExpired) && $isExpired));

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
                    @php
                        // Display only: "19:35:00" reads as "7:35 PM"; anything unparseable is shown as stored.
                        $eventTimeDisplay = null;
                        if ($booking->event_time) {
                            try { $eventTimeDisplay = \Illuminate\Support\Carbon::parse($booking->event_time)->format('g:i A'); }
                            catch (\Throwable $e) { $eventTimeDisplay = $booking->event_time; }
                        }
                    @endphp
                    <section aria-labelledby="booking-summary-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col md:flex-row gap-5 items-start">
                        @if($previewImage)
                            <div class="w-full md:w-32 md:h-32 rounded-xl overflow-hidden shrink-0 bg-slate-100 border border-slate-200 hidden md:block">
                                <img src="{{ $previewImage }}" alt="Event Inspiration" class="w-full h-full object-cover">
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            {{-- The current status is shown once, in the status & action section below. --}}
                            <h1 id="booking-summary-heading" class="mb-2 break-words text-2xl font-semibold leading-tight text-slate-800 [overflow-wrap:anywhere]">
                                {{ ucfirst($booking->event_type ?? 'Event') }}
                                @if($booking->package)
                                    <span class="font-normal text-slate-500">— {{ $booking->package->title }}</span>
                                @endif
                            </h1>
                            <div class="flex min-w-0 flex-col gap-1 text-sm font-medium text-slate-600 sm:flex-row sm:flex-wrap sm:gap-x-3">
                                @if($booking->event_date)
                                    <span class="min-w-0 break-words">{{ $booking->event_date->format('F j, Y') }} at {{ $eventTimeDisplay ?? 'TBD' }}</span>
                                @endif
                                @if($booking->venue)
                                    <span class="min-w-0 break-words [overflow-wrap:anywhere]" title="{{ $booking->venue }}">{{ $booking->venue }}</span>
                                @endif
                                @if($booking->event_size)
                                    <span>{{ number_format($booking->event_size) }} guests</span>
                                @endif
                                @if($booking->table_count)
                                    <span>{{ number_format($booking->table_count) }} tables</span>
                                @endif
                            </div>
                        </div>
                    </section>

                {{-- 3. Booking progress: client stages; event stages appear once the booking is confirmed --}}
                @if($booking->isDelivery())
                    <x-delivery-order-notice :booking="$booking" audience="client" class="mb-6" />
                @endif

                <x-booking-workflow :workflow="$bookingWorkflow" audience="client" :show-current="false" heading="Booking Progress" description="Where your booking is in Raflora's process." id="client-booking-workflow" collapsible />

                <div class="flex flex-col lg:flex-row gap-6 mb-8">

                    {{-- 4-10. Current Status / Action Area --}}
                    <div class="w-full lg:flex-1 space-y-6">
                        
                        <section aria-labelledby="status-action-heading" class="rounded-2xl border-2 {{ $isTerminal ? 'border-slate-300' : 'border-emerald-600' }} bg-white shadow-sm overflow-hidden h-full">
                            <div class="{{ $isTerminal ? 'bg-slate-50 border-slate-200' : 'bg-emerald-50/50 border-emerald-100' }} p-5 sm:p-6 sm:p-8 border-b flex flex-col sm:flex-row sm:items-start justify-between gap-8">
                                
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
                                            {{ $booking->isDelivery() ? 'Out for Delivery' : 'Event in Progress' }}
                                        @elseif($booking->status === 'event_completed')
                                            {{ $booking->isDelivery() ? 'Order Received' : 'Event Completed' }}
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
                                            <p>{{ $booking->isDelivery() ? 'Your order is on its way. Tap "Item Received" above once it arrives.' : 'Your event is currently taking place.' }}</p>
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
                                                <p>{{ $booking->isDelivery() ? 'You received your order, and final payment remains outstanding.' : 'Your event has concluded, and final payment remains outstanding.' }} Please submit your payment reference below to settle your balance.</p>
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

                                    @php
                                        // The client's latest schedule change request while the quotation is still being settled.
                                        $latestSchedule = in_array($booking->status, \App\Services\ScheduleChangeService::OPEN_STATUSES, true)
                                            ? $booking->scheduleRequests()->where('status', '!=', \App\Models\BookingScheduleRequest::STATUS_SUPERSEDED)->latest('id')->first()
                                            : null;
                                    @endphp
                                    @if($latestSchedule)
                                        @php
                                            $scheduleTone = match ($latestSchedule->status) {
                                                'accepted' => ['border-emerald-200 bg-emerald-50 text-emerald-900', 'fa-calendar-check text-emerald-600'],
                                                'declined' => ['border-rose-200 bg-rose-50 text-rose-900', 'fa-calendar-xmark text-rose-600'],
                                                default => ['border-amber-200 bg-amber-50 text-amber-900', 'fa-calendar-days text-amber-600'],
                                            };
                                        @endphp
                                        <div id="client-schedule-request" class="mt-4 flex items-start gap-3 rounded-xl border p-4 text-sm {{ $scheduleTone[0] }}">
                                            <i class="fa-solid {{ $scheduleTone[1] }} mt-0.5" aria-hidden="true"></i>
                                            <div>
                                                @if($latestSchedule->status === 'accepted')
                                                    <p class="font-semibold">Schedule change accepted</p>
                                                    <p>Your event is now on {{ $latestSchedule->requestedLabel() }}.</p>
                                                @elseif($latestSchedule->status === 'declined')
                                                    <p class="font-semibold">Schedule change declined</p>
                                                    <p>Your event stays on {{ $latestSchedule->previousLabel() }}.@if($latestSchedule->decision_note) Reason: {{ $latestSchedule->decision_note }}@endif</p>
                                                @else
                                                    <p class="font-semibold">Schedule change requested</p>
                                                    <p>You asked to move your event to {{ $latestSchedule->requestedLabel() }}. Raflora is checking availability and will confirm with you in Messages.</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

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

                                    @if($canPayRemainingBalance)
                                        <div class="mt-3">
                                            <button type="button" id="pay-remaining-balance-btn" onclick="document.getElementById('remainingBalanceModal').classList.remove('hidden')" class="rf-btn rf-btn-primary w-full sm:w-auto px-5" aria-haspopup="dialog">
                                                Pay Remaining Balance
                                            </button>
                                            @if($balancePaymentRejected)
                                                <p class="mt-2 text-xs font-medium text-rose-700">Your last reference could not be verified. Please send a corrected one.</p>
                                            @endif
                                        </div>
                                    @elseif($balancePaymentWaiting)
                                        <p class="mt-3 text-xs font-medium text-slate-600 sm:max-w-[16rem] sm:ml-auto">Remaining balance payment sent. Raflora is checking your reference.</p>
                                    @endif
                                </div>
                                @endif
                            </div>

                            {{-- Action Area & Modals --}}
                            @if(!$isTerminal && ($booking->status === 'quotation_sent' || $canShowPaymentForm || $canRequestCancellation || $showChangeRequestRow))
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

                                    @if($showChangeRequestRow)
                                        <div id="client-request-changes-row" class="pt-4 mt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <p class="text-sm text-slate-600">Need to change a material, the schedule, or something else? Send a request and Raflora will review it.</p>
                                            <button type="button" onclick="document.getElementById('changeModal').classList.remove('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto px-4 shrink-0">Request Changes</button>
                                        </div>
                                    @endif
                                </div>
                            @endif

                        </section>

                            {{-- Meetings with Raflora (README Client Workflow: client and Raflora can meet about the booking) --}}
                            @php
                                $clientMeetings = $booking->meetings()->orderByDesc('scheduled_datetime')->get();
                                $meetingsAllowed = \App\Models\Meeting::bookingAllowsMeetings($booking);
                                $hasPendingMeetingRequest = $clientMeetings->contains(fn ($m) => $m->status === \App\Models\Meeting::STATUS_REQUESTED);
                            @endphp
                        @php
                            $showMeetingsView = $clientMeetings->isNotEmpty() || $meetingsAllowed;
                            $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
                            $meetingFormFailed = $errors->hasAny(['meeting_type', 'preferred_datetime', 'agenda']);
                        @endphp
                        {{-- Floating chat panel: messages (and meetings) with Raflora, open from any point on the page. --}}
                        <style>
                            #client-chat-panel #booking-conversation-root { border: 0; border-radius: 0; box-shadow: none; display: flex; flex-direction: column; min-height: 100%; }
                            /* The panel header already names the conversation, so the component's own title bar is hidden here. */
                            #client-chat-panel #booking-conversation-root > div:first-child { display: none; }
                            #client-chat-panel #booking-conversation-root > .relative { flex: 1 1 0; display: flex; flex-direction: column; min-height: 160px; }
                            #client-chat-panel #comm-messages-scroll { flex: 1 1 0; max-height: none; min-height: 0; padding: 1rem; }
                            #client-chat-panel #comm-empty-state { padding-top: 2rem; padding-bottom: 2rem; }
                            #client-chat-panel #comm-message-text { height: 4.5rem; min-height: 4.5rem; }
                        </style>
                        <div id="client-chat-panel" class="fixed inset-x-3 top-20 bottom-24 z-[45] hidden flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:inset-x-auto sm:right-6 sm:top-auto sm:h-[min(720px,calc(100vh-8rem))] sm:w-[440px]" role="dialog" aria-modal="false" aria-labelledby="client-chat-panel-title" data-meeting-error="{{ $meetingFormFailed ? '1' : '0' }}">
                            <div class="flex items-center justify-between gap-3 bg-emerald-700 px-4 py-3 text-white">
                                <div class="min-w-0">
                                    <h2 id="client-chat-panel-title" class="text-sm font-semibold !text-white">Messages · Booking #{{ $booking->id }}</h2>
                                    <p class="truncate text-xs text-white/80">Chat with Raflora about this booking</p>
                                </div>
                                <button type="button" id="client-chat-close" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close messages"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            </div>
                            @if($showMeetingsView)
                                <div class="flex gap-1 border-b border-slate-200 bg-slate-50 p-1.5" role="group" aria-label="Messages or meetings">
                                    <button type="button" data-chat-view="messages" aria-pressed="true" class="client-chat-view-btn inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition">
                                        <i class="fa-regular fa-comments text-xs" aria-hidden="true"></i>Messages
                                    </button>
                                    <button type="button" data-chat-view="meetings" aria-pressed="false" class="client-chat-view-btn inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition">
                                        <i class="fa-regular fa-calendar text-xs" aria-hidden="true"></i>Meetings
                                    </button>
                                </div>
                            @endif
                            <div id="client-chat-view-messages" class="min-h-0 flex-1 overflow-y-auto">
                                <x-booking-conversation
                                    :booking="$booking"
                                    role="client"
                                    :booking-messages="$bookingMessages ?? collect()"
                                    :unread-count="$unreadMessageCount ?? 0"
                                    :active-quotation="$activeQuotation"
                                />
                            </div>
                            <div id="client-chat-view-meetings" class="hidden min-h-0 flex-1 overflow-y-auto">
                            @if($clientMeetings->isNotEmpty() || $meetingsAllowed)
                                <div id="client-meetings" class="p-4 sm:p-5 bg-white">
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
                            </div>
                        </div>
                        <button type="button" id="client-chat-launcher" class="fixed bottom-6 right-6 z-[45] inline-flex h-14 w-14 items-center justify-center rounded-full bg-emerald-700 text-white shadow-lg ring-4 ring-white transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-emerald-300" aria-controls="client-chat-panel" aria-expanded="false" aria-label="Open messages" title="Messages">
                            <i id="client-chat-launcher-icon" class="fa-solid fa-comments text-xl" aria-hidden="true"></i>
                            <span data-chat-unread-badge class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-rose-500 px-1.5 py-0.5 text-center text-[11px] font-semibold leading-none text-white tabular-nums ring-2 ring-white {{ ($unreadMessageCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $unreadMessageCount ?? 0 }}</span>
                        </button>
                        <script>
                            (() => {
                                const panel = document.getElementById('client-chat-panel');
                                const launcher = document.getElementById('client-chat-launcher');
                                const launcherIcon = document.getElementById('client-chat-launcher-icon');
                                if (!panel || !launcher) return;
                                // The layout's content wrapper (relative z-10) would keep the panel under the sticky header.
                                document.body.append(panel, launcher);
                                const storageKey = 'raflora_client_booking_chat_{{ $booking->id }}';
                                const ACTIVE = ['bg-white', 'text-emerald-800', 'shadow-sm'];
                                const INACTIVE = ['text-slate-500', 'hover:text-slate-800'];
                                const meetingsView = document.getElementById('client-chat-view-meetings');
                                let currentView = 'messages';

                                const remember = (open) => {
                                    try { sessionStorage.setItem(storageKey, JSON.stringify({ open, view: currentView })); } catch (e) {}
                                };

                                const setView = (view) => {
                                    currentView = view === 'meetings' && panel.querySelector('[data-chat-view="meetings"]') ? 'meetings' : 'messages';
                                    document.getElementById('client-chat-view-messages').classList.toggle('hidden', currentView !== 'messages');
                                    meetingsView.classList.toggle('hidden', currentView !== 'meetings');
                                    panel.querySelectorAll('.client-chat-view-btn').forEach((btn) => {
                                        const isActive = btn.dataset.chatView === currentView;
                                        btn.classList.remove(...(isActive ? INACTIVE : ACTIVE));
                                        btn.classList.add(...(isActive ? ACTIVE : INACTIVE));
                                        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                                    });
                                    if (panel.classList.contains('hidden')) return;
                                    if (currentView === 'messages') document.getElementById('comm-refresh-btn')?.click();
                                    remember(true);
                                };

                                const openChat = (view) => {
                                    panel.classList.remove('hidden');
                                    panel.classList.add('flex');
                                    launcher.setAttribute('aria-expanded', 'true');
                                    launcher.setAttribute('aria-label', 'Close messages');
                                    launcherIcon.className = 'fa-solid fa-xmark text-xl';
                                    setView(view || currentView);
                                    remember(true);
                                    if (currentView === 'messages') document.getElementById('comm-message-text')?.focus({ preventScroll: true });
                                };

                                const closeChat = () => {
                                    panel.classList.add('hidden');
                                    panel.classList.remove('flex');
                                    launcher.setAttribute('aria-expanded', 'false');
                                    launcher.setAttribute('aria-label', 'Open messages');
                                    launcherIcon.className = 'fa-solid fa-comments text-xl';
                                    remember(false);
                                };

                                window.openBookingChat = openChat;
                                launcher.addEventListener('click', () => panel.classList.contains('hidden') ? openChat() : closeChat());
                                document.getElementById('client-chat-close').addEventListener('click', () => { closeChat(); launcher.focus(); });
                                panel.querySelectorAll('.client-chat-view-btn').forEach((btn) => btn.addEventListener('click', () => setView(btn.dataset.chatView)));
                                document.addEventListener('keydown', (event) => {
                                    if (event.key !== 'Escape' || panel.classList.contains('hidden')) return;
                                    // Let an open pop-up (request changes, cancellation, etc.) close first.
                                    if ([...document.querySelectorAll('[aria-modal="true"]')].some((el) => el.getClientRects().length > 0)) return;
                                    closeChat();
                                    launcher.focus();
                                });

                                let saved = null;
                                try { saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
                                if (panel.dataset.meetingError === '1') {
                                    openChat('meetings');
                                } else if (['#communication', '#booking-conversation-root'].includes(location.hash)) {
                                    openChat('messages');
                                } else if (saved && saved.open) {
                                    openChat(saved.view);
                                } else {
                                    setView(saved?.view || 'messages');
                                }
                            })();
                        </script>
                        
                        {{-- Modals --}}
                        @php
                            // The fields shown follow the change type (material, schedule, other); after a failed
                            // submit the pop-up reopens on the same type with the client's input kept.
                            $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
                            $changeFieldErrors = $errors->hasAny(['change_type', 'change_item', 'change_quantity', 'requested_date', 'requested_start_time', 'requested_end_time', 'change_reason']);
                            $oldChangeType = old('change_type', '');
                            $oldChangeGroup = $oldChangeType === 'item' ? 'material' : $oldChangeType;
                            $currentScheduleText = $booking->event_date
                                ? $booking->event_date->format('M j, Y') . ($eventTimeDisplay ? ' at ' . $eventTimeDisplay : '')
                                : 'Not set';
                        @endphp
                        <div id="changeModal" class="{{ $changeFieldErrors ? '' : 'hidden' }} fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="changeModalTitle">
                            <div class="bg-white rounded-2xl w-full max-w-lg max-h-full flex flex-col overflow-hidden shadow-xl">
                                <div class="p-5 sm:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                                    <h3 id="changeModalTitle" class="text-lg font-bold text-slate-800">Request Changes</h3>
                                    <button type="button" onclick="document.getElementById('changeModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600" aria-label="Close modal">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('bookings.request-changes', $booking->id) }}" class="p-5 sm:p-6 space-y-4 overflow-y-auto" id="changeRequestForm">
                                    @csrf
                                    <div class="rf-field">
                                        <label class="rf-label" for="change_type">Change Type</label>
                                        <select name="change_type" id="change_type" class="rf-input" required>
                                            <option value="">Select a change type</option>
                                            <option value="material" @selected($oldChangeGroup === 'material')>Material / Item</option>
                                            <option value="schedule" @selected($oldChangeGroup === 'schedule')>Schedule</option>
                                            <option value="other" @selected($oldChangeGroup === 'other')>Other</option>
                                        </select>
                                        @error('change_type')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- Material / Item --}}
                                    <div data-change-fields="material" class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $oldChangeGroup === 'material' ? '' : 'hidden' }}">
                                        <div class="rf-field">
                                            <label class="rf-label" for="change_item">Item / Material</label>
                                            <input type="text" name="change_item" id="change_item" class="rf-input" value="{{ old('change_item') }}" required placeholder="e.g., Roses" maxlength="255">
                                            @error('change_item')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="rf-field">
                                            <label class="rf-label" for="change_quantity">Quantity</label>
                                            <input type="number" name="change_quantity" id="change_quantity" class="rf-input" min="1" value="{{ old('change_quantity', 1) }}" required>
                                            @error('change_quantity')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                        </div>
                                    </div>

                                    {{-- Schedule --}}
                                    <div data-change-fields="schedule" class="space-y-4 {{ $oldChangeGroup === 'schedule' ? '' : 'hidden' }}">
                                        <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">Current schedule: <strong class="text-slate-800">{{ $currentScheduleText }}</strong></p>
                                        <div class="rf-field">
                                            <label class="rf-label" for="requested_date">New Event Date</label>
                                            <input type="date" name="requested_date" id="requested_date" class="rf-input" value="{{ old('requested_date') }}" min="{{ now()->toDateString() }}" required>
                                            @error('requested_date')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="rf-field">
                                                <label class="rf-label" for="requested_start_time">New Start Time (optional)</label>
                                                <input type="time" name="requested_start_time" id="requested_start_time" class="rf-input" value="{{ old('requested_start_time') }}">
                                                @error('requested_start_time')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                            </div>
                                            <div class="rf-field">
                                                <label class="rf-label" for="requested_end_time">New End Time (optional)</label>
                                                <input type="time" name="requested_end_time" id="requested_end_time" class="rf-input" value="{{ old('requested_end_time') }}">
                                                @error('requested_end_time')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Details: optional for material/schedule, the whole request for "Other" --}}
                                    <div class="rf-field {{ $oldChangeGroup === '' ? 'hidden' : '' }}" data-change-details>
                                        <label class="rf-label" for="change_reason" data-change-details-label>{{ $oldChangeGroup === 'other' ? 'What would you like to change?' : 'Additional Details / Reason (Optional)' }}</label>
                                        <textarea name="change_reason" id="change_reason" rows="3" class="rf-input resize-none" maxlength="2000" placeholder="e.g., Please add 15 red roses to the arrangement." @required($oldChangeGroup === 'other')>{{ old('change_reason') }}</textarea>
                                        @error('change_reason')<p class="text-xs text-rose-600 mt-1 font-semibold" role="alert">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="flex justify-end gap-3 mt-6">
                                        <button type="button" onclick="document.getElementById('changeModal').classList.add('hidden')" class="rf-btn rf-btn-secondary">Cancel</button>
                                        <button type="submit" class="rf-btn rf-btn-primary">Send Request</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <script>
                            (() => {
                                // The layout's content wrapper (relative z-10) would keep the pop-up under the sticky header.
                                const modal = document.getElementById('changeModal');
                                if (modal) document.body.appendChild(modal);
                                const form = document.getElementById('changeRequestForm');
                                const select = document.getElementById('change_type');
                                if (!form || !select) return;
                                const details = form.querySelector('[data-change-details]');
                                const detailsLabel = form.querySelector('[data-change-details-label]');
                                const reason = document.getElementById('change_reason');
                                const placeholders = {
                                    material: 'e.g., Please add 15 red roses to the arrangement.',
                                    schedule: 'e.g., The venue moved our reception to the afternoon.',
                                    other: 'e.g., Please change the ribbon color to champagne gold.',
                                };
                                // Only the selected type's fields are shown, required and submitted (hidden ones are disabled).
                                const sync = () => {
                                    const type = select.value === 'item' ? 'material' : select.value;
                                    form.querySelectorAll('[data-change-fields]').forEach((group) => {
                                        const active = group.dataset.changeFields === type;
                                        group.classList.toggle('hidden', !active);
                                        group.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !active; });
                                    });
                                    details.classList.toggle('hidden', type === '');
                                    reason.required = type === 'other';
                                    detailsLabel.textContent = type === 'other' ? 'What would you like to change?' : 'Additional Details / Reason (Optional)';
                                    reason.placeholder = placeholders[type] || '';
                                };
                                select.addEventListener('change', sync);
                                sync();
                            })();
                        </script>


                    </div>
                </div>

                {{-- 11. Supporting information in tabs: one panel at a time keeps the page short. Event type,
                     date, venue, guests and tables are in the header above, so they are not repeated here. --}}
                @php
                    $hasPackageInfo = $booking->package_id && $booking->package;
                    $hasProposals = $presentations && $presentations->count() > 0;
                    // Quotation lines with the same name and unit price are shown once, quantities added.
                    $quoteRows = collect($quotationItems ?? [])->map(function ($item) use ($sharedQuotation) {
                        $unitPrice = isset($item['selling_unit_price'])
                            ? (float) $item['selling_unit_price']
                            : (float) ($item['quoted_unit_price'] ?? 0) * (float) ($sharedQuotation?->multiplier ?? 3.0);

                        return ['name' => (string) ($item['item_name'] ?? 'Item'), 'quantity' => (float) ($item['quantity'] ?? 0), 'unit_price' => $unitPrice];
                    })->groupBy(fn (array $row) => mb_strtolower(trim($row['name'])) . '|' . number_format($row['unit_price'], 2, '.', ''))
                      ->map(fn ($rows) => ['name' => $rows->first()['name'], 'quantity' => $rows->sum('quantity'), 'unit_price' => $rows->first()['unit_price']])
                      ->values();
                    $quotePreviewCount = 10;
                    $detailTabs = array_filter([
                        'quotation' => ['Quotation', 'fa-file-invoice', $quoteRows->count() ?: null],
                        'inspiration' => ($previewImage || $hasAiAnalysis) ? ['Inspiration & AI', 'fa-wand-magic-sparkles', count($galleryPhotos) > 1 ? count($galleryPhotos) : null] : null,
                        'materials' => $hasAiAnalysis ? ['Materials per Area', 'fa-layer-group', null] : null,
                        'details' => ($hasPackageInfo || $booking->special_requests) ? [$hasPackageInfo ? 'Package & Requests' : 'Special Requests', 'fa-clipboard-list', null] : null,
                        'proposals' => $hasProposals ? ['Proposals', 'fa-file-pdf', $presentations->count()] : null,
                    ]);
                    $defaultDetailTab = $quoteRows->isNotEmpty() || !isset($detailTabs['inspiration']) ? 'quotation' : 'inspiration';
                @endphp
                <section id="client-booking-tabs" aria-label="Booking information" class="rounded-2xl border border-slate-200 bg-white shadow-sm" data-booking-tabs="{{ $booking->id }}">
                    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-200 px-3 pt-2 sm:px-5" role="tablist" aria-label="Booking information">
                        @foreach($detailTabs as $tabKey => [$tabLabel, $tabIcon, $tabCount])
                            @php $tabSelected = $tabKey === $defaultDetailTab; @endphp
                            <button type="button" role="tab" id="booking-tab-{{ $tabKey }}" aria-controls="booking-panel-{{ $tabKey }}" aria-selected="{{ $tabSelected ? 'true' : 'false' }}" tabindex="{{ $tabSelected ? '0' : '-1' }}" data-booking-tab="{{ $tabKey }}"
                                    class="-mb-px inline-flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm font-semibold text-slate-500 transition hover:text-slate-800 aria-selected:border-emerald-600 aria-selected:text-emerald-800">
                                <i class="fa-solid {{ $tabIcon }} text-xs" aria-hidden="true"></i>{{ $tabLabel }}
                                @if($tabCount)<span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ $tabCount }}</span>@endif
                            </button>
                        @endforeach
                    </div>

                    {{-- Quotation --}}
                    <div id="booking-panel-quotation" role="tabpanel" aria-labelledby="booking-tab-quotation" tabindex="0" class="p-4 sm:p-6 {{ $defaultDetailTab === 'quotation' ? '' : 'hidden' }}" data-booking-panel="quotation">
                        <section aria-labelledby="breakdown-heading">
                            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h2 id="breakdown-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Quotation Breakdown</h2>
                                @if($quoteRows->count() > $quotePreviewCount)
                                    <label class="relative block sm:w-64">
                                        <span class="sr-only">Search quotation items</span>
                                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                                        <input type="search" id="quoteItemSearch" placeholder="Search items…" class="rf-input !py-2 !pl-8 text-sm" autocomplete="off">
                                    </label>
                                @endif
                            </div>

                            @if($quoteRows->isNotEmpty())
                                <ul class="grid grid-cols-1 gap-x-8 md:grid-cols-2" role="list" id="quoteItemList">
                                    @foreach($quoteRows as $index => $row)
                                        <li class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 {{ $index >= $quotePreviewCount ? 'hidden' : '' }}" data-quote-row data-name="{{ mb_strtolower($row['name']) }}">
                                            <span class="min-w-0 text-sm font-medium text-slate-700">{{ $row['name'] }}</span>
                                            <span class="shrink-0 text-sm font-medium tabular-nums text-slate-500">{{ number_format($row['quantity'], 0) }} × ₱{{ number_format($row['unit_price'], 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p id="quoteItemEmpty" class="hidden py-4 text-center text-sm text-slate-500">No item matches your search.</p>
                                @if($quoteRows->count() > $quotePreviewCount)
                                    <button type="button" id="quoteShowAll" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 hover:text-emerald-800" aria-expanded="false" aria-controls="quoteItemList"
                                            data-more="Show all {{ $quoteRows->count() }} items" data-less="Show fewer">
                                        <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i><span>Show all {{ $quoteRows->count() }} items</span>
                                    </button>
                                @endif
                            @elseif(!$sharedQuotation)
                                <p class="text-sm text-slate-500">Item prices appear here once Raflora issues your official quotation.</p>
                            @elseif($hasAiAnalysis)
                                <p class="text-sm text-slate-500">Pricing is based on the reviewed event details. See the Materials per Area tab for the suggested materials.</p>
                            @elseif($booking->package_id)
                                <p class="text-sm text-slate-500">Pricing is based on the selected package. See the Package &amp; Requests tab.</p>
                            @else
                                <p class="text-sm text-slate-500">A detailed quotation breakdown is not available yet.</p>
                            @endif
                        </section>
                    </div>

                    {{-- Inspiration photos and the AI estimate, side by side --}}
                    @isset($detailTabs['inspiration'])
                        <div id="booking-panel-inspiration" role="tabpanel" aria-labelledby="booking-tab-inspiration" tabindex="0" class="p-4 sm:p-6 {{ $defaultDetailTab === 'inspiration' ? '' : 'hidden' }}" data-booking-panel="inspiration">
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                @if($previewImage)
                                    <section aria-labelledby="concept-heading" class="min-w-0 {{ $hasAiAnalysis ? '' : 'lg:col-span-2' }}">
                                        <h2 id="concept-heading" class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Inspiration Image &amp; AI Recommendation</h2>
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

                                @if($hasAiAnalysis)
                                    <section aria-labelledby="ai-heading" class="min-w-0 {{ $previewImage ? '' : 'lg:col-span-2' }}">
                                        <h2 id="ai-heading" class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">AI Visual Analysis (Decision Support)</h2>
                                        <p class="mb-4 text-sm text-slate-500">Suggested materials based on Gemini AI visual analysis. These suggestions serve as decision support for Raflora florist review and quotation preparation, not final pricing.</p>

                                        {{-- Indicative AI ranges per area; the official quotation is the price that applies --}}
                                        <x-ai-estimate :estimate="$aiEstimate ?? null" :quotation-issued="(bool) $sharedQuotation" />
                                    </section>
                                @endif
                            </div>
                        </div>
                    @endisset

                    {{-- AI-suggested materials: one area at a time --}}
                    @if($hasAiAnalysis)
                        <div id="booking-panel-materials" role="tabpanel" aria-labelledby="booking-tab-materials" tabindex="0" class="hidden p-4 sm:p-6" data-booking-panel="materials">
                            <section aria-labelledby="ai-materials-heading">
                                <h2 id="ai-materials-heading" class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">AI Suggested Materials</h2>

                                <x-decor-area-breakdown :materials="$analysisMaterials" :booking="$booking" view="client" compact />

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
                        </div>
                    @endif

                    {{-- Package and special requests (the event basics are in the header) --}}
                    @isset($detailTabs['details'])
                        <div id="booking-panel-details" role="tabpanel" aria-labelledby="booking-tab-details" tabindex="0" class="hidden p-4 sm:p-6" data-booking-panel="details">
                            <div class="grid grid-cols-1 gap-6 {{ $hasPackageInfo && $booking->special_requests ? 'lg:grid-cols-2' : '' }}">
                                @if($hasPackageInfo)
                                    <section aria-labelledby="package-info-heading">
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

                                @if($booking->special_requests)
                                    <section aria-labelledby="special-requests-heading">
                                        <h2 id="special-requests-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Special Requests</h2>
                                        <p class="text-sm text-slate-700 italic">"{{ $booking->special_requests }}"</p>
                                    </section>
                                @endif
                            </div>
                        </div>
                    @endisset

                    {{-- Proposals --}}
                    @if($hasProposals)
                        <div id="booking-panel-proposals" role="tabpanel" aria-labelledby="booking-tab-proposals" tabindex="0" class="hidden p-4 sm:p-6" data-booking-panel="proposals">
                            <section aria-labelledby="proposals-heading">
                                <h2 id="proposals-heading" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-4">Proposals &amp; Feedback</h2>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                        </div>
                    @endif
                </section>

                <script>
                    // Booking information tabs (arrow keys move between tabs; the last tab is remembered per
                    // booking for this browser tab, since the page reloads itself when the booking changes)
                    // and the quotation list's search / "Show all".
                    (() => {
                        const root = document.getElementById('client-booking-tabs');
                        if (!root) return;
                        const tabs = [...root.querySelectorAll('[data-booking-tab]')];
                        const storeKey = 'raflora.bookingTab.' + root.dataset.bookingTabs;
                        const select = (key, focus = false) => {
                            const tab = tabs.find((t) => t.dataset.bookingTab === key);
                            if (!tab) return;
                            tabs.forEach((t) => {
                                const on = t === tab;
                                t.setAttribute('aria-selected', on ? 'true' : 'false');
                                t.tabIndex = on ? 0 : -1;
                                document.getElementById(t.getAttribute('aria-controls'))?.classList.toggle('hidden', !on);
                            });
                            if (focus) tab.focus();
                            try { sessionStorage.setItem(storeKey, key); } catch (e) { /* storage unavailable */ }
                        };
                        tabs.forEach((tab, index) => {
                            tab.addEventListener('click', () => select(tab.dataset.bookingTab));
                            tab.addEventListener('keydown', (event) => {
                                const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
                                if (event.key === 'Home' || event.key === 'End') {
                                    event.preventDefault();
                                    select(tabs[event.key === 'Home' ? 0 : tabs.length - 1].dataset.bookingTab, true);
                                } else if (step) {
                                    event.preventDefault();
                                    select(tabs[(index + step + tabs.length) % tabs.length].dataset.bookingTab, true);
                                }
                            });
                        });
                        // A link to something inside a panel (e.g. #proposals-heading) opens that panel.
                        const hashTarget = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
                        const hashPanel = hashTarget?.closest('[data-booking-panel]');
                        let remembered = null;
                        try { remembered = sessionStorage.getItem(storeKey); } catch (e) { /* storage unavailable */ }
                        if (hashPanel) {
                            select(hashPanel.dataset.bookingPanel);
                            hashTarget.scrollIntoView({ block: 'start' });
                        } else if (remembered) {
                            select(remembered);
                        }

                        const search = document.getElementById('quoteItemSearch');
                        const more = document.getElementById('quoteShowAll');
                        const rows = [...root.querySelectorAll('[data-quote-row]')];
                        let expanded = false;
                        const filterQuote = () => {
                            const q = (search?.value || '').trim().toLowerCase();
                            let shown = 0;
                            rows.forEach((row, index) => {
                                const visible = q ? row.dataset.name.includes(q) : (expanded || index < {{ $quotePreviewCount }});
                                row.classList.toggle('hidden', !visible);
                                shown += visible ? 1 : 0;
                            });
                            document.getElementById('quoteItemEmpty')?.classList.toggle('hidden', shown > 0);
                            if (more) more.classList.toggle('hidden', q !== '');
                        };
                        search?.addEventListener('input', filterQuote);
                        more?.addEventListener('click', () => {
                            expanded = !expanded;
                            more.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                            more.querySelector('span').textContent = expanded ? more.dataset.less : more.dataset.more;
                            more.querySelector('i').classList.toggle('rotate-180', expanded);
                            filterQuote();
                        });
                    })();
                </script>
                    </div>
                </div>

                {{-- Remaining Balance Payment Modal --}}
                @if($canPayRemainingBalance)
                    <div id="remainingBalanceModal" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="remainingBalanceModalTitle">
                        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
                            <div class="p-5 sm:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 id="remainingBalanceModalTitle" class="text-lg font-bold text-slate-800">Pay Remaining Balance</h3>
                                <button type="button" onclick="document.getElementById('remainingBalanceModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600" aria-label="Close modal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <form method="POST" action="{{ route('bookings.payment.reference', ['booking' => $booking->id]) }}" class="p-5 sm:p-6 space-y-4">
                                @csrf
                                <dl class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-sm">
                                    <div class="flex justify-between gap-3"><dt class="text-slate-600">Total</dt><dd class="font-semibold tabular-nums">₱{{ number_format((float) $displayedTotal, 2) }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-600">Paid</dt><dd class="font-semibold tabular-nums text-emerald-700">₱{{ number_format($paidAmount, 2) }}</dd></div>
                                    <div class="flex justify-between gap-3 border-t border-slate-200 pt-2"><dt class="font-semibold text-slate-800">Remaining balance to pay</dt><dd class="font-bold tabular-nums text-slate-900">₱{{ number_format($remaining, 2) }}</dd></div>
                                </dl>

                                @if($balancePaymentRejected)
                                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-900" role="alert">
                                        <p class="font-bold text-sm text-rose-800">Previous Payment Submission Not Verified</p>
                                        <p class="mt-1 text-xs text-rose-700">
                                            Your previous payment reference (<span class="font-semibold">{{ $latestPayment->reference_number }}</span>
                                            via <span class="font-semibold uppercase">{{ strtoupper($latestPayment->payment_type) }}</span>)
                                            could not be verified by Raflora Administration.
                                        </p>
                                        <p class="mt-1 text-xs text-rose-600">Please double-check your payment receipt or transaction slip, verify the reference number, and submit a corrected reference below to complete your final settlement.</p>
                                    </div>
                                @endif

                                <div class="rf-field mb-0">
                                    <label for="balance_payment_option" class="rf-label">Payment Option</label>
                                    {{-- The server charges exactly the remaining balance as a final settlement. --}}
                                    <select id="balance_payment_option" name="payment_option" class="rf-select">
                                        <option value="full_payment" selected>Final Balance Settlement (₱{{ number_format($remaining, 2) }})</option>
                                    </select>
                                </div>

                                <div class="rf-field mb-0">
                                    <label for="balance_payment_type" class="rf-label">Payment Method</label>
                                    <select id="balance_payment_type" name="payment_type" class="rf-select">
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="rf-field mb-0">
                                    <label for="balance_reference_number" class="rf-label">Reference Number</label>
                                    <input id="balance_reference_number" type="text" name="reference_number" maxlength="255" placeholder="Reference # from your GCash or bank receipt" class="rf-input" required>
                                </div>
                                <p class="text-xs text-slate-500">Raflora checks this reference against its account before recording your payment. Your balance updates once it is verified.</p>

                                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
                                    <button type="button" onclick="document.getElementById('remainingBalanceModal').classList.add('hidden')" class="rf-btn rf-btn-secondary">Cancel</button>
                                    <button type="submit" class="rf-btn rf-btn-primary">Submit Payment Reference</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Cancellation Modal --}}
                @if($canRequestCancellation)
                    <div id="cancellationModal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs sm:items-center" role="dialog" aria-modal="true" aria-labelledby="cancellationModalTitle">
                        <div class="my-auto max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 p-5 sm:p-6">
                                <h3 id="cancellationModalTitle" class="min-w-0 break-words text-lg font-bold text-rose-600">Request Cancellation</h3>
                                <button type="button" onclick="document.getElementById('cancellationModal').classList.add('hidden')" class="shrink-0 text-slate-400 hover:text-slate-600" aria-label="Close modal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <form method="POST" action="{{ route('bookings.request-cancellation', $booking->id) }}" class="p-5 sm:p-6">
                                @csrf
                                <div class="rf-field">
                                    <label for="cancellation_reason" class="rf-label">Please provide a reason for cancelling this booking request:</label>
                                    <textarea id="cancellation_reason" name="cancellation_reason" rows="4" class="rf-input w-full min-w-0 resize-none" required placeholder="Reason for cancellation..."></textarea>
                                </div>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button type="button" onclick="document.getElementById('cancellationModal').classList.add('hidden')" class="rf-btn rf-btn-secondary w-full sm:w-auto">Keep Booking</button>
                                    <button type="submit" class="rf-btn rf-btn-primary w-full whitespace-normal text-center sm:w-auto !bg-rose-600 hover:!bg-rose-700 !border-rose-600">Submit Cancellation Request</button>
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
            const remainingBalanceModal = document.getElementById('remainingBalanceModal');

            [changeModal, cancellationModal, remainingBalanceModal].forEach((m) => {
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
                    if (remainingBalanceModal && !remainingBalanceModal.classList.contains('hidden')) {
                        remainingBalanceModal.classList.add('hidden');
                    }
                }
            });
        });
    </script>

</x-app-layout>
