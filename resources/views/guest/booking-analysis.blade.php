<x-app-layout title="Guest Booking Tracking & Confirmation">
    <x-navbar title="GUEST BOOKING" />
    @php
        $isCurated = $booking->booking_type === 'preset' && !empty($booking->package_id);
        $package = $isCurated ? \App\Models\Package::with('images')->find($booking->package_id) : null;
        $packageName = $package?->title ?? ($isCurated ? 'Curated Package' : null);
        $cleanSpecialRequests = $booking->special_requests ?? '';
        
        // Quotation resolution
        $activeQuotation = $activeQuotation ?? ($booking instanceof \App\Models\Booking ? ($booking->activeQuotation ?? $booking->acceptedQuotation) : null);
        $hasFinalQuotation = ($activeQuotation || ($booking instanceof \App\Models\Booking && $booking->final_quoted_price > 0)) && in_array($booking->status, ['quotation_sent', 'approved', 'admin_approved', 'payment_pending', 'payment_submitted', 'downpayment_received', 'confirmed', 'fully_paid'], true);
        $canAcceptQuotation = false;

        $isTemporary = $booking instanceof \App\Models\TemporaryGuestBooking;
        $rawStatus = $isTemporary ? 'pending' : ($booking->status ?? 'pending');

        // Expiration resolution
        $expiresAt = $isTemporary 
            ? $booking->expires_at 
            : ($quotationValidUntil ?? ($activeQuotation?->valid_until ?? ($booking instanceof \App\Models\Booking ? $booking->price_valid_until : null)));

        // A temporary guest request expires when unclaimed; a permanent guest booking never
        // expires, but its issued quotation can (README: claim is still required to continue).
        $isBookingExpired = $isTemporary
            && ((isset($isExpired) && $isExpired) || $booking->isExpired());
        $isQuotationExpired = !$isTemporary && $rawStatus === 'quotation_sent'
            && ((isset($isExpired) && $isExpired) || ($expiresAt && $expiresAt->copy()->endOfDay()->isPast()));

        $bookingWorkflow = app(\App\Services\BookingWorkflowService::class)->resolve($booking);

        $now = \Carbon\Carbon::now();
        $remainingSeconds = ($expiresAt && !$isBookingExpired && $expiresAt->isFuture()) 
            ? max(0, $now->diffInSeconds($expiresAt, false)) 
            : 0;
        $remainingHours = floor($remainingSeconds / 3600);
        $remainingMinutes = floor(($remainingSeconds % 3600) / 60);
        $remainingText = $remainingHours > 0 
            ? "{$remainingHours}h {$remainingMinutes}m remaining" 
            : "{$remainingMinutes}m remaining";
        $isExpiringSoon = !$isBookingExpired && $expiresAt && ($remainingSeconds > 0 && $remainingSeconds <= 14400); // 4 hours or less

        $isClaimed = (!$isTemporary && !empty($booking->client_id)) || ($isTemporary && $booking->isClaimed());

        $previewImage = $booking->inspiration_image
            ? ($isTemporary 
                ? route('guest.bookings.image', ['token' => $token]) 
                : route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => $booking->guest_access_token]))
            : null;

        $rawAnalysisData = $isTemporary ? $booking->analysis_data : ($booking->ai_analysis_data ?? $booking->analysis_data);
        $multiImages = [];
        if (is_array($rawAnalysisData) && !empty($rawAnalysisData['images']) && is_array($rawAnalysisData['images'])) {
            foreach ($rawAnalysisData['images'] as $idx => $imgEntry) {
                $imageUrl = $isTemporary 
                    ? route('guest.bookings.image', ['token' => $token, 'imageIndex' => $idx])
                    : route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => $booking->guest_access_token, 'image_index' => $idx]);
                $multiImages[] = [
                    'index' => $idx,
                    'id' => (int) ($imgEntry['id'] ?? $idx + 1),
                    'url' => $imageUrl,
                    'filename' => $imgEntry['original_filename'] ?? ('Image ' . ($idx + 1)),
                    'materials' => $imgEntry['suggested_materials'] ?? [],
                    'pricing' => $imgEntry['pricing_summary'] ?? [],
                ];
            }
        }
        $imagePath = $booking->inspiration_image ? storage_path('app/private/' . ltrim($booking->inspiration_image, '/')) : null;
        $imageDimensions = $imagePath ? \App\Support\ImageOrientation::displaySize($imagePath) : null;
        $imageAspectRatio = is_array($imageDimensions) && !empty($imageDimensions[0]) && !empty($imageDimensions[1])
            ? $imageDimensions[0] . ' / ' . $imageDimensions[1]
            : null;
        $analysisGroups = app(\App\Services\GeminiVisionService::class)->normalizeAreaAnalysis(['suggested_materials' => $analysisMaterials]);
        $overlayItems = collect($analysisGroups)->flatMap(fn($group) => $group['items'] ?? [])->values();

        // Status descriptions for the Guest Workflow (README: Request Submitted → Awaiting Claim)
        if ($isTemporary) {
            $currentStatusText = $isBookingExpired ? 'Request Expired Unclaimed' : 'Request Submitted — Awaiting Claim';
            $currentStatusDesc = $isBookingExpired
                ? 'This request was not claimed before it expired.'
                : 'Your request is saved securely and is waiting to be claimed.';
            $statusColorClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            $whatRafloraDoes = 'Your request and inspiration details are saved securely. Raflora Review begins as soon as you claim this request with a registered client account.';
            $whatGuestExpects = 'Create an account or log in to claim this request before it expires. Once claimed, you can continue through the Client Booking process.';
        } elseif ($rawStatus === 'pending') {
            $currentStatusText = 'Request Received & Under Review';
            $statusColorClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            $whatRafloraDoes = 'Our styling team is assessing your event specifications, checking seasonal floral availability, and formulating initial material calculations.';
            $whatGuestExpects = 'Create an account or log in to claim this booking. Once claimed, you can continue through the Client Booking process.';
        } elseif ($rawStatus === 'quotation_sent') {
            $currentStatusText = 'Quotation Ready (Claim Required)';
            $statusColorClass = 'bg-blue-100 text-blue-800 border-blue-200';
            $whatRafloraDoes = 'Our team has finalized an official quotation proposal for your event.';
            $whatGuestExpects = 'Please claim this booking with your account to review the itemized quotation and accept or request modifications. Unclaimed guests cannot accept quotations.';
        } elseif ($rawStatus === 'approved') {
            $currentStatusText = 'Quotation Accepted (Admin Review)';
            $statusColorClass = 'bg-amber-100 text-amber-800 border-amber-200';
            $whatRafloraDoes = 'Admin is conducting final review of the accepted proposal and scheduling inventory.';
            $whatGuestExpects = 'Once admin approves, downpayment instructions will be unlocked on your client portal.';
        } elseif ($rawStatus === 'admin_approved') {
            $currentStatusText = 'Quotation Approved (Downpayment Ready)';
            $statusColorClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            $whatRafloraDoes = 'Quotation is officially approved by administration. Ready for client downpayment.';
            $whatGuestExpects = 'Log in to your client account to submit your downpayment reference. Payment is restricted to authenticated clients.';
        } elseif (in_array($rawStatus, ['payment_submitted', 'payment_pending'], true)) {
            $currentStatusText = 'Payment Verification in Progress';
            $statusColorClass = 'bg-amber-100 text-amber-800 border-amber-200';
            $whatRafloraDoes = 'Our finance team is verifying your payment reference.';
            $whatGuestExpects = 'Upon verification, your event date will be officially locked and confirmed.';
        } elseif (in_array($rawStatus, ['downpayment_received', 'confirmed', 'fully_paid'], true)) {
            $currentStatusText = 'Booking Confirmed';
            $statusColorClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
            $whatRafloraDoes = 'Booking is confirmed! Raflora reserves the required materials when the preparation period begins.';
            $whatGuestExpects = 'Raflora will prepare and execute your floral styling arrangements for your event.';
        } elseif (in_array($rawStatus, ['cancelled', 'declined'], true)) {
            $currentStatusText = 'Booking ' . ucfirst($rawStatus);
            $statusColorClass = 'bg-rose-100 text-rose-800 border-rose-200';
            $whatRafloraDoes = 'This booking inquiry has been closed or cancelled.';
            $whatGuestExpects = 'Please submit a new inquiry if you wish to reschedule.';
        } else {
            $currentStatusText = 'Request Received';
            $statusColorClass = 'bg-slate-100 text-slate-800 border-slate-200';
            $whatRafloraDoes = 'Our team is processing your event request.';
            $whatGuestExpects = 'Check back shortly for status updates.';
        }

        $currentStatusDesc = $currentStatusDesc ?? (in_array($rawStatus, ['cancelled', 'declined'], true)
            ? 'This booking is no longer active.'
            : 'Claim this booking with a registered client account to continue.');

        // Claim status mapping
        $claimStatusText = 'Not Yet Claimed';
        $claimStatusDesc = $isTemporary
            ? 'Create an account or log in to claim this request before it expires.'
            : 'Create an account or log in with the booking email to claim this booking.';
        $claimStatusBadgeClass = 'bg-amber-100 text-amber-900 border-amber-200';
        if ($isBookingExpired) {
            $claimStatusText = 'Expired Unclaimed';
            $claimStatusDesc = 'This temporary guest request was not claimed before its expiration time.';
            $claimStatusBadgeClass = 'bg-rose-100 text-rose-900 border-rose-200';
        } elseif ($isClaimed) {
            $claimStatusText = 'Claimed';
            $claimStatusDesc = 'This request is linked to your authenticated client account.';
            $claimStatusBadgeClass = 'bg-emerald-100 text-emerald-900 border-emerald-200';
        }
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">

        {{-- Flash Messages --}}
        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        {{-- ─── CARD 1: BOOKING REQUEST HEADER & CLAIM EXPIRATION PANEL ─── --}}
        <div class="mb-6 rounded-3xl border border-stone-200 bg-white p-6 sm:p-7 shadow-xs">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                {{-- Left Side: Request Identity & Metadata (col-span-12 lg:col-span-7) --}}
                <div class="lg:col-span-7 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-start gap-3.5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                                <i class="fa-solid fa-clipboard-check text-xl" aria-hidden="true"></i>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-emerald-800 border border-emerald-200">
                                        <i class="fa-solid fa-file-invoice text-[10px]"></i> Guest Request
                                    </span>
                                    <span class="text-xs text-stone-400">•</span>
                                    <span class="text-xs font-semibold text-stone-500"> <strong class="text-stone-800 font-mono">REF-{{ strtoupper(substr($token, 0, 8)) }}</strong></span>
                                </div>
                                <h1 class="text-2xl sm:text-3xl font-bold font-serif text-[#0B1E43] mt-1">
                                    Booking Request Received
                                </h1>
                            </div>
                        </div>

                        <p class="text-xs sm:text-sm text-stone-800 leading-relaxed mt-3">
                            Hello <strong class="text-stone-900">{{ $booking->guest_name }}</strong>, your booking request has been received. Raflora will review your event details and, where applicable, analyze your inspiration image before preparing your quotation.
                        </p>

                        {{-- Non-Negotiable Payment Notice --}}
                        <div class="mt-3.5 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-emerald-950 flex items-start gap-2.5">
                            <i class="fa-solid fa-shield-halved text-emerald-700 mt-0.5 text-sm shrink-0" aria-hidden="true"></i>
                            <div class="text-xs leading-relaxed">
                                <strong class="font-bold uppercase tracking-wider text-emerald-900 text-[11px] block">NO PAYMENT IS REQUIRED AT THIS STAGE</strong>
                                <span class="text-emerald-800">At this stage, no payment is required. All pricing estimates and AI material suggestions are preliminary. An official quotation will be prepared by Raflora. Payment submission is strictly restricted to authenticated clients after official quotation approval.</span>
                            </div>
                        </div>
                    </div>

                    {{-- Compact Metadata Row --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-4 mt-3 border-t border-stone-100">
                        <div class="flex items-center gap-2.5 rounded-xl border border-stone-100 bg-stone-50/80 p-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                                <i class="fa-solid fa-calendar-day"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block truncate">Event Type</span>
                                <span class="font-bold text-stone-900 text-xs truncate block">{{ ucfirst($booking->event_type ?? 'Event') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 rounded-xl border border-stone-100 bg-stone-50/80 p-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block truncate">Event Date</span>
                                <span class="font-bold text-stone-900 text-xs truncate block">{{ \Carbon\Carbon::parse($booking->event_date)->format('M d, Y') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 rounded-xl border border-stone-100 bg-stone-50/80 p-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block truncate">Venue</span>
                                <span class="font-bold text-stone-900 text-xs truncate block" title="{{ $booking->venue }}">{{ $booking->venue ?? 'To be arranged' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 rounded-xl border border-stone-100 bg-stone-50/80 p-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                                <i class="fa-solid fa-box-archive"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block truncate">Booking Type</span>
                                <span class="font-bold text-stone-900 text-xs truncate block">{{ $isCurated ? 'Curated Package' : 'Custom AI Design' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Side: Actions & Claim/Expiration Panel (col-span-12 lg:col-span-5) --}}
                <div class="lg:col-span-5 flex flex-col justify-between gap-3">
                    {{-- Action buttons row --}}
                    <div class="flex items-center justify-start lg:justify-end gap-2">
                        <a href="#guest-request-journey" class="inline-flex items-center gap-1.5 rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-xs font-bold text-stone-700 hover:bg-stone-50 transition shadow-xs">
                            <i class="fa-regular fa-eye text-emerald-600"></i> Track My Booking
                        </a>
                        @if(!$isBookingExpired && !in_array($rawStatus, ['cancelled', 'declined', 'rejected'], true))
                            @guest
                                <a href="{{ route('register', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" data-claim-action class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 text-xs font-bold text-white transition shadow-xs">
                                    <i class="fa-solid fa-user-plus text-[11px]"></i> Claim Booking
                                </a>
                            @else
                                <a href="{{ route('client.claim-guest-booking.show', ['token' => $token]) }}" data-claim-action class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 text-xs font-bold text-white transition shadow-xs">
                                    <i class="fa-solid fa-link text-[11px]"></i> Claim Booking
                                </a>
                            @endguest
                        @endif
                    </div>

                    {{-- Highlighted Expiration & Claim Panel --}}
                    @if($isTemporary)
                    <div id="claim-expiration-panel" class="rounded-2xl border {{ $isBookingExpired ? 'border-rose-200 bg-rose-50/70' : ($isExpiringSoon ? 'border-amber-300 bg-amber-50/80' : 'border-emerald-200 bg-emerald-50/60') }} p-4 sm:p-5 transition shadow-xs">
                        <div class="flex items-start gap-3.5">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 {{ $isBookingExpired ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }} text-lg">
                                <i class="fa-solid {{ $isBookingExpired ? 'fa-triangle-exclamation' : 'fa-hourglass-half' }}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[11px] font-extrabold uppercase tracking-wider block {{ $isBookingExpired ? 'text-rose-800' : 'text-emerald-800' }}">
                                        CLAIM REQUIRED BEFORE EXPIRATION
                                    </span>
                                    <span id="expirationStatusBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $isBookingExpired ? 'border-rose-300 bg-rose-100 text-rose-800' : ($isExpiringSoon ? 'border-amber-300 bg-amber-100 text-amber-900' : 'border-emerald-300 bg-emerald-100/80 text-emerald-800') }}" role="status">
                                        @if($isBookingExpired)
                                            <i class="fa-solid fa-triangle-exclamation"></i> Guest Request Expired
                                        @elseif($isExpiringSoon)
                                            <i class="fa-solid fa-clock"></i> Claim Soon
                                        @else
                                            <i class="fa-solid fa-circle-check"></i> Request Active
                                        @endif
                                    </span>
                                </div>

                                @if($isBookingExpired)
                                    <p class="text-base font-bold text-rose-900 mt-1">Expired Unclaimed</p>
                                    <p class="text-xs text-rose-700 mt-0.5">Expired on {{ $expiresAt ? $expiresAt->format('F j, Y \a\t g:i A') : 'N/A' }}.</p>
                                    <p class="text-xs text-rose-800 mt-2 leading-relaxed">
                                        This temporary guest request was not claimed before its expiration time.
                                    </p>
                                    <div class="text-xs text-rose-800 mt-2 bg-rose-100/60 p-2.5 rounded-xl border border-rose-200 leading-relaxed">
                                        No recovery action exists for expired temporary requests. Please submit a new booking request if you wish to proceed with Raflora floral styling.
                                    </div>
                                    <div class="mt-3">
                                        <a href="{{ route('guest.booking.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white px-3.5 py-2 text-xs font-bold transition">
                                            <i class="fa-solid fa-arrow-rotate-left"></i> Start New Booking Request
                                        </a>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <span class="text-2xl sm:text-3xl font-extrabold font-mono tracking-tight {{ $isExpiringSoon ? 'text-amber-900' : 'text-[#0B1E43]' }}" id="expirationCountdownText" data-expires-at="{{ $expiresAt?->toISOString() }}">
                                            {{ $remainingText }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-stone-600 mt-0.5">
                                        Expires on <strong class="text-stone-800 font-semibold">{{ $expiresAt ? $expiresAt->format('F j, Y \a\t g:i A') : 'N/A' }}</strong>
                                    </p>
                                    <p class="text-xs text-stone-600 mt-2 leading-relaxed">
                                        Create an account or log in to claim this request before it expires. You can still track this request as a guest until the expiration time. Claim before <strong class="text-stone-800 font-semibold">{{ $expiresAt ? $expiresAt->format('F j, Y') : 'expiration' }}</strong>.
                                    </p>

                                    {{-- Primary Claim CTAs --}}
                                    <div class="mt-3.5 pt-3 border-t border-emerald-200/60 flex flex-col sm:flex-row gap-2">
                                        @guest
                                            <a href="{{ route('register', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 text-xs font-bold transition shadow-xs text-center">
                                                <i class="fa-solid fa-user-plus text-[11px]"></i> Create Account &amp; Claim
                                            </a>
                                            <a href="{{ route('login', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 flex-1 rounded-xl border border-stone-300 bg-white hover:bg-emerald-50 text-stone-800 px-3 py-2 text-xs font-bold transition shadow-xs text-center">
                                                <i class="fa-solid fa-right-to-bracket text-[11px]"></i> <span class="sr-only">Already have an account? </span>Log In &amp; Claim
                                            </a>
                                        @else
                                            <a href="{{ route('client.claim-guest-booking.show', ['token' => $token]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-bold transition shadow-xs text-center">
                                                <i class="fa-solid fa-link text-[11px]"></i> Claim Booking Request
                                            </a>
                                        @endguest
                                    </div>
                                @endif

                                {{-- Accessible Claim context and testing assertions preservation --}}
                                @if(!$isBookingExpired)
                                    <div class="sr-only">
                                        <h3>Claim &amp; Manage Your Booking Request</h3>
                                        <span>CLAIM YOUR REQUEST</span>
                                        <span>Claim this request before it expires</span>
                                        <span>Create Account</span>
                                        <p>Already have an account? Log In &amp; Claim</p>
                                        <p>Your request is saved securely. Create an account now to claim this request and manage it from your client account. If you already have an account, log in to claim it.</p>
                                        <p>You can continue tracking this request as a guest until it expires.</p>
                                        @if($isExpiringSoon)
                                            <p>Your guest request expires in {{ $remainingText }}.</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @elseif(!in_array($rawStatus, ['cancelled', 'declined', 'rejected'], true))
                    {{-- Permanent guest booking: never expires, but must be claimed before the Client Workflow continues --}}
                    <div id="claim-expiration-panel" class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 sm:p-5 transition shadow-xs">
                        <div class="flex items-start gap-3.5">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-emerald-100 text-emerald-700 text-lg">
                                <i class="fa-solid fa-user-lock" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider block text-emerald-800">
                                    CLAIM REQUIRED TO CONTINUE
                                </span>
                                <p class="text-base font-bold text-[#0B1E43] mt-1">Awaiting Claim</p>
                                <p class="text-xs text-stone-600 mt-1 leading-relaxed">
                                    Raflora continues bookings only through registered client accounts. Create a free account or log in with the email used for this booking to claim it, then review quotations and submit payments from your client portal.
                                </p>

                                {{-- Primary Claim CTAs --}}
                                <div class="mt-3.5 pt-3 border-t border-emerald-200/60 flex flex-col sm:flex-row gap-2">
                                    @guest
                                        <a href="{{ route('register', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 text-xs font-bold transition shadow-xs text-center">
                                            <i class="fa-solid fa-user-plus text-[11px]"></i> Create a free account &amp; Claim
                                        </a>
                                        <a href="{{ route('login', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 flex-1 rounded-xl border border-stone-300 bg-white hover:bg-emerald-50 text-stone-800 px-3 py-2 text-xs font-bold transition shadow-xs text-center">
                                            <i class="fa-solid fa-right-to-bracket text-[11px]"></i> <span class="sr-only">Already have an account? </span>Log In &amp; Claim
                                        </a>
                                    @else
                                        <a href="{{ route('client.claim-guest-booking.show', ['token' => $token]) }}" data-claim-action class="inline-flex items-center justify-center gap-1.5 w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-bold transition shadow-xs text-center">
                                            <i class="fa-solid fa-link text-[11px]"></i> Claim Booking
                                        </a>
                                    @endguest
                                </div>

                                <div class="sr-only">
                                    <h3>Claim &amp; Manage Your Booking Request</h3>
                                    <span>CLAIM YOUR REQUEST</span>
                                    <span>Create Account</span>
                                    <p>Already have an account? Log In &amp; Claim</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- ─── CARD 2: BOOKING WORKFLOW (README: Guest → Client → Staff) ─── --}}
        <x-booking-workflow
            :workflow="$bookingWorkflow"
            id="guest-request-journey"
            heading="Guest Request Journey"
            description="Track this request through the Raflora booking workflow: Guest → Client → Staff."
            class="mb-6" />

        {{-- Accessible Workflow Steps for Testing & Screen Readers --}}
        <div class="sr-only">
            <h3>What Happens Next?</h3>
            <ol>
                <li>1. Claim Your Request With a Raflora Account</li>
                <li>2. Raflora Review</li>
                <li>3. Material Preparation &amp; Validation</li>
                <li>4. Quotation &amp; Approval</li>
                <li>5. Payment &amp; Confirmation</li>
            </ol>
        </div>

        {{-- Hidden loading state kept for DOM compatibility --}}
        <div id="loadingState" class="hidden"></div>

        {{-- ─── CARD 3: TWO-COLUMN MAIN CONTENT (PACKAGE & STATUS) ─── --}}
        <div id="quotationState" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ─── LEFT COLUMN: SELECTED PACKAGE (col-span-12 lg:col-span-7) ─── --}}
            <div class="lg:col-span-7 rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                <div>
                    {{-- Header --}}
                    <div class="flex items-center justify-between gap-2 border-b border-stone-100 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold font-serif text-[#0B1E43]">
                                {{ $isCurated ? 'Selected Package' : 'Your Design Request' }}
                            </h2>
                            <span class="sr-only">Pricing Summary</span>
                        </div>
                        @if($isCurated && $package)
                            <a href="{{ route('packages.index') }}" target="_blank" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1">
                                View Full Details <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        @endif
                    </div>

                    {{-- Package Hero Row (Image + Details) --}}
                    <div class="flex flex-col sm:flex-row gap-4 items-start">
                        {{-- Package / Inspiration Image --}}
                        <div class="w-full sm:w-64 flex flex-col gap-2 shrink-0">
                            @if(count($multiImages) > 1)
                                <div class="flex items-center justify-between bg-stone-50 px-2.5 py-1.5 rounded-xl border border-stone-200 text-xs">
                                    <span class="font-bold text-stone-700 flex items-center gap-1.5 text-[11px]">
                                        <i class="fa-solid fa-images text-emerald-600"></i>
                                        Image <span id="tracking-img-current">1</span> of {{ count($multiImages) }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" onclick="switchTrackingImage(-1)" id="tracking-prev-btn" class="w-5 h-5 rounded bg-white border border-stone-200 hover:bg-stone-100 text-stone-700 flex items-center justify-center font-bold text-[10px] transition disabled:opacity-40">‹</button>
                                        <button type="button" onclick="switchTrackingImage(1)" id="tracking-next-btn" class="w-5 h-5 rounded bg-white border border-stone-200 hover:bg-stone-100 text-stone-700 flex items-center justify-center font-bold text-[10px] transition disabled:opacity-40">›</button>
                                    </div>
                                </div>
                            @endif

                            <div class="w-full rounded-2xl overflow-hidden border border-stone-200 bg-stone-100 relative shadow-2xs">
                                @php
                                    $packageImg = ($isCurated && $package && $package->primary_image_url) ? $package->primary_image_url : null;
                                @endphp

                                @if($previewImage)
                                    <div id="tracking-image-wrapper">
                                        @include('components.annotated-image', [
                                            'previewImage' => $previewImage,
                                            'imageAspectRatio' => $imageAspectRatio,
                                            'imageWidth' => $imageDimensions[0] ?? null,
                                            'imageHeight' => $imageDimensions[1] ?? null,
                                            'overlayItems' => $overlayItems,
                                        ])
                                    </div>
                                @elseif($packageImg)
                                    <img src="{{ $packageImg }}" alt="{{ $packageName }}" class="w-full h-full object-cover" />
                                @else
                                    <div class="flex flex-col items-center justify-center h-48 text-center p-4 bg-stone-50 text-stone-400">
                                        <i class="fa-solid fa-flower text-2xl text-emerald-600/70 mb-1"></i>
                                        <span class="text-xs font-bold text-stone-600">{{ $packageName ?: 'Floral Design Request' }}</span>
                                    </div>
                                @endif
                            </div>

                            @if(count($multiImages) > 1)
                                <div class="flex items-center gap-1.5 overflow-x-auto py-1">
                                    @foreach($multiImages as $mIdx => $mImg)
                                        <button type="button" onclick="selectTrackingImage({{ $mIdx }})" class="tracking-thumb-btn w-11 h-11 rounded-lg overflow-hidden border-2 transition shrink-0 {{ $mIdx === 0 ? 'border-emerald-600 ring-2 ring-emerald-500/20' : 'border-stone-200 opacity-60 hover:opacity-100' }}" data-index="{{ $mIdx }}" data-url="{{ $mImg['url'] }}" data-name="{{ $mImg['filename'] }}">
                                            <img src="{{ $mImg['url'] }}" alt="{{ $mImg['filename'] }}" class="w-full h-full object-cover">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Package Info & Pricing --}}
                        <div class="flex-1 min-w-0">
                            <span class="inline-block bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full mb-1">
                                {{ $isCurated ? 'Curated Package' : 'Smart AI Custom Design' }}
                            </span>
                            <h3 class="text-xl sm:text-2xl font-bold font-serif text-[#0B1E43] leading-snug">
                                {{ $isCurated ? $packageName : (ucfirst($booking->event_type ?? 'Event') . ' Proposal') }}
                            </h3>
                            @if(!$isCurated && count($multiImages) > 1)
                                <div class="mt-1">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
                                        <i class="fa-solid fa-images text-emerald-600"></i>
                                        {{ count($multiImages) }} inspiration images
                                    </span>
                                </div>
                            @endif

                            <div class="mt-2">
                                <span class="text-2xl sm:text-3xl font-extrabold text-stone-900 font-mono">
                                    ₱{{ number_format($totalCost ?? 0, 2) }}
                                </span>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="text-xs font-semibold text-stone-500">{{ $isCurated ? 'Catalog Package Price' : 'AI-Assisted Initial Estimate' }}</span>
                                    <i class="fa-solid fa-circle-info text-stone-400 text-xs" title="{{ $isCurated ? 'Preliminary catalog price' : 'AI baseline estimate' }}"></i>
                                </div>
                            </div>

                            {{-- Catalog or AI Estimate Notice Callout Box --}}
                            <div class="mt-2.5 rounded-xl border border-amber-200 bg-amber-50/80 p-2.5 text-xs text-amber-950 flex items-start gap-2">
                                <i class="fa-solid fa-circle-info text-amber-700 mt-0.5 text-xs shrink-0"></i>
                                <p class="text-[11px] leading-relaxed text-amber-900">
                                    @if($isCurated)
                                        This is the catalog price. Official quotation prepared after Raflora review. Additional requests may affect the final price.
                                    @else
                                        This is an AI-assisted initial estimate based on detected materials and baseline market costs. It is NOT the official quotation. Final pricing is subject to Raflora review, material validation, availability/procurement considerations, and official quotation.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Dynamic Lifecycle Status Notifications for Quotation / Cancelled / Approved --}}
                    @if(in_array($rawStatus, ['cancelled', 'declined'], true))
                        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-center">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-rose-800 mb-1">Booking {{ ucfirst($rawStatus) }}</h3>
                            <p class="text-xs text-rose-700 leading-relaxed">This booking request is no longer active.</p>
                        </div>
                    @elseif($rawStatus === 'quotation_sent' && $isQuotationExpired)
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-center gap-2 mb-1.5">
                                <i class="fa-solid fa-clock-rotate-left text-amber-700"></i>
                                <h4 class="text-sm font-bold uppercase tracking-wider text-amber-900">Quotation Expired — Awaiting Update</h4>
                            </div>
                            <p class="text-xs text-amber-800 leading-relaxed">
                                This quotation expired{{ $expiresAt ? ' on ' . $expiresAt->format('M j, Y') : '' }} due to floral price volatility. Raflora will issue an updated quotation. Claim this booking with your Raflora account to review it once it is re-issued.
                            </p>
                        </div>
                    @elseif($rawStatus === 'quotation_sent')
                        @if($isBookingExpired)
                            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-center">
                                <div class="flex items-center justify-center gap-2 mb-1.5">
                                    <i class="fa-solid fa-clock-rotate-left text-rose-700"></i>
                                    <h4 class="text-sm font-bold uppercase tracking-wider text-rose-900">Quotation Expired — Awaiting Update</h4>
                                </div>
                                <p class="text-xs text-rose-800 leading-relaxed">
                                    This quotation has expired. Administration will review and provide an updated price quotation.
                                </p>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <i class="fa-solid fa-file-invoice-dollar text-emerald-700"></i>
                                    <h4 class="text-sm font-bold uppercase tracking-wider text-emerald-900">Official Quotation Ready</h4>
                                </div>
                                @if($expiresAt)
                                    <p class="text-xs font-semibold text-emerald-900 mb-1">Valid until {{ $expiresAt->format('M j, Y') }}</p>
                                @endif
                                <p class="text-xs text-emerald-800 leading-relaxed mb-3">
                                    Your official quotation is ready for review. To securely review and continue with your booking, claim this booking using your verified Raflora account.
                                </p>
                                <a href="{{ route('login', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" class="inline-block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition shadow-xs">
                                    Log In / Register to Review &amp; Accept Quotation
                                </a>
                            </div>
                        @endif
                    @elseif($rawStatus === 'admin_approved')
                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                            <div class="flex items-center gap-2 mb-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-700"></i>
                                <h4 class="text-sm font-bold uppercase tracking-wider text-emerald-900">Quotation Approved by Admin</h4>
                            </div>
                            <p class="text-xs text-emerald-800 leading-relaxed mb-3">
                                Your quotation has been approved. Please log in to your registered client account to submit your downpayment.
                            </p>
                            <div class="rounded-xl bg-white border border-emerald-200 p-2.5 mb-3 text-xs text-emerald-900">
                                <p class="font-bold text-[11px] mb-1"><i class="fa-solid fa-lock mr-1"></i> Payment Boundary &amp; Account Claim Required:</p>
                                <p class="text-[11px] text-emerald-800">Payment submission is restricted to authenticated clients. You must first log in or register to claim this booking before submitting a downpayment.</p>
                            </div>
                            <a href="{{ route('login', ['guest_token' => $token, 'email' => $booking->guest_email]) }}" class="inline-block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition shadow-xs">
                                Log In to Submit Downpayment
                            </a>
                        </div>
                    @elseif($rawStatus === 'approved')
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-center">
                            <h4 class="text-sm font-bold uppercase tracking-wider text-amber-900 mb-1">Quotation Accepted</h4>
                            <p class="text-xs text-amber-800">Awaiting Admin final review before payment instructions are unlocked.</p>
                        </div>
                    @elseif(in_array($rawStatus, ['payment_submitted', 'payment_pending'], true))
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-center">
                            <h4 class="text-sm font-bold uppercase tracking-wider text-amber-900 mb-1">Payment Submitted</h4>
                            <p class="text-xs text-amber-800">Payment Verification in Progress — your payment reference is awaiting Admin verification.</p>
                        </div>
                    @elseif(in_array($rawStatus, ['downpayment_received', 'confirmed', 'fully_paid'], true))
                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                            <h4 class="text-sm font-bold uppercase tracking-wider text-emerald-900 mb-1">Booking Confirmed</h4>
                            <p class="text-xs text-emerald-800">Your payment has been verified and your booking is confirmed with Raflora!</p>
                        </div>
                    @endif

                    @if($isCurated)
                        {{-- Package Inclusions Section --}}
                        <div class="mt-5 pt-4 border-t border-stone-100">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-stone-700">
                                    Package Inclusions <span class="text-stone-400 font-normal">({{ count($items) }} items)</span>
                                </h4>
                                <span class="text-xs font-bold text-emerald-700">Included</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-stone-700">
                                @forelse($items ?? [] as $item)
                                    @php
                                        $rawName = $item->name ?? $item->item_name ?? 'Floral Item';
                                        $qty = max(1, $item->pivot?->quantity ?? $item->quantity ?? 1);
                                        $hasCountPrefix = preg_match('/^\d+(\s*x|\s+)/i', $rawName);
                                        $displayName = $hasCountPrefix ? $rawName : ($qty > 1 ? "{$qty}x {$rawName}" : "{$rawName}");
                                    @endphp
                                    <div class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-stone-50 transition">
                                        <i class="fa-solid fa-check text-emerald-600 font-bold shrink-0 text-xs"></i>
                                        <span class="truncate font-medium text-stone-800" title="{{ $displayName }}">{{ $displayName }}</span>
                                    </div>
                                @empty
                                    <div class="col-span-2 text-xs text-stone-400 italic py-2">
                                        Package details will be finalized shortly.
                                    </div>
                                @endforelse
                            </div>

                            @if(!empty($cleanSpecialRequests))
                                <div class="mt-3 pt-3 border-t border-stone-100">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block mb-1">Additional Requests / Notes</span>
                                    <p class="text-xs text-stone-700 bg-stone-50 p-2.5 rounded-xl border border-stone-200">{{ $cleanSpecialRequests }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- AI Analyzed Floral Materials Section --}}
                        <div class="mt-5 pt-4 border-t border-stone-100">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-stone-700">
                                    AI-Analyzed Materials <span class="text-stone-400 font-normal">({{ count($analysisMaterials) }} items)</span>
                                </h4>
                                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Decision Support</span>
                            </div>

                            @if(count($multiImages) > 1)
                                <div class="mb-3 flex items-center gap-1.5 overflow-x-auto pb-1" id="tracking-material-tabs">
                                    <button type="button" onclick="filterTrackingMaterials('all')" class="tracking-mat-tab px-3 py-1 rounded-lg text-xs font-bold transition bg-emerald-700 text-white shadow-2xs shrink-0" data-target="all">
                                        All Materials ({{ count($analysisMaterials) }})
                                    </button>
                                    @foreach($multiImages as $mIdx => $mImg)
                                        <button type="button" onclick="filterTrackingMaterials({{ $mIdx + 1 }})" class="tracking-mat-tab px-3 py-1 rounded-lg text-xs font-bold transition bg-white border border-stone-200 text-stone-600 hover:bg-stone-50 shrink-0" data-target="{{ $mIdx + 1 }}">
                                            Image {{ $mIdx + 1 }} ({{ count($mImg['materials'] ?? []) }})
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <x-decor-area-breakdown :materials="$analysisMaterials" :booking="$booking" view="client" class="mb-3" />

                            <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                                @forelse($analysisMaterials as $mat)
                                    @php
                                        $matName = $mat['item_name'] ?? 'Floral Item';
                                        $category = ucfirst($mat['category'] ?? 'flower');
                                        $qty = $mat['quantity'] ?? $mat['estimated_quantity'] ?? null;
                                        $unit = $mat['unit_type'] ?? 'pcs';
                                        $isDetected = !empty($mat['is_detected']) && empty($mat['is_recommendation']);
                                        $confidence = isset($mat['confidence']) ? round((float)$mat['confidence'] * 100) : null;
                                        $alt = $mat['suggested_alternative'] ?? null;
                                        $altReason = $mat['alternative_reason'] ?? null;
                                        $seasonalNote = $mat['seasonal_notes'] ?? null;
                                        $matImgId = $mat['image_id'] ?? 1;
                                    @endphp
                                    <div class="tracking-mat-item p-3 rounded-xl border border-stone-200 bg-stone-50/60 hover:bg-stone-50 transition" data-image-id="{{ $matImgId }}" data-line-id="{{ $mat['line_id'] ?? 'm' . ($loop->index + 1) }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-bold text-stone-900 text-xs sm:text-sm">{{ $matName }}</span>
                                                    @if(count($multiImages) > 1)
                                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                            Inspiration Image {{ $matImgId }}
                                                        </span>
                                                    @endif
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $isDetected ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                                        {{ $isDetected ? 'AI Detected' : 'AI Recommendation' }}
                                                    </span>
                                                    <span class="text-[10px] font-medium text-stone-500 bg-white border border-stone-200 px-1.5 py-0.2 rounded">
                                                        {{ $category }}
                                                    </span>
                                                    @if(!empty($mat['area']))
                                                        <span class="text-[10px] font-semibold text-stone-600 bg-white border border-stone-200 px-1.5 py-0.5 rounded">
                                                            {{ ucfirst($mat['area']) }}
                                                        </span>
                                                    @endif
                                                    @if($confidence !== null)
                                                        <span class="text-[10px] text-stone-400 font-medium">({{ $confidence }}% confidence)</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-stone-600 mt-1">
                                                    <span class="font-semibold text-stone-800">Est. Qty:</span> {{ $qty !== null ? $qty . ' ' . $unit : 'To be confirmed by Raflora' }}
                                                    @if(!empty($mat['area']) && $mat['area'] !== 'overall')
                                                        <span class="mx-1 text-stone-300">•</span>
                                                        <span class="text-stone-500 capitalize">Area: {{ str_replace('_', ' ', $mat['area']) }}</span>
                                                    @endif
                                                </div>
                                                @if(!empty($mat['note']))
                                                    <p class="text-[11px] text-stone-500 italic mt-1 leading-snug">{{ $mat['note'] }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if(!empty($alt))
                                            <div class="mt-2 pt-2 border-t border-stone-200/60 text-xs bg-white/70 p-2 rounded-lg border border-amber-100">
                                                <span class="font-bold text-amber-800 text-[10px] uppercase tracking-wider block">AI-Suggested Alternative (Subject to Raflora Validation):</span>
                                                <div class="text-stone-700 mt-0.5">
                                                    <strong class="text-stone-900">{{ $matName }}</strong> → <span class="text-emerald-700 font-semibold">{{ $alt }}</span>
                                                    @if($altReason)
                                                        <p class="text-[11px] text-stone-500 mt-0.5">{{ $altReason }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        @if(!empty($seasonalNote))
                                            <div class="mt-1.5 text-[11px] text-stone-500 flex items-center gap-1.5">
                                                <i class="fa-solid fa-leaf text-emerald-600 text-[10px]"></i>
                                                <span>Seasonal Context: {{ $seasonalNote }} (Requires Raflora validation)</span>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-4 rounded-xl border border-stone-200 bg-stone-50 text-center text-xs text-stone-400">
                                        No individual materials itemized. Our team will specify materials during request review.
                                    </div>
                                @endforelse
                            </div>

                            @if(!empty($cleanSpecialRequests))
                                <div class="mt-3 pt-3 border-t border-stone-100">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-stone-500 block mb-1">Additional Requests / Notes</span>
                                    <p class="text-xs text-stone-700 bg-stone-50 p-2.5 rounded-xl border border-stone-200">{{ $cleanSpecialRequests }}</p>
                                </div>
                            @endif

                            {{-- Seasonality & Quotation Disclaimers --}}
                            <div class="mt-3 p-2.5 rounded-xl border border-stone-200 bg-stone-50/80 text-[11px] text-stone-600 space-y-1">
                                <p><i class="fa-solid fa-circle-info text-stone-500 mr-1"></i><strong>Seasonal Context:</strong> Flower seasonality is decision support only and requires Raflora florist validation for your event date. It does not guarantee supplier availability.</p>
                                <p><i class="fa-solid fa-scale-balanced text-stone-500 mr-1"></i><strong>Distinct Business Truth:</strong> AI detection ≠ supplier procurement ≠ current inventory stock ≠ official quotation.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ─── RIGHT COLUMN: REQUEST STATUS (col-span-12 lg:col-span-5) ─── --}}
            <div class="lg:col-span-5 rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                <div>
                    {{-- Header --}}
                    <div class="flex items-center justify-between gap-2 border-b border-stone-100 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-file-lines text-stone-600"></i>
                            <h2 class="text-xl font-bold font-serif text-[#0B1E43]">
                                Request Status
                            </h2>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span> {{ $bookingWorkflow['terminal_label'] ?? $bookingWorkflow['current_label'] ?? 'Guest Workflow' }}
                        </span>
                    </div>

                    {{-- 3 Compact Status Cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mb-4">
                        {{-- 1. Current Status --}}
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-1.5 text-stone-500 mb-1">
                                    <i class="fa-regular fa-file-lines text-xs text-emerald-600"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider block">Current Status</span>
                                </div>
                                <span class="text-xs font-bold text-stone-900 block leading-tight mt-1">
                                    {{ $currentStatusText }}
                                </span>
                            </div>
                            <span class="text-[10px] text-stone-500 mt-2 block leading-tight">
                                {{ $currentStatusDesc }}
                            </span>
                        </div>

                        {{-- 2. Claim Status --}}
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-1.5 text-stone-500 mb-1">
                                    <i class="fa-regular fa-user text-xs text-stone-600"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider block">Claim Status</span>
                                </div>
                                <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $claimStatusBadgeClass }}">
                                    {{ $claimStatusText }}
                                </span>
                            </div>
                            <p class="text-[10px] text-stone-600 mt-2 leading-tight">
                                {{ $claimStatusDesc }}
                            </p>
                        </div>

                        {{-- 3. Time Remaining --}}
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-1.5 text-stone-500 mb-1">
                                    <i class="fa-regular fa-clock text-xs text-stone-600"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider block">{{ $isTemporary ? 'Time Remaining' : 'Quotation Validity' }}</span>
                                </div>
                                <span class="text-xs sm:text-sm font-bold text-stone-900 font-mono block mt-1">
                                    @if($isTemporary)
                                        {{ $isBookingExpired ? 'Expired' : $remainingText }}
                                    @elseif($expiresAt)
                                        {{ $expiresAt->copy()->endOfDay()->isPast() ? 'Expired' : 'Valid until ' . $expiresAt->format('M j, Y') }}
                                    @else
                                        Not yet issued
                                    @endif
                                </span>
                            </div>
                            <span class="text-[10px] text-stone-500 mt-2 block leading-tight">
                                @if($isTemporary)
                                    Expires on {{ $expiresAt ? $expiresAt->format('M j, Y \a\t g:i A') : 'N/A' }}
                                @else
                                    Claim this booking to continue.
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Information Blocks --}}
                    <div class="space-y-3">
                        {{-- What Raflora Is Doing --}}
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-3.5">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fa-solid fa-gear text-stone-600 text-xs"></i>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-stone-800">
                                    What Raflora Is Doing
                                </h4>
                            </div>
                            <p class="text-xs text-stone-600 leading-relaxed">
                                {{ $whatRafloraDoes }}
                            </p>
                        </div>

                        {{-- Your Next Step --}}
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-3.5">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fa-solid fa-arrow-right text-stone-600 text-xs"></i>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-stone-800">
                                    Your Next Step
                                </h4>
                            </div>
                            <p class="text-xs text-stone-600 leading-relaxed">
                                {{ $whatGuestExpects }}
                            </p>
                            <span class="sr-only">What You Should Expect Next: Raflora will review your request and prepare the appropriate material and quotation details. Claim this request before it expires by creating an account or logging in. Once claimed and the official quotation is ready, you can review and accept the quotation before proceeding with payment.</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const bookingStatusUrl = @json(route('guest.bookings.status', ['token' => $token]));
            const initialBookingUpdatedAt = @json($booking->updated_at?->toISOString());

            // Live Expiration Countdown
            const countdownElements = document.querySelectorAll('[data-expires-at]');
            const updateCountdowns = () => {
                countdownElements.forEach((el) => {
                    const expiresAtIso = el.dataset.expiresAt;
                    if (!expiresAtIso) return;
                    const expiresAtMs = new Date(expiresAtIso).getTime();
                    const nowMs = Date.now();
                    const diffMs = expiresAtMs - nowMs;

                    if (diffMs <= 0) {
                        el.textContent = 'Expired';
                        const badge = document.getElementById('expirationStatusBadge');
                        if (badge) {
                            badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border border-rose-300 bg-rose-100 text-rose-800';
                            badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Guest Request Expired';
                        }
                        document.querySelectorAll('[data-claim-action]').forEach(btn => {
                            btn.classList.add('pointer-events-none', 'opacity-50', 'cursor-not-allowed');
                        });
                        return;
                    }

                    const diffSec = Math.floor(diffMs / 1000);
                    const hours = Math.floor(diffSec / 3600);
                    const minutes = Math.floor((diffSec % 3600) / 60);
                    const seconds = diffSec % 60;

                    if (hours > 0) {
                        el.textContent = `${hours}h ${minutes}m remaining`;
                    } else if (minutes > 0) {
                        el.textContent = `${minutes}m ${seconds}s remaining`;
                    } else {
                        el.textContent = `${seconds}s remaining`;
                    }

                    const badge = document.getElementById('expirationStatusBadge');
                    if (badge && diffSec <= 14400) {
                        badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border border-amber-300 bg-amber-100 text-amber-900';
                        badge.innerHTML = '<i class="fa-solid fa-clock"></i> Claim Soon';
                    }
                });
            };

            updateCountdowns();
            setInterval(updateCountdowns, 1000);

            let lastBookingUpdatedAt = initialBookingUpdatedAt;
            let lastBookingStatus = @json($booking->status ?? 'pending');
            let pollTimer = null;

            const checkForBookingUpdates = async () => {
                try {
                    const response = await fetch(bookingStatusUrl, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });

                    if (response.status === 404 || response.status === 403) {
                        if (pollTimer) clearInterval(pollTimer);
                        return;
                    }

                    if (!response.ok) return;

                    const latest = await response.json();

                    // If request was claimed by a registered account, redirect
                    if (latest.status === 'claimed' || latest.claimed) {
                        if (pollTimer) clearInterval(pollTimer);
                        if (latest.redirect_url) {
                            window.location.href = latest.redirect_url;
                        } else {
                            window.location.reload();
                        }
                        return;
                    }

                    if (latest.status === 'expired' || latest.is_expired) {
                        window.location.reload();
                        return;
                    }

                    const statusChanged = latest.status && latest.status !== lastBookingStatus;
                    const timestampChanged = lastBookingUpdatedAt && latest.updated_at && latest.updated_at !== lastBookingUpdatedAt;

                    if (statusChanged || timestampChanged) {
                        window.location.reload();
                        return;
                    }

                    lastBookingUpdatedAt = latest.updated_at || lastBookingUpdatedAt;
                    lastBookingStatus = latest.status || lastBookingStatus;
                } catch (error) {
                    // Network temporary interruption handled silently
                }
            };

            pollTimer = window.setInterval(checkForBookingUpdates, 10000);

            const modal = document.getElementById('imageLightboxModal');
            const modalContent = document.querySelector('[data-lightbox-content]');
            const annotatedStage = document.querySelector('[data-annotated-image-stage]');

            const closeModal = () => {
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            const openModal = (src) => {
                if (!modal || !modalContent || !src) return;
                if (annotatedStage) {
                    const annotatedCopy = annotatedStage.cloneNode(true);
                    annotatedCopy.querySelector('[data-image-lightbox-trigger]')?.remove();
                    modalContent.replaceChildren(annotatedCopy);
                } else {
                    modalContent.innerHTML = `<img src="${src}" class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl" alt="Preview Image" />`;
                }
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                const repositionModalAnnotations = () => window.positionAnnotatedImages?.(modalContent);
                requestAnimationFrame(() => {
                    repositionModalAnnotations();
                    modalContent.querySelector('img')?.addEventListener('load', repositionModalAnnotations, { once: true });
                });
            };

            document.querySelectorAll('[data-image-lightbox-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    const src = event.currentTarget?.dataset?.imageSrc || button.getAttribute('data-image-src');
                    openModal(src);
                });
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('[data-close-image-lightbox]')) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });

        const trackingImages = @json($multiImages);
        let currentTrackingImgIdx = 0;
        function selectTrackingImage(idx) {
            if (!trackingImages || !trackingImages[idx]) return;
            currentTrackingImgIdx = idx;
            const stage = document.querySelector('[data-annotated-image-stage]');
            const imgEl = stage ? stage.querySelector('img') : document.getElementById('tracking-active-image');
            if (imgEl) {
                imgEl.src = trackingImages[idx].url;
            }
            const trigger = stage ? stage.querySelector('[data-image-lightbox-trigger]') : null;
            if (trigger) {
                trigger.setAttribute('data-image-src', trackingImages[idx].url);
            }
            // Each photo shows only the items the AI found in that photo.
            window.showAnnotationsForImage?.(stage, trackingImages[idx].id);
            const curEl = document.getElementById('tracking-img-current');
            if (curEl) curEl.textContent = idx + 1;
            document.querySelectorAll('.tracking-thumb-btn').forEach((btn, bIdx) => {
                if (bIdx === idx) {
                    btn.classList.add('border-emerald-600', 'ring-2', 'ring-emerald-500/20');
                    btn.classList.remove('border-stone-200', 'opacity-60');
                } else {
                    btn.classList.remove('border-emerald-600', 'ring-2', 'ring-emerald-500/20');
                    btn.classList.add('border-stone-200', 'opacity-60');
                }
            });
            if (typeof filterTrackingMaterials === 'function') {
                filterTrackingMaterials(idx + 1);
            }
        }
        function switchTrackingImage(delta) {
            if (!trackingImages || trackingImages.length === 0) return;
            let next = currentTrackingImgIdx + delta;
            if (next < 0) next = 0;
            if (next >= trackingImages.length) next = trackingImages.length - 1;
            selectTrackingImage(next);
        }
        function filterTrackingMaterials(imageId) {
            document.querySelectorAll('.tracking-mat-tab').forEach(btn => {
                if (btn.dataset.target === String(imageId)) {
                    btn.className = 'tracking-mat-tab px-3 py-1 rounded-lg text-xs font-bold transition bg-emerald-700 text-white shadow-2xs shrink-0';
                } else {
                    btn.className = 'tracking-mat-tab px-3 py-1 rounded-lg text-xs font-bold transition bg-white border border-stone-200 text-stone-600 hover:bg-stone-50 shrink-0';
                }
            });
            document.querySelectorAll('.tracking-mat-item').forEach(item => {
                if (imageId === 'all' || item.dataset.imageId === String(imageId)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }
    </script>

    <div id="imageLightboxModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-950/80" data-close-image-lightbox></div>
        <div class="relative z-10 flex min-h-full items-center justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-6xl rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-2xl sm:p-4">
                <button type="button" class="absolute right-3 top-3 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-slate-900/70 text-white transition hover:bg-slate-800" data-close-image-lightbox aria-label="Close image view">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="flex max-h-[90vh] items-center justify-center overflow-auto rounded-xl bg-slate-950 p-2 sm:p-4" data-lightbox-content>
                    <p class="text-sm text-white/70">Loading annotated image...</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
