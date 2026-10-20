<x-app-layout title="New Booking">
    <x-client-layout active="bookings" plain>
        <div class="mx-auto w-full max-w-[92rem] px-4 sm:px-6 lg:px-8">
            <div class="rf-panel p-5 sm:p-8 lg:p-10">
                <!-- Header -->
                <span class="rf-badge rf-badge--success mb-3">Authenticated client portal</span>
                <h1 class="page-title mt-1">Create Event Booking</h1>
                <p class="section-subtitle mt-2 mb-6 max-w-2xl">Tell us about your event first, then choose a custom AI design or a curated package, and review your request before sending it. Your event details help the AI suggest the right quantities.</p>

                @php
                    $clientDisplayErrors = collect($errors->keys())
                        ->reject(fn($key) => in_array($key, ['inspiration_image', 'analysis_token']))
                        ->flatMap(fn($key) => $errors->get($key));
                    // Same order as the guest form: event details, then booking method and design (the photos
                    // are analyzed with the event details already filled in), then review. Contact details come
                    // from the client's profile, so there is no contact step.
                    $hasStep1Errors = $errors->hasAny(['event_type', 'other_event_type', 'event_date', 'event_time', 'end_time', 'venue', 'venue_latitude', 'venue_longitude', 'venue_setting', 'guest_count', 'table_count', 'special_requests']);
                    $hasStep2Errors = $errors->hasAny(array_merge(['inspiration_image', 'analysis_token', 'analysis_tokens', 'package_id', 'booking_type', 'photo_areas'], \App\Support\EventDesignBrief::FIELDS, ['decor_areas.*', 'color_palette.*', 'decor_area_notes.*']))
                        || session('analysis_error');
                    $initialStep = ($hasStep2Errors && !$hasStep1Errors) ? 2 : 1;
                    $selectedBookingType = old('booking_type', request('package_id') ? 'preset' : 'custom_ai');
                    $steps = [1 => 'Event Details', 2 => 'Booking Method & Design', 3 => 'Review & Submit'];

                    $clientUser = auth()->user();
                    $clientModel = $clientUser?->client ?? \App\Models\Client::where('email', $clientUser?->email)->first();
                    $clientName = $clientModel?->full_name ?: ($clientUser?->name ?: 'Valued Client');
                    $clientEmail = $clientModel?->email ?: ($clientUser?->email ?: 'Not specified');
                    $clientPhone = $clientModel?->phone ?: ($clientUser?->mobile_number ?: 'Not provided');
                    $clientAddress = $clientModel?->address ?: ($clientUser?->address ?: 'Not provided');
                @endphp

                <nav class="mb-6 grid grid-cols-3 gap-2 sm:gap-3" aria-label="Booking form progress">
                    @foreach($steps as $number => $label)
                        <div id="progress-step-{{ $number }}" data-progress-step="{{ $number }}" class="rounded-xl border {{ $initialStep === $number ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }} px-2.5 py-2 sm:px-4 sm:py-3" {!! $initialStep === $number ? 'aria-current="step"' : '' !!}>
                            <p class="text-[11px] font-bold uppercase tracking-wide {{ $initialStep === $number ? 'text-emerald-700' : 'text-slate-500' }}">Step {{ $number }}</p>
                            <p class="text-xs font-semibold sm:text-sm {{ $initialStep === $number ? 'text-slate-900' : 'text-slate-500' }}">{{ $label }}</p>
                        </div>
                    @endforeach
                </nav>

                @if($clientDisplayErrors->isNotEmpty())
                    <div class="rf-alert rf-alert--danger mb-6" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($clientDisplayErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form id="client-booking-form" method="POST" action="{{ route('bookings.store') }}" enctype="multipart/form-data" class="space-y-8">
                    @csrf

                    <!-- STEP 1: EVENT DETAILS -->
                    <div id="booking-step-1" class="{{ $initialStep === 2 ? 'hidden space-y-8' : 'space-y-8' }}">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">Event Details</h3>
                            <span class="text-xs text-slate-500 font-medium">Fields marked with <span class="text-rose-600 font-bold" aria-hidden="true">*</span> are required</span>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="event_type" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Event Type <span class="text-rose-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <select name="event_type" id="event_type" required aria-invalid="{{ $errors->has('event_type') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('event_type') ? 'event_type-error' : '' }}" class="rf-select @error('event_type') is-invalid @enderror">
                                    <option value="">Select Type</option>
                                    <option value="wedding" {{ old('event_type') === 'wedding' ? 'selected' : '' }}>Wedding</option>
                                    <option value="birthday" {{ old('event_type') === 'birthday' ? 'selected' : '' }}>Birthday</option>
                                    <option value="corporate" {{ old('event_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                                    <option value="delivery" {{ old('event_type') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                                    <option value="other" {{ old('event_type') === 'other' ? 'selected' : '' }}>Other Event</option>
                                </select>
                                @error('event_type')
                                    <p id="event_type-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                @enderror
                                <div id="otherEventTypeContainer" class="mt-3 {{ old('event_type') === 'other' ? '' : 'hidden' }}">
                                    <label for="other_event_type" class="block text-xs font-semibold text-slate-700 mb-1">
                                        Please specify other event type <span class="text-rose-600 font-bold" aria-hidden="true">*</span>
                                    </label>
                                    <input type="text" name="other_event_type" id="other_event_type" value="{{ old('other_event_type') }}" placeholder="e.g. Delivery, Engagement, Product Launch" aria-invalid="{{ $errors->has('other_event_type') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('other_event_type') ? 'other_event_type-error' : '' }}" class="rf-input @error('other_event_type') is-invalid @enderror" {{ old('event_type') === 'other' ? 'required' : '' }}>
                                    @error('other_event_type')
                                        <p id="other_event_type-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div>
                                <label for="event_date" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Event Date <span class="text-rose-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <input type="date" name="event_date" id="event_date" min="{{ date('Y-m-d') }}" value="{{ old('event_date') }}" required aria-invalid="{{ $errors->has('event_date') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('event_date') ? 'event_date-error' : '' }}" class="rf-input @error('event_date') is-invalid @enderror">
                                @error('event_date')
                                    <p id="event_date-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="event_time" class="block text-xs font-semibold text-slate-700 mb-1">Start Time</label>
                                    <input type="time" name="event_time" id="event_time" value="{{ old('event_time') }}" aria-invalid="{{ $errors->has('event_time') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('event_time') ? 'event_time-error' : '' }}" class="rf-input @error('event_time') is-invalid @enderror">
                                    @error('event_time')
                                        <p id="event_time-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="end_time" class="block text-xs font-semibold text-slate-700 mb-1">End Time</label>
                                    <input type="time" name="end_time" id="end_time" value="{{ old('end_time') }}" aria-invalid="{{ $errors->has('end_time') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('end_time') ? 'end_time-error' : '' }}" class="rf-input @error('end_time') is-invalid @enderror">
                                    @error('end_time')
                                        <p id="end_time-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="venue" class="block text-xs font-semibold text-slate-700 mb-1">
                                Venue Address <span class="text-rose-600 font-bold" aria-hidden="true">*</span>
                            </label>
                            <input type="text" name="venue" id="venue" value="{{ old('venue') }}" placeholder="e.g. Delivery Address, Grand Ballroom, Shangri-La The Fort, BGC" maxlength="500" required aria-invalid="{{ $errors->has('venue') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('venue') ? 'venue-error' : '' }}" class="rf-input @error('venue') is-invalid @enderror">
                            @error('venue')
                                <p id="venue-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                            @enderror
                            @if($errors->hasAny(['venue_latitude', 'venue_longitude']))
                                <p role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $errors->first('venue_latitude') ?: $errors->first('venue_longitude') }}</p>
                            @endif
                            <x-location-finder id="venue-finder" class="mt-2"
                                :latitude="old('venue_latitude')" :longitude="old('venue_longitude')" />
                        </div>

                        <x-venue-setting-field :value="old('venue_setting')" />

                        {{-- The counts anchor the AI's quantities; a delivery order has no guests or tables, so they are hidden --}}
                        <div id="scale-fields-container" data-delivery-hidden class="grid grid-cols-1 md:grid-cols-2 gap-4 relative {{ old('event_type') === 'delivery' ? 'hidden' : '' }}">
                            <div>
                                <label for="guest_count" class="block text-xs font-semibold text-slate-700 mb-1">Estimated Guests / Pax <span id="large-event-warning" class="hidden text-amber-600 ml-2 font-bold">(Large Event)</span></label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" name="guest_count" id="guest_count" value="{{ old('guest_count') }}" placeholder="e.g. 150" aria-invalid="{{ $errors->has('guest_count') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('guest_count') ? 'guest_count-error' : '' }}" class="rf-input @error('guest_count') is-invalid @enderror">
                                @error('guest_count')
                                    <p id="guest_count-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="table_count" class="block text-xs font-semibold text-slate-700 mb-1">Number of Tables</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" name="table_count" id="table_count" value="{{ old('table_count') }}" placeholder="e.g. 15" aria-invalid="{{ $errors->has('table_count') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('table_count') ? 'table_count-error' : '' }}" class="rf-input @error('table_count') is-invalid @enderror">
                                @error('table_count')
                                    <p id="table_count-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="special_requests" class="block text-xs font-semibold text-slate-700 mb-1">Additional Requests / Notes</label>
                            <textarea name="special_requests" id="special_requests" rows="3" placeholder="Specify color motifs, preferred flower types, or special venue guidelines..." maxlength="2000" aria-invalid="{{ $errors->has('special_requests') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('special_requests') ? 'special_requests-error' : '' }}" class="rf-textarea @error('special_requests') is-invalid @enderror">{{ old('special_requests') }}</textarea>
                            @error('special_requests')
                                <p id="special_requests-error" role="alert" class="text-xs text-rose-600 mt-1.5 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 pt-6">
                        <button type="button" id="next-step-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">
                            Next <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    </div>

                    <!-- STEP 2: BOOKING METHOD & DESIGN -->
                    <div id="booking-step-2" class="{{ $initialStep === 2 ? 'space-y-6' : 'hidden space-y-6' }}">

                    <!-- 1. SELECT BOOKING TYPE -->
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 mb-3">1. Choose Booking Method</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- AI Card -->
                            <label id="card-ai" onclick="switchMode('ai')" class="relative flex items-start gap-3 rounded-xl border-2 {{ $selectedBookingType === 'custom_ai' ? 'border-emerald-600 bg-white ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:border-slate-300' }} p-4 shadow-sm transition-all cursor-pointer focus-within:ring-4 focus-within:ring-emerald-100">
                                <input type="radio" name="booking_type" value="custom_ai" {{ $selectedBookingType === 'custom_ai' ? 'checked' : '' }} class="sr-only">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
                                    <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <div class="flex items-center">
                                        <span class="font-bold text-slate-900 text-sm">Smart AI Custom Design</span>
                                        <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded ml-2 uppercase">POPULAR</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">Upload up to 5 inspiration photos. The AI suggests floral materials for each one, and Raflora reviews them before your official quotation.</p>
                                </div>
                            </label>

                            <!-- Preset Card -->
                            <label id="card-preset" onclick="switchMode('preset')" class="relative flex items-start gap-3 rounded-xl border-2 {{ $selectedBookingType === 'preset' ? 'border-emerald-600 bg-white ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:border-slate-300' }} p-4 transition-all cursor-pointer focus-within:ring-4 focus-within:ring-emerald-100">
                                <input type="radio" name="booking_type" value="preset" {{ $selectedBookingType === 'preset' ? 'checked' : '' }} class="sr-only">
                                <div id="client-preset-icon-box" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $selectedBookingType === 'preset' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }} transition-all">
                                    <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 text-sm">Pre-Set Curated Package</div>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">Choose a ready-made floral package with a fixed catalog price set by our florists.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SMART AI CUSTOM DESIGN: preferences, photos and their analysis (shared with the guest form) -->
                    <section id="section-ai" class="{{ $selectedBookingType === 'preset' ? 'hidden' : '' }} space-y-3" aria-label="Smart AI custom design">
                        @if(session('analysis_error'))
                            <div id="client-analysis-error" class="rf-alert rf-alert--danger" role="alert">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <span>{{ session('analysis_error') }}</span>
                            </div>
                        @endif
                        @error('inspiration_image')
                            <p class="text-xs font-semibold text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                        @error('analysis_tokens')
                            <p class="text-xs font-semibold text-rose-600" role="alert">{{ $message }}</p>
                        @enderror
                        <x-inspiration-photos prefix="client" :analyze-url="route('bookings.analyze-temp-image')" />
                    </section>

                    <!-- DYNAMIC PRESET PACKAGE CARDS GRID -->
                    <div id="section-preset" class="{{ $selectedBookingType === 'preset' ? '' : 'hidden' }} space-y-3">
                        <h3 class="text-sm font-bold text-slate-800">Select a package <span class="text-rose-600 font-bold" aria-hidden="true">*</span></h3>
                        @error('package_id')
                            <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror

                        <div id="client-package-carousel" aria-label="Available curated packages" class="package-carousel relative grid w-full auto-cols-[88%] grid-flow-col gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scroll-smooth sm:auto-cols-[320px] md:auto-cols-[calc(50%-8px)] lg:auto-cols-[calc(33.333%-11px)]" tabindex="0" style="-webkit-overflow-scrolling: touch; cursor: grab;">
                            @forelse($packages as $package)
                                @php
                                    $titleLower = strtolower($package->title);
                                    $tagColor = str_contains($titleLower, 'wedding') ? 'text-emerald-600' : (str_contains($titleLower, 'debut') ? 'text-purple-600' : 'text-blue-600');
                                    $tagName = str_contains($titleLower, 'wedding') ? 'WEDDINGS' : (str_contains($titleLower, 'debut') ? 'DEBUTS' : 'OTHER');
                                    $packageMeta = json_encode([
                                        'id' => $package->id,
                                        'title' => $package->title,
                                        'price' => $package->price,
                                        'description' => $package->description,
                                        'included_items' => $package->included_items ?? [],
                                        'image_url' => $package->image_path ? asset("storage/{$package->image_path}") : null,
                                        'event_type' => str_contains($titleLower, 'wedding') ? 'wedding' : (str_contains($titleLower, 'debut') ? 'debut' : 'other'),
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                @endphp
                                <label class="client-package-card snap-start p-0 rounded-xl border-2 border-slate-200 cursor-pointer transition-all bg-white hover:border-slate-300 relative overflow-hidden" data-package='{!! $packageMeta !!}'>
                                    <input type="radio" name="package_id" value="{{ $package->id }}" {{ request('package_id') == $package->id ? 'checked' : '' }} class="sr-only package-radio">
                                    <div class="h-36 bg-slate-100 flex items-center justify-center overflow-hidden">
                                        @if($package->image_path)
                                            <img src="{{ asset('storage/' . $package->image_path) }}" alt="{{ $package->title }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-slate-400">
                                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a4 4 0 004 4h10a4 4 0 004-4V7M3 7a4 4 0 014-4h10a4 4 0 014 4M8 7v8m8-8v8"></path></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-4 flex flex-col gap-3 h-[220px]">
                                        <div>
                                            <span class="text-[10px] font-bold tracking-wider uppercase mb-1 block {{ $tagColor }}">{{ $tagName }}</span>
                                            <div class="font-bold text-slate-900 text-base mb-1">{{ $package->title }}</div>
                                            <div class="text-2xl font-extrabold text-emerald-600">₱{{ number_format($package->price, 0) }}</div>
                                        </div>

                                        <div class="mt-1 text-xs text-slate-600 flex-1 overflow-y-auto max-h-48 pr-2">
                                            @if(is_array($package->included_items) && count($package->included_items) > 0)
                                                <ul class="space-y-1">
                                                    @foreach($package->included_items as $item)
                                                        <li class="flex items-start gap-2">
                                                            <span class="text-emerald-600 font-bold mt-0.5">✓</span>
                                                            <span>{{ $item }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @elseif($package->description)
                                                <p class="text-xs text-slate-500 leading-relaxed">{{ $package->description }}</p>
                                            @endif
                                        </div>

                                        <div class="pt-2">
                                            <button type="button" class="view-package-btn w-full py-2 bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50 text-sm font-semibold">View Package</button>
                                        </div>
                                    </div>
                                    <div class="package-badge absolute right-3 top-3 hidden h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">✓</div>
                                </label>
                            @empty
                                <div class="p-4 text-center text-sm text-slate-500 bg-slate-50 rounded-xl border border-slate-200 w-full">
                                    No curated packages currently available.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <p id="client-step2-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800" role="alert" tabindex="-1"></p>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-between">
                        <button type="button" id="back-step-btn" class="rf-btn rf-btn-secondary">
                            <span aria-hidden="true">←</span> Back
                        </button>
                        <button type="button" id="review-step-btn" class="rf-btn rf-btn-primary">
                            Next: Review <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    </div>

                    <!-- STEP 3: REVIEW & SUBMIT -->
                    <div id="booking-step-3" class="hidden space-y-5 animate-fade-in">
                        
                        <!-- Request Submission & Payment Notice -->
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5 text-emerald-950">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div class="text-xs sm:text-sm leading-relaxed">
                                    <h4 class="font-bold text-emerald-900 uppercase tracking-wider text-xs mb-1">Booking Request Submission Notice</h4>
                                    <p class="text-emerald-800">This submission creates a <strong>booking request</strong> for Raflora review. It does not confirm your booking.</p>
                                    <p class="text-emerald-900 font-bold mt-1">NO PAYMENT IS REQUIRED AT THIS STAGE.</p>
                                    <p class="text-emerald-700 mt-0.5">Package prices shown are catalog rates. Custom designs show an indicative AI price range per area, which is not a quotation; the official price is set in the official quotation prepared after Raflora staff review and material validation.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-6 items-start">
                            
                            <!-- Left Content: Compact Review Cards -->
                            <div class="lg:col-span-2 space-y-4">

                                <!-- Review Card 1: Event Details -->
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden transition-all" id="client-review-card-event">
                                    <div class="p-4 sm:p-5 flex items-start gap-3.5">
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 mt-0.5">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">Event Details</h4>
                                                    <span id="client-review-event-mobile-summary" class="sm:hidden text-xs text-slate-500 font-normal truncate block mt-0.5">-</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" data-goto-step="1" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shadow-2xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit
                                                    </button>
                                                    <button type="button" onclick="toggleClientReviewCard('client-review-card-event')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle Event Details">
                                                        <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Detailed Content Grid -->
                                            <div id="client-review-card-event-body" class="mt-4 pt-3.5 border-t border-slate-100 hidden sm:grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-3.5 gap-x-3">
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Type</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-event-type">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Date</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-event-date">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Time</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-event-time">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Venue Address</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-venue">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Indoor / Outdoor</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-venue-setting">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Guests / Pax</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-guest-count">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Tables</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="client-review-table-count">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Additional Notes</div>
                                                    <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100 mt-1 leading-relaxed" id="client-review-notes">-</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review Card 2: Booking Method & Design -->
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden transition-all" id="client-review-card-method">
                                    <div class="p-4 sm:p-5 flex items-start gap-3.5">
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 mt-0.5">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">Booking Method &amp; Design</h4>
                                                    <span id="client-review-method-mobile-summary" class="sm:hidden text-xs text-slate-500 font-normal truncate block mt-0.5">Smart AI Custom Design</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" data-goto-step="2" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shadow-2xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit
                                                    </button>
                                                    <button type="button" onclick="toggleClientReviewCard('client-review-card-method')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle Booking Method">
                                                        <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="client-review-card-method-body" class="mt-2 text-xs sm:text-sm font-bold text-[#0B1E43] hidden sm:block">
                                                <span id="client-review-method-title">Smart AI Custom Design</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review Card 3: Inspiration Images & Design Brief -->
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden transition-all" id="client-review-card-images">
                                    <div class="p-4 sm:p-5 flex items-start gap-3.5">
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 mt-0.5">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif" id="client-review-design-title">Inspiration Images</h4>
                                                    <span id="client-review-media-mobile-summary" class="sm:hidden text-xs text-slate-500 font-normal truncate block mt-0.5">Photos analyzed</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" data-goto-step="2" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shadow-2xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit
                                                    </button>
                                                    <button type="button" onclick="toggleClientReviewCard('client-review-card-images')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle Inspiration Images">
                                                        <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div id="client-review-card-images-body" class="space-y-3.5 hidden sm:block">
                                                <p class="text-xs text-slate-600" id="client-review-design-status"></p>
                                                
                                                <!-- Package Inclusions Block -->
                                                <div id="client-review-package-inclusions-wrapper" class="hidden pt-2 border-t border-slate-100">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Package Inclusions</span>
                                                    <ul id="client-review-package-items" class="space-y-1 text-xs text-slate-600"></ul>
                                                </div>

                                                <!-- Multi-image thumbnails strip (for custom AI) -->
                                                <div id="client-review-multi-images-row" class="hidden pt-2 border-t border-slate-100">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Inspiration Photos</span>
                                                    <div id="client-review-multi-thumbnails" class="flex flex-wrap gap-2.5"></div>
                                                </div>

                                                <!-- Design brief (custom AI, only when something was filled in) -->
                                                <div id="client-review-design-brief-row" class="hidden pt-2 border-t border-slate-100">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Design Brief</span>
                                                    <dl id="client-review-brief" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs"></dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Right Column: Request Summary & Submission Card (Sticky on Desktop) -->
                            <div class="lg:col-span-1 space-y-4 lg:sticky lg:top-6">
                                <aside class="bg-white rounded-2xl border border-slate-200/90 p-4 sm:p-5 shadow-2xs space-y-4" id="client-review-card-summary" aria-labelledby="client-review-summary-heading">
                                    <h3 id="client-review-summary-heading" class="text-sm sm:text-base font-bold text-[#0B1E43] font-serif border-b border-slate-100 pb-3">Request Summary</h3>
                                    
                                    <dl class="space-y-2.5 text-xs">
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500 font-medium">Booking Method</dt>
                                            <dd class="text-right font-bold text-[#0B1E43]" id="client-review-summary-method">Smart AI Custom Design</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500 font-medium" id="client-review-summary-price-label">Price</dt>
                                            <dd class="text-right font-bold text-[#0B1E43]" id="client-review-summary-price">In your official quotation</dd>
                                        </div>
                                    </dl>

                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-600 leading-relaxed space-y-1">
                                        <p class="font-bold text-emerald-900">No payment is required today.</p>
                                        <p>Raflora will review your request, prepare floral blueprints, and send an official quotation to your portal.</p>
                                    </div>

                                    <div class="space-y-2.5 pt-2">
                                        <button type="submit" id="clientSubmitBtn" class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer focus:ring-2 focus:ring-emerald-500 outline-none">
                                            <span>Submit Booking Request</span>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        </button>
                                        <button type="button" data-goto-step="2" class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold transition text-center">
                                            <span aria-hidden="true">←</span> Back to Design
                                        </button>
                                    </div>
                                </aside>

                                <!-- Review shortcuts: open the AI Analysis Summary and Client Profile dialogs -->
                                <button type="button" id="client-open-review-ai-summary" data-review-dialog-open="client-review-ai-summary-modal" class="hidden w-full text-left bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-2xs hover:border-emerald-300 hover:bg-emerald-50/40 transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-haspopup="dialog">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-bold text-[#0B1E43] text-xs sm:text-sm font-serif">View AI Analysis Summary</span>
                                        <span class="block text-[11px] text-slate-500 truncate">Indicative ranges and materials per area</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>

                                <button type="button" id="client-open-review-contact" data-review-dialog-open="client-review-contact-modal" class="w-full text-left bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-2xs hover:border-emerald-300 hover:bg-emerald-50/40 transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-haspopup="dialog">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100/60 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-bold text-[#0B1E43] text-xs sm:text-sm font-serif">Review Client Profile</span>
                                        <span id="client-review-contact-mobile-summary" class="block text-[11px] text-slate-500 truncate">{{ $clientPhone !== 'Not provided' ? $clientName . ' · ' . $clientPhone : $clientName }}</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </x-client-layout>
</x-app-layout>

<script>
    function switchMode(mode) {
        const isAi = mode === 'ai';
        const form = document.getElementById('client-booking-form');
        const radio = form?.querySelector(`input[name="booking_type"][value="${isAi ? 'custom_ai' : 'preset'}"]`);
        if (radio) radio.checked = true;
        const selected = 'border-emerald-600 bg-white ring-1 ring-emerald-600';
        const idle = 'border-slate-200 bg-white hover:border-slate-300';
        const cardAi = document.getElementById('card-ai');
        const cardPreset = document.getElementById('card-preset');
        [[cardAi, isAi], [cardPreset, !isAi]].forEach(([card, on]) => {
            if (!card) return;
            selected.split(' ').forEach(cls => card.classList.toggle(cls, on));
            idle.split(' ').forEach(cls => card.classList.toggle(cls, !on));
        });
        const iconPreset = document.getElementById('client-preset-icon-box');
        if (iconPreset) {
            ['bg-emerald-600', 'text-white'].forEach(cls => iconPreset.classList.toggle(cls, !isAi));
            ['bg-slate-100', 'text-slate-600'].forEach(cls => iconPreset.classList.toggle(cls, isAi));
        }
        // Photos stay when switching: a package request ignores them, and switching back keeps them.
        document.getElementById('section-ai')?.classList.toggle('hidden', !isAi);
        document.getElementById('section-preset')?.classList.toggle('hidden', isAi);
        document.getElementById('client-step2-error')?.classList.add('hidden');
        document.dispatchEvent(new CustomEvent('client-booking-mode-changed'));
    }
    window.switchMode = switchMode;

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('client-booking-form');
        const submitBtn = document.getElementById('clientSubmitBtn');
        const steps = [1, 2, 3].map(step => document.getElementById('booking-step-' + step));
        const nextStepBtn = document.getElementById('next-step-btn');
        const backStepBtn = document.getElementById('back-step-btn');
        const reviewStepBtn = document.getElementById('review-step-btn');
        const step2Error = document.getElementById('client-step2-error');
        const designBrief = () => window.RafloraDesignBrief?.get('design-brief');
        const analysisError = @json(session('analysis_error'));
        let currentStep = {{ $initialStep }};

        // The event details an analysis depends on (the server hashes the same details and refuses
        // photos analyzed with different ones when the booking is submitted).
        function getClientContextFingerprint() {
            const eventType = document.querySelector('select[name="event_type"]')?.value || '';
            const otherEventType = document.querySelector('input[name="other_event_type"]')?.value || '';
            const value = (selector) => (document.querySelector(selector)?.value || '').trim();
            return [
                eventType === 'other' ? (otherEventType.trim() || 'other') : eventType.trim(),
                value('input[name="event_date"]'),
                value('input[name="event_time"]'),
                value('input[name="end_time"]'),
                value('input[name="venue"]'),
                value('input[name="venue_setting"]:checked'),
                value('input[name="guest_count"]'),
                value('input[name="table_count"]'),
                value('textarea[name="special_requests"]'),
            ].join('|');
        }

        // The same details, sent with each photo. "Other" event types go as other_event_type, exactly
        // as the booking is submitted, so the analysis and the submission describe the same event.
        function appendClientEventContext(formData) {
            const value = (selector) => document.querySelector(selector)?.value || '';
            formData.append('event_type', value('select[name="event_type"]'));
            formData.append('other_event_type', value('input[name="other_event_type"]'));
            formData.append('event_date', value('input[name="event_date"]'));
            formData.append('event_time', value('input[name="event_time"]'));
            formData.append('end_time', value('input[name="end_time"]'));
            formData.append('venue', value('input[name="venue"]'));
            formData.append('venue_setting', value('input[name="venue_setting"]:checked'));
            formData.append('special_requests', value('textarea[name="special_requests"]'));
            const guestCount = value('input[name="guest_count"]');
            const tableCount = value('input[name="table_count"]');
            if (guestCount) formData.append('guest_count', guestCount);
            if (tableCount) formData.append('table_count', tableCount);
        }

        const clientPhotos = window.RafloraInspirationPhotos.create(document.getElementById('client-inspiration-photos'), {
            contextFingerprint: getClientContextFingerprint,
            appendContext: appendClientEventContext,
            isActive: () => currentStep === 2,
        });

        // Event details changed: an analysis in flight for the old details is cancelled, and finished
        // photos show as out of date until they are re-analyzed (or the details are changed back).
        function handleClientEventContextChanged() {
            clientPhotos.handleContextChanged();
        }
        function cancelActiveClientAnalysis() {
            clientPhotos.cancel();
        }
        window.cancelActiveClientAnalysis = cancelActiveClientAnalysis;

        function showBookingStep(step) {
            currentStep = step;
            steps.forEach((el, index) => el?.classList.toggle('hidden', index + 1 !== step));
            document.querySelectorAll('[data-progress-step]').forEach(item => {
                const on = Number(item.dataset.progressStep) === step;
                item.classList.toggle('bg-emerald-50', on);
                item.classList.toggle('border-emerald-200', on);
                item.classList.toggle('bg-slate-50', !on);
                item.classList.toggle('border-slate-200', !on);
                item.querySelector('p:first-child')?.classList.toggle('text-emerald-700', on);
                item.querySelector('p:first-child')?.classList.toggle('text-slate-500', !on);
                item.querySelector('p:last-child')?.classList.toggle('text-slate-900', on);
                item.querySelector('p:last-child')?.classList.toggle('text-slate-500', !on);
                if (on) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
            });
            if (step === 2) clientPhotos.resume();
            if (step === 3) populateClientReview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Why Step 2 cannot be left yet, or null.
        function step2Issue() {
            const bookingType = form?.querySelector('input[name="booking_type"]:checked')?.value || 'custom_ai';
            if (bookingType === 'preset') {
                return form?.querySelector('input[name="package_id"]:checked') ? null : 'Please select a curated package before continuing.';
            }
            return clientPhotos.blockingIssue();
        }

        // Step 1 (event details) must be complete before the photo is analyzed in Step 2.
        nextStepBtn?.addEventListener('click', () => {
            const firstInvalid = Array.from(steps[0]?.querySelectorAll('input, select, textarea') || [])
                .find(el => !el.disabled && !el.checkValidity());
            if (firstInvalid) {
                firstInvalid.reportValidity();
                firstInvalid.focus();
                return;
            }
            showBookingStep(2);
        });
        backStepBtn?.addEventListener('click', () => showBookingStep(1));
        reviewStepBtn?.addEventListener('click', () => {
            const issue = step2Issue();
            if (step2Error) {
                step2Error.textContent = issue || '';
                step2Error.classList.toggle('hidden', !issue);
                if (issue) step2Error.focus();
            }
            if (!issue) showBookingStep(3);
        });
        document.querySelectorAll('[data-goto-step]').forEach(button => {
            button.addEventListener('click', () => showBookingStep(Number(button.dataset.gotoStep)));
        });

        // Handle numeric stripping for guest_count and table_count
        ['guest_count', 'table_count'].map(id => document.getElementById(id)).filter(Boolean).forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        });

        // Large event warning logic
        const guestCountInputWarning = document.getElementById('guest_count');
        const largeEventWarning = document.getElementById('large-event-warning');
        guestCountInputWarning?.addEventListener('input', function() {
            const count = parseInt(this.value, 10);
            largeEventWarning?.classList.toggle('hidden', !(!isNaN(count) && count >= 1000));
        });

        // Clear validation errors on input
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500', 'is-invalid', 'focus:border-red-500', 'focus:ring-red-500');
            });
        });

        // Location finder: a chosen place becomes the venue address (still editable).
        document.getElementById('venue-finder')?.addEventListener('location-finder:select', function (e) {
            const venueInput = document.getElementById('venue');
            const address = [e.detail.specific, e.detail.city].filter(Boolean).join(', ');
            if (venueInput && address) {
                venueInput.value = address.slice(0, 500);
                venueInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            handleClientEventContextChanged();
        });
        document.getElementById('venue-finder')?.addEventListener('location-finder:clear', handleClientEventContextChanged);

        // Context-affecting Step 1 field listeners
        ['other_event_type', 'event_date', 'event_time', 'end_time', 'venue', 'guest_count', 'table_count', 'special_requests'].forEach(id => {
            const el = document.getElementById(id);
            ['input', 'change'].forEach(evt => el?.addEventListener(evt, handleClientEventContextChanged));
        });
        document.querySelector('select[name="event_type"]')?.addEventListener('change', handleClientEventContextChanged);
        document.querySelectorAll('input[name="venue_setting"]').forEach(el => el.addEventListener('change', handleClientEventContextChanged));

        // Photos analyzed in this session come back after a validation error; photos the server
        // refused at submission are marked so the client can re-analyze or replace them.
        try {
            const restoredTokens = JSON.parse(clientPhotos.initialInputs().tokens || '[]');
            if (Array.isArray(restoredTokens) && restoredTokens.length) {
                clientPhotos.restoreTokens(restoredTokens, { rejected: analysisError || null });
            }
        } catch (e) {}

        // ---------------------------------------------------------------- Review step
        const text = (selector) => (document.querySelector(selector)?.value || '').trim();
        const formatTime = (value) => {
            if (!value) return '';
            const [h, m] = value.split(':');
            const hour = Number(h);
            return ((hour % 12) || 12) + ':' + m + ' ' + (hour >= 12 ? 'PM' : 'AM');
        };
        function reviewItem(list, label, value, wide) {
            const row = document.createElement('div');
            if (wide) row.className = 'sm:col-span-2';
            const dt = document.createElement('dt');
            dt.className = 'text-xs font-medium text-slate-500';
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.className = 'mt-0.5 break-words font-semibold text-slate-900';
            dd.textContent = value;
            row.append(dt, dd);
            list.appendChild(row);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        let clientReviewEstimateRequest = 0;
        window.latestEstimateData = null;
        window.latestBreakdownData = null;

        function loadClientReviewEstimate(tokens) {
            const statusEl = document.getElementById('client-review-estimate-status');
            const requestId = ++clientReviewEstimateRequest;
            window.latestEstimateData = null;
            window.latestBreakdownData = null;
            window.RafloraAiEstimate?.render('client-review-ai-estimate', null);
            window.RafloraAreaBreakdown?.render('client-review-area-breakdown', null);
            if (!tokens || tokens.length === 0) return;
            if (statusEl) { statusEl.textContent = 'Working out the AI estimate for your photos…'; statusEl.classList.remove('hidden'); }

            const formData = new FormData();
            tokens.forEach(token => formData.append('analysis_tokens[]', token));
            window.RafloraDesignBrief?.get('design-brief')?.appendTo(formData);
            fetch(@json(route('bookings.analysis-estimate')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData,
            })
            .then(async res => ({ ok: res.ok, data: await res.json().catch(() => ({})) }))
            .then(({ ok, data }) => {
                if (requestId !== clientReviewEstimateRequest) return;
                if (!ok) {
                    if (statusEl) statusEl.textContent = data.message || 'The AI estimate is unavailable right now. Raflora will price your design in the official quotation.';
                    return;
                }
                statusEl?.classList.add('hidden');
                window.latestEstimateData = data.estimate || null;
                window.latestBreakdownData = data.area_breakdown || null;
                window.RafloraAiEstimate?.render('client-review-ai-estimate', data.estimate);
                window.RafloraAreaBreakdown?.render('client-review-area-breakdown', data.area_breakdown, data.estimate);
                const matsCountText = document.getElementById('client-review-materials-count-text');
                if (matsCountText && Number.isInteger(data.material_count)) matsCountText.textContent = `${data.material_count} items`;
            })
            .catch(() => {
                if (requestId === clientReviewEstimateRequest && statusEl) statusEl.textContent = 'The AI estimate could not be loaded. Raflora will price your design in the official quotation.';
            });
        }

        function populateClientReview() {
            // 1. Event Details
            const eventSelect = document.querySelector('select[name="event_type"]');
            const eventType = eventSelect?.value === 'other' ? (text('input[name="other_event_type"]') || 'Other') : (eventSelect?.selectedOptions[0]?.textContent.trim() || '-');
            const date = text('input[name="event_date"]');
            const start = formatTime(text('input[name="event_time"]'));
            const end = formatTime(text('input[name="end_time"]'));
            const setting = document.querySelector('input[name="venue_setting"]:checked');
            const venue = text('input[name="venue"]') || '-';
            const guests = text('input[name="guest_count"]') || 'Not specified';
            const tables = text('input[name="table_count"]') || 'Not specified';
            const notes = text('textarea[name="special_requests"]') || 'No additional notes.';
            const formattedDate = date ? new Date(date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
            const formattedTime = start ? start + (end ? ' – ' + end : '') : 'Not specified';
            const settingLabel = setting ? setting.closest('label')?.textContent.trim() || setting.value : 'Not specified';

            const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setEl('client-review-event-type', eventType);
            setEl('client-review-event-date', formattedDate);
            setEl('client-review-event-time', formattedTime);
            setEl('client-review-venue', venue);
            setEl('client-review-venue-setting', settingLabel);
            setEl('client-review-guest-count', guests);
            setEl('client-review-table-count', tables);
            setEl('client-review-notes', notes);
            setEl('client-review-event-mobile-summary', `${eventType} · ${formattedDate}`);

            // 2. Method
            const isPreset = form?.querySelector('input[name="booking_type"]:checked')?.value === 'preset';
            const methodText = isPreset ? 'Curated Package' : 'Smart AI Custom Design';
            setEl('client-review-method-title', methodText);
            setEl('client-review-method-mobile-summary', methodText);
            setEl('client-review-summary-method', methodText);

            // 3. Images / Package
            const title = document.getElementById('client-review-design-title');
            const status = document.getElementById('client-review-design-status');
            const thumbnails = document.getElementById('client-review-multi-thumbnails');
            const thumbnailsRow = document.getElementById('client-review-multi-images-row');
            const packageWrapper = document.getElementById('client-review-package-inclusions-wrapper');
            const packageItems = document.getElementById('client-review-package-items');
            const briefRow = document.getElementById('client-review-design-brief-row');
            const briefList = document.getElementById('client-review-brief');
            const aiSection = document.getElementById('client-review-ai-materials-section');
            const estimateStatus = document.getElementById('client-review-estimate-status');

            if (thumbnails) thumbnails.replaceChildren();
            if (packageItems) packageItems.replaceChildren();
            if (briefList) briefList.replaceChildren();

            if (isPreset) {
                if (aiSection) aiSection.classList.add('hidden');
                if (thumbnailsRow) thumbnailsRow.classList.add('hidden');
                if (briefRow) briefRow.classList.add('hidden');
                if (packageWrapper) packageWrapper.classList.remove('hidden');

                let pkg = null;
                try { pkg = JSON.parse(form.querySelector('input[name="package_id"]:checked')?.closest('.client-package-card')?.getAttribute('data-package') || 'null'); } catch (e) {}
                const pkgTitle = pkg?.title || 'Curated package';
                if (title) title.textContent = pkgTitle;
                if (status) status.textContent = pkg?.description || 'Selected curated package';
                setEl('client-review-media-mobile-summary', pkgTitle);
                const price = pkg?.price ? '₱' + Number(pkg.price).toLocaleString('en-PH', { maximumFractionDigits: 0 }) : 'In your official quotation';
                setEl('client-review-summary-price-label', 'Catalog price');
                setEl('client-review-summary-price', price);

                (Array.isArray(pkg?.included_items) ? pkg.included_items : []).forEach(item => {
                    const li = document.createElement('li');
                    li.textContent = '✓ ' + item;
                    packageItems?.appendChild(li);
                });
                window.RafloraAiEstimate?.render('client-review-ai-estimate', null);
                window.RafloraAreaBreakdown?.render('client-review-area-breakdown', null);
                estimateStatus?.classList.add('hidden');
                return;
            }

            // Custom AI Design
            if (packageWrapper) packageWrapper.classList.add('hidden');
            if (aiSection) aiSection.classList.remove('hidden');

            const all = clientPhotos.photos();
            const ready = clientPhotos.readyPhotos();
            const leftOut = all.length - ready.length;

            if (title) title.textContent = 'Smart AI Custom Design';
            const readySummary = ready.length + ' of ' + all.length + ' photo' + (all.length === 1 ? '' : 's') + ' analyzed and included'
                + (leftOut > 0 ? ' · ' + leftOut + ' could not be analyzed and will be left out' : '') + '.';
            if (status) status.textContent = readySummary;
            setEl('client-review-media-mobile-summary', `${ready.length} photo${ready.length === 1 ? '' : 's'} analyzed`);
            setEl('client-review-images-count-text', `${ready.length} image${ready.length === 1 ? '' : 's'}`);
            setEl('client-review-summary-price-label', 'Price');
            setEl('client-review-summary-price', 'In your official quotation');

            if (ready.length > 0 && thumbnailsRow) {
                thumbnailsRow.classList.remove('hidden');
                ready.forEach(photo => {
                    const number = all.indexOf(photo) + 1;
                    const img = document.createElement('img');
                    img.src = photo.thumbnailUrl || photo.previewUrl || '';
                    img.alt = 'Inspiration photo ' + number;
                    img.className = 'h-14 w-14 rounded-xl border border-slate-200 object-cover shadow-2xs';
                    thumbnails?.appendChild(img);
                });
            } else if (thumbnailsRow) {
                thumbnailsRow.classList.add('hidden');
            }

            const briefRows = designBrief()?.summaryRows() || [];
            if (briefRows.length > 0 && briefRow) {
                briefRow.classList.remove('hidden');
                briefRows.forEach(([label, value]) => reviewItem(briefList, label, value));
            } else if (briefRow) {
                briefRow.classList.add('hidden');
            }

            // Load and render AI Estimate & Area Breakdown
            const cachedData = clientPhotos.estimate();
            if (cachedData && cachedData.estimate && cachedData.area_breakdown) {
                window.latestEstimateData = cachedData.estimate || null;
                window.latestBreakdownData = cachedData.area_breakdown || null;
                window.RafloraAiEstimate?.render('client-review-ai-estimate', cachedData.estimate);
                window.RafloraAreaBreakdown?.render('client-review-area-breakdown', cachedData.area_breakdown, cachedData.estimate);
                const matsCountText = document.getElementById('client-review-materials-count-text');
                if (matsCountText && Number.isInteger(cachedData.material_count)) matsCountText.textContent = `${cachedData.material_count} items`;
                estimateStatus?.classList.add('hidden');
            } else {
                const tokens = ready.map(p => p.token).filter(Boolean);
                loadClientReviewEstimate(tokens);
            }
        }

        // ==========================================
        // AREA DETAILS MODAL & ACCORDION CONTROLLERS
        // ==========================================
        let currentModalAreaKey = 'stage';
        let modalOpenerEl = null;
        let modalItems = [];
        let modalShowAll = false;

        window.toggleClientReviewCard = function(cardId) {
            const card = document.getElementById(cardId);
            if (!card) return;
            const body = document.getElementById(cardId + '-body');
            const chevron = card.querySelector('[data-accordion-chevron]');
            if (!body) return;
            const isHidden = body.classList.contains('hidden');
            body.classList.toggle('hidden', !isHidden);
            if (chevron) {
                chevron.classList.toggle('rotate-180', isHidden);
            }
        };

        window.openAreaDetailsModal = function(areaKey, openerEl) {
            currentModalAreaKey = areaKey || 'stage';
            modalOpenerEl = openerEl || document.activeElement;
            modalShowAll = false;

            const modal = document.getElementById('booking-area-details-modal');
            if (!modal) return;

            // Find area data from latest breakdown
            const breakdown = window.latestBreakdownData;
            const area = (breakdown && Array.isArray(breakdown.areas))
                ? breakdown.areas.find(a => a.key === currentModalAreaKey)
                : null;

            const areaLabel = area?.label || (currentModalAreaKey === 'stage' ? 'Stage or backdrop' : (currentModalAreaKey === 'shared' ? 'Shared Materials' : currentModalAreaKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())));
            const areaColor = area?.color || '#7c3aed';

            // 1. Header
            const titleEl = document.getElementById('area-modal-title');
            if (titleEl) {
                titleEl.textContent = areaLabel.toLowerCase().includes('details') ? areaLabel : `${areaLabel} details`;
            }
            const iconBox = document.getElementById('area-modal-icon-box');
            if (iconBox) {
                iconBox.style.backgroundColor = areaColor + '20';
                iconBox.style.color = areaColor;
            }

            // 2. Client Note
            syncModalNoteUI();

            // 3. AI Price Estimate
            const estimate = window.latestEstimateData;
            const estGroup = (estimate && Array.isArray(estimate.groups))
                ? estimate.groups.find(g => g.key === currentModalAreaKey)
                : null;

            const estPriceEl = document.getElementById('area-modal-estimate-price');
            const partialBadge = document.getElementById('area-modal-partial-badge');
            const estNoteEl = document.getElementById('area-modal-estimate-note');
            const labourEl = document.getElementById('area-modal-labour-line');

            if (estGroup && estGroup.display) {
                if (estPriceEl) estPriceEl.textContent = estGroup.display;
                if (partialBadge) partialBadge.classList.toggle('hidden', estGroup.status !== 'incomplete');
                if (estNoteEl) estNoteEl.textContent = estGroup.note || 'Indicative estimate only (±15%). Labour and setup are not included. Final price is confirmed after Raflora staff review and material validation.';
            } else {
                if (estPriceEl) estPriceEl.textContent = area?.missing ? 'No estimate' : 'Price pending florist quotation';
                if (partialBadge) partialBadge.classList.add('hidden');
                if (estNoteEl) estNoteEl.textContent = 'The AI could not benchmark prices for these materials. Raflora confirms the authoritative price in your official quotation.';
            }

            if (labourEl) {
                labourEl.textContent = (estimate && estimate.labour_included) ? 'Includes florist labour & setup' : 'Labour & setup not included';
            }

            // 4. Materials
            modalItems = (area && Array.isArray(area.items)) ? area.items : [];
            const totalCount = modalItems.length;
            const isMissing = Boolean(area?.missing || totalCount === 0);

            const matCountEl = document.getElementById('area-modal-mat-count');
            if (matCountEl) matCountEl.textContent = `${totalCount} item${totalCount === 1 ? '' : 's'}`;

            const detectedCount = modalItems.filter(i => i.is_detected).length;
            const suggestedCount = modalItems.filter(i => !i.is_detected).length;

            const detectedBadge = document.getElementById('area-modal-detected-badge');
            if (detectedBadge) detectedBadge.textContent = `${detectedCount} Detected`;
            const suggestedBadge = document.getElementById('area-modal-suggested-badge');
            if (suggestedBadge) suggestedBadge.textContent = `${suggestedCount} Suggested`;

            const missingNotice = document.getElementById('area-modal-missing-notice');
            const matContainer = document.getElementById('area-modal-materials-container');
            const toggleMoreBtn = document.getElementById('area-modal-toggle-more-btn');

            if (isMissing) {
                if (missingNotice) missingNotice.classList.remove('hidden');
                if (matContainer) matContainer.classList.add('hidden');
                if (toggleMoreBtn) toggleMoreBtn.classList.add('hidden');
            } else {
                if (missingNotice) missingNotice.classList.add('hidden');
                if (matContainer) matContainer.classList.remove('hidden');
                renderModalMaterials();
            }

            // Open modal
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            const closeBtn = modal.querySelector('[data-action="close-modal"]');
            if (closeBtn) closeBtn.focus();
        };

        window.closeAreaDetailsModal = function() {
            const modal = document.getElementById('booking-area-details-modal');
            if (modal) modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (modalOpenerEl && typeof modalOpenerEl.focus === 'function') {
                modalOpenerEl.focus();
                modalOpenerEl = null;
            }
        };

        function syncModalNoteUI() {
            const input = document.querySelector(`input[name="decor_area_notes[${currentModalAreaKey}]"]`);
            const briefState = window.RafloraDesignBrief?.get('design-brief')?.getState() || {};
            const savedNote = input ? input.value : (briefState.decor_area_notes?.[currentModalAreaKey] || '');

            const displayEl = document.getElementById('area-modal-note-display');
            const editorEl = document.getElementById('area-modal-note-editor');
            const textarea = document.getElementById('area-modal-note-input');
            const charCount = document.getElementById('area-modal-note-char-count');

            if (displayEl) {
                if (savedNote && savedNote.trim()) {
                    displayEl.textContent = savedNote.trim();
                    displayEl.classList.remove('italic', 'text-slate-400');
                    displayEl.classList.add('text-slate-800');
                } else {
                    displayEl.textContent = 'No specific instructions added yet for this area.';
                    displayEl.classList.add('italic', 'text-slate-400');
                    displayEl.classList.remove('text-slate-800');
                }
            }

            if (editorEl) editorEl.classList.add('hidden');
            if (displayEl) displayEl.classList.remove('hidden');
            if (textarea) textarea.value = savedNote || '';
            if (charCount) charCount.textContent = `${(savedNote || '').length} / 500 characters`;
        }

        function renderModalMaterials() {
            const tbody = document.getElementById('area-modal-table-body');
            const cardsList = document.getElementById('area-modal-cards-list');
            const toggleMoreBtn = document.getElementById('area-modal-toggle-more-btn');
            const toggleMoreText = document.getElementById('area-modal-toggle-more-text');
            const toggleMoreIcon = document.getElementById('area-modal-toggle-more-icon');

            if (!tbody || !cardsList) return;
            tbody.innerHTML = '';
            cardsList.innerHTML = '';

            const itemsToShow = modalShowAll ? modalItems : modalItems.slice(0, 5);

            itemsToShow.forEach((item, idx) => {
                const indexNum = idx + 1;
                const name = item.name || item.item_name || 'Floral Material';
                const qty = item.quantity !== null && item.quantity !== undefined ? item.quantity : '—';
                const unit = item.unit_type || 'pcs';
                const basis = item.quantity_basis || '';
                const isDetected = Boolean(item.is_detected);

                // Table row (Desktop)
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/70 transition-colors';
                tr.innerHTML = `
                    <td class="py-2.5 px-3 text-slate-400 font-mono text-[11px]">${indexNum}</td>
                    <td class="py-2.5 px-3">
                        <div class="font-semibold text-slate-800">${escapeHtml(name)}</div>
                        ${basis ? `<div class="text-[10px] text-slate-400 mt-0.5 leading-snug">${escapeHtml(basis)}</div>` : ''}
                    </td>
                    <td class="py-2.5 px-3 text-center font-bold text-slate-900 font-mono">${qty}</td>
                    <td class="py-2.5 px-3 text-slate-500">${escapeHtml(unit)}</td>
                    <td class="py-2.5 px-3 text-right text-slate-400 font-mono">—</td>
                    <td class="py-2.5 px-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${isDetected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                            ${isDetected ? 'Detected' : 'Suggested'}
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);

                // Card row (Mobile)
                const card = document.createElement('div');
                card.className = 'p-3 flex items-start justify-between gap-2.5';
                card.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-slate-800 text-xs">${escapeHtml(name)}</div>
                        ${basis ? `<div class="text-[10px] text-slate-400 mt-0.5">${escapeHtml(basis)}</div>` : ''}
                        <div class="text-[11px] text-slate-500 font-semibold mt-1">
                            ${qty} ${escapeHtml(unit)}
                        </div>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${isDetected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                            ${isDetected ? 'Detected' : 'Suggested'}
                        </span>
                    </div>
                `;
                cardsList.appendChild(card);
            });

            if (toggleMoreBtn) {
                if (modalItems.length > 5) {
                    toggleMoreBtn.classList.remove('hidden');
                    if (modalShowAll) {
                        if (toggleMoreText) toggleMoreText.textContent = 'Show less';
                        if (toggleMoreIcon) toggleMoreIcon.classList.add('rotate-180');
                    } else {
                        const rem = modalItems.length - 5;
                        if (toggleMoreText) toggleMoreText.textContent = `Show ${rem} more material${rem === 1 ? '' : 's'}`;
                        if (toggleMoreIcon) toggleMoreIcon.classList.remove('rotate-180');
                    }
                } else {
                    toggleMoreBtn.classList.add('hidden');
                }
            }
        }

        // Interoperability with RafloraStageBackdropModal
        window.RafloraStageBackdropModal = {
            open: function(id, opener) { window.openAreaDetailsModal('stage', opener); },
            close: function() { window.closeAreaDetailsModal(); }
        };

        // ---------------------------------------------------------------- Submission
        form?.addEventListener('submit', function (e) {
            if (form.dataset.submitting === 'true') {
                e.preventDefault();
                return false;
            }
            // Something changed after the review was shown: back to the step that needs attention.
            const issue = step2Issue();
            if (issue) {
                e.preventDefault();
                showBookingStep(2);
                step2Error.textContent = issue;
                step2Error.classList.remove('hidden');
                step2Error.focus();
                return false;
            }
            form.dataset.submitting = 'true';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Submitting...`;
            }
        });

        window.addEventListener('pageshow', function () {
            if (form) form.dataset.submitting = 'false';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.innerHTML = 'Submit Booking Request <span aria-hidden="true">→</span>';
            }
        });

        // Initialize Carousel Drag & Keyboard Navigation directly in DOMContentLoaded
        const carousel = document.getElementById('client-package-carousel');
        if (carousel) {
            carousel.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') { e.preventDefault(); carousel.scrollBy({ left: -carousel.clientWidth * 0.6, behavior: 'smooth' }); }
                if (e.key === 'ArrowRight') { e.preventDefault(); carousel.scrollBy({ left: carousel.clientWidth * 0.6, behavior: 'smooth' }); }
            });

            let isPointerDown = false, isDragging = false, dragStartX = 0, dragScrollLeft = 0;
            carousel.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'mouse') return;
                isPointerDown = true; isDragging = false; dragStartX = e.clientX; dragScrollLeft = carousel.scrollLeft;
                carousel.classList.add('cursor-grabbing');
            });
            carousel.addEventListener('pointermove', (e) => {
                if (!isPointerDown || e.pointerType !== 'mouse') return;
                const deltaX = e.clientX - dragStartX;
                if (!isDragging && Math.abs(deltaX) > 5) isDragging = true;
                if (isDragging) { e.preventDefault(); carousel.scrollLeft = dragScrollLeft - deltaX; }
            });
            const stopDrag = () => { isPointerDown = false; carousel.classList.remove('cursor-grabbing'); setTimeout(() => { isDragging = false; }, 0); };
            carousel.addEventListener('pointerup', stopDrag);
            carousel.addEventListener('pointercancel', stopDrag);
            carousel.addEventListener('click', (e) => { if (isDragging) { e.preventDefault(); e.stopPropagation(); } });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const oldBookingType = @json(old('booking_type', request('package_id') ? 'preset' : null));
        if (oldBookingType === 'preset' || urlParams.has('package_id')) {
            window.switchMode('preset');
        }

        // Package selection UI refresh & event type sync
        const packageRadios = document.querySelectorAll('.client-package-card .package-radio');
        const packageCards = document.querySelectorAll('.client-package-card');
        const packageViewButtons = document.querySelectorAll('.view-package-btn');
        const eventTypeSelect = document.querySelector('select[name="event_type"]');
        const otherEventTypeContainer = document.getElementById('otherEventTypeContainer');
        const otherEventTypeInput = document.querySelector('input[name="other_event_type"]');

        // Event details now come first, so a package only fills the event type when the client left
        // it empty (and only with a type the select actually offers).
        function setPackageEventType(eventType) {
            if (!eventTypeSelect || !eventType || eventTypeSelect.value) return;
            if (!Array.from(eventTypeSelect.options).some(option => option.value === eventType)) return;
            eventTypeSelect.value = eventType;
            eventTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function refreshPackageSelection() {
            packageCards.forEach(card => {
                const radio = card.querySelector('.package-radio');
                const badge = card.querySelector('.package-badge');
                const on = Boolean(radio && radio.checked);
                ['ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg', 'bg-emerald-50/20'].forEach(cls => card.classList.toggle(cls, on));
                card.classList.toggle('border-slate-200', !on);
                badge?.classList.toggle('hidden', !on);
                badge?.classList.toggle('flex', on);
            });
        }

        // Guest and table counts anchor the AI's quantities; a delivery order has neither, so they are
        // hidden, cleared and left out of the submission (disabled).
        function handleEventTypeChange() {
            if (!eventTypeSelect || !otherEventTypeContainer || !otherEventTypeInput) return;
            const isDelivery = eventTypeSelect.value === 'delivery';
            document.querySelectorAll('[data-delivery-hidden]').forEach(el => el.classList.toggle('hidden', isDelivery));
            ['guest_count', 'table_count'].map(id => document.getElementById(id)).filter(Boolean).forEach(input => {
                if (isDelivery) input.value = '';
                input.disabled = isDelivery;
            });
            const isOther = eventTypeSelect.value.toLowerCase() === 'other';
            otherEventTypeContainer.classList.toggle('hidden', !isOther);
            otherEventTypeInput.required = isOther;
            if (!isOther) otherEventTypeInput.value = '';
        }

        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', handleEventTypeChange);
            handleEventTypeChange();
        }

        const packageFromCard = (card) => {
            try { return JSON.parse(card?.getAttribute('data-package') || 'null'); } catch (err) { return null; }
        };

        packageRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                refreshPackageSelection();
                document.getElementById('client-step2-error')?.classList.add('hidden');
                if (radio.checked) {
                    const pkg = packageFromCard(radio.closest('.client-package-card'));
                    if (pkg?.event_type) setPackageEventType(pkg.event_type);
                }
            });
        });

        // Single-click selection for the entire card (excluding View Package button)
        packageCards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.closest('.view-package-btn')) return;
                const radio = card.querySelector('.package-radio');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        // Initial state sync on page load
        refreshPackageSelection();
        const initialPackage = packageFromCard(document.querySelector('.client-package-card .package-radio:checked')?.closest('.client-package-card'));
        if (initialPackage?.event_type) setPackageEventType(initialPackage.event_type);

        // View Package Button Click Handlers
        packageViewButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const pkg = packageFromCard(e.currentTarget.closest('.client-package-card'));
                if (pkg) window.openPackageModal(pkg, e.currentTarget);
            });
        });

        // Package details dialog (Admin-entered text is inserted as text, never as HTML)
        let packageModalOpener = null;
        window.closePackageModal = function() {
            document.body.classList.remove('overflow-hidden');
            const modal = document.getElementById('packageModal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); modal.style.display = 'none'; }
            packageModalOpener?.focus();
            packageModalOpener = null;
        };

        window.openPackageModal = function(pkg, opener) {
            const modal = document.getElementById('packageModal');
            if (!modal) return;
            packageModalOpener = opener || null;
            document.body.classList.add('overflow-hidden');
            modal.classList.remove('hidden'); modal.classList.add('flex'); modal.style.display = 'flex';

            document.getElementById('packageModalTitle').textContent = pkg.title || '';
            document.getElementById('packageModalDescription').textContent = pkg.description || '';
            document.getElementById('packageModalPrice').textContent = pkg.price ? ('₱' + Number(pkg.price).toLocaleString('en-PH', {minimumFractionDigits:2})) : '';

            const imageBox = document.getElementById('packageModalImage');
            if (imageBox) {
                imageBox.replaceChildren();
                if (pkg.image_url) {
                    const img = document.createElement('img');
                    img.src = pkg.image_url;
                    img.alt = pkg.title || 'Package photo';
                    img.className = 'w-full h-56 object-cover rounded-md';
                    imageBox.appendChild(img);
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'w-full h-56 flex items-center justify-center bg-slate-100 text-slate-400';
                    empty.textContent = 'No Image Available';
                    imageBox.appendChild(empty);
                }
            }

            const list = document.getElementById('packageModalInclusions');
            if (list) {
                list.replaceChildren();
                (Array.isArray(pkg.included_items) ? pkg.included_items : []).forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'flex items-start gap-2';
                    const mark = document.createElement('span');
                    mark.className = 'text-emerald-600 font-bold';
                    mark.textContent = '✓';
                    const label = document.createElement('span');
                    label.textContent = item;
                    li.append(mark, label);
                    list.appendChild(li);
                });
            }
            document.getElementById('packageModalSelectBtn').dataset.packageId = pkg.id;
            modal.querySelector('[aria-label="Close"]')?.focus();
        };

        // Area Details Modal Event Listeners
        document.getElementById('area-modal-edit-note-btn')?.addEventListener('click', function() {
            document.getElementById('area-modal-note-editor')?.classList.remove('hidden');
            document.getElementById('area-modal-note-display')?.classList.add('hidden');
            const textarea = document.getElementById('area-modal-note-input');
            if (textarea) textarea.focus();
        });

        document.getElementById('area-modal-cancel-note-btn')?.addEventListener('click', function() {
            syncModalNoteUI();
        });

        document.getElementById('area-modal-save-note-btn')?.addEventListener('click', function() {
            const noteInput = document.getElementById('area-modal-note-input');
            const newNote = noteInput ? noteInput.value.trim().slice(0, 500) : '';
            
            let hiddenInput = document.querySelector(`input[name="decor_area_notes[${currentModalAreaKey}]"]`);
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `decor_area_notes[${currentModalAreaKey}]`;
                document.getElementById('client-booking-form')?.appendChild(hiddenInput);
            }
            hiddenInput.value = newNote;

            const briefEl = document.getElementById('design-brief');
            if (briefEl) {
                const briefInput = briefEl.querySelector(`[data-area-note-input="${currentModalAreaKey}"]`);
                if (briefInput) briefInput.value = newNote;
                briefEl.dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncModalNoteUI();
        });

        document.getElementById('area-modal-note-input')?.addEventListener('input', function() {
            const charCount = document.getElementById('area-modal-note-char-count');
            if (charCount) charCount.textContent = `${this.value.length} / 500 characters`;
        });

        document.getElementById('area-modal-toggle-more-btn')?.addEventListener('click', function() {
            modalShowAll = !modalShowAll;
            renderModalMaterials();
        });

        document.addEventListener('keydown', (e) => {
            const packageModal = document.getElementById('packageModal');
            if (e.key === 'Escape' && packageModal && !packageModal.classList.contains('hidden')) {
                window.closePackageModal();
                return;
            }
            const areaModal = document.getElementById('booking-area-details-modal');
            if (e.key === 'Escape' && areaModal && !areaModal.classList.contains('hidden')) {
                window.closeAreaDetailsModal();
            }
        });

        document.getElementById('booking-area-details-modal')?.addEventListener('click', function(e) {
            if (e.target === this || e.target.closest('[data-action="close-modal"]')) {
                window.closeAreaDetailsModal();
            }
        });

        window.selectPackageFromModal = function(id) {
            if (!id) return;
            const radio = document.querySelector(`input[name="package_id"][value="${CSS.escape(String(id))}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
            window.closePackageModal();
        }

        if (analysisError) switchMode('ai');
        showBookingStep(currentStep);
    });

    // Step 3 review dialogs (same pattern as the guest booking review): the AI Analysis Summary is the
    // existing section, filled and shown/hidden by populateClientReview(); the Client Profile is read-only.
    document.addEventListener('DOMContentLoaded', function() {
        const aiSection = document.getElementById('client-review-ai-materials-section');
        const aiOpenBtn = document.getElementById('client-open-review-ai-summary');
        let opener = null;

        const isOpen = (id) => {
            const el = document.getElementById(id);
            return !!el && !el.classList.contains('hidden');
        };
        const openDialogs = () => Array.from(document.querySelectorAll('[data-review-dialog]:not(.hidden)'));

        // The AI shortcut is offered only when the review shows the AI section (custom design with analyzed photos).
        const syncAiButton = () => aiOpenBtn?.classList.toggle('hidden', !aiSection || aiSection.classList.contains('hidden'));
        if (aiSection) new MutationObserver(syncAiButton).observe(aiSection, { attributes: true, attributeFilter: ['class'] });
        syncAiButton();

        function openDialog(id, trigger) {
            const modal = document.getElementById(id);
            if (!modal) return;
            opener = trigger || document.activeElement;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            modal.querySelector('[data-review-dialog-close]')?.focus();
        }

        function closeDialog(modal) {
            if (!modal || modal.classList.contains('hidden')) return;
            modal.classList.add('hidden');
            if (!openDialogs().length) document.body.classList.remove('overflow-hidden');
            if (opener && typeof opener.focus === 'function') opener.focus();
            opener = null;
        }

        document.querySelectorAll('[data-review-dialog-open]').forEach(btn => {
            btn.addEventListener('click', () => openDialog(btn.dataset.reviewDialogOpen, btn));
        });
        document.querySelectorAll('[data-review-dialog-close]').forEach(btn => {
            btn.addEventListener('click', () => closeDialog(btn.closest('[data-review-dialog]')));
        });
        document.querySelectorAll('[data-review-dialog]').forEach(modal => {
            modal.addEventListener('click', e => { if (e.target === modal) closeDialog(modal); });
        });

        // The AI summary's "View AI Analysis" button goes back to Step 2: close the dialog first.
        aiSection?.querySelectorAll('[data-goto-step]').forEach(btn => {
            btn.addEventListener('click', () => closeDialog(btn.closest('[data-review-dialog]')), true);
        });

        // Escape closes the top review dialog, unless the package or area-details dialog is on top of it.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape' || isOpen('packageModal') || isOpen('booking-area-details-modal')) return;
            const top = openDialogs().pop();
            if (top) closeDialog(top);
        }, true);

        // Closing the area-details dialog (opened from the AI summary) unlocks page scroll; keep it locked.
        const areaModal = document.getElementById('booking-area-details-modal');
        if (areaModal) new MutationObserver(() => {
            if (areaModal.classList.contains('hidden') && openDialogs().length) document.body.classList.add('overflow-hidden');
        }).observe(areaModal, { attributes: true, attributeFilter: ['class'] });
    });
</script>

<!-- Review dialogs (Step 3): opened from the cards under Request Summary. Placed outside the page
     layout like the modals below, so they cover the navigation bar and the sticky summary. -->
<div id="client-review-ai-summary-modal" class="fixed inset-0 z-[45] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-label="AI Analysis Summary" tabindex="-1" data-review-dialog>
    <div class="relative w-full sm:max-w-3xl lg:max-w-4xl max-h-[92dvh] sm:max-h-[calc(100dvh-2rem)] bg-slate-50 rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
        <div class="flex items-center justify-end gap-2 px-3 py-2 sm:px-4 border-b border-slate-200/80 bg-white shrink-0">
            <button type="button" data-review-dialog-close class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition" aria-label="Close AI Analysis Summary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span>Close</span>
            </button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3 sm:p-4">
            <!-- Review Card 4: AI Analysis Summary (Custom AI Only), filled and shown/hidden by populateClientReview() as before -->
                    <div id="client-review-ai-materials-section" class="bg-white rounded-2xl border border-slate-200/90 p-4 sm:p-5 shadow-2xs space-y-3.5 hidden">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">AI Analysis Summary</h4>
                                    <p class="text-[11px] text-slate-500">Indicative ranges and material suggestions per decoration area</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button type="button" data-goto-step="2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 transition shadow-2xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span class="hidden sm:inline">View AI Analysis</span>
                                </button>
                                <button type="button" onclick="toggleClientReviewCard('client-review-ai-materials-section')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle AI Analysis Summary">
                                    <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </div>
                        </div>

                        <div id="client-review-ai-materials-section-body" class="space-y-3.5">
                            <!-- Compact Multi-Image AI Summary Metrics -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-emerald-50/40 rounded-xl border border-emerald-100 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Inspiration Images</span>
                                    <span class="font-bold text-slate-800" id="client-review-images-count-text">1 image</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Detected Materials</span>
                                    <span class="font-bold text-emerald-700" id="client-review-materials-count-text">0 items</span>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Price</span>
                                    <span class="font-bold text-slate-800">In your official quotation</span>
                                </div>
                            </div>

                            {{-- All photos combined on the server: per-area AI ranges and quantities, with no figure counted twice --}}
                            <p id="client-review-estimate-status" class="hidden text-[11px] text-slate-500" role="status"></p>
                            <x-ai-estimate id="client-review-ai-estimate" class="mb-3 hidden" />
                            <x-decor-area-breakdown-live id="client-review-area-breakdown" class="mb-3" />

                            <!-- Review AI Disclaimers -->
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
                                <p><strong class="text-slate-700">Seasonal Context:</strong> Sourced information is AI-assisted and reference-based. It requires Raflora florist validation and does not guarantee supplier availability.</p>
                                <p><strong class="text-slate-700">Business Truth:</strong> AI detection ≠ procurement feasibility ≠ inventory availability ≠ official quotation.</p>
                            </div>
                        </div>
                    </div>
        </div>
    </div>
</div>

<!-- Review Card 5: Client Information (Authenticated Profile), shown in a dialog; read-only, from the client account -->
<div id="client-review-contact-modal" class="fixed inset-0 z-[45] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="client-review-contact-modal-title" tabindex="-1" data-review-dialog>
    <div class="relative w-full sm:max-w-2xl max-h-[92dvh] sm:max-h-[calc(100dvh-2rem)] bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden" id="client-review-card-contact">
        <header class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5 border-b border-slate-100 bg-slate-50/90 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div class="min-w-0 flex items-center gap-2 flex-wrap">
                    <h3 id="client-review-contact-modal-title" class="text-base sm:text-lg font-bold text-[#0B1E43] font-serif tracking-tight">Client Profile</h3>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800">
                        Authenticated
                    </span>
                </div>
            </div>
            <button type="button" data-review-dialog-close class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-white transition" aria-label="Close Client Profile">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5 sm:py-5">
            <div id="client-review-card-contact-body" class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-3 text-xs">
                <div>
                    <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Client Name</span>
                    <span class="font-bold text-slate-800 text-xs sm:text-sm mt-0.5 block" id="client-review-contact-name">{{ $clientName }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Email Address</span>
                    <span class="font-bold text-slate-800 text-xs sm:text-sm mt-0.5 block truncate" id="client-review-contact-email">{{ $clientEmail }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Mobile Number</span>
                    <span class="font-bold text-slate-800 text-xs sm:text-sm mt-0.5 block" id="client-review-contact-phone">{{ $clientPhone }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Billing / Contact Address</span>
                    <span class="font-bold text-slate-800 text-xs sm:text-sm mt-0.5 block" id="client-review-contact-address">{{ $clientAddress }}</span>
                </div>
                <div class="sm:col-span-2 pt-1 text-[11px] text-slate-500">
                    This booking request will be linked to your client account. Contact details can be updated anytime in your profile settings.
                </div>
            </div>
        </div>
        <footer class="flex items-center justify-end gap-2 px-4 py-3 sm:px-5 border-t border-slate-100 bg-slate-50/90 shrink-0">
            <button type="button" data-review-dialog-close class="flex-1 sm:flex-none px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">Close</button>
        </footer>
    </div>
</div>
<!-- Package Details Modal -->
<div id="packageModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="packageModalTitle">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/60 transition-opacity" onclick="closePackageModal()"></div>
    
    <!-- Centered Modal Box -->
    <div class="relative z-10 w-full max-w-lg mx-auto max-h-[85dvh] overflow-y-auto p-4 md:p-6 rounded-2xl bg-white shadow-xl flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4 flex-shrink-0">
            <h3 id="packageModalTitle" class="text-lg font-bold text-slate-900"></h3>
            <button type="button" class="text-slate-400 hover:text-slate-700 transition text-xl leading-none" onclick="closePackageModal()" aria-label="Close">✕</button>
        </div>
        <!-- Scrollable body -->
        <div class="space-y-4 flex-1">
            <div id="packageModalImage" class="rounded-xl overflow-hidden"></div>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                <div id="packageModalDescription" class="text-sm text-slate-600 flex-1"></div>
                <div id="packageModalPrice" class="text-2xl font-extrabold text-emerald-600 flex-shrink-0"></div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-800 mb-2">Inclusions</h4>
                <ul id="packageModalInclusions" class="space-y-2 text-sm text-slate-700"></ul>
            </div>
        </div>
        <!-- Footer actions -->
        <div class="px-6 py-4 border-t flex-shrink-0 flex flex-col sm:flex-row justify-end gap-3">
            <button type="button" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition text-sm" onclick="closePackageModal()">Close</button>
            <button id="packageModalSelectBtn" type="button" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition text-sm" onclick="selectPackageFromModal(this.dataset.packageId)">Select This Package</button>
        </div>
    </div>
</div>

<!-- Reusable Area Details Modal (Center on Desktop, Bottom Sheet Drawer on Mobile) -->
<div id="booking-area-details-modal"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-300 hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="area-modal-title"
     aria-describedby="area-modal-desc"
     tabindex="-1">

    <div class="relative w-full sm:max-w-2xl max-h-[88vh] sm:max-h-[90vh] bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden animate-slide-up sm:animate-fade-in"
         data-dialog-panel>

        <!-- Mobile Drag / Pull Handle -->
        <div class="sm:hidden pt-2.5 pb-1 flex justify-center cursor-pointer" onclick="closeAreaDetailsModal()">
            <div class="w-12 h-1.5 bg-slate-300 rounded-full"></div>
        </div>

        <!-- Header -->
        <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-100 bg-slate-50/90 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div id="area-modal-icon-box" class="w-8 h-8 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 id="area-modal-title" class="text-base sm:text-lg font-bold text-[#0B1E43] font-serif tracking-tight truncate">
                            Stage or backdrop details
                        </h3>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800">
                            AI SUGGESTION
                        </span>
                    </div>
                    <p id="area-modal-desc" class="text-xs text-slate-500 mt-0.5 truncate">
                        This design is based on your inspiration photos. Materials and quantities are AI-generated estimates and may vary.
                    </p>
                </div>
            </div>

            <button type="button"
                    onclick="closeAreaDetailsModal()"
                    data-action="close-modal"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 transition-colors focus:ring-2 focus:ring-emerald-500 outline-none"
                    aria-label="Close area details">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </header>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 text-xs">

            <!-- 1. Client Note for This Area -->
            <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 shadow-2xs space-y-2">
                <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-slate-200/70">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Client Note for This Area</h4>
                    </div>
                    <button type="button"
                            id="area-modal-edit-note-btn"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        Edit note
                    </button>
                </div>

                <!-- Read Note View -->
                <div id="area-modal-note-display" class="text-slate-800 leading-relaxed">
                    No specific instructions added yet for this area.
                </div>

                <!-- In-Place Note Editor -->
                <div id="area-modal-note-editor" class="hidden space-y-2 pt-1 border-t border-slate-200/60">
                    <label for="area-modal-note-input" class="block text-[11px] font-semibold text-slate-700">
                        Notes for Raflora florists (max 500 characters):
                    </label>
                    <textarea id="area-modal-note-input"
                              maxlength="500"
                              rows="3"
                              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none resize-none bg-white text-slate-800 leading-relaxed shadow-2xs"
                              placeholder="e.g. Keep the center of the backdrop open so the couple remains visible. Prefer soft ivory drapes with blush and white florals."></textarea>
                    
                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span id="area-modal-note-char-count">0 / 500 characters</span>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    id="area-modal-cancel-note-btn"
                                    class="px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200/60 rounded-lg transition">
                                Cancel
                            </button>
                            <button type="button"
                                    id="area-modal-save-note-btn"
                                    class="px-3 py-1 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition shadow-2xs">
                                Save Note
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. AI Indicative Price Estimate Card -->
            <section class="rounded-xl border border-violet-200 bg-violet-50/50 p-3.5 sm:p-4 shadow-2xs space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-violet-600" aria-hidden="true"></span>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-violet-950">AI Indicative Price Estimate</h4>
                    </div>
                    <span class="rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800 shadow-2xs">
                        INDICATIVE - NOT AN OFFICIAL QUOTATION
                    </span>
                </div>

                <div class="flex items-baseline justify-between gap-4 pt-0.5">
                    <div class="flex items-center gap-2">
                        <span id="area-modal-estimate-price" class="text-lg sm:text-2xl font-extrabold text-[#0B1E43] font-mono tabular-nums tracking-tight">
                            Price pending florist quotation
                        </span>
                        <span id="area-modal-partial-badge" class="hidden rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800 align-middle">
                            Partial Estimate
                        </span>
                    </div>
                    <p id="area-modal-labour-line" class="text-[11px] text-slate-500 text-right shrink-0">
                        Labour &amp; setup not included
                    </p>
                </div>

                <p id="area-modal-estimate-note" class="text-[11px] leading-relaxed text-slate-600 border-t border-violet-100/90 pt-1.5">
                    Indicative estimate only (±15%). Labour and setup are not included. Final price is confirmed after Raflora staff review and material validation.
                </p>
            </section>

            <!-- 3. AI Material Suggestions -->
            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">
                        AI Material Suggestions (<span id="area-modal-mat-count">0 items</span>)
                    </h4>
                    <div id="area-modal-count-badges" class="flex items-center gap-1.5 text-[11px]">
                        <span id="area-modal-detected-badge" class="text-emerald-700 font-semibold">0 Detected</span>
                        <span class="text-slate-300">·</span>
                        <span id="area-modal-suggested-badge" class="text-amber-700 font-semibold">0 Suggested</span>
                    </div>
                </div>

                <!-- Missing Area Notice -->
                <div id="area-modal-missing-notice" class="hidden p-3.5 rounded-xl border border-dashed border-amber-300 bg-amber-50/70 text-center">
                    <p class="text-xs font-medium text-amber-900">
                        No materials were suggested for this area. Raflora florists will plan it during quotation review.
                    </p>
                </div>

                <!-- Materials List Table (Desktop) / Cards (Mobile) -->
                <div id="area-modal-materials-container" class="space-y-2">
                    <div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase text-slate-400">
                                <tr>
                                    <th class="py-2.5 px-3">#</th>
                                    <th class="py-2.5 px-3">Material Name &amp; Basis</th>
                                    <th class="py-2.5 px-3 text-center">Qty</th>
                                    <th class="py-2.5 px-3">Unit</th>
                                    <th class="py-2.5 px-3 text-right">Price</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="area-modal-table-body" class="divide-y divide-slate-100 bg-white"></tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards List -->
                    <div id="area-modal-cards-list" class="sm:hidden space-y-2 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white overflow-hidden"></div>

                    <!-- Toggle More Materials Button -->
                    <div class="pt-1 flex justify-center">
                        <button type="button"
                                id="area-modal-toggle-more-btn"
                                class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 shadow-2xs transition">
                            <span id="area-modal-toggle-more-text">Show more materials</span>
                            <svg id="area-modal-toggle-more-icon" class="w-3.5 h-3.5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>
            </section>

        </div>

        <!-- Footer -->
        <footer class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/80 text-[11px] text-slate-500 shrink-0">
            <span class="truncate">
                AI decision support. Official pricing confirmed in Raflora quotation.
            </span>
            <button type="button"
                    onclick="closeAreaDetailsModal()"
                    data-action="close-modal"
                    class="px-4 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 font-bold text-slate-700 text-xs transition cursor-pointer">
                Close
            </button>
        </footer>
    </div>
</div>
