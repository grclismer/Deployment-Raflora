<x-app-layout title="New Booking">
    <x-client-layout active="bookings" plain>
        <div class="mx-auto w-full max-w-[92rem] px-4 sm:px-6 lg:px-8">
            <div class="rf-panel p-5 sm:p-8 lg:p-10">
                <!-- Header -->
                <span class="rf-badge rf-badge--success mb-3">Authenticated client portal</span>
                <h1 class="page-title mt-1">Create Event Booking</h1>
                <p class="section-subtitle mt-2 mb-8 max-w-2xl">Tell us about your event first, then choose a custom AI design or a curated package. Your event details help the AI suggest the right quantities.</p>

                @php
                    $clientDisplayErrors = collect($errors->keys())
                        ->reject(fn($key) => in_array($key, ['inspiration_image', 'analysis_token']))
                        ->flatMap(fn($key) => $errors->get($key));
                    // Step 1 = event details, Step 2 = booking method, photo and design brief (same order as the guest form,
                    // so the photo is analyzed with the event details already filled in).
                    $hasStep1Errors = $errors->hasAny(['event_type', 'other_event_type', 'event_date', 'event_time', 'end_time', 'venue', 'venue_latitude', 'venue_longitude', 'venue_setting', 'guest_count', 'table_count', 'special_requests']);
                    $hasStep2Errors = $errors->hasAny(array_merge(['inspiration_image', 'analysis_token', 'package_id', 'booking_type'], \App\Support\EventDesignBrief::FIELDS, ['decor_areas.*', 'color_palette.*']));
                    $initialStep = ($hasStep2Errors && !$hasStep1Errors) ? 2 : 1;
                    $selectedBookingType = old('booking_type', request('package_id') ? 'preset' : 'custom_ai');
                @endphp

                <nav class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-2" aria-label="Booking form progress">
                    <div id="progress-step-1" class="rounded-xl border {{ $initialStep === 1 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3" {{ $initialStep === 1 ? 'aria-current="step"' : '' }}>
                        <p class="text-[11px] font-bold uppercase tracking-wide {{ $initialStep === 1 ? 'text-emerald-700' : 'text-slate-500' }}">Step 1</p>
                        <p class="text-sm font-semibold {{ $initialStep === 1 ? 'text-slate-900' : 'text-slate-500' }}">Event Details</p>
                    </div>
                    <div id="progress-step-2" class="rounded-xl border {{ $initialStep === 2 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3" {{ $initialStep === 2 ? 'aria-current="step"' : '' }}>
                        <p class="text-[11px] font-bold uppercase tracking-wide {{ $initialStep === 2 ? 'text-emerald-700' : 'text-slate-500' }}">Step 2</p>
                        <p class="text-sm font-semibold {{ $initialStep === 2 ? 'text-slate-900' : 'text-slate-500' }}">Booking Method & Design</p>
                    </div>
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

                        {{-- Shown for every event type: the counts anchor the AI's quantities --}}
                        <div id="scale-fields-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 relative">
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

                    <!-- STEP 2: BOOKING METHOD, PHOTO & DESIGN BRIEF -->
                    <div id="booking-step-2" class="{{ $initialStep === 2 ? 'space-y-8' : 'hidden space-y-8' }}">

                    <!-- 1. SELECT BOOKING TYPE -->
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 mb-3">1. Select Booking Type</h3>
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
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">Upload your inspiration image. Gemini AI analyzes your inspiration image and suggests possible floral materials. These suggestions are reviewed by Raflora staff before they are used for your quotation.</p>
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
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">Select from our ready-made floral packages with fixed pricing set by our master florists.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- DYNAMIC AI SECTION -->
                    <section id="section-ai" class="{{ $selectedBookingType === 'preset' ? 'hidden' : '' }} rounded-xl border border-dashed border-emerald-300 bg-slate-50/60 p-5 sm:p-6" aria-labelledby="inspiration-heading">
                        {{-- Read by the AI when the photo is analyzed, so it comes before the upload --}}
                        <x-design-brief-fields id="design-brief" class="mb-5" />

                        <h3 id="inspiration-heading" class="mb-2 text-sm font-bold text-slate-800">Inspiration image <span class="text-rose-600 font-bold" aria-hidden="true">*</span></h3>
                        <p class="mb-4 text-xs leading-5 text-slate-500">Upload a floral or event reference photo so Gemini can prepare an initial design analysis. AI output serves as decision support for staff quotation review, not final pricing.</p>
                        <div id="dropzone_container" role="button" tabindex="0" aria-controls="inspiration_image_input" aria-describedby="inspiration-help" class="cursor-pointer select-none rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/20 p-6 text-center transition hover:bg-emerald-50/50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100 sm:p-8">
                            <div id="dropzone_preview_target" class="flex flex-col items-center justify-center">
                                <div class="flex flex-col items-center">
                                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                        <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <p class="text-sm font-bold text-slate-800">Upload your event photo or bouquet reference</p>
                                    <p id="inspiration-help" class="mt-1 text-xs text-slate-500">PNG, JPG or WEBP. Maximum file size is enforced by the existing upload validation.</p>
                                </div>
                            </div>
                            <input type="file" id="inspiration_image_input" name="inspiration_image" accept="image/*" class="hidden">
                        </div>
                        @error('inspiration_image')
                            <p class="text-xs text-rose-600 mt-2 font-semibold">{{ $message }}</p>
                        @enderror
                        <input type="hidden" name="analysis_token" id="client_analysis_token" value="{{ old('analysis_token') }}">
                        <input type="hidden" name="analysis_temp_path" id="client_analysis_temp_path" value="{{ old('analysis_temp_path') }}">
                        <input type="hidden" name="analysis_data" id="client_analysis_data" value="{{ old('analysis_data') }}">
                        <input type="hidden" name="analysis_nonce" id="client_analysis_nonce" value="{{ old('analysis_nonce') }}">
                        <div id="clientImageStatus" class="mt-3 text-sm" role="status" aria-live="polite"></div>
                        {{-- Materials and estimate per area the client picked in the design brief --}}
                        <x-decor-area-breakdown-live id="client-area-breakdown" class="mt-3" />
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

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-between">
                        <button type="button" id="back-step-btn" class="rf-btn rf-btn-secondary">
                            <span aria-hidden="true">←</span> Back
                        </button>
                        <button type="submit" id="clientSubmitBtn" class="rf-btn rf-btn-primary">
                            Submit Booking Request <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </x-client-layout>
</x-app-layout>

<script>
    function switchMode(mode) {
        const cardAi = document.getElementById('card-ai');
        const cardPreset = document.getElementById('card-preset');
        const iconPreset = document.getElementById('client-preset-icon-box');
        const sectionAi = document.getElementById('section-ai');
        const sectionPreset = document.getElementById('section-preset');
        const form = document.getElementById('client-booking-form');
        const fileInput = document.getElementById('inspiration_image_input');
        const packageRadios = form?.querySelectorAll('input[name="package_id"]') || [];
        const analysisInputs = form?.querySelectorAll('input[name^="analysis_"]') || [];

        if (mode === 'ai') {
            packageRadios.forEach(radio => { radio.checked = false; });
            const aiRadio = form?.querySelector('input[name="booking_type"][value="custom_ai"]');
            if (aiRadio) aiRadio.checked = true;
            cardAi.className = 'relative flex items-start p-4 border-2 border-emerald-600 bg-emerald-50/30 rounded-xl cursor-pointer transition-all shadow-sm ring-1 ring-emerald-600';
            cardPreset.className = 'relative flex items-start p-4 border-2 border-slate-200 bg-white rounded-xl cursor-pointer transition-all hover:border-slate-300';
            iconPreset.className = 'w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center flex-shrink-0 mr-3 mt-0.5 transition-all';
            sectionAi.classList.remove('hidden');
            sectionPreset.classList.add('hidden');
        } else {
            if (fileInput) fileInput.value = '';
            analysisInputs.forEach(input => { input.value = ''; });
            const presetRadio = form?.querySelector('input[name="booking_type"][value="preset"]');
            if (presetRadio) presetRadio.checked = true;
            cardPreset.className = 'relative flex items-start p-4 border-2 border-emerald-600 bg-emerald-50/30 rounded-xl cursor-pointer transition-all shadow-sm ring-1 ring-emerald-600';
            cardAi.className = 'relative flex items-start p-4 border-2 border-slate-200 bg-white rounded-xl cursor-pointer transition-all hover:border-slate-300';
            iconPreset.className = 'w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 mr-3 mt-0.5 transition-all';
            sectionPreset.classList.remove('hidden');
            sectionAi.classList.add('hidden');
        }

        document.dispatchEvent(new CustomEvent('client-booking-mode-changed'));
    }
    window.switchMode = switchMode;

    document.addEventListener('DOMContentLoaded', function() {
        const dropzone = document.getElementById('dropzone_container');
        const contentTarget = document.getElementById('dropzone_preview_target');
        const fileInput = document.getElementById('inspiration_image_input');
        const analysisTokenInput = document.getElementById('client_analysis_token');
        const analysisTempPathInput = document.getElementById('client_analysis_temp_path');
        const analysisDataInput = document.getElementById('client_analysis_data');
        const analysisNonceInput = document.getElementById('client_analysis_nonce');
        const statusBox = document.getElementById('clientImageStatus');
        const submitBtn = document.getElementById('clientSubmitBtn');
        const form = document.getElementById('client-booking-form');
        const stepOne = document.getElementById('booking-step-1');
        const stepTwo = document.getElementById('booking-step-2');
        const nextStepBtn = document.getElementById('next-step-btn');
        const backStepBtn = document.getElementById('back-step-btn');
        const progressStepOne = document.getElementById('progress-step-1');
        const progressStepTwo = document.getElementById('progress-step-2');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        const analyzeUrl = '{{ route("bookings.analyze-temp-image") }}';
        const designBrief = window.RafloraDesignBrief?.get('design-brief');
        let imageValid = Boolean(analysisTokenInput && analysisTokenInput.value);
        let analysisPending = false;

        if (imageValid) {
            setStatus('success', '✓ Inspiration image verified. Material suggestions prepared for staff quotation review.');
            try {
                window.RafloraAreaBreakdown?.render('client-area-breakdown', JSON.parse(analysisDataInput?.value || '{}').area_breakdown);
            } catch (e) {}
        }

        document.addEventListener('client-booking-mode-changed', () => {
            imageValid = false;
            analysisPending = false;
            resetAnalysisPayload();
            resetStatus();
            updateSubmitState();
        });

        function showBookingStep(step) {
            const showFirst = step === 1;
            stepOne?.classList.toggle('hidden', !showFirst);
            stepTwo?.classList.toggle('hidden', showFirst);
            progressStepOne?.classList.toggle('bg-emerald-50', showFirst);
            progressStepOne?.classList.toggle('border-emerald-200', showFirst);
            progressStepTwo?.classList.toggle('bg-emerald-50', !showFirst);
            progressStepTwo?.classList.toggle('border-emerald-200', !showFirst);
            progressStepTwo?.querySelector('p:first-child')?.classList.toggle('text-emerald-700', !showFirst);
            progressStepTwo?.querySelector('p:first-child')?.classList.toggle('text-slate-500', showFirst);
            progressStepTwo?.querySelector('p:last-child')?.classList.toggle('text-slate-900', !showFirst);
            progressStepTwo?.querySelector('p:last-child')?.classList.toggle('text-slate-500', showFirst);
            progressStepOne?.toggleAttribute('aria-current', showFirst);
            progressStepTwo?.toggleAttribute('aria-current', !showFirst);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        const initialStep = {{ $initialStep }};
        if (initialStep === 2) {
            showBookingStep(2);
        }

        // Handle numeric stripping for guest_count and table_count
        const numericInputs = ['guest_count', 'table_count'].map(id => document.getElementById(id)).filter(el => el !== null);
        numericInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        });

        // Large event warning logic
        const guestCountInputWarning = document.getElementById('guest_count');
        const largeEventWarning = document.getElementById('large-event-warning');
        if (guestCountInputWarning && largeEventWarning) {
            guestCountInputWarning.addEventListener('input', function() {
                const count = parseInt(this.value, 10);
                if (!isNaN(count) && count >= 1000) {
                    largeEventWarning.classList.remove('hidden');
                } else {
                    largeEventWarning.classList.add('hidden');
                }
            });
        }

        // Clear validation errors on input
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500', 'is-invalid');
                this.classList.remove('focus:border-red-500');
                this.classList.remove('focus:ring-red-500');
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
        });

        // Step 1 (event details) must be complete before the photo is analyzed in Step 2.
        nextStepBtn?.addEventListener('click', () => {
            const firstInvalid = Array.from(stepOne?.querySelectorAll('input, select, textarea') || [])
                .find(el => !el.disabled && !el.checkValidity());
            if (firstInvalid) {
                firstInvalid.reportValidity();
                firstInvalid.focus();
                return;
            }
            showBookingStep(2);
        });
        backStepBtn?.addEventListener('click', () => showBookingStep(1));

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('click', (e) => e.stopPropagation());
            dropzone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    fileInput.click();
                }
            });
        }

        function resetAnalysisPayload() {
            if (analysisTokenInput) analysisTokenInput.value = '';
            if (analysisTempPathInput) analysisTempPathInput.value = '';
            if (analysisDataInput) analysisDataInput.value = '';
            if (analysisNonceInput) analysisNonceInput.value = '';
            window.RafloraAreaBreakdown?.render('client-area-breakdown', null);
        }

        function resetStatus() {
            if (!statusBox) return;
            statusBox.className = 'mt-3 text-sm rounded-lg px-3 py-2 font-medium hidden';
            statusBox.innerHTML = '';
        }

        function setStatus(type, html) {
            if (!statusBox) return;
            statusBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200',
                                       'bg-red-50', 'text-red-800', 'border-red-200',
                                       'bg-amber-50', 'text-amber-800', 'border-amber-200');
            if (type === 'success') {
                statusBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
            } else if (type === 'error') {
                statusBox.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
            } else {
                statusBox.classList.add('bg-amber-50', 'text-amber-800', 'border', 'border-amber-200');
            }
            statusBox.innerHTML = html;
        }

        function setSubmitState(disabled) {
            if (!submitBtn) return;
            submitBtn.disabled = disabled;
            if (disabled) {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        function updateSubmitState() {
            const bookingType = form?.querySelector('input[name="booking_type"]:checked')?.value;
            const requiresAnalysis = bookingType !== 'preset';
            const analysisReady = requiresAnalysis ? imageValid : Boolean(form?.querySelector('input[name="package_id"]:checked'));
            const eventType = form?.querySelector('select[name="event_type"]')?.value;
            const requiresScale = eventType && eventType !== 'other';
            const guestCountInput = form?.querySelector('input[name="guest_count"]');
            const tableCountInput = form?.querySelector('input[name="table_count"]');
            const scaleFieldsReady = !requiresScale || (
                (!guestCountInput?.value.trim() || guestCountInput?.validity.valid) &&
                (!tableCountInput?.value.trim() || tableCountInput?.validity.valid)
            );
            setSubmitState(analysisPending || !form?.checkValidity() || !analysisReady || !scaleFieldsReady);
        }

        form?.addEventListener('input', updateSubmitState);
        form?.addEventListener('change', updateSubmitState);
        updateSubmitState();

        form?.addEventListener('submit', function (e) {
            if (form.dataset.submitting === 'true') {
                e.preventDefault();
                return false;
            }
            form.dataset.submitting = 'true';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Submitting...`;
            }
        });

        window.addEventListener('pageshow', function () {
            if (form) {
                form.dataset.submitting = 'false';
            }
            if (submitBtn) {
                submitBtn.innerHTML = 'Submit Booking Request <span aria-hidden="true">→</span>';
                updateSubmitState();
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

        function renderClientPreview(file) {
            if (!contentTarget) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                contentTarget.innerHTML = `
                    <div class="relative pointer-events-none text-center">
                        <img src="${ev.target.result}" class="max-h-48 rounded-lg object-contain mx-auto mb-2" />
                        <p class="text-sm text-amber-600 font-medium animate-pulse">Analyzing design elements with AI...</p>
                    </div>`;
            };
            reader.onerror = renderClientError;
            reader.readAsDataURL(file);
        }

        let currentClientFile = null;

        function renderClientError(message = null) {
            if (fileInput) fileInput.value = '';
            if (!contentTarget) return;
            const fallbackMessage = 'Please choose a clearer floral or event photo.';
            const detailText = message || fallbackMessage;
            contentTarget.innerHTML = `
                <div class="flex flex-col items-center pointer-events-none">
                    <div class="w-12 h-12 rounded-xl bg-red-100 text-red-600 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-800">This image could not be analyzed.</p>
                    <p class="text-xs text-slate-500 mt-1">${detailText}</p>
                </div>`;
        }

        function renderClientQuotaError(message = null) {
            if (!contentTarget) return;
            const detailText = message || "Your image was uploaded successfully, but Raflora's AI service is currently busy. Please try again shortly.";
            contentTarget.innerHTML = `
                <div class="flex flex-col items-center p-3 text-center">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-bold text-amber-900">AI analysis temporarily unavailable</p>
                    <p class="text-xs text-amber-800 mt-1 max-w-sm">${detailText}</p>
                    <button type="button" onclick="event.stopPropagation(); retryClientAnalysis();" class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition">
                        <span>↻ Retry Analysis</span>
                    </button>
                </div>`;
        }

        window.retryClientAnalysis = function() {
            if (!currentClientFile || analysisPending) return;
            renderClientPreview(currentClientFile);
            analyzeClientImage(currentClientFile);
        };

        function analyzeClientImage(file) {
            if (!file) return;
            if (analysisPending) return;

            currentClientFile = file;
            designBrief?.resetAnalyzed();
            window.RafloraAreaBreakdown?.render('client-area-breakdown', null);
            setStatus('warning', '<span class="inline-flex items-center gap-2"><svg class="inline w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>Analyzing design elements with AI...</span>');
            analysisPending = true;
            imageValid = false;
            updateSubmitState();

            const formData = new FormData();
            const eventType = document.querySelector('select[name="event_type"]')?.value || '';
            const guestCount = document.querySelector('input[name="guest_count"]')?.value || '';
            const tableCount = document.querySelector('input[name="table_count"]')?.value || '';
            let specialRequests = document.querySelector('textarea[name="special_requests"]')?.value || '';
            const otherEventType = document.querySelector('input[name="other_event_type"]')?.value || '';
            if (eventType === 'other' && otherEventType) {
                specialRequests = `${specialRequests}\nOther request type: ${otherEventType}`.trim();
            }
            formData.append('inspiration_image', file);
            formData.append('event_type', eventType);
            formData.append('event_date', document.querySelector('input[name="event_date"]')?.value || '');
            formData.append('event_time', document.querySelector('input[name="event_time"]')?.value || '');
            formData.append('end_time', document.querySelector('input[name="end_time"]')?.value || '');
            formData.append('venue', document.querySelector('input[name="venue"]')?.value || '');
            formData.append('venue_setting', document.querySelector('input[name="venue_setting"]:checked')?.value || '');
            formData.append('special_requests', specialRequests);
            if (guestCount) formData.append('guest_count', guestCount);
            if (tableCount) formData.append('table_count', tableCount);
            designBrief?.appendTo(formData);
            formData.append('_token', csrfToken);

            fetch(analyzeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const err = new Error(data.message || `AI analysis failed`);
                    err.data = data;
                    err.status = res.status;
                    throw err;
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    imageValid = true;
                    analysisPending = false;
                    updateSubmitState();
                    if (analysisTokenInput) analysisTokenInput.value = data.analysis_token || '';
                    if (analysisTempPathInput) analysisTempPathInput.value = data.analysis_temp_path || '';
                    if (analysisDataInput) analysisDataInput.value = data.analysis_data || '';
                    if (analysisNonceInput) analysisNonceInput.value = data.analysis_nonce || '';
                    designBrief?.markAnalyzed();
                    window.RafloraAreaBreakdown?.render('client-area-breakdown', data.analysis?.area_breakdown);
                    setStatus('success', '✓ Image analyzed successfully. Material suggestions prepared for staff quotation review.');
                } else {
                    imageValid = false;
                    analysisPending = false;
                    updateSubmitState();
                    resetAnalysisPayload();
                    const isQuota = Boolean(data.is_quota_error || data.error_type === 'service' || data.error_type === 'rate_limit');
                    if (isQuota) {
                        renderClientQuotaError(data.message);
                        setStatus('warning', 'Raflora\'s AI service is currently busy. Please click "Retry Analysis" shortly.');
                    } else {
                        renderClientError(data.message || 'Please choose a clearer floral or event photo.');
                        setStatus('error', '✗ ' + (data.message || 'Image rejected.'));
                    }
                }
            })
            .catch(err => {
                imageValid = false;
                analysisPending = false;
                updateSubmitState();
                resetAnalysisPayload();
                const errData = err.data || {};
                const isQuota = Boolean(errData.is_quota_error || errData.error_type === 'service' || errData.error_type === 'rate_limit' || err.status === 429 || err.status === 503);
                if (isQuota) {
                    renderClientQuotaError(errData.message);
                    setStatus('warning', 'Raflora\'s AI service is currently busy. Please click "Retry Analysis" shortly.');
                } else if (errData.error_type === 'connection' || err.message?.includes('connection')) {
                    renderClientError('AI analysis could not be completed because of a connection problem. Please check your internet connection and try again.');
                    setStatus('error', '✗ AI analysis could not be completed because of a connection problem. Please check your internet connection and try again.');
                } else {
                    renderClientError(err.message || 'Please choose a clearer floral or event photo.');
                    setStatus('error', '✗ ' + (err.message || 'Image rejected.'));
                }
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                resetAnalysisPayload(); resetStatus();
                const file = e.target.files?.[0];
                if (!file) return;
                renderClientPreview(file);
                analyzeClientImage(file);
            });
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                const bookingTypeInput = form.querySelector('input[name="booking_type"]:checked');
                const isAiMode = !bookingTypeInput || bookingTypeInput.value === 'custom_ai';
                if (isAiMode && !imageValid) {
                    e.preventDefault();
                    setStatus('error', '✗ Please select and wait for image analysis to complete.');
                    return false;
                }
                if (!isAiMode && !form.querySelector('input[name="package_id"]:checked')) {
                    e.preventDefault();
                    form.dataset.submitting = 'false';
                    if (submitBtn) submitBtn.innerHTML = 'Submit Booking Request <span aria-hidden="true">→</span>';
                    updateSubmitState();
                    setStatus('error', 'Please select a curated package before submitting.');
                    document.getElementById('section-preset')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return false;
                }
            });
        }

        // Design brief edited after the photo was analyzed: run the analysis again with the new brief.
        document.getElementById('design-brief')?.addEventListener('design-brief:reanalyze', () => {
            if (currentClientFile) {
                window.retryClientAnalysis();
            } else {
                designBrief?.setStaleMessage('Please upload your inspiration photo again so the AI can use your updated brief.');
            }
        });
        document.addEventListener('client-booking-mode-changed', () => designBrief?.resetAnalyzed());

        const urlParams = new URLSearchParams(window.location.search);
        const oldBookingType = @json(old('booking_type', request('package_id') ? 'preset' : null));
        if (oldBookingType === 'preset' || urlParams.has('package_id')) {
            window.switchMode('preset');
            const radioPreset = document.querySelector('input[name="booking_type"][value="preset"]');
            if (radioPreset) radioPreset.checked = true;
            updateSubmitState();
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
                if (radio && radio.checked) {
                    card.classList.add('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg', 'bg-emerald-50/20');
                    card.classList.remove('border-slate-200');
                    if (badge) badge.classList.remove('hidden');
                } else {
                    card.classList.remove('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg', 'bg-emerald-50/20');
                    card.classList.add('border-slate-200');
                    if (badge) badge.classList.add('hidden');
                }
            });
        }

        // Guest and table counts stay visible for every event type: they anchor the AI's quantities.
        function handleEventTypeChange() {
            if (!eventTypeSelect) return;
            const val = eventTypeSelect.value.toLowerCase();

            if (otherEventTypeContainer && otherEventTypeInput) {
                if (val === 'other') {
                    otherEventTypeContainer.classList.remove('hidden');
                    otherEventTypeInput.required = true;
                } else {
                    otherEventTypeContainer.classList.add('hidden');
                    otherEventTypeInput.required = false;
                    otherEventTypeInput.value = '';
                }
            }
        }

        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', handleEventTypeChange);
            handleEventTypeChange();
        }

        packageRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                refreshPackageSelection();
                if (radio.checked) {
                    const card = radio.closest('.client-package-card');
                    if (!card) return;
                    try {
                        const pkg = JSON.parse(card.getAttribute('data-package'));
                        if (pkg?.event_type) setPackageEventType(pkg.event_type);
                    } catch (err) {
                        console.error('[Client] JSON parse error on radio change:', err);
                    }
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
        const initialCheckedPackage = document.querySelector('.client-package-card .package-radio:checked');
        if (initialCheckedPackage) {
            const card = initialCheckedPackage.closest('.client-package-card');
            if (card) {
                try {
                    const pkg = JSON.parse(card.getAttribute('data-package'));
                    if (pkg?.event_type) setPackageEventType(pkg.event_type);
                } catch (err) {
                    console.error('[Client] JSON parse error on initial package:', err);
                }
            }
        }

        // View Package Button Click Handlers
        packageViewButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const card = e.currentTarget.closest('.client-package-card');
                if (!card) return;
                try {
                    const rawData = card.getAttribute('data-package');
                    const pkg = JSON.parse(rawData);
                    window.openPackageModal(pkg);
                } catch (err) {
                    console.error('[Client] JSON parse error on View Package click:', err);
                }
            });
        });

        // Global Modal Helpers
        window.closePackageModal = function() {
            document.body.classList.remove('overflow-hidden');
            const modal = document.getElementById('packageModal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); modal.style.display = 'none'; }
        };

        window.openPackageModal = function(pkg) {
            const modal = document.getElementById('packageModal');
            if (!modal) return;
            document.body.classList.add('overflow-hidden');
            modal.classList.remove('hidden'); modal.classList.add('flex'); modal.style.display = 'flex';
            
            document.getElementById('packageModalTitle').textContent = pkg.title || '';
            document.getElementById('packageModalDescription').textContent = pkg.description || '';
            document.getElementById('packageModalPrice').textContent = pkg.price ? ('₱' + Number(pkg.price).toLocaleString('en-PH', {minimumFractionDigits:2})) : '';
            
            const img = document.getElementById('packageModalImage');
            if (img) {
                img.innerHTML = pkg.image_url 
                    ? `<img src="${pkg.image_url}" class="w-full h-56 object-cover rounded-md">`
                    : `<div class="w-full h-56 flex items-center justify-center bg-slate-100 text-slate-400">No Image Available</div>`;
            }

            const list = document.getElementById('packageModalInclusions');
            if (list) {
                list.innerHTML = '';
                if (Array.isArray(pkg.included_items)) {
                    pkg.included_items.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'flex items-start gap-2';
                        li.innerHTML = `<span class="text-emerald-600 font-bold">✓</span><span>${item}</span>`;
                        list.appendChild(li);
                    });
                }
            }
            document.getElementById('packageModalSelectBtn').dataset.packageId = pkg.id;
        };

        window.selectPackageFromModal = function(id) {
            if (!id) return;
            const radio = document.querySelector(`input[name="package_id"][value="${id}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
            window.closePackageModal();
        }
    });
</script>

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
