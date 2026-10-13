<x-app-layout title="Guest Booking">
    <x-navbar title="GUEST BOOKING" />
    <div class="py-4 sm:py-6 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8">
            <div class="bg-white p-4 sm:p-6 lg:p-7 rounded-2xl shadow-lg border border-slate-100">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 gap-2">
                    <div class="flex items-center gap-3">
                        <span class="inline-block bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full w-max tracking-wide">GUEST BOOKING</span>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-[#0B1E43] tracking-tight font-serif">Create Event Booking</h1>
                    </div>
                    <a href="{{ route('login') }}" class="text-xs sm:text-sm font-semibold text-emerald-600 hover:text-emerald-700 hover:underline shrink-0">Have an account? Log in</a>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 mb-4 sm:mb-5" id="step-description">Provide basic information and schedule for your event.</p>

                <!-- Progress Indicator (4 Steps) -->
                <div class="mb-5 sm:mb-6 flex items-center w-full" aria-label="Booking progress">
                    <!-- Step 1: Event Details -->
                    <div class="flex items-center cursor-pointer group shrink-0" id="nav-step-1" onclick="showGuestStep(1)">
                        <div id="icon-step-1" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300 shadow-md">
                            1
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-1" class="text-xs sm:text-sm font-bold text-[#0B1E43] whitespace-nowrap transition-colors">Event Details</p>
                        </div>
                    </div>
                    
                    <!-- Line 1 -->
                    <div class="flex-1 min-w-[12px] sm:min-w-[20px] mx-1 sm:mx-2.5 h-1 bg-[#EBF0F9] relative overflow-hidden rounded-full shrink">
                        <div id="progress-line-1" class="absolute top-0 left-0 h-full w-0 bg-emerald-500 transition-all duration-700 ease-out"></div>
                    </div>
                    
                    <!-- Step 2: Booking Method -->
                    <div class="flex items-center cursor-pointer group shrink-0 opacity-60 hover:opacity-100 transition-all duration-300" id="nav-step-2" onclick="showGuestStep(2)">
                        <div id="icon-step-2" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300">
                            2
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-2" class="text-xs sm:text-sm font-bold text-[#0B1E43] whitespace-nowrap transition-colors">Booking Method</p>
                        </div>
                    </div>

                    <!-- Line 2 -->
                    <div class="flex-1 min-w-[12px] sm:min-w-[20px] mx-1 sm:mx-2.5 h-1 bg-[#EBF0F9] relative overflow-hidden rounded-full shrink">
                        <div id="progress-line-2" class="absolute top-0 left-0 h-full w-0 bg-emerald-500 transition-all duration-700 ease-out"></div>
                    </div>

                    <!-- Step 3: Contact Information -->
                    <div class="flex items-center cursor-pointer group shrink-0 opacity-60 hover:opacity-100 transition-all duration-300" id="nav-step-3" onclick="showGuestStep(3)">
                        <div id="icon-step-3" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300">
                            3
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-3" class="text-xs sm:text-sm font-bold text-[#0B1E43] whitespace-nowrap transition-colors">Contact Information</p>
                        </div>
                    </div>

                    <!-- Line 3 -->
                    <div class="flex-1 min-w-[12px] sm:min-w-[20px] mx-1 sm:mx-2.5 h-1 bg-[#EBF0F9] relative overflow-hidden rounded-full shrink">
                        <div id="progress-line-3" class="absolute top-0 left-0 h-full w-0 bg-emerald-500 transition-all duration-700 ease-out"></div>
                    </div>

                    <!-- Step 4: Review & Submit -->
                    <div class="flex items-center cursor-pointer group shrink-0 opacity-60 hover:opacity-100 transition-all duration-300" id="nav-step-4" onclick="showGuestStep(4)">
                        <div id="icon-step-4" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300">
                            4
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-4" class="text-xs sm:text-sm font-bold text-[#0B1E43] whitespace-nowrap transition-colors">Review & Submit</p>
                        </div>
                    </div>
                </div>

                @php
                    $guestDisplayErrors = collect($errors->keys())
                        ->reject(fn($key) => in_array($key, ['inspiration_image', 'analysis_token']))
                        ->flatMap(fn($key) => $errors->get($key));

                    $hasStep1Errors = $errors->hasAny([
                        'event_type', 'other_event_type', 'event_date', 'event_time', 'end_time',
                        'venue', 'venue_city', 'venue_specific', 'venue_latitude', 'venue_longitude', 'venue_setting', 'table_count', 'guest_count', 'special_requests'
                    ]);

                    $hasStep2Errors = $errors->hasAny(array_merge([
                        'booking_type', 'package_id', 'inspiration_image', 'analysis_token', 'analysis_data'
                    ], \App\Support\EventDesignBrief::FIELDS, ['decor_areas.*', 'color_palette.*']));

                    $hasStep3Errors = $errors->hasAny([
                        'guest_name', 'guest_email', 'guest_phone', 'guest_address'
                    ]);

                    $initialStep = 1;
                    if ($hasStep1Errors) {
                        $initialStep = 1;
                    } elseif ($hasStep2Errors) {
                        $initialStep = 2;
                    } elseif ($hasStep3Errors) {
                        $initialStep = 3;
                    }
                @endphp

                <!-- Raflora-Compatible Validation Popup Modal -->
                <div id="raflora-validation-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="validation-modal-title">
                    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all animate-fade-in">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 id="validation-modal-title" class="text-lg font-bold text-[#0B1E43] font-serif">Incomplete Information</h3>
                                    <p id="validation-modal-subtitle" class="text-xs sm:text-sm text-slate-600 mt-1 leading-relaxed">
                                        Some required fields are missing. Please review the highlighted fields before continuing.
                                    </p>
                                </div>
                            </div>
                            
                            <div id="validation-modal-list-container" class="mt-4 p-3 bg-rose-50/60 rounded-xl border border-rose-100 max-h-48 overflow-y-auto hidden">
                                <ul id="validation-modal-list" class="space-y-1.5 text-xs text-rose-800 list-disc pl-4"></ul>
                            </div>
                            
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" id="validation-modal-close-btn" onclick="closeValidationModal()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition-colors focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    Review Highlighted Fields
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raflora Upload Tips Modal -->
                <div id="upload-tips-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="upload-tips-modal-title">
                    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all animate-fade-in">
                        <div class="p-6">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">💡</span>
                                    <h3 id="upload-tips-modal-title" class="text-lg font-bold text-[#0B1E43] font-serif">Upload Tips</h3>
                                </div>
                                <button type="button" onclick="closeUploadTipsModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition" aria-label="Close tips">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <p class="text-xs sm:text-sm text-slate-600 mb-3">For better AI analysis results, follow these guidelines:</p>

                            <!-- Example Photos Strip (3 Cards) -->
                            <div class="grid grid-cols-3 gap-2.5 mb-4">
                                <!-- Example 1: Close-up -->
                                <div class="relative rounded-xl overflow-hidden border border-emerald-200 bg-emerald-50/40 h-24 flex items-center justify-center">
                                    <img src="{{ asset('flowaah1.jpg') }}" alt="Good Floral Photo" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                                    <div class="hidden flex flex-col items-center justify-center text-emerald-800 text-[10px] font-bold p-1 text-center">
                                        <span>🌸 Clear Bouquet</span>
                                    </div>
                                    <div class="absolute bottom-1.5 right-1.5 w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold shadow-xs">✓</div>
                                </div>
                                <!-- Example 2: Arch/Setup -->
                                <div class="relative rounded-xl overflow-hidden border border-emerald-200 bg-emerald-50/40 h-24 flex items-center justify-center">
                                    <img src="{{ asset('flower 2.jpg') }}" alt="Good Event Setup" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                                    <div class="hidden flex flex-col items-center justify-center text-emerald-800 text-[10px] font-bold p-1 text-center">
                                        <span>🌿 Full Arch</span>
                                    </div>
                                    <div class="absolute bottom-1.5 right-1.5 w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold shadow-xs">✓</div>
                                </div>
                                <!-- Example 3: Low-light / Blurry -->
                                <div class="relative rounded-xl overflow-hidden border border-rose-200 bg-rose-50/50 h-24 flex flex-col items-center justify-center text-center p-1.5">
                                    <svg class="w-6 h-6 text-rose-400 mb-0.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    <span class="text-[9px] font-bold text-rose-700 leading-tight">Dark / Blurry Photo</span>
                                    <div class="absolute bottom-1.5 right-1.5 w-5 h-5 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] font-bold shadow-xs">✕</div>
                                </div>
                            </div>

                            <!-- Guidelines Checklist -->
                            <ul class="space-y-2 text-xs text-slate-600 mb-4">
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>Use clear, high-quality floral photos</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>Include the full arrangement when possible</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>Upload multiple images (different angles or setups)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>Make sure the image is well-lit and not blurry</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>Inspiration can be from Pinterest, past events, magazines, or personal photos</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-emerald-500 font-bold text-xs mt-0.5">✓</span>
                                    <span>AI results are suggestions and require Raflora florist validation</span>
                                </li>
                            </ul>

                            <!-- Accepted / Not Accepted Box -->
                            <div class="p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl text-[11px] space-y-1.5 text-slate-700 mb-5">
                                <div class="flex items-start gap-2">
                                    <span class="font-bold text-emerald-800 shrink-0">Accepted:</span>
                                    <span>Floral arrangements, event setups, bouquets, venue decorations, greenery.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="font-bold text-rose-700 shrink-0">Not accepted:</span>
                                    <span class="text-slate-600">Electronics, random objects, memes, screenshots, or low-quality/blurry photos.</span>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="button" onclick="closeUploadTipsModal()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors">
                                    Got It
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="guest-booking-form" action="{{ route('guest.booking.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf

                    <!-- ========================================== -->
                    <!-- STEP 1: EVENT DETAILS                     -->
                    <!-- ========================================== -->
                    <div id="guest-step-1" class="space-y-3 sm:space-y-4 block animate-fade-in">
                        <!-- Top Banner (Helpful Tip) -->
                        <div class="bg-emerald-50/70 rounded-xl py-2 px-3 sm:px-4 border border-emerald-100 flex items-center gap-2.5 text-xs text-slate-700">
                            <span class="text-base leading-none">💡</span>
                            <span class="leading-relaxed"><strong class="font-bold text-[#0B1E43]">Helpful tip:</strong> Providing your event date, schedule, and venue details first allows our AI and florist team to give you tailored recommendations.</span>
                        </div>

                        <!-- Event Info Card -->
                        <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5">
                            <div class="flex items-center justify-between pb-2.5 mb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <h2 class="text-sm sm:text-base font-bold text-[#0B1E43]">Event Details</h2>
                                        <p class="text-xs text-slate-500 hidden sm:block">Enter the schedule and location for flower quantities, travel timing, and setup logistics.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
                                <!-- Event Type -->
                                <div class="col-span-1">
                                    <label for="event_type" class="block text-xs font-semibold text-[#0B1E43] mb-1">Event Type <span class="text-rose-500">*</span></label>
                                    <select name="event_type" id="event_type" required class="w-full py-2 px-2.5 bg-white border {{ $errors->has('event_type') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" aria-describedby="event_type_error" aria-invalid="{{ $errors->has('event_type') ? 'true' : 'false' }}">
                                        <option value="">Select Event Type</option>
                                        <option value="wedding" {{ old('event_type') === 'wedding' ? 'selected' : '' }}>Wedding</option>
                                        <option value="birthday" {{ old('event_type') === 'birthday' ? 'selected' : '' }}>Birthday</option>
                                        <option value="corporate" {{ old('event_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                                        <option value="other" {{ old('event_type') === 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    <p id="event_type_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('event_type') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('event_type') }}</span>
                                    </p>
                                    <div id="otherEventTypeContainer" class="mt-2 {{ old('event_type') === 'other' ? '' : 'hidden' }}">
                                        <label for="other_event_type" class="block text-xs font-semibold text-slate-500 mb-1">Please specify <span class="text-rose-500">*</span></label>
                                        <input type="text" name="other_event_type" id="other_event_type" value="{{ old('other_event_type') }}" placeholder="e.g. Delivery, Engagement" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('other_event_type') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" {{ old('event_type') === 'other' ? 'required' : '' }} aria-describedby="other_event_type_error" aria-invalid="{{ $errors->has('other_event_type') ? 'true' : 'false' }}">
                                        <p id="other_event_type_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('other_event_type') ? '' : 'hidden' }}" role="alert">
                                            <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                            <span class="error-msg">{{ $errors->first('other_event_type') }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Event Date -->
                                <div class="col-span-1">
                                    <label for="event_date" class="block text-xs font-semibold text-[#0B1E43] mb-1">Event Date <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-[#0B1E43]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <input type="date" name="event_date" id="event_date" required min="{{ date('Y-m-d') }}" value="{{ old('event_date') }}" class="w-full pl-8 py-2 pr-2.5 bg-white border {{ $errors->has('event_date') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" aria-describedby="event_date_error" aria-invalid="{{ $errors->has('event_date') ? 'true' : 'false' }}">
                                    </div>
                                    <p id="event_date_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('event_date') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('event_date') }}</span>
                                    </p>
                                </div>

                                <!-- Start Time -->
                                <div class="col-span-1">
                                    <label for="event_time" class="block text-xs font-semibold text-[#0B1E43] mb-1">Start Time <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-[#0B1E43]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <input type="time" name="event_time" id="event_time" required value="{{ old('event_time') }}" class="w-full pl-8 py-2 pr-2.5 bg-white border {{ $errors->has('event_time') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" aria-describedby="event_time_error" aria-invalid="{{ $errors->has('event_time') ? 'true' : 'false' }}">
                                    </div>
                                    <p id="event_time_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('event_time') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('event_time') }}</span>
                                    </p>
                                </div>

                                <!-- End Time -->
                                <div class="col-span-1">
                                    <label for="end_time" class="block text-xs font-semibold text-[#0B1E43] mb-1">End Time <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-[#0B1E43]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <input type="time" name="end_time" id="end_time" required value="{{ old('end_time') }}" class="w-full pl-8 py-2 pr-2.5 bg-white border {{ $errors->has('end_time') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" aria-describedby="end_time_error" aria-invalid="{{ $errors->has('end_time') ? 'true' : 'false' }}">
                                    </div>
                                    <p id="end_time_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('end_time') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('end_time') }}</span>
                                    </p>
                                </div>

                                <!-- Scale Fields (shown for every event type) -->
                                <div class="col-span-1 sm:col-span-1 lg:col-span-2" id="scale-fields-container">
                                    <label for="guest_count" class="block text-xs font-semibold text-[#0B1E43] mb-1">Expected Guests</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-[#0B1E43]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        </div>
                                        <input type="text" inputmode="numeric" name="guest_count" id="guest_count" value="{{ old('guest_count') }}" placeholder="e.g. 150" class="w-full pl-8 py-2 pr-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]">
                                    </div>
                                    @error('guest_count')
                                        <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="col-span-1 sm:col-span-1 lg:col-span-2" id="scale-tables-container">
                                    <label for="table_count" class="block text-xs font-semibold text-[#0B1E43] mb-1">Number of Tables</label>
                                    <div class="relative">
                                        <input type="text" inputmode="numeric" pattern="[0-9]*" name="table_count" id="table_count" value="{{ old('table_count') }}" placeholder="e.g. 15" class="w-full py-2 px-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]">
                                    </div>
                                    @error('table_count')
                                        <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Venue Location (Grouped together on dedicated row) -->
                                <!-- Venue City / Municipality -->
                                <div class="col-span-1 sm:col-span-1 lg:col-span-1">
                                    <label for="venue_city" class="block text-xs font-semibold text-[#0B1E43] mb-1">Venue City / Municipality <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-[#0B1E43]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                        </div>
                                        <input type="text" name="venue_city" id="venue_city" required placeholder="CALOOCAN" value="{{ old('venue_city') }}" class="w-full pl-8 py-2 pr-2.5 bg-white border {{ $errors->has('venue_city') || $errors->has('venue') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] uppercase" oninput="updateVenueField()" aria-describedby="venue_city_error" aria-invalid="{{ $errors->has('venue_city') || $errors->has('venue') ? 'true' : 'false' }}">
                                    </div>
                                    <p id="venue_city_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('venue_city') || $errors->has('venue') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('venue_city') ?: $errors->first('venue') }}</span>
                                    </p>
                                </div>

                                <!-- Specific Venue / Address -->
                                <div class="col-span-1 sm:col-span-1 lg:col-span-3">
                                    <label for="venue_specific" class="block text-xs font-semibold text-[#0B1E43] mb-1">Specific Venue / Address <span class="text-rose-500">*</span></label>
                                    <input type="text" name="venue_specific" id="venue_specific" required placeholder="e.g. Grand Ballroom, Shangri-La Fort, BGC" value="{{ old('venue_specific') }}" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('venue_specific') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" oninput="updateVenueField()" aria-describedby="venue_specific_error" aria-invalid="{{ $errors->has('venue_specific') ? 'true' : 'false' }}">
                                    <p id="venue_specific_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('venue_specific') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('venue_specific') }}</span>
                                    </p>
                                </div>
                                <input type="hidden" name="venue" id="venue" value="{{ old('venue') }}">

                                <!-- Venue Location Finder (map search / pin; fills the venue fields above) -->
                                <x-location-finder id="venue-finder" class="col-span-1 sm:col-span-2 lg:col-span-4"
                                    :latitude="old('venue_latitude')" :longitude="old('venue_longitude')" />

                                <!-- Indoor / Outdoor -->
                                <x-venue-setting-field :value="old('venue_setting')" class="col-span-1 sm:col-span-2 lg:col-span-4" />

                                <!-- Additional Notes -->
                                <div class="col-span-1 sm:col-span-2 lg:col-span-4 relative">
                                    <label for="special_requests" class="block text-xs font-semibold text-slate-500 mb-1">Additional Notes (Optional)</label>
                                    <textarea name="special_requests" id="special_requests" rows="2" class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] resize-none" oninput="document.getElementById('char-count').innerText = this.value.length + '/500'" placeholder="e.g. theme, color motif, specific floral preferences, etc.">{{ old('special_requests') }}</textarea>
                                    <div class="absolute bottom-2 right-3 text-[10px] text-slate-400 font-medium" id="char-count">0/500</div>
                                    @error('special_requests')
                                        <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- STEP 2: CHOOSE BOOKING METHOD             -->
                    <!-- ========================================== -->
                    <div id="guest-step-2" class="space-y-4 hidden animate-fade-in">
                        <h2 class="text-xl sm:text-2xl font-bold text-[#0B1E43] font-serif mb-1" id="step-2-title">2. Choose Booking Method</h2>
                        
                        <div id="method-selection-view">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-4">
                                <!-- AI Card -->
                                <label id="guest-card-ai" onclick="switchGuestMode('ai')" class="relative flex flex-col p-3.5 sm:p-4 border-2 border-emerald-600 bg-emerald-50/20 rounded-xl cursor-pointer transition-all hover:bg-emerald-50/30">
                                    <input type="radio" name="booking_type" value="custom_ai" {{ old('booking_type', request('package_id') ? 'preset' : 'custom_ai') === 'custom_ai' ? 'checked' : '' }} class="sr-only">
                                    <div class="absolute top-3 right-3" id="ai-check">
                                        <div class="w-5 h-5 rounded-full bg-emerald-600 flex items-center justify-center text-white">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    <div class="font-bold text-[#0B1E43] text-sm sm:text-base mb-1 flex items-center gap-2">
                                        Smart AI Custom Design
                                        <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase">POPULAR</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-1.5 leading-relaxed">Upload up to 5 inspiration images. Gemini AI analyzes each image independently to suggest floral materials and estimates.</p>
                                </label>

                                <!-- Preset Card -->
                                <label id="guest-card-preset" onclick="switchGuestMode('preset')" class="relative flex flex-col p-3.5 sm:p-4 border-2 border-slate-200 bg-white rounded-xl cursor-pointer transition-all hover:border-slate-300">
                                    <input type="radio" name="booking_type" value="preset" {{ old('booking_type', request('package_id') ? 'preset' : 'custom_ai') === 'preset' ? 'checked' : '' }} class="sr-only">
                                    <div class="absolute top-3 right-3 hidden" id="preset-check">
                                        <div class="w-5 h-5 rounded-full bg-emerald-600 flex items-center justify-center text-white">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </div>
                                    <div id="preset-icon-box" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center mb-2 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    </div>
                                    <div class="font-bold text-[#0B1E43] text-sm sm:text-base mb-1">Pre-Set Curated Package</div>
                                    <p class="text-xs text-slate-500 mb-1.5 leading-relaxed">Choose from our ready-made curated floral packages with transparent catalog pricing crafted by our florists.</p>
                                </label>
                            </div>
                            
                            <!-- Helpful Tips Strip -->
                            <div class="mt-3 bg-emerald-50/70 rounded-xl py-2 px-3 sm:px-4 border border-emerald-100 flex items-center gap-2.5 text-xs text-slate-700">
                                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                                    💡
                                </div>
                                <div class="flex-1 leading-relaxed">
                                    <strong class="font-bold text-[#0B1E43]">Helpful tip:</strong> Choose AI Custom Design to analyze your own floral inspirations, or Pre-Set Curated Package for florist-tested event packages.
                                </div>
                            </div>
                        </div>

                        <!-- AI Custom Design Sub-view -->
                        <div id="guest-section-ai" class="hidden animate-fade-in mt-4 border-t border-slate-100 pt-4">
                            <!-- Design brief: read by the AI when each photo is analyzed, so it comes before the upload -->
                            <x-design-brief-fields id="design-brief" class="mb-4" />

                            <!-- Header with Upload Tips Button -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                <div>
                                    <h3 class="text-sm sm:text-base font-bold text-[#0B1E43]">Upload Inspiration Images <span class="text-rose-600">*</span></h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Upload up to 5 inspiration images. Each image is analyzed separately so you can review its materials individually.</p>
                                </div>
                                <button type="button" onclick="openUploadTipsModal()" class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs shadow-2xs transition">
                                    <span class="text-emerald-600 font-bold">ⓘ</span>
                                    <span>Upload Tips</span>
                                </button>
                            </div>

                            <!-- Upload Dropzone (Multi-Image Capable) -->
                            <div id="dropzone_container" class="border-[2px] border-dashed border-emerald-400 rounded-xl p-4 sm:p-5 text-center cursor-pointer select-none bg-emerald-50/10 hover:bg-emerald-50/30 transition-all flex flex-col items-center justify-center gap-2">
                                <div id="dropzone_preview_target" class="flex flex-col items-center text-center max-w-md w-full">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-100/70 text-emerald-700 flex items-center justify-center mb-1">
                                        <svg class="h-5 w-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-[#0B1E43] font-bold text-sm sm:text-base mb-0.5">Add Inspiration Photos</h4>
                                    <p class="text-xs text-slate-500 mb-2">Upload clear photos of your floral inspiration, event setup, or design ideas (up to 5 images).</p>
                                    <button type="button" class="pointer-events-none px-3.5 py-1.5 bg-emerald-600 text-white rounded-lg font-bold text-xs shadow-xs hover:bg-emerald-700 transition flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                        Add Images
                                    </button>
                                    <span class="text-[10px] text-slate-400 mt-1.5">Supports JPG, PNG, WEBP (max 10MB each)</span>
                                </div>

                                <input type="file" id="inspiration_image_input" name="inspiration_images[]" accept="image/png,image/jpeg,image/webp" multiple class="hidden">
                                <input type="file" id="legacy_inspiration_image" name="inspiration_image" accept="image/*" class="hidden">
                            </div>
                            
                            <input type="hidden" name="analysis_tokens" id="guest_analysis_tokens" value="{{ old('analysis_tokens') }}">
                            <input type="hidden" name="analysis_token" id="guest_analysis_token" value="{{ old('analysis_token') }}">
                            <input type="hidden" name="analysis_temp_path" id="guest_analysis_temp_path" value="{{ old('analysis_temp_path') }}">
                            <input type="hidden" name="analysis_data" id="guest_analysis_data" value="{{ old('analysis_data') }}">
                            <input type="hidden" name="analysis_nonce" id="guest_analysis_nonce" value="{{ old('analysis_nonce') }}">

                            <div id="step1-ai-error" class="mt-3 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl font-medium flex items-center gap-2 hidden" role="alert">
                                <svg class="w-5 h-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                <span>Please upload at least one inspiration image and wait for AI analysis to complete before proceeding.</span>
                            </div>
                            <div id="guest-img-status" class="mt-2 text-sm rounded-lg px-3 py-2 font-medium hidden"></div>

                            <!-- Uploaded Images Row (Cards 1..N + Add Another Image) -->
                            <div id="uploaded-images-section" class="mt-6 hidden">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-2 mb-3 border-b border-slate-100 gap-2">
                                    <div>
                                        <h4 class="text-xs font-bold text-[#0B1E43] uppercase tracking-wider">
                                            Uploaded Images (<span id="uploaded-count-text"><span id="uploaded-count-badge">0</span> of 5 images</span>)
                                        </h4>
                                        <span id="uploaded-status-breakdown" class="text-[11px] text-slate-500 font-medium"></span>
                                    </div>
                                    <span class="text-[11px] text-slate-400">Click an analyzed image to inspect its floral materials</span>
                                </div>
                                <div id="uploaded-images-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"></div>
                            </div>

                            <!-- Truthful Per-Image Analysis Progress Card -->
                            <div id="ai-progress-card" class="mt-6 p-5 rounded-2xl border border-emerald-200 bg-white shadow-xs hidden">
                                <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-[#0B1E43]" id="progress-card-title">Analyzing Image 1 of 1</h4>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-5 items-center sm:items-start">
                                    <div class="w-28 h-28 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 shrink-0">
                                        <img id="progress-img-thumb" src="" alt="Analyzing" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1 w-full space-y-3">
                                        <!-- Step 1: Uploaded -->
                                        <div id="progress-step-1" class="flex items-center gap-2.5 text-xs">
                                            <div class="step-icon w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px]">✓</div>
                                            <span class="step-label text-slate-700 font-medium">Image uploaded</span>
                                        </div>
                                        <!-- Step 2: Validating -->
                                        <div id="progress-step-2" class="flex items-center gap-2.5 text-xs">
                                            <div class="step-icon w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px]">✓</div>
                                            <span class="step-label text-slate-700 font-medium">Validating image</span>
                                        </div>
                                        <!-- Step 3: Identifying -->
                                        <div id="progress-step-3" class="flex items-center gap-2.5 text-xs">
                                            <div class="step-icon w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-[10px] animate-pulse">3</div>
                                            <span class="step-label text-emerald-700 font-bold">Identifying floral materials...</span>
                                        </div>
                                        <!-- Step 4: Preparing -->
                                        <div id="progress-step-4" class="flex items-center gap-2.5 text-xs">
                                            <div class="step-icon w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold text-[10px]">○</div>
                                            <span class="step-label text-slate-400">Preparing results</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-100 flex items-center gap-2 text-[11px] text-emerald-800">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>This may take 30–60 seconds. Please keep this page open.</span>
                                </div>
                            </div>

                            <!-- AI Analysis View for Selected Image (2-Column Main + Full Width Below) -->
                            <div id="ai-material-preview-card" class="mt-6 p-5 sm:p-6 rounded-2xl border border-emerald-200 bg-emerald-50/20 hidden">
                                <!-- Top Bar: Consistent Image Identity & Navigation -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-emerald-100 gap-3">
                                    <button type="button" onclick="scrollToUploadedGrid()" class="self-start sm:self-auto flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                        <span>Back to Images</span>
                                    </button>

                                    <h4 class="text-base sm:text-lg font-bold font-serif text-[#0B1E43]">
                                        Inspiration Image <span id="viewer-img-title">1</span> of <span id="viewer-img-total">1</span>
                                    </h4>

                                    <!-- Image Navigator Controls -->
                                    <div class="flex items-center gap-2 self-start sm:self-auto">
                                        <span class="text-xs font-bold text-[#0B1E43] bg-white px-3 py-1 rounded-full border border-slate-200 shadow-2xs">
                                            Inspiration Image <span id="img-nav-current">1</span> of <span id="img-nav-total">1</span>
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button type="button" id="btn-img-prev" onclick="navigateViewingImage(-1)" class="w-8 h-8 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 flex items-center justify-center font-bold text-xs shadow-2xs transition disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Previous inspiration image">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                            </button>
                                            <button type="button" id="btn-img-next" onclick="navigateViewingImage(1)" class="w-8 h-8 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 flex items-center justify-center font-bold text-xs shadow-2xs transition disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Next inspiration image">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Middle Section: 2 Columns (Image Stage vs Material Details & Quick List) -->
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-5 items-start">
                                    
                                    <!-- ============================================ -->
                                    <!-- LEFT COLUMN: Image Viewer & Annotations (6 cols) -->
                                    <!-- ============================================ -->
                                    <div class="lg:col-span-6 flex flex-col gap-4">
                                        <!-- View Mode Switcher Tabs -->
                                        <div class="flex items-center gap-2 p-1 bg-white rounded-xl border border-slate-200 shadow-2xs">
                                            <button type="button" id="tab-img-original" onclick="switchImageViewMode('original')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition bg-emerald-600 text-white shadow-2xs">
                                                Original Image
                                            </button>
                                            <button type="button" id="tab-img-annotated" onclick="switchImageViewMode('annotated')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                                                AI Annotated Image
                                            </button>
                                        </div>

                                        <!-- Image Stage with AI boxes drawn over the whole photo -->
                                        <div class="relative w-full rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 shadow-xs">
                                            <div class="w-full aspect-[4/3] relative flex items-center justify-center overflow-hidden">
                                                {{-- object-contain keeps the whole photo visible, so boxes drawn from the AI's coordinates stay on their items --}}
                                                <img id="viewer-main-img" src="" alt="Selected Inspiration" class="w-full h-full object-contain">
                                                <div id="viewer-markers-container" class="absolute left-0 top-0 h-full w-full pointer-events-auto hidden"></div>
                                            </div>
                                        </div>
                                        <p id="viewer-markers-hint" class="text-[11px] text-slate-500 flex items-center gap-1.5 hidden">
                                            <span class="text-emerald-600 font-bold">ⓘ</span>
                                            <span id="viewer-markers-hint-text">Numbered boxes show where the AI found each material, colored by area. Click a box to inspect that material.</span>
                                        </p>
                                    </div>

                                    <!-- ============================================ -->
                                    <!-- RIGHT COLUMN: Material Details Card & Quick-Jump (6 cols) -->
                                    <!-- ============================================ -->
                                    <div class="lg:col-span-6 flex flex-col gap-4">
                                        <!-- Selected Material Details Card -->
                                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3.5">
                                            <!-- Header & Material Counter -->
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                                <h5 class="text-xs font-bold text-[#0B1E43] uppercase tracking-wider flex items-center gap-1.5">
                                                    <span>📋</span> Material Details
                                                </h5>
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" id="mat-btn-prev" onclick="changeMaterialNavigator(-1)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs disabled:opacity-40 disabled:cursor-not-allowed transition" aria-label="Previous material">‹</button>
                                                    <span class="text-xs font-bold text-[#0B1E43] px-1">
                                                        Material <span id="mat-current-idx">1</span> of <span id="mat-total-count">0</span>
                                                    </span>
                                                    <button type="button" id="mat-btn-next" onclick="changeMaterialNavigator(1)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs disabled:opacity-40 disabled:cursor-not-allowed transition" aria-label="Next material">›</button>
                                                </div>
                                            </div>

                                            <!-- Material Reference Image (Real or Clean Honest Fallback) -->
                                            <div class="w-full h-36 rounded-xl overflow-hidden bg-slate-50 border border-slate-200 flex items-center justify-center relative">
                                                <img id="mat-ref-img" src="" alt="Material Reference" class="w-full h-full object-cover hidden">
                                                <div id="mat-ref-fallback" class="flex flex-col items-center justify-center text-center p-3 text-slate-400">
                                                    <svg class="w-7 h-7 text-slate-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                    <span class="text-xs font-bold text-slate-600">Reference image unavailable</span>
                                                    <span class="text-[10px] text-slate-400">No verified inventory photo currently on file</span>
                                                </div>
                                            </div>

                                            <!-- Badges & Material Name -->
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span id="mat-source-badge" class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">AI Detected</span>
                                                    <span id="mat-category-badge" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700">FLOWER</span>
                                                    <span id="mat-confidence-badge" class="text-[10px] font-medium text-slate-500"></span>
                                                </div>
                                                <h5 id="mat-name" class="text-base font-bold text-[#0B1E43] font-serif mt-1">Dried Wheat Stalks</h5>
                                            </div>

                                            <!-- Specifications Grid -->
                                            <div class="grid grid-cols-2 gap-2.5 text-xs bg-slate-50 p-3 rounded-xl border border-slate-100">
                                                <div>
                                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Est. Quantity</span>
                                                    <span class="font-bold text-slate-800"><span id="mat-qty">—</span> <span id="mat-unit"></span></span>
                                                </div>
                                                <div>
                                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Unit Cost (AI Est.)</span>
                                                    <span class="font-bold text-emerald-700" id="mat-unit-cost">—</span>
                                                </div>
                                                <div class="col-span-2 pt-1 border-t border-slate-200/50">
                                                    <span class="text-[10px] text-slate-400 font-semibold uppercase block">Used For</span>
                                                    <span class="font-medium text-slate-700" id="mat-area">Arrangement component</span>
                                                </div>
                                            </div>

                                            <p id="mat-note" class="text-xs text-slate-500 italic leading-snug"></p>

                                            <!-- Seasonal Information Box -->
                                            <div id="mat-seasonal-box" class="p-2.5 rounded-xl bg-emerald-50/50 border border-emerald-100 text-xs">
                                                <div class="flex items-center gap-1 text-emerald-800 font-bold text-[11px] mb-0.5">
                                                    <span>🌿 Seasonal Information</span>
                                                </div>
                                                <p id="mat-seasonal-text" class="text-[11px] text-slate-700 leading-snug"></p>
                                                <p class="text-[10px] text-slate-400 mt-1">ⓘ This information is AI-assisted and reference-based. Requires Raflora florist validation.</p>
                                            </div>

                                            <!-- AI-Suggested Alternative Box -->
                                            <div id="mat-alt-box" class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200 text-xs hidden">
                                                <div class="flex items-center gap-1.5 font-bold text-amber-900 text-[10px] uppercase tracking-wider mb-1">
                                                    <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                    <span>AI-Suggested Alternative (Subject to Raflora Validation)</span>
                                                </div>
                                                <div class="text-slate-800 text-[11px] font-medium">
                                                    <span id="mat-orig-name"></span> → <span id="mat-alt-name" class="font-bold text-emerald-700"></span>
                                                </div>
                                                <p id="mat-alt-reason" class="text-[10px] text-slate-600 mt-0.5 leading-snug"></p>
                                            </div>
                                        </div>

                                        <!-- All Materials in this Image Quick Jump List (Full 6-col width for complete readability) -->
                                        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs flex flex-col">
                                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                                                <h5 class="text-xs font-bold text-[#0B1E43] uppercase tracking-wider">
                                                    All Materials in this Image
                                                </h5>
                                                <span class="text-[10px] font-bold text-slate-400">
                                                    <span id="mat-quick-count">0</span> items
                                                </span>
                                            </div>

                                            <div id="mat-quick-list" class="space-y-1.5 max-h-56 overflow-y-auto pr-1"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ======================================================== -->
                                <!-- BELOW MIDDLE: Analysis Summary Card (Full Width)         -->
                                <!-- ======================================================== -->
                                <div class="mt-5 p-4 sm:p-5 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-emerald-600">✨</span>
                                        <h5 class="text-xs font-bold text-[#0B1E43] uppercase tracking-wider">Analysis Summary</h5>
                                    </div>
                                    <p id="viewer-summary-text" class="text-xs sm:text-sm text-slate-600 leading-relaxed italic"></p>
                                    <div class="flex flex-wrap gap-2 pt-1">
                                        <span id="viewer-badge-mats" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                                            🌱 <span id="viewer-badge-mats-count">0</span> Materials Detected
                                        </span>
                                        <span id="viewer-badge-style" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-100 text-slate-800 text-xs font-semibold border border-slate-200">
                                            🏺 <span id="viewer-badge-style-text">Table Arrangement</span>
                                        </span>
                                        <span id="viewer-badge-conf" class="hidden inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 text-blue-800 text-xs font-semibold border border-blue-200">
                                            🔍 <span id="viewer-badge-conf-text"></span>
                                        </span>
                                    </div>
                                </div>

                                {{-- Materials and estimate per area the guest picked in the design brief --}}
                                <x-decor-area-breakdown-live id="guest-area-breakdown" class="mt-4" />

                                <!-- ======================================================== -->
                                <!-- BELOW SUMMARY: AI-Assisted Initial Estimate (Full Width) -->
                                <!-- ======================================================== -->
                                <div class="mt-4 p-5 sm:p-6 bg-white rounded-2xl border-2 border-emerald-300 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                            <span>AI-Assisted Initial Estimate</span>
                                        </div>
                                        <p class="text-xs text-slate-500 max-w-xl leading-relaxed">
                                            This is an initial estimate based on AI-assisted material identification and estimated quantities. It is not an official quotation. Final pricing is subject to Raflora staff review, material validation, and official quotation.
                                        </p>
                                    </div>
                                    <div class="sm:text-right shrink-0">
                                        <div class="text-xs text-slate-400 font-semibold uppercase">Estimated Total</div>
                                        <div class="text-3xl font-extrabold text-emerald-600 font-mono">
                                            ₱<span id="mat-grand-total">0</span>
                                        </div>
                                        <p id="mat-estimate-incomplete" class="hidden text-[11px] text-amber-700 mt-1 max-w-xs sm:ml-auto"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PRESET PACKAGE Sub-view -->
                        <div id="guest-section-preset" class="hidden animate-fade-in mt-6 border-t border-slate-100 pt-6">
                            <h3 class="text-sm font-bold text-slate-800 mb-4">Select a package <span class="text-rose-600">*</span></h3>
                            
                            <div id="step1-preset-error" class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl font-medium flex items-center gap-2 hidden" role="alert">
                                <svg class="w-5 h-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                <span>Please select a curated package before proceeding.</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="package-list">
                                @forelse($packages as $package)
                                    @php
                                        $titleLower = strtolower($package->title);
                                        $eventType = 'other';
                                        if (str_contains($titleLower, 'wedding')) $eventType = 'wedding';
                                        elseif (str_contains($titleLower, 'corporate')) $eventType = 'corporate';
                                        elseif (str_contains($titleLower, 'birthday') || str_contains($titleLower, 'debut')) $eventType = 'birthday';
                                        elseif (str_contains($titleLower, 'anniversary')) $eventType = 'anniversary';
                                        
                                        $primaryImageUrl = $package->primary_image_url;
                                        $inclusions = is_array($package->included_items) ? $package->included_items : (is_string($package->included_items) ? json_decode($package->included_items, true) ?? [] : []);

                                        $packageMeta = json_encode([
                                            'id' => $package->id,
                                            'title' => $package->title,
                                            'price' => (float)$package->price,
                                            'description' => $package->description,
                                            'event_type' => $eventType,
                                            'image_url' => $primaryImageUrl,
                                            'included_items' => $inclusions,
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                    @endphp
                                    <label class="guest-package-card relative flex flex-col p-0 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all bg-white hover:border-emerald-300 overflow-hidden shadow-xs" data-type="{{ $eventType }}" data-package='{!! $packageMeta !!}'>
                                        <input type="radio" name="package_id" value="{{ $package->id }}" {{ (string)old('package_id', request('package_id')) === (string)$package->id ? 'checked' : '' }} class="sr-only package-radio">
                                        
                                        <!-- Selected Badge -->
                                        <div class="package-badge absolute top-3 right-3 hidden z-10 bg-emerald-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-md">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>

                                        <div class="h-36 bg-slate-100 flex items-center justify-center overflow-hidden relative">
                                            @if($primaryImageUrl)
                                                <img src="{{ $primaryImageUrl }}" alt="{{ $package->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                                            @else
                                                <div class="w-full h-full flex flex-col items-center justify-center text-slate-400 bg-slate-50 gap-1 p-3 text-center">
                                                    <svg class="w-7 h-7 text-emerald-600/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                    <span class="text-[11px] font-medium text-slate-500">Floral Package</span>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <div class="p-4 flex flex-col flex-1 h-56">
                                            <div class="font-bold text-[#0B1E43] text-base mb-1 truncate">{{ $package->title }}</div>
                                            <div class="flex items-baseline gap-1.5 mb-2">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Catalog Price</span>
                                                <span class="text-lg font-extrabold text-emerald-600">₱{{ number_format($package->price, 0) }}</span>
                                            </div>
                                            
                                            <div class="mt-1 text-xs text-slate-600 flex-1 overflow-y-auto max-h-48 pr-1">
                                                @if(is_array($inclusions) && count($inclusions) > 0)
                                                    <ul class="space-y-1">
                                                        @foreach($inclusions as $item)
                                                            <li class="flex items-start gap-1.5">
                                                                <span class="text-emerald-500 font-bold mt-0.5">✓</span>
                                                                <span class="leading-tight">{{ $item }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @elseif($package->description)
                                                    <p class="text-xs text-slate-500 leading-relaxed">{{ $package->description }}</p>
                                                @endif
                                            </div>
                                            
                                            <div class="mt-3 pt-2">
                                                <button type="button" class="view-package-btn w-full py-1.5 bg-white border border-slate-200 text-[#0B1E43] rounded-lg hover:bg-slate-50 text-xs font-semibold transition-colors">View Package Details</button>
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="col-span-full p-8 text-center text-sm text-slate-500 bg-slate-50 rounded-xl border border-slate-200">
                                        No curated packages currently available.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- STEP 3: CONTACT INFORMATION               -->
                    <!-- ========================================== -->
                    <div id="guest-step-3" class="space-y-6 hidden animate-fade-in">
                        <!-- Top Banner (Helpful Tip) -->
                        <div class="bg-emerald-50/70 rounded-xl py-2 px-3 sm:px-4 border border-emerald-100 flex items-center gap-2.5 text-xs text-slate-700">
                            <span class="text-base leading-none">💡</span>
                            <span class="leading-relaxed"><strong class="font-bold text-[#0B1E43]">Helpful tip:</strong> Since you are booking as a guest, please double check your contact details so we can reach you with your booking tracking link and official quotation.</span>
                        </div>

                        <!-- Guest Contact Info Card -->
                        <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5">
                            <div class="flex items-center justify-between pb-2.5 mb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </div>
                                    <div>
                                        <h2 class="text-sm sm:text-base font-bold text-[#0B1E43]">Contact Information</h2>
                                        <p class="text-xs text-slate-500 hidden sm:block">Please provide your valid contact details for your booking tracking link and quotation.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                                <!-- Full Name -->
                                <div>
                                    <label for="guest_name" class="block text-xs font-semibold text-slate-500 mb-1">Full Name <span class="text-rose-500">*</span></label>
                                    <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" placeholder="Juan Dela Cruz" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('guest_name') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-slate-800 transition-colors" required aria-describedby="guest_name_error" aria-invalid="{{ $errors->has('guest_name') ? 'true' : 'false' }}">
                                    <p id="guest_name_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('guest_name') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('guest_name') }}</span>
                                    </p>
                                </div>

                                <!-- Email Address -->
                                <div>
                                    <label for="guest_email" class="block text-xs font-semibold text-slate-500 mb-1">Email Address <span class="text-rose-500">*</span></label>
                                    <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" placeholder="juan@example.com" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('guest_email') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-slate-800 transition-colors" required aria-describedby="guest_email_error" aria-invalid="{{ $errors->has('guest_email') ? 'true' : 'false' }}">
                                    <p id="guest_email_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('guest_email') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('guest_email') }}</span>
                                    </p>
                                </div>

                                <!-- Mobile Phone -->
                                <div>
                                    <label for="guest_phone" class="block text-xs font-semibold text-slate-500 mb-1">Mobile Phone Number <span class="text-rose-500">*</span></label>
                                    <input type="tel" name="guest_phone" id="guest_phone" value="{{ old('guest_phone') }}" placeholder="09XXXXXXXXX" maxlength="11" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('guest_phone') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-slate-800 transition-colors" required aria-describedby="guest_phone_error" aria-invalid="{{ $errors->has('guest_phone') ? 'true' : 'false' }}">
                                    <p id="guest_phone_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('guest_phone') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('guest_phone') }}</span>
                                    </p>
                                </div>

                                <!-- Physical Address -->
                                <div>
                                    <label for="guest_address" class="block text-xs font-semibold text-slate-500 mb-1">Physical Address <span class="text-rose-500">*</span></label>
                                    <input type="text" name="guest_address" id="guest_address" value="{{ old('guest_address') }}" placeholder="City / Barangay / Street" class="w-full py-2 px-2.5 bg-white border {{ $errors->has('guest_address') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-slate-800 transition-colors" required aria-describedby="guest_address_error" aria-invalid="{{ $errors->has('guest_address') ? 'true' : 'false' }}">
                                    <p id="guest_address_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('guest_address') ? '' : 'hidden' }}" role="alert">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span class="error-msg">{{ $errors->first('guest_address') }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- STEP 4: REVIEW & SUBMIT                   -->
                    <!-- ========================================== -->
                    <div id="guest-step-4" class="space-y-6 hidden animate-fade-in">
                        
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
                                    <p class="text-emerald-700 mt-0.5">Prices shown reflect catalog rates or initial AI-assisted estimates. An official quotation will be prepared following Raflora staff review and material validation.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            
                            <!-- Left Content: Comprehensive Review Cards -->
                            <div class="lg:col-span-2 space-y-4">
                                <!-- Review: Event Details -->
                                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-4">
                                            <h4 class="font-bold text-[#0B1E43] text-sm">Event Details</h4>
                                            <button type="button" onclick="showGuestStep(1)" class="flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit
                                            </button>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-3">
                                            <div>
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Event Type</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-event-type">-</div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Event Date</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-event-date">-</div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Event Time</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-event-time">-</div>
                                            </div>
                                            <div class="sm:col-span-2 md:col-span-3">
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Venue City / Municipality</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-venue-city">-</div>
                                            </div>
                                            <div class="sm:col-span-2 md:col-span-3">
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Specific Venue / Address</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-venue-specific">-</div>
                                                <div class="mt-0.5 hidden text-[11px] font-semibold text-emerald-700" id="review-venue-pin"><i class="fa-solid fa-location-dot mr-1" aria-hidden="true"></i>Pinned on map</div>
                                            </div>
                                            <div class="sm:col-span-2 md:col-span-3">
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Indoor / Outdoor</div>
                                                <div class="text-sm font-bold text-[#0B1E43]" id="review-venue-setting">-</div>
                                            </div>
                                            <div class="sm:col-span-2 md:col-span-3">
                                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Additional Notes</div>
                                                <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100" id="review-notes">-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review: Booking Method -->
                                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <h4 class="font-bold text-[#0B1E43] text-sm">Booking Method</h4>
                                            <button type="button" onclick="showGuestStep(2)" class="flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-bold text-[#0B1E43]" id="review-method-title">Smart AI Custom Design</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Review: Inspiration Image / Package -->
                                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-bold text-[#0B1E43] text-sm" id="review-media-title">Inspiration Images</h4>
                                            <button type="button" onclick="showGuestStep(2)" class="flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit
                                            </button>
                                        </div>

                                        <div class="flex flex-col gap-4">
                                            <div class="flex flex-col sm:flex-row gap-4 items-start">
                                                <!-- Single image / Package preview (backward compatible) -->
                                                <div id="review-media-single-wrapper" class="w-24 h-24 rounded-xl overflow-hidden bg-slate-100 shrink-0 border border-slate-200 flex items-center justify-center">
                                                    <img src="" id="review-media-img" class="w-full h-full object-cover hidden" alt="Uploaded or Package Preview">
                                                    <div id="review-media-placeholder" class="text-slate-400 text-xs flex flex-col items-center justify-center p-2 text-center">
                                                        <svg class="w-6 h-6 text-emerald-600/50 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                        <span>Floral Concept</span>
                                                    </div>
                                                </div>

                                                <div class="flex-1">
                                                    <div class="font-bold text-sm text-[#0B1E43]" id="review-media-subtitle">Uploaded Image</div>
                                                    <div class="text-xs font-bold text-emerald-600 mt-0.5" id="review-media-status">Ready</div>
                                                    <div class="text-xs text-slate-500 mt-1" id="review-media-desc"></div>
                                                    
                                                    <!-- Package Inclusions Block -->
                                                    <div id="review-package-inclusions-wrapper" class="mt-3 pt-3 border-t border-slate-100 hidden">
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Package Inclusions</span>
                                                        <ul id="review-package-inclusions" class="space-y-1 text-xs text-slate-600"></ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Multi-image thumbnails strip (for custom AI) -->
                                            <div id="review-multi-images-row" class="hidden pt-3 border-t border-slate-100">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Inspiration Photos</span>
                                                <div id="review-multi-thumbnails" class="flex flex-wrap gap-2"></div>
                                            </div>

                                            <!-- Design brief (custom AI, only when something was filled in) -->
                                            <div id="review-design-brief-row" class="hidden pt-3 border-t border-slate-100">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Design Brief</span>
                                                <dl id="review-design-brief" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs"></dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review: AI Material Summary (Custom AI Only) -->
                                <div id="review-ai-materials-section" class="bg-white rounded-2xl border border-slate-200 p-5 hidden">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            </div>
                                            <h4 class="font-bold text-[#0B1E43] text-sm">AI Analysis Summary</h4>
                                        </div>
                                        <button type="button" onclick="showGuestStep(2)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 transition shadow-2xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            View AI Analysis
                                        </button>
                                    </div>

                                    <!-- Compact Multi-Image AI Summary Metrics -->
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-emerald-50/40 rounded-xl border border-emerald-100 mb-3 text-xs">
                                        <div>
                                            <span class="text-[10px] text-slate-400 font-semibold uppercase block">Inspiration Images</span>
                                            <span class="font-bold text-slate-800" id="review-images-count-text">1 image</span>
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-slate-400 font-semibold uppercase block">Detected Materials</span>
                                            <span class="font-bold text-emerald-700" id="review-materials-count-text">0 items</span>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <span class="text-[10px] text-slate-400 font-semibold uppercase block">AI Initial Estimate</span>
                                            <span class="font-extrabold text-[#0B1E43] font-mono" id="review-estimate-val">₱0</span>
                                        </div>
                                    </div>

                                    <x-decor-area-breakdown-live id="review-area-breakdown" class="mb-3" />

                                    <div id="review-materials-list" class="space-y-1.5 max-h-48 overflow-y-auto pr-1"></div>

                                    <!-- Review AI Disclaimers -->
                                    <div class="mt-4 p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
                                        <p><strong class="text-slate-700">Seasonal Context:</strong> Sourced information is AI-assisted and reference-based. It requires Raflora florist validation and does not guarantee supplier availability.</p>
                                        <p><strong class="text-slate-700">Business Truth:</strong> AI detection ≠ procurement feasibility ≠ inventory availability ≠ official quotation.</p>
                                    </div>
                                </div>

                                <!-- Review: Contact Information -->
                                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-4">
                                            <h4 class="font-bold text-[#0B1E43] text-sm">Contact Information</h4>
                                            <button type="button" onclick="showGuestStep(3)" class="flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit
                                            </button>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-3 text-xs">
                                            <div>
                                                <span class="text-slate-400 font-semibold block text-[10px] uppercase">Full Name</span>
                                                <span class="font-bold text-slate-800" id="review-contact-name">-</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 font-semibold block text-[10px] uppercase">Email</span>
                                                <span class="font-bold text-slate-800" id="review-contact-email">-</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 font-semibold block text-[10px] uppercase">Mobile Phone</span>
                                                <span class="font-bold text-slate-800" id="review-contact-phone">-</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 font-semibold block text-[10px] uppercase">Physical Address</span>
                                                <span class="font-bold text-slate-800" id="review-contact-address">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Sidebar -->
                            <div class="lg:col-span-1 space-y-4">
                                <!-- Ready to Submit -->
                                <div class="bg-emerald-50 rounded-2xl p-5 border border-emerald-100 flex gap-3 items-start">
                                    <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-[#0B1E43] text-sm mb-1">Ready to Submit?</h4>
                                        <p class="text-xs text-slate-600">Please review all information on this page. Once submitted, our team will review your request and prepare an official quotation.</p>
                                    </div>
                                </div>
                                
                                <!-- Summary -->
                                <div class="bg-white rounded-2xl p-5 border border-slate-200">
                                    <div class="flex gap-2 items-center mb-1">
                                        <h4 class="font-bold text-[#0B1E43] text-sm">Booking Summary</h4>
                                    </div>
                                    
                                    <div class="space-y-2 text-xs mt-4">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Method</span>
                                            <span class="font-bold text-[#0B1E43] text-right" id="summary-method">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Event Type</span>
                                            <span class="font-bold text-[#0B1E43] text-right" id="summary-type">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Event Date</span>
                                            <span class="font-bold text-[#0B1E43] text-right" id="summary-date">-</span>
                                        </div>
                                        <div class="pt-3 border-t border-slate-100 flex justify-between items-baseline">
                                            <span class="text-[11px] text-slate-500" id="summary-price-label">Price / Estimate</span>
                                            <span class="font-extrabold text-emerald-600 text-sm text-right font-mono" id="summary-price">Calculated Upon Review</span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 italic pt-1">Official quotation prepared after Raflora review. No payment is required today.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="mt-5 pt-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:justify-between items-center gap-3">
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button type="button" id="btn-back" onclick="navigateGuest(-1)" class="hidden py-2.5 px-5 rounded-xl border border-slate-300 bg-white text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 text-sm shadow-2xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Back
                            </button>
                            
                            <!-- Pagination Controls (Preset Packages) -->
                            <div id="package-pagination" class="flex gap-2 hidden"></div>
                        </div>
                        <div class="flex-1"></div>
                        <button type="button" id="btn-next" onclick="navigateGuest(1)" class="py-2.5 px-6 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 text-sm">
                            Next
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                        <button type="submit" id="btn-submit" class="hidden py-2.5 px-6 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                            Submit Booking Request
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        let isImageValid = false;
        let isAiAnalyzing = false;
        let currentAnalysisMaterials = [];
        let currentAnalysisPricing = null;
        let currentMaterialIndex = 0;
        let uploadedImages = [];
        let viewingImageIndex = 0;
        let imageViewMode = 'original';
        
        const DRAFT_STORAGE_KEY = 'raflora_guest_booking_draft_v1';
        let isRestoringDraft = false;

        function generateThumbnail(file, callback) {
            if (!file || !file.type || !file.type.startsWith('image/')) {
                if (callback) callback(null);
                return;
            }
            try {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        try {
                            const maxDim = 320;
                            let w = img.width;
                            let h = img.height;
                            if (w > h) {
                                if (w > maxDim) {
                                    h = Math.round((h * maxDim) / w);
                                    w = maxDim;
                                }
                            } else {
                                if (h > maxDim) {
                                    w = Math.round((w * maxDim) / h);
                                    h = maxDim;
                                }
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.max(1, w);
                            canvas.height = Math.max(1, h);
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, w, h);
                            const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                            if (callback) callback(dataUrl);
                        } catch(err) {
                            if (callback) callback(e.target.result);
                        }
                    };
                    img.onerror = function() {
                        if (callback) callback(null);
                    };
                    img.src = e.target.result;
                };
                reader.onerror = function() {
                    if (callback) callback(null);
                };
                reader.readAsDataURL(file);
            } catch(e) {
                if (callback) callback(null);
            }
        }

        function saveGuestDraft() {
            if (isRestoringDraft) return;
            try {
                const methodInput = document.querySelector('input[name="booking_type"]:checked');
                const selectedPkgInput = document.querySelector('input[name="package_id"]:checked');

                const draft = {
                    version: 1,
                    timestamp: Date.now(),
                    step: currentStep,
                    // Step 1: Event Details
                    event_type: document.getElementById('event_type')?.value || '',
                    other_event_type: document.getElementById('other_event_type')?.value || '',
                    event_date: document.getElementById('event_date')?.value || '',
                    event_time: document.getElementById('event_time')?.value || '',
                    end_time: document.getElementById('end_time')?.value || '',
                    guest_count: document.getElementById('guest_count')?.value || '',
                    table_count: document.getElementById('table_count')?.value || '',
                    venue_city: document.getElementById('venue_city')?.value || '',
                    venue_specific: document.getElementById('venue_specific')?.value || '',
                    venue: document.getElementById('venue')?.value || '',
                    venue_latitude: document.querySelector('#venue-finder [data-finder-lat]')?.value || '',
                    venue_longitude: document.querySelector('#venue-finder [data-finder-lng]')?.value || '',
                    venue_setting: document.querySelector('input[name="venue_setting"]:checked')?.value || '',
                    special_requests: document.getElementById('special_requests')?.value || '',
                    design_brief: window.RafloraDesignBrief?.get('design-brief')?.getState() || null,
                    // Step 2: Booking Method & Inspiration Images
                    booking_type: methodInput ? methodInput.value : 'custom_ai',
                    package_id: selectedPkgInput ? selectedPkgInput.value : '',
                    viewingImageIndex: viewingImageIndex,
                    uploadedImages: uploadedImages.map(img => ({
                        id: img.id,
                        filename: img.filename,
                        previewUrl: img.previewUrl && !img.previewUrl.startsWith('blob:') ? img.previewUrl : null,
                        thumbnailUrl: img.thumbnailUrl || null,
                        status: img.status,
                        statusText: img.statusText,
                        errorMessage: img.errorMessage,
                        analysisToken: img.analysisToken,
                        tempPath: img.tempPath,
                        nonce: img.nonce,
                        analysisData: img.analysisData,
                        analysis: img.analysis,
                        suggestedMaterials: img.suggestedMaterials || [],
                        pricingSummary: img.pricingSummary || {},
                        visualAnalysis: img.visualAnalysis || null
                    })),
                    analysis_tokens: document.getElementById('guest_analysis_tokens')?.value || '',
                    analysis_token: document.getElementById('guest_analysis_token')?.value || '',
                    analysis_temp_path: document.getElementById('guest_analysis_temp_path')?.value || '',
                    analysis_data: document.getElementById('guest_analysis_data')?.value || '',
                    analysis_nonce: document.getElementById('guest_analysis_nonce')?.value || '',
                    // Step 3: Contact Information
                    guest_name: document.getElementById('guest_name')?.value || '',
                    guest_email: document.getElementById('guest_email')?.value || '',
                    guest_phone: document.getElementById('guest_phone')?.value || '',
                    guest_address: document.getElementById('guest_address')?.value || ''
                };

                sessionStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
            } catch (e) {
                // Silently handle quota / private-browsing restrictions
            }
        }

        function clearGuestDraft() {
            try {
                sessionStorage.removeItem(DRAFT_STORAGE_KEY);
            } catch (e) {}
        }

        function restoreGuestDraft() {
            try {
                const raw = sessionStorage.getItem(DRAFT_STORAGE_KEY);
                if (!raw) return false;
                const draft = JSON.parse(raw);
                if (!draft || typeof draft !== 'object') return false;

                // Ignore drafts older than 24 hours
                const maxAgeMs = 24 * 60 * 60 * 1000;
                if (draft.timestamp && (Date.now() - draft.timestamp > maxAgeMs)) {
                    clearGuestDraft();
                    return false;
                }

                isRestoringDraft = true;

                // 1. Restore Step 1 Fields
                if (draft.event_type !== undefined) {
                    const el = document.getElementById('event_type');
                    if (el && draft.event_type) {
                        el.value = draft.event_type;
                        el.dispatchEvent(new Event('change'));
                    }
                }
                if (draft.other_event_type !== undefined) {
                    const el = document.getElementById('other_event_type');
                    if (el && draft.other_event_type) el.value = draft.other_event_type;
                }
                if (draft.event_date !== undefined) {
                    const el = document.getElementById('event_date');
                    if (el && draft.event_date) el.value = draft.event_date;
                }
                if (draft.event_time !== undefined) {
                    const el = document.getElementById('event_time');
                    if (el && draft.event_time) el.value = draft.event_time;
                }
                if (draft.end_time !== undefined) {
                    const el = document.getElementById('end_time');
                    if (el && draft.end_time) el.value = draft.end_time;
                }
                if (draft.guest_count !== undefined) {
                    const el = document.getElementById('guest_count');
                    if (el && draft.guest_count) el.value = draft.guest_count;
                }
                if (draft.table_count !== undefined) {
                    const el = document.getElementById('table_count');
                    if (el && draft.table_count) el.value = draft.table_count;
                }
                if (draft.venue_city !== undefined) {
                    const el = document.getElementById('venue_city');
                    if (el && draft.venue_city) el.value = draft.venue_city;
                }
                if (draft.venue_specific !== undefined) {
                    const el = document.getElementById('venue_specific');
                    if (el && draft.venue_specific) el.value = draft.venue_specific;
                }
                if (draft.venue !== undefined) {
                    const el = document.getElementById('venue');
                    if (el && draft.venue) el.value = draft.venue;
                }
                updateVenueField();
                if (draft.venue_latitude && draft.venue_longitude) {
                    window.RafloraLocationFinder?.get('venue-finder')?.setCoordinates(draft.venue_latitude, draft.venue_longitude);
                }
                if (draft.venue_setting !== undefined) {
                    document.querySelectorAll('input[name="venue_setting"]').forEach(el => { el.checked = el.value === draft.venue_setting; });
                }
                if (draft.design_brief) {
                    window.RafloraDesignBrief?.get('design-brief')?.setState(draft.design_brief);
                }

                if (draft.special_requests !== undefined) {
                    const el = document.getElementById('special_requests');
                    if (el && draft.special_requests) {
                        el.value = draft.special_requests;
                        const charCount = document.getElementById('char-count');
                        if (charCount) charCount.innerText = el.value.length + '/500';
                    }
                }

                // 2. Restore Step 2 Method
                if (draft.booking_type === 'preset') {
                    switchGuestMode('preset');
                    if (draft.package_id) {
                        const radio = document.querySelector(`input[name="package_id"][value="${draft.package_id}"]`);
                        if (radio) {
                            radio.checked = true;
                            const card = radio.closest('.guest-package-card');
                            if (card) {
                                document.querySelectorAll('.guest-package-card').forEach(c => {
                                    c.classList.remove('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                                    c.querySelector('.package-badge')?.classList.add('hidden');
                                });
                                card.classList.add('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                                card.querySelector('.package-badge')?.classList.remove('hidden');
                            }
                        }
                    }
                } else {
                    switchGuestMode('ai');
                    if (draft.uploadedImages && Array.isArray(draft.uploadedImages) && draft.uploadedImages.length > 0) {
                        uploadedImages = draft.uploadedImages.map(img => {
                            let preview = img.thumbnailUrl;
                            if (!preview && img.analysisToken) {
                                preview = '/guest/bookings/analysis-image/' + encodeURIComponent(img.analysisToken);
                            }
                            if (!preview && img.previewUrl && !img.previewUrl.startsWith('blob:')) {
                                preview = img.previewUrl;
                            }
                            if (!preview) {
                                preview = '{{ asset("images/placeholder.svg") }}';
                            }
                            return {
                                ...img,
                                file: null,
                                previewUrl: preview
                            };
                        });

                        const tokensInput = document.getElementById('guest_analysis_tokens');
                        if (tokensInput && draft.analysis_tokens) tokensInput.value = draft.analysis_tokens;
                        const tokenInput = document.getElementById('guest_analysis_token');
                        if (tokenInput && draft.analysis_token) tokenInput.value = draft.analysis_token;
                        const pathInput = document.getElementById('guest_analysis_temp_path');
                        if (pathInput && draft.analysis_temp_path) pathInput.value = draft.analysis_temp_path;
                        const dataInput = document.getElementById('guest_analysis_data');
                        if (dataInput && draft.analysis_data) dataInput.value = draft.analysis_data;
                        const nonceInput = document.getElementById('guest_analysis_nonce');
                        if (nonceInput && draft.analysis_nonce) nonceInput.value = draft.analysis_nonce;

                        syncAnalysisHiddenInputs();
                        renderUploadedGrid();
                        viewingImageIndex = (draft.viewingImageIndex !== undefined && draft.viewingImageIndex >= 0 && draft.viewingImageIndex < uploadedImages.length)
                            ? draft.viewingImageIndex
                            : 0;
                        renderActiveImageAnalysis();
                    }
                }

                // 3. Restore Step 3 Fields
                if (draft.guest_name !== undefined) {
                    const el = document.getElementById('guest_name');
                    if (el && draft.guest_name) el.value = draft.guest_name;
                }
                if (draft.guest_email !== undefined) {
                    const el = document.getElementById('guest_email');
                    if (el && draft.guest_email) el.value = draft.guest_email;
                }
                if (draft.guest_phone !== undefined) {
                    const el = document.getElementById('guest_phone');
                    if (el && draft.guest_phone) el.value = draft.guest_phone;
                }
                if (draft.guest_address !== undefined) {
                    const el = document.getElementById('guest_address');
                    if (el && draft.guest_address) el.value = draft.guest_address;
                }

                // 4. Restore Step Position
                const targetStep = parseInt(draft.step) || 1;
                if (targetStep > 1 && targetStep <= 4) {
                    currentStep = 1;
                    showGuestStep(targetStep, true);
                } else {
                    showGuestStep(1, true);
                }

                return true;
            } catch (e) {
                console.error('Failed to restore guest booking draft:', e);
                return false;
            } finally {
                isRestoringDraft = false;
            }
        }
        
        function formatDateTime(dateStr, timeStr) {
            if(!dateStr) return '-';
            const date = new Date(dateStr);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = date.toLocaleDateString('en-US', options);
            if(!timeStr) return formattedDate;
            
            return formattedDate;
        }

        function formatTimeSpan(timeStr) {
            if(!timeStr) return '-';
            const timeParts = timeStr.split(':');
            let h = parseInt(timeParts[0]);
            const m = timeParts[1];
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12;
            return `${h}:${m} ${ampm}`;
        }

        let pendingFocusEl = null;

        function showValidationModal(items, focusEl) {
            if (isRestoringDraft) return;
            pendingFocusEl = focusEl || null;
            const modal = document.getElementById('raflora-validation-modal');
            if (!modal) return;
            
            const listContainer = document.getElementById('validation-modal-list-container');
            const listEl = document.getElementById('validation-modal-list');
            
            if (items && items.length > 0 && listContainer && listEl) {
                listEl.innerHTML = '';
                items.forEach(item => {
                    const li = document.createElement('li');
                    li.textContent = typeof item === 'string' ? item : `${item.label}: ${item.message}`;
                    listEl.appendChild(li);
                });
                listContainer.classList.remove('hidden');
            } else if (listContainer) {
                listContainer.classList.add('hidden');
            }
            
            modal.classList.remove('hidden');
            const closeBtn = document.getElementById('validation-modal-close-btn');
            if (closeBtn) closeBtn.focus();
        }

        function closeValidationModal() {
            const modal = document.getElementById('raflora-validation-modal');
            if (modal) modal.classList.add('hidden');
            if (pendingFocusEl) {
                try {
                    pendingFocusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    pendingFocusEl.focus();
                } catch(e){}
                pendingFocusEl = null;
            }
        }

        function openUploadTipsModal() {
            const modal = document.getElementById('upload-tips-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeUploadTipsModal() {
            const modal = document.getElementById('upload-tips-modal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('raflora-validation-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeValidationModal();
                }
                const tipsModal = document.getElementById('upload-tips-modal');
                if (tipsModal && !tipsModal.classList.contains('hidden')) {
                    closeUploadTipsModal();
                }
            }
        });

        // One "Materials per area" breakdown for several analyzed photos: each area lists every
        // photo's materials, and an area counts as missing only when no photo has materials for it.
        function combinedAreaBreakdown(images) {
            const breakdowns = images.map((img) => img.analysis?.area_breakdown).filter((b) => b && Array.isArray(b.areas));
            if (breakdowns.length <= 1) return breakdowns[0] || null;
            const byKey = new Map();
            breakdowns.forEach((breakdown, photoIndex) => breakdown.areas.forEach((area) => {
                const entry = byKey.get(area.key) || { ...area, items: [], missing: true };
                entry.items = entry.items.concat((area.items || []).map((item) => ({ ...item, line_id: 'p' + (photoIndex + 1) + '-' + item.line_id })));
                entry.item_count = entry.items.length;
                entry.missing = entry.missing && Boolean(area.missing);
                byKey.set(area.key, entry);
            }));
            return { areas: [...byKey.values()] };
        }

        function populateReview() {
            // Get selected method
            const methodInput = document.querySelector('input[name="booking_type"]:checked');
            const method = methodInput ? methodInput.value : 'custom_ai';
            window.RafloraAreaBreakdown?.render('review-area-breakdown', null);
            
            const inclusionsWrapper = document.getElementById('review-package-inclusions-wrapper');
            const inclusionsList = document.getElementById('review-package-inclusions');
            const reviewImg = document.getElementById('review-media-img');
            const reviewPlaceholder = document.getElementById('review-media-placeholder');
            const reviewDesc = document.getElementById('review-media-desc');
            const reviewAiSection = document.getElementById('review-ai-materials-section');
            const reviewMaterialsList = document.getElementById('review-materials-list');
            const multiRow = document.getElementById('review-multi-images-row');
            const multiThumbnails = document.getElementById('review-multi-thumbnails');

            // Design brief rows (built with textContent: the values are the guest's own text)
            const briefRow = document.getElementById('review-design-brief-row');
            const briefList = document.getElementById('review-design-brief');
            const briefRows = method === 'custom_ai' ? (window.RafloraDesignBrief?.get('design-brief')?.summaryRows() || []) : [];
            if (briefList) {
                briefList.innerHTML = '';
                briefRows.forEach(([label, value]) => {
                    const item = document.createElement('div');
                    const dt = document.createElement('dt');
                    dt.className = 'text-slate-400 font-semibold';
                    dt.textContent = label;
                    const dd = document.createElement('dd');
                    dd.className = 'font-bold text-slate-800 break-words';
                    dd.textContent = value;
                    item.append(dt, dd);
                    briefList.appendChild(item);
                });
            }
            briefRow?.classList.toggle('hidden', briefRows.length === 0);

            if (method === 'custom_ai') {
                document.getElementById('review-method-title').textContent = 'Smart AI Custom Design';
                document.getElementById('review-media-title').textContent = 'Inspiration Images';
                document.getElementById('review-media-subtitle').textContent = 'Uploaded Floral Images';
                if (reviewDesc) reviewDesc.textContent = 'Custom floral design based on your inspiration photos.';
                if (inclusionsWrapper) inclusionsWrapper.classList.add('hidden');
                document.getElementById('summary-method').textContent = 'Smart AI Custom Design';

                const completedImages = uploadedImages.filter(img => img.status === 'completed');
                const imgCount = completedImages.length;
                
                let totalMaterialsCount = 0;
                let totalEst = 0;
                completedImages.forEach(img => {
                    const mats = img.suggestedMaterials || [];
                    totalMaterialsCount += mats.length;
                    const pricing = img.pricingSummary || {};
                    totalEst += parseFloat(pricing.estimated_grand_total_php || pricing.raw_materials_total_php || 0);
                });

                if (imgCount > 0) {
                    document.getElementById('review-media-status').textContent = `${imgCount} of ${uploadedImages.length} image(s) analyzed successfully`;
                    if (reviewImg && completedImages[0]?.previewUrl) {
                        reviewImg.src = completedImages[0].previewUrl;
                        reviewImg.classList.remove('hidden');
                    }
                    if (reviewPlaceholder) reviewPlaceholder.classList.add('hidden');
                } else if (isImageValid) {
                    document.getElementById('review-media-status').textContent = 'Image analyzed successfully';
                } else {
                    document.getElementById('review-media-status').textContent = 'Pending Analysis';
                }

                // Render multi-thumbnails strip
                if (multiRow && multiThumbnails) {
                    if (completedImages.length > 0) {
                        multiThumbnails.innerHTML = '';
                        completedImages.forEach((img, i) => {
                            const thumb = document.createElement('div');
                            thumb.className = 'w-16 h-16 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 relative shadow-2xs';
                            thumb.innerHTML = `
                                <img src="${img.previewUrl}" alt="Inspiration ${i+1}" class="w-full h-full object-cover">
                                <span class="absolute bottom-0 inset-x-0 bg-slate-900/60 text-white text-[9px] text-center font-bold py-0.5">Image ${i+1}</span>
                            `;
                            multiThumbnails.appendChild(thumb);
                        });
                        multiRow.classList.remove('hidden');
                    } else {
                        multiRow.classList.add('hidden');
                    }
                }

                // Total Estimate (labelled partial when some AI rows had no quantity or price)
                const estimateIsPartial = completedImages.some(img => Number(img.pricingSummary?.unpriced_item_count || 0) > 0);
                if (totalEst > 0) {
                    document.getElementById('summary-price').textContent = '₱' + Math.round(totalEst).toLocaleString();
                    document.getElementById('summary-price-label').textContent = estimateIsPartial ? 'Partial AI Estimate' : 'AI Initial Estimate';
                    const reviewEst = document.getElementById('review-estimate-val');
                    if (reviewEst) reviewEst.textContent = '₱' + Math.round(totalEst).toLocaleString();
                } else {
                    const singleEst = currentAnalysisPricing?.estimated_grand_total_php || currentAnalysisPricing?.raw_materials_total_php || 0;
                    if (singleEst > 0) {
                        document.getElementById('summary-price').textContent = '₱' + Number(singleEst).toLocaleString();
                        document.getElementById('summary-price-label').textContent = Number(currentAnalysisPricing?.unpriced_item_count || 0) > 0 ? 'Partial AI Estimate' : 'AI Initial Estimate';
                        const reviewEst = document.getElementById('review-estimate-val');
                        if (reviewEst) reviewEst.textContent = '₱' + Number(singleEst).toLocaleString();
                    } else {
                        document.getElementById('summary-price').textContent = 'Calculated Upon Review';
                        document.getElementById('summary-price-label').textContent = 'Price / Estimate';
                        const reviewEst = document.getElementById('review-estimate-val');
                        if (reviewEst) reviewEst.textContent = 'Calculated Upon Review';
                    }
                }

                const imgCountText = document.getElementById('review-images-count-text');
                if (imgCountText) imgCountText.textContent = `${imgCount > 0 ? imgCount : 1} image${imgCount > 1 ? 's' : ''}`;
                const matsCountText = document.getElementById('review-materials-count-text');
                if (matsCountText) matsCountText.textContent = `${totalMaterialsCount > 0 ? totalMaterialsCount : currentAnalysisMaterials.length} items`;

                // Materials per area: the same grouping as the analysis step, all analyzed photos together.
                window.RafloraAreaBreakdown?.render('review-area-breakdown', combinedAreaBreakdown(completedImages));

                // Render AI materials in Review step
                if (reviewAiSection && reviewMaterialsList) {
                    reviewMaterialsList.innerHTML = '';
                    if (completedImages.length > 0) {
                        completedImages.forEach((img, imgIdx) => {
                            const mats = img.suggestedMaterials || [];
                            mats.forEach((mat) => {
                                const name = mat.item_name || 'Floral Material';
                                const qty = mat.quantity || mat.estimated_quantity || 1;
                                const unit = mat.unit_type || 'pcs';
                                const isDetected = Boolean(mat.is_detected && !mat.is_recommendation);
                                const itemDiv = document.createElement('div');
                                itemDiv.className = 'p-2 rounded-lg border border-slate-100 bg-slate-50 text-[11px] flex items-center justify-between';
                                itemDiv.innerHTML = `
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-slate-400 font-semibold text-[10px]">[Img ${imgIdx+1}]</span>
                                        <span class="font-bold text-slate-800 truncate">${name}</span>
                                    </div>
                                    <span class="text-slate-500 font-medium shrink-0">${qty} ${unit}</span>
                                `;
                                reviewMaterialsList.appendChild(itemDiv);
                            });
                        });
                        reviewAiSection.classList.remove('hidden');
                    } else if (currentAnalysisMaterials && currentAnalysisMaterials.length > 0) {
                        currentAnalysisMaterials.forEach((mat) => {
                            const name = mat.item_name || 'Floral Material';
                            const qty = mat.quantity || mat.estimated_quantity || 1;
                            const unit = mat.unit_type || 'pcs';
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'p-2 rounded-lg border border-slate-100 bg-slate-50 text-[11px] flex items-center justify-between';
                            itemDiv.innerHTML = `
                                <span class="font-bold text-slate-800 truncate">${name}</span>
                                <span class="text-slate-500 font-medium">${qty} ${unit}</span>
                            `;
                            reviewMaterialsList.appendChild(itemDiv);
                        });
                        reviewAiSection.classList.remove('hidden');
                    } else {
                        reviewAiSection.classList.add('hidden');
                    }
                }
            } else {
                document.getElementById('review-method-title').textContent = 'Pre-Set Curated Package';
                document.getElementById('review-media-title').textContent = 'Selected Package';
                if (reviewAiSection) reviewAiSection.classList.add('hidden');
                
                const selectedPkgInput = document.querySelector('input[name="package_id"]:checked');
                if (selectedPkgInput) {
                    const card = selectedPkgInput.closest('.guest-package-card');
                    if (card) {
                        try {
                            const pkgData = JSON.parse(card.dataset.package);
                            document.getElementById('review-media-subtitle').textContent = pkgData.title || 'Curated Package';
                            const formattedPrice = '₱' + parseInt(pkgData.price || 0).toLocaleString();
                            document.getElementById('review-media-status').textContent = formattedPrice + ' (Catalog Price)';
                            document.getElementById('summary-price').textContent = formattedPrice;
                            document.getElementById('summary-price-label').textContent = 'Catalog Price';
                            
                            if (reviewDesc) {
                                reviewDesc.textContent = pkgData.description || '';
                            }
                            
                            if (pkgData.image_url) {
                                if (reviewImg) {
                                    reviewImg.src = pkgData.image_url;
                                    reviewImg.classList.remove('hidden');
                                }
                                if (reviewPlaceholder) reviewPlaceholder.classList.add('hidden');
                            } else {
                                if (reviewImg) reviewImg.classList.add('hidden');
                                if (reviewPlaceholder) reviewPlaceholder.classList.remove('hidden');
                            }

                            // Render inclusions
                            if (inclusionsWrapper && inclusionsList) {
                                if (pkgData.included_items && Array.isArray(pkgData.included_items) && pkgData.included_items.length > 0) {
                                    inclusionsList.innerHTML = '';
                                    pkgData.included_items.forEach(item => {
                                        const li = document.createElement('li');
                                        li.className = 'flex items-start gap-1.5';
                                        li.innerHTML = `<svg class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg><span>${item}</span>`;
                                        inclusionsList.appendChild(li);
                                    });
                                    inclusionsWrapper.classList.remove('hidden');
                                } else {
                                    inclusionsWrapper.classList.add('hidden');
                                }
                            }
                        } catch(e) {}
                    }
                }
                document.getElementById('summary-method').textContent = 'Curated Package';
            }
            
            // Event Details (from Step 1)
            const type = document.getElementById('event_type').value;
            const typeText = type ? (type === 'other' ? document.getElementById('other_event_type').value || 'Other' : type.charAt(0).toUpperCase() + type.slice(1)) : '-';
            const dateStr = document.getElementById('event_date').value;
            const timeStr = document.getElementById('event_time').value;
            const endTimeStr = document.getElementById('end_time')?.value;
            
            let timeSpan = formatTimeSpan(timeStr);
            if (endTimeStr && timeSpan !== '-') {
                timeSpan += ' - ' + formatTimeSpan(endTimeStr);
            }
            
            const city = document.getElementById('venue_city')?.value.trim() || '';
            const specific = document.getElementById('venue_specific')?.value.trim() || '';
            // Fix direct bug: use special_requests (not special_instructions)
            const notes = document.getElementById('special_requests')?.value.trim() || '';
            
            document.getElementById('review-event-type').textContent = typeText;
            document.getElementById('review-event-date').textContent = formatDateTime(dateStr, null);
            document.getElementById('review-event-time').textContent = timeSpan;
            document.getElementById('review-venue-city').textContent = city || '-';
            document.getElementById('review-venue-specific').textContent = specific || '-';
            const venuePinned = !!document.querySelector('#venue-finder [data-finder-lat]')?.value;
            document.getElementById('review-venue-pin')?.classList.toggle('hidden', !venuePinned);
            const venueSettingInput = document.querySelector('input[name="venue_setting"]:checked');
            document.getElementById('review-venue-setting').textContent = venueSettingInput?.value
                ? venueSettingInput.closest('label').textContent.trim()
                : 'Not specified';
            document.getElementById('review-notes').textContent = notes || 'No additional notes provided.';
            
            // Contact Information (from Step 3)
            document.getElementById('review-contact-name').textContent = document.getElementById('guest_name')?.value.trim() || '-';
            document.getElementById('review-contact-email').textContent = document.getElementById('guest_email')?.value.trim() || '-';
            document.getElementById('review-contact-phone').textContent = document.getElementById('guest_phone')?.value.trim() || '-';
            document.getElementById('review-contact-address').textContent = document.getElementById('guest_address')?.value.trim() || '-';
            
            document.getElementById('summary-type').textContent = typeText;
            document.getElementById('summary-date').textContent = formatDateTime(dateStr, null);
        }

        function setFieldError(input, errorId, message, fieldLabel, errorsArray) {
            input.classList.remove('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
            input.classList.add('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-2', 'focus:ring-rose-500/20');
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', errorId);

            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                const msgSpan = errorEl.querySelector('.error-msg');
                if (msgSpan) msgSpan.textContent = message;
                errorEl.classList.remove('hidden');
            }

            errorsArray.push({ input, message, label: fieldLabel });
        }

        function clearField(input, errorId) {
            input.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
            input.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
            input.setAttribute('aria-invalid', 'false');
            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                errorEl.classList.add('hidden');
            }
        }

        function validateStep(step) {
            // STEP 1: EVENT DETAILS
            if (step === 1) {
                let isValid = true;
                const errors = [];
                let firstInvalidEl = null;

                // 1. Event Type
                const eventTypeSelect = document.getElementById('event_type');
                if (eventTypeSelect) {
                    const val = eventTypeSelect.value;
                    if (!val) {
                        isValid = false;
                        setFieldError(eventTypeSelect, 'event_type_error', 'Please select an event type.', 'Event Type', errors);
                        if (!firstInvalidEl) firstInvalidEl = eventTypeSelect;
                    } else {
                        clearField(eventTypeSelect, 'event_type_error');
                    }
                }

                // 2. Other Event Type (if other)
                const otherEventTypeInput = document.getElementById('other_event_type');
                if (eventTypeSelect && eventTypeSelect.value === 'other' && otherEventTypeInput) {
                    const val = otherEventTypeInput.value.trim();
                    if (!val) {
                        isValid = false;
                        setFieldError(otherEventTypeInput, 'other_event_type_error', 'Please specify the event type.', 'Specified Event Type', errors);
                        if (!firstInvalidEl) firstInvalidEl = otherEventTypeInput;
                    } else {
                        clearField(otherEventTypeInput, 'other_event_type_error');
                    }
                } else if (otherEventTypeInput) {
                    clearField(otherEventTypeInput, 'other_event_type_error');
                }

                // 3. Event Date
                const dateInput = document.getElementById('event_date');
                if (dateInput) {
                    const val = dateInput.value;
                    if (!val) {
                        isValid = false;
                        setFieldError(dateInput, 'event_date_error', 'Event date is required.', 'Event Date', errors);
                        if (!firstInvalidEl) firstInvalidEl = dateInput;
                    } else {
                        const selectedDate = new Date(val + 'T00:00:00');
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (selectedDate < today) {
                            isValid = false;
                            setFieldError(dateInput, 'event_date_error', 'The event date cannot be in the past.', 'Event Date', errors);
                            if (!firstInvalidEl) firstInvalidEl = dateInput;
                        } else {
                            clearField(dateInput, 'event_date_error');
                        }
                    }
                }

                // 4. Start Time
                const timeInput = document.getElementById('event_time');
                if (timeInput) {
                    const val = timeInput.value;
                    if (!val) {
                        isValid = false;
                        setFieldError(timeInput, 'event_time_error', 'Start time is required.', 'Start Time', errors);
                        if (!firstInvalidEl) firstInvalidEl = timeInput;
                    } else {
                        clearField(timeInput, 'event_time_error');
                    }
                }

                // 5. End Time
                const endTimeInput = document.getElementById('end_time');
                if (endTimeInput) {
                    const val = endTimeInput.value;
                    if (!val) {
                        isValid = false;
                        setFieldError(endTimeInput, 'end_time_error', 'End time is required.', 'End Time', errors);
                        if (!firstInvalidEl) firstInvalidEl = endTimeInput;
                    } else {
                        clearField(endTimeInput, 'end_time_error');
                    }
                }

                // 6. Venue City
                const cityInput = document.getElementById('venue_city');
                if (cityInput) {
                    const val = cityInput.value.trim();
                    if (!val) {
                        isValid = false;
                        setFieldError(cityInput, 'venue_city_error', 'Venue city or municipality is required.', 'Venue City / Municipality', errors);
                        if (!firstInvalidEl) firstInvalidEl = cityInput;
                    } else {
                        clearField(cityInput, 'venue_city_error');
                    }
                }

                // 7. Venue Specific
                const specificInput = document.getElementById('venue_specific');
                if (specificInput) {
                    const val = specificInput.value.trim();
                    if (!val) {
                        isValid = false;
                        setFieldError(specificInput, 'venue_specific_error', 'Specific venue name or street address is required.', 'Specific Venue / Address', errors);
                        if (!firstInvalidEl) firstInvalidEl = specificInput;
                    } else {
                        clearField(specificInput, 'venue_specific_error');
                    }
                }

                updateVenueField();

                if (!isValid) {
                    showValidationModal(errors, firstInvalidEl);
                }

                return isValid;
            }

            // STEP 2: BOOKING METHOD
            if (step === 2) {
                const bookingType = document.querySelector('input[name="booking_type"]:checked')?.value || 'custom_ai';
                const aiErrorEl = document.getElementById('step1-ai-error');
                const presetErrorEl = document.getElementById('step1-preset-error');
                const dropzoneEl = document.getElementById('dropzone_container');

                if (bookingType === 'custom_ai') {
                    if (presetErrorEl) presetErrorEl.classList.add('hidden');
                    const completedCount = uploadedImages.filter(img => img.status === 'completed').length;
                    const legacyToken = document.getElementById('guest_analysis_token')?.value.trim();
                    if (completedCount === 0 && !legacyToken) {
                        if (aiErrorEl) aiErrorEl.classList.remove('hidden');
                        if (dropzoneEl) {
                            dropzoneEl.classList.remove('border-emerald-400', 'bg-emerald-50/10');
                            dropzoneEl.classList.add('border-rose-400', 'bg-rose-50/20');
                        }
                        showValidationModal([{ label: 'Inspiration Images', message: 'Please upload at least one inspiration photo and wait for floral analysis to complete before proceeding.' }], dropzoneEl);
                        return false;
                    } else {
                        if (aiErrorEl) aiErrorEl.classList.add('hidden');
                        if (dropzoneEl) {
                            dropzoneEl.classList.remove('border-rose-400', 'bg-rose-50/20');
                            dropzoneEl.classList.add('border-emerald-400', 'bg-emerald-50/10');
                        }
                    }
                } else if (bookingType === 'preset') {
                    if (aiErrorEl) aiErrorEl.classList.add('hidden');
                    const hasPackage = Boolean(document.querySelector('input[name="package_id"]:checked'));
                    if (!hasPackage) {
                        if (presetErrorEl) presetErrorEl.classList.remove('hidden');
                        const firstPkgCard = document.querySelector('.guest-package-card');
                        showValidationModal([{ label: 'Curated Package', message: 'Please select a curated package before continuing.' }], firstPkgCard);
                        return false;
                    } else {
                        if (presetErrorEl) presetErrorEl.classList.add('hidden');
                    }
                }
                return true;
            }

            // STEP 3: CONTACT INFORMATION
            if (step === 3) {
                let isValid = true;
                const errors = [];
                let firstInvalidEl = null;

                // 1. Full Name
                const nameInput = document.getElementById('guest_name');
                if (nameInput) {
                    const val = nameInput.value.trim();
                    if (!val) {
                        isValid = false;
                        setFieldError(nameInput, 'guest_name_error', 'Full name is required.', 'Full Name', errors);
                        if (!firstInvalidEl) firstInvalidEl = nameInput;
                    } else {
                        clearField(nameInput, 'guest_name_error');
                    }
                }

                // 2. Email Address
                const emailInput = document.getElementById('guest_email');
                if (emailInput) {
                    const val = emailInput.value.trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!val) {
                        isValid = false;
                        setFieldError(emailInput, 'guest_email_error', 'Email address is required.', 'Email Address', errors);
                        if (!firstInvalidEl) firstInvalidEl = emailInput;
                    } else if (!emailRegex.test(val)) {
                        isValid = false;
                        setFieldError(emailInput, 'guest_email_error', 'Please enter a valid email address (e.g. name@example.com).', 'Email Address', errors);
                        if (!firstInvalidEl) firstInvalidEl = emailInput;
                    } else {
                        clearField(emailInput, 'guest_email_error');
                    }
                }

                // 3. Mobile Phone Number
                const phoneInput = document.getElementById('guest_phone');
                if (phoneInput) {
                    const val = phoneInput.value.trim();
                    const phoneRegex = /^09\d{9}$/;
                    if (!val) {
                        isValid = false;
                        setFieldError(phoneInput, 'guest_phone_error', 'Mobile number is required.', 'Mobile Phone', errors);
                        if (!firstInvalidEl) firstInvalidEl = phoneInput;
                    } else if (!phoneRegex.test(val)) {
                        isValid = false;
                        setFieldError(phoneInput, 'guest_phone_error', 'Mobile number must contain exactly 11 digits starting with 09.', 'Mobile Phone', errors);
                        if (!firstInvalidEl) firstInvalidEl = phoneInput;
                    } else {
                        clearField(phoneInput, 'guest_phone_error');
                    }
                }

                // 4. Physical Address
                const addressInput = document.getElementById('guest_address');
                if (addressInput) {
                    const val = addressInput.value.trim();
                    if (!val) {
                        isValid = false;
                        setFieldError(addressInput, 'guest_address_error', 'Physical address is required.', 'Physical Address', errors);
                        if (!firstInvalidEl) firstInvalidEl = addressInput;
                    } else {
                        clearField(addressInput, 'guest_address_error');
                    }
                }

                if (!isValid) {
                    showValidationModal(errors, firstInvalidEl);
                }

                return isValid;
            }

            return true;
        }

        function showGuestStep(step, skipValidation = false) {
            if (!skipValidation && !isRestoringDraft && step > currentStep) {
                for (let s = currentStep; s < step; s++) {
                    if (!validateStep(s)) return;
                }
            }
            if (step === 4) {
                populateReview();
            }
            
            currentStep = step;
            
            // Hide all 4 steps
            [1, 2, 3, 4].forEach(s => {
                const stepEl = document.getElementById(`guest-step-${s}`);
                if (stepEl) {
                    stepEl.classList.add('hidden');
                }
                
                // Update nav indicator
                const nav = document.getElementById(`nav-step-${s}`);
                const icon = document.getElementById(`icon-step-${s}`);
                const title = document.getElementById(`title-step-${s}`);
                
                if (nav && icon) {
                    if (s < step) {
                        // Completed step
                        nav.classList.remove('opacity-60');
                        nav.setAttribute('aria-current', 'false');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300 shadow-md';
                        icon.innerHTML = '<svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-emerald-800 transition-colors';
                    } else if (s === step) {
                        // Current step
                        nav.classList.remove('opacity-60');
                        nav.setAttribute('aria-current', 'step');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300 shadow-md ring-4 ring-emerald-100';
                        icon.innerHTML = s;
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-[#0B1E43] transition-colors';
                    } else {
                        // Future step
                        nav.classList.add('opacity-60');
                        nav.removeAttribute('aria-current');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300';
                        icon.innerHTML = s;
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-slate-500 transition-colors';
                    }
                }
            });
            
            // Show current step container
            const activeStepEl = document.getElementById(`guest-step-${step}`);
            if (activeStepEl) activeStepEl.classList.remove('hidden');

            // Update step description
            const stepDescriptions = {
                1: 'Provide basic information, schedule, and venue for your event.',
                2: 'Select how you want to design your event florals: Smart AI Custom Design or Curated Package.',
                3: 'Provide your contact details so our team can coordinate with you.',
                4: 'Review your event specifications and submit your booking request for Raflora review.'
            };
            const descEl = document.getElementById('step-description');
            if (descEl && stepDescriptions[step]) {
                descEl.textContent = stepDescriptions[step];
            }
            
            // Progress lines
            const line1 = document.getElementById('progress-line-1');
            const line2 = document.getElementById('progress-line-2');
            const line3 = document.getElementById('progress-line-3');
            
            if (line1 && line2 && line3) {
                if (step === 1) {
                    line1.style.width = '0%';
                    line2.style.width = '0%';
                    line3.style.width = '0%';
                } else if (step === 2) {
                    line1.style.width = '100%';
                    line2.style.width = '0%';
                    line3.style.width = '0%';
                } else if (step === 3) {
                    line1.style.width = '100%';
                    line2.style.width = '100%';
                    line3.style.width = '0%';
                } else if (step === 4) {
                    line1.style.width = '100%';
                    line2.style.width = '100%';
                    line3.style.width = '100%';
                }
            }
            
            // Navigation Buttons
            const btnBack = document.getElementById('btn-back');
            const btnNext = document.getElementById('btn-next');
            const btnSubmit = document.getElementById('btn-submit');
            const pagination = document.getElementById('package-pagination');
            
            const isPreset = document.querySelector('input[name="booking_type"][value="preset"]')?.checked;
            
            if (step === 1) {
                btnBack.classList.add('hidden');
                btnNext.classList.remove('hidden');
                btnSubmit.classList.add('hidden');
                if (pagination) pagination.classList.add('hidden');
            } else if (step === 2) {
                btnBack.classList.remove('hidden');
                btnNext.classList.remove('hidden');
                btnSubmit.classList.add('hidden');
                if (isPreset && pagination) pagination.classList.remove('hidden');
                else if (pagination) pagination.classList.add('hidden');
            } else if (step === 3) {
                btnBack.classList.remove('hidden');
                btnNext.classList.remove('hidden');
                btnSubmit.classList.add('hidden');
                if (pagination) pagination.classList.add('hidden');
            } else {
                btnBack.classList.remove('hidden');
                btnNext.classList.add('hidden');
                btnSubmit.classList.remove('hidden');
                if (pagination) pagination.classList.add('hidden');
            }
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
            saveGuestDraft();
        }

        window.navigateGuest = function(dir) {
            showGuestStep(currentStep + dir);
        }
        
        window.switchGuestMode = function(mode) {
            const cardAi = document.getElementById('guest-card-ai');
            const cardPreset = document.getElementById('guest-card-preset');
            const aiCheck = document.getElementById('ai-check');
            const presetCheck = document.getElementById('preset-check');
            
            const secAi = document.getElementById('guest-section-ai');
            const secPreset = document.getElementById('guest-section-preset');

            const aiErrorEl = document.getElementById('step1-ai-error');
            const presetErrorEl = document.getElementById('step1-preset-error');
            const dropzoneEl = document.getElementById('dropzone_container');
            
            if (mode === 'ai') {
                const radio = document.querySelector('input[name="booking_type"][value="custom_ai"]');
                if (radio) radio.checked = true;
                
                cardAi.classList.add('border-emerald-600', 'bg-emerald-50/20');
                cardAi.classList.remove('border-slate-200', 'bg-white');
                aiCheck.classList.remove('hidden');
                
                cardPreset.classList.remove('border-emerald-600', 'bg-emerald-50/20');
                cardPreset.classList.add('border-slate-200', 'bg-white');
                presetCheck.classList.add('hidden');
                
                secAi.classList.remove('hidden');
                secPreset.classList.add('hidden');
                
                if (presetErrorEl) presetErrorEl.classList.add('hidden');

                const pagination = document.getElementById('package-pagination');
                if (pagination) pagination.classList.add('hidden');

                // Retain image valid status if token exists
                const existingToken = document.getElementById('guest_analysis_token')?.value.trim();
                isImageValid = Boolean(existingToken);
            } else {
                const radio = document.querySelector('input[name="booking_type"][value="preset"]');
                if (radio) radio.checked = true;
                
                cardPreset.classList.add('border-emerald-600', 'bg-emerald-50/20');
                cardPreset.classList.remove('border-slate-200', 'bg-white');
                presetCheck.classList.remove('hidden');
                
                cardAi.classList.remove('border-emerald-600', 'bg-emerald-50/20');
                cardAi.classList.add('border-slate-200', 'bg-white');
                aiCheck.classList.add('hidden');
                
                secPreset.classList.remove('hidden');
                secAi.classList.add('hidden');

                if (aiErrorEl) aiErrorEl.classList.add('hidden');
                if (dropzoneEl) {
                    dropzoneEl.classList.remove('border-rose-400', 'bg-rose-50/20');
                    dropzoneEl.classList.add('border-emerald-400', 'bg-emerald-50/10');
                }
                
                const pagination = document.getElementById('package-pagination');
                if (pagination && currentStep === 2) pagination.classList.remove('hidden');
                
                // For preset mode, image analysis is not required
                isImageValid = true;
            }
            saveGuestDraft();
        }

        function scrollToUploadedGrid() {
            const grid = document.getElementById('uploaded-images-section');
            if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        window.navigateViewingImage = function(delta) {
            const completed = uploadedImages.filter(img => img.status === 'completed');
            if (completed.length === 0) return;
            
            const currentImg = uploadedImages[viewingImageIndex];
            const currentCompletedIdx = completed.indexOf(currentImg);
            let nextCompletedIdx = (currentCompletedIdx !== -1 ? currentCompletedIdx : 0) + delta;
            
            if (nextCompletedIdx < 0) nextCompletedIdx = 0;
            if (nextCompletedIdx >= completed.length) nextCompletedIdx = completed.length - 1;
            
            const targetImg = completed[nextCompletedIdx];
            if (targetImg) {
                viewingImageIndex = uploadedImages.indexOf(targetImg);
                currentMaterialIndex = 0;
                renderActiveImageAnalysis();
                renderUploadedGrid();
            }
        };

        window.selectViewingImage = function(index) {
            if (index >= 0 && index < uploadedImages.length) {
                viewingImageIndex = index;
                currentMaterialIndex = 0;
                renderActiveImageAnalysis();
                renderUploadedGrid();
                const previewCard = document.getElementById('ai-material-preview-card');
                if (previewCard && !previewCard.classList.contains('hidden')) {
                    previewCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        };

        window.switchImageViewMode = function(mode) {
            imageViewMode = mode;
            const tabOrig = document.getElementById('tab-img-original');
            const tabAnnot = document.getElementById('tab-img-annotated');
            const markersCont = document.getElementById('viewer-markers-container');
            const hint = document.getElementById('viewer-markers-hint');

            if (mode === 'annotated') {
                if (tabAnnot) {
                    tabAnnot.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition bg-emerald-600 text-white shadow-2xs';
                }
                if (tabOrig) {
                    tabOrig.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 hover:bg-slate-50';
                }
                if (markersCont) markersCont.classList.remove('hidden');
                if (hint) hint.classList.remove('hidden');
            } else {
                if (tabOrig) {
                    tabOrig.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition bg-emerald-600 text-white shadow-2xs';
                }
                if (tabAnnot) {
                    tabAnnot.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 hover:bg-slate-50';
                }
                if (markersCont) markersCont.classList.add('hidden');
                if (hint) hint.classList.add('hidden');
            }
        };

        // Area colors shared with the booking pages (App\Support\DecorAreaBreakdown).
        const annotationColors = @json(\App\Support\DecorAreaBreakdown::colors());

        // Where the AI found a material in the photo, as shares of the photo (x/y top-left plus
        // width/height): one box per cluster, already cleaned by the server. Only detected rows
        // qualify, so suggested items never get a box. Analyses saved before boxes were requested
        // may only have a point.
        function annotationBoxes(mat) {
            if (!mat || !mat.is_detected || mat.is_recommendation) return [];
            const boxes = (Array.isArray(mat.boxes) ? mat.boxes : []).map((entry) => {
                const box = Array.isArray(entry?.box_2d) ? entry.box_2d.map(Number) : null;
                if (!box || box.length !== 4 || box.some((value) => !Number.isFinite(value))) return null;
                const [ymin, xmin, ymax, xmax] = box;
                if (ymin < 0 || xmin < 0 || ymax > 1000 || xmax > 1000 || ymax <= ymin || xmax <= xmin) return null;
                return { x: xmin / 1000, y: ymin / 1000, width: (xmax - xmin) / 1000, height: (ymax - ymin) / 1000, possible: Boolean(entry.possible) };
            }).filter(Boolean);
            if (boxes.length > 0 || (Array.isArray(mat.boxes) && mat.boxes.length > 0)) return boxes;

            const loc = mat.visual_location;
            const x = Number(loc?.x);
            const y = Number(loc?.y);
            if (!loc || !Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 1 || y < 0 || y > 1) return [];
            const width = Number(loc.width) > 0 ? Math.min(Number(loc.width), 1 - x) : 0;
            const height = Number(loc.height) > 0 ? Math.min(Number(loc.height), 1 - y) : 0;
            return [{ x, y, width, height, possible: false, point: !(width > 0 && height > 0) }];
        }

        function annotationColor(mat) {
            return annotationColors[mat?.decor_area] || annotationColors[mat?.area] || annotationColors.default;
        }

        // Lays the box layer exactly over the photo as drawn (object-contain leaves bars at the sides
        // or top), so a box at 50% sits on the middle of the photo rather than of the frame.
        function fitViewerMarkers() {
            const img = document.getElementById('viewer-main-img');
            const container = document.getElementById('viewer-markers-container');
            if (!img || !container || !img.naturalWidth || !img.naturalHeight || !img.clientWidth) return;
            const scale = Math.min(img.clientWidth / img.naturalWidth, img.clientHeight / img.naturalHeight);
            const width = img.naturalWidth * scale;
            const height = img.naturalHeight * scale;
            container.style.left = `${img.offsetLeft + (img.clientWidth - width) / 2}px`;
            container.style.top = `${img.offsetTop + (img.clientHeight - height) / 2}px`;
            container.style.width = `${width}px`;
            container.style.height = `${height}px`;
        }

        (function watchViewerImage() {
            const img = document.getElementById('viewer-main-img');
            if (!img) return;
            img.addEventListener('load', fitViewerMarkers);
            if (typeof ResizeObserver !== 'undefined') {
                new ResizeObserver(fitViewerMarkers).observe(img);
            } else {
                window.addEventListener('resize', fitViewerMarkers);
            }
        })();

        // Draws every located material's boxes in its area color, numbered like the material list
        // ("Material N of M"). Clicking a box opens that material; the selected material's boxes stay
        // bright and the others are dimmed. Dashed boxes are possible matches for Raflora to confirm.
        function renderAnnotationMarkers(imgItem) {
            const container = document.getElementById('viewer-markers-container');
            if (!container) return;
            container.innerHTML = '';

            const materials = imgItem.suggestedMaterials || [];
            const hintText = document.getElementById('viewer-markers-hint-text');
            let locatedCount = 0;
            let possibleCount = 0;

            materials.forEach((mat, idx) => {
                const boxes = annotationBoxes(mat);
                if (boxes.length === 0) return;
                locatedCount++;
                const color = annotationColor(mat);
                const selected = idx === currentMaterialIndex;

                boxes.forEach((region) => {
                    if (region.possible) possibleCount++;
                    const box = document.createElement('button');
                    box.type = 'button';
                    box.dataset.annotationBox = '';
                    box.dataset.lineId = mat.line_id || '';
                    const label = `${idx + 1} · ${mat.item_name || 'Material'}${region.possible ? ' · possible match' : ''}`;
                    box.title = label;
                    box.setAttribute('aria-label', `Select material ${label}`);
                    if (region.point) {
                        box.className = 'absolute -translate-x-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white text-[10px] font-bold text-white shadow focus:outline-none';
                        box.style.left = `${region.x * 100}%`;
                        box.style.top = `${region.y * 100}%`;
                        box.style.backgroundColor = color;
                        box.textContent = String(idx + 1);
                    } else {
                        box.className = 'absolute rounded-[3px] border-2 bg-transparent p-0 transition-opacity focus:outline-none';
                        box.style.left = `${region.x * 100}%`;
                        box.style.top = `${region.y * 100}%`;
                        box.style.width = `${region.width * 100}%`;
                        box.style.height = `${region.height * 100}%`;
                        box.style.borderColor = color;
                        box.style.borderStyle = region.possible ? 'dashed' : 'solid';
                        const tag = document.createElement('span');
                        tag.className = 'pointer-events-none absolute left-0 top-0 rounded-br-[3px] px-1 text-[10px] font-bold leading-4 text-white';
                        tag.style.backgroundColor = color;
                        tag.textContent = `${idx + 1}${region.possible ? '?' : ''}`;
                        box.appendChild(tag);
                    }
                    box.style.zIndex = selected ? '20' : '10';
                    box.style.opacity = selected || currentMaterialIndex < 0 ? '1' : '0.45';
                    if (selected) box.style.boxShadow = '0 0 0 2px rgba(255,255,255,0.9)';
                    box.onclick = (e) => {
                        e.stopPropagation();
                        currentMaterialIndex = idx;
                        renderCurrentMaterialCard(imgItem);
                        renderMaterialQuickList(imgItem);
                        renderAnnotationMarkers(imgItem);
                    };
                    container.appendChild(box);
                });
            });

            if (hintText) {
                hintText.textContent = locatedCount === 0
                    ? 'The AI could not pinpoint any material in this photo, so no boxes are shown. The material list still applies.'
                    : `Boxes show where the AI found ${locatedCount} of ${materials.length} materials in this photo, colored by area.${possibleCount > 0 ? ' Dashed boxes are possible matches for Raflora to confirm.' : ''} Items without a box are suggestions or could not be pinpointed. Quantities come from your event details, not from the number of boxes.`;
            }
            fitViewerMarkers();
        }

        function renderCurrentMaterialCard(imgItem) {
            const materials = imgItem.suggestedMaterials || [];
            const total = materials.length;
            if (total === 0) return;

            if (currentMaterialIndex >= total) currentMaterialIndex = total - 1;
            if (currentMaterialIndex < 0) currentMaterialIndex = 0;

            const mat = materials[currentMaterialIndex];
            const name = mat.item_name || 'Floral Material';
            const category = (mat.category || 'flower').toUpperCase();
            // A missing AI quantity or price is shown as unconfirmed, never as 1 or ₱0.
            const qty = mat.quantity ?? mat.estimated_quantity ?? null;
            const unit = mat.unit_type || 'pcs';
            const unitCost = mat.unit_cost_php ?? mat.estimated_unit_cost_php ?? null;
            const isDetected = Boolean(mat.is_detected && !mat.is_recommendation);
            const conf = mat.confidence ? Math.round(Number(mat.confidence) * 100) : null;
            const area = mat.area ? mat.area.replace('_', ' ') : 'Arrangement component';
            const note = mat.note || '';
            const alt = mat.suggested_alternative || null;
            const altReason = mat.alternative_reason || null;
            const seasonalNote = mat.seasonal_notes || null;
            const refImageUrl = mat.reference_image_url || mat.image_url || null;

            // Counters
            document.getElementById('mat-current-idx').textContent = currentMaterialIndex + 1;
            document.getElementById('mat-total-count').textContent = total;
            document.getElementById('mat-name').textContent = name;
            document.getElementById('mat-category-badge').textContent = category;

            // Reference Image
            const refImg = document.getElementById('mat-ref-img');
            const refFallback = document.getElementById('mat-ref-fallback');
            if (refImg && refFallback) {
                if (refImageUrl) {
                    refImg.src = refImageUrl;
                    refImg.classList.remove('hidden');
                    refFallback.classList.add('hidden');
                } else {
                    refImg.classList.add('hidden');
                    refFallback.classList.remove('hidden');
                }
            }

            const sourceBadge = document.getElementById('mat-source-badge');
            if (sourceBadge) {
                sourceBadge.textContent = isDetected ? 'AI Detected' : 'Suggested';
                sourceBadge.className = isDetected 
                    ? 'text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800' 
                    : 'text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700';
            }

            const confBadge = document.getElementById('mat-confidence-badge');
            if (confBadge) {
                confBadge.textContent = conf ? `${conf}% confidence` : '';
            }

            document.getElementById('mat-qty').textContent = qty !== null ? qty : 'To be confirmed';
            document.getElementById('mat-unit').textContent = qty !== null ? unit : '';
            document.getElementById('mat-area').textContent = area.charAt(0).toUpperCase() + area.slice(1);
            document.getElementById('mat-unit-cost').textContent = unitCost !== null ? '₱' + Number(unitCost).toLocaleString() : 'To be priced by Raflora';
            document.getElementById('mat-note').textContent = note;

            // Seasonal Box
            const seasonalText = document.getElementById('mat-seasonal-text');
            if (seasonalText) {
                seasonalText.textContent = seasonalNote
                    ? `${seasonalNote} (Requires Raflora florist validation for event date)`
                    : 'No seasonal information was provided for this item. Raflora will confirm availability for your event date.';
            }

            // Alternative Box
            const altBox = document.getElementById('mat-alt-box');
            if (altBox) {
                if (alt) {
                    document.getElementById('mat-orig-name').textContent = name;
                    document.getElementById('mat-alt-name').textContent = alt;
                    document.getElementById('mat-alt-reason').textContent = altReason || '';
                    altBox.classList.remove('hidden');
                } else {
                    altBox.classList.add('hidden');
                }
            }

            // Prev/Next buttons
            const btnPrev = document.getElementById('mat-btn-prev');
            const btnNext = document.getElementById('mat-btn-next');
            if (btnPrev) btnPrev.disabled = (currentMaterialIndex === 0);
            if (btnNext) btnNext.disabled = (currentMaterialIndex === total - 1);
        }

        function renderMaterialQuickList(imgItem) {
            const listContainer = document.getElementById('mat-quick-list');
            const countEl = document.getElementById('mat-quick-count');
            if (!listContainer) return;

            const materials = imgItem.suggestedMaterials || [];
            if (countEl) countEl.textContent = materials.length;
            listContainer.innerHTML = '';

            materials.forEach((mat, idx) => {
                const isSelected = (idx === currentMaterialIndex);
                const isDetected = Boolean(mat.is_detected && !mat.is_recommendation);
                const itemBtn = document.createElement('button');
                itemBtn.type = 'button';
                itemBtn.className = `w-full text-left p-2 rounded-xl flex items-center justify-between gap-2 border transition ${isSelected ? 'border-emerald-500 bg-emerald-50/60 shadow-2xs' : 'border-slate-100 bg-white hover:bg-slate-50'}`;
                itemBtn.innerHTML = `
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-5 h-5 rounded-md flex items-center justify-center font-bold text-[10px] shrink-0 ${isSelected ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}">${idx + 1}</span>
                        <span class="text-xs font-semibold text-slate-800 truncate">${mat.item_name || 'Material ' + (idx + 1)}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded ${isDetected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${isDetected ? 'Detected' : 'Suggested'}</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                `;
                itemBtn.onclick = () => {
                    currentMaterialIndex = idx;
                    renderCurrentMaterialCard(imgItem);
                    renderMaterialQuickList(imgItem);
                    renderAnnotationMarkers(imgItem);
                };
                listContainer.appendChild(itemBtn);
            });
        }

        function renderActiveImageAnalysis() {
            const previewCard = document.getElementById('ai-material-preview-card');
            if (!previewCard) return;

            const completed = uploadedImages.filter(img => img.status === 'completed');
            if (completed.length === 0) {
                previewCard.classList.add('hidden');
                return;
            }

            if (viewingImageIndex < 0 || viewingImageIndex >= uploadedImages.length || uploadedImages[viewingImageIndex].status !== 'completed') {
                const firstCompleted = uploadedImages.find(img => img.status === 'completed');
                viewingImageIndex = uploadedImages.indexOf(firstCompleted);
            }

            const currentImg = uploadedImages[viewingImageIndex];
            if (!currentImg || currentImg.status !== 'completed') {
                previewCard.classList.add('hidden');
                return;
            }

            previewCard.classList.remove('hidden');

            // Top bar
            const completedIndex = completed.indexOf(currentImg);
            const titleEl = document.getElementById('viewer-img-title');
            if (titleEl) titleEl.textContent = viewingImageIndex + 1;
            const totalTitleEl = document.getElementById('viewer-img-total');
            if (totalTitleEl) totalTitleEl.textContent = uploadedImages.length;
            const curEl = document.getElementById('img-nav-current');
            if (curEl) curEl.textContent = viewingImageIndex + 1;
            const totEl = document.getElementById('img-nav-total');
            if (totEl) totEl.textContent = uploadedImages.length;

            const btnPrev = document.getElementById('btn-img-prev');
            const btnNext = document.getElementById('btn-img-next');
            if (btnPrev) btnPrev.disabled = (completedIndex === 0);
            if (btnNext) btnNext.disabled = (completedIndex === completed.length - 1);

            // Column 1: Image stage
            const mainImg = document.getElementById('viewer-main-img');
            if (mainImg) mainImg.src = currentImg.previewUrl;

            // Switch view mode tabs
            switchImageViewMode(imageViewMode);
            renderAnnotationMarkers(currentImg);

            // Summary card
            const assessment = currentImg.analysis?.overall_assessment 
                || currentImg.visualAnalysis?.composition_summary 
                || 'Floral inspiration analyzed by Gemini AI. Material breakdown and initial estimates prepared.';
            document.getElementById('viewer-summary-text').textContent = `“${assessment}”`;
            document.getElementById('viewer-badge-mats-count').textContent = currentImg.suggestedMaterials.length;
            
            const style = currentImg.visualAnalysis?.detected_arrangement?.style || currentImg.analysis?.event_style || 'Table Arrangement';
            document.getElementById('viewer-badge-style-text').textContent = style.charAt(0).toUpperCase() + style.slice(1);
            
            // Only show an overall confidence when the analysis actually reported one.
            const compositionConfidence = Number(currentImg.visualAnalysis?.composition_confidence);
            const confBadgeEl = document.getElementById('viewer-badge-conf');
            if (compositionConfidence > 0) {
                document.getElementById('viewer-badge-conf-text').textContent = `${Math.round(compositionConfidence * 100)}% Overall Confidence`;
                confBadgeEl?.classList.remove('hidden');
            } else {
                confBadgeEl?.classList.add('hidden');
            }

            // Column 2: Material card
            renderCurrentMaterialCard(currentImg);
            window.RafloraAreaBreakdown?.render('guest-area-breakdown', currentImg.analysis?.area_breakdown);

            // Column 3: Grand total & Quick list
            const estTotal = currentImg.pricingSummary?.estimated_grand_total_php || currentImg.pricingSummary?.raw_materials_total_php || 0;
            const totalEl = document.getElementById('mat-grand-total');
            if (totalEl) totalEl.textContent = Number(estTotal).toLocaleString();
            const incompleteEl = document.getElementById('mat-estimate-incomplete');
            if (incompleteEl) {
                const missing = Number(currentImg.pricingSummary?.unpriced_item_count || 0);
                incompleteEl.textContent = missing > 0
                    ? `Partial estimate: ${missing} item${missing > 1 ? 's' : ''} without an AI quantity or price ${missing > 1 ? 'are' : 'is'} not included. Raflora will price ${missing > 1 ? 'them' : 'it'} during review.`
                    : '';
                incompleteEl.classList.toggle('hidden', missing === 0);
            }

            renderMaterialQuickList(currentImg);
        }

        window.changeMaterialNavigator = function(delta) {
            const currentImg = uploadedImages[viewingImageIndex];
            if (!currentImg) return;
            currentMaterialIndex += delta;
            renderCurrentMaterialCard(currentImg);
            renderMaterialQuickList(currentImg);
            renderAnnotationMarkers(currentImg);
        };
        
        // Setup package cards selection logic
        document.querySelectorAll('.guest-package-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if(e.target.closest('.view-package-btn')) return;
                
                const presetErr = document.getElementById('step1-preset-error');
                if (presetErr) presetErr.classList.add('hidden');

                const radio = this.querySelector('input[type="radio"]');
                if(radio) {
                    radio.checked = true;
                    // Reset all
                    document.querySelectorAll('.guest-package-card').forEach(c => {
                        c.classList.remove('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                        const b = c.querySelector('.package-badge');
                        if(b) b.classList.add('hidden');
                    });
                    // Select this
                    this.classList.add('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                    const b = this.querySelector('.package-badge');
                    if(b) b.classList.remove('hidden');
                    
                    try {
                        const pkgData = JSON.parse(this.dataset.package);
                        if(pkgData.event_type) {
                            const eventSelect = document.getElementById('event_type');
                            if(eventSelect && !eventSelect.value) {
                                eventSelect.value = pkgData.event_type;
                                eventSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    } catch(e){}
                    saveGuestDraft();
                }
            });
        });
        
        // Event Type change listener
        const eventSelect = document.getElementById('event_type');
        const otherContainer = document.getElementById('otherEventTypeContainer');
        const otherInput = document.getElementById('other_event_type');
        // Guest and table counts stay visible for every event type: they anchor the AI's quantities.
        if(eventSelect) {
            eventSelect.addEventListener('change', function() {
                if(this.value === 'other') {
                    if (otherContainer) otherContainer.classList.remove('hidden');
                    if (otherInput) otherInput.setAttribute('required', 'required');
                } else {
                    if (otherContainer) otherContainer.classList.add('hidden');
                    if (otherInput) otherInput.removeAttribute('required');
                }
            });
        }
        
        function syncAnalysisHiddenInputs() {
            const completed = uploadedImages.filter(img => img.status === 'completed');
            const tokensInput = document.getElementById('guest_analysis_tokens');
            const tokenInput = document.getElementById('guest_analysis_token');
            const pathInput = document.getElementById('guest_analysis_temp_path');
            const dataInput = document.getElementById('guest_analysis_data');
            const nonceInput = document.getElementById('guest_analysis_nonce');

            if (completed.length > 0) {
                isImageValid = true;
                const tokens = completed.map(i => i.analysisToken).filter(Boolean);
                if (tokensInput) tokensInput.value = JSON.stringify(tokens);
                
                // Authoritative primary item for backward compatibility
                const primary = completed[0];
                if (tokenInput) tokenInput.value = primary.analysisToken || '';
                if (pathInput) pathInput.value = primary.tempPath || '';
                if (dataInput) dataInput.value = primary.analysisData || '';
                if (nonceInput) nonceInput.value = primary.nonce || '';
            } else {
                isImageValid = false;
                if (tokensInput) tokensInput.value = '';
                if (tokenInput) tokenInput.value = '';
                if (pathInput) pathInput.value = '';
                if (dataInput) dataInput.value = '';
                if (nonceInput) nonceInput.value = '';
            }
        }

        function renderUploadedGrid() {
            const section = document.getElementById('uploaded-images-section');
            const grid = document.getElementById('uploaded-images-grid');
            const badge = document.getElementById('uploaded-count-badge');
            const countText = document.getElementById('uploaded-count-text');
            const breakdownText = document.getElementById('uploaded-status-breakdown');
            if (!section || !grid) return;

            if (uploadedImages.length === 0) {
                section.classList.add('hidden');
                return;
            }

            section.classList.remove('hidden');
            const totalUploaded = uploadedImages.length;
            const completedCount = uploadedImages.filter(i => i.status === 'completed').length;
            const failedCount = uploadedImages.filter(i => i.status === 'failed').length;
            const analyzingCount = uploadedImages.filter(i => i.status === 'uploading' || i.status === 'pending').length;

            if (badge) badge.textContent = totalUploaded;
            if (countText) countText.innerHTML = `<span id="uploaded-count-badge">${totalUploaded}</span> of 5 images`;
            if (breakdownText) {
                const parts = [`${totalUploaded} uploaded`, `${completedCount} analyzed`];
                if (failedCount > 0) parts.push(`${failedCount} failed`);
                if (analyzingCount > 0) parts.push(`${analyzingCount} analyzing`);
                breakdownText.textContent = parts.join(' · ');
            }
            grid.innerHTML = '';

            uploadedImages.forEach((img, idx) => {
                const card = document.createElement('div');
                const isSelected = (idx === viewingImageIndex && img.status === 'completed');
                card.className = `relative rounded-2xl border-2 p-2.5 flex flex-col justify-between transition-all bg-white shadow-2xs ${isSelected ? 'border-emerald-600 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:border-slate-300'}`;

                let statusBadgeHtml = '';
                if (img.status === 'completed') {
                    const matsCount = (img.suggestedMaterials || []).length;
                    statusBadgeHtml = `
                        <div class="mt-1.5 flex items-center gap-1 text-[11px] text-emerald-700 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Analyzed</span>
                        </div>
                        <div class="text-[10px] text-slate-500">${matsCount} materials detected</div>
                    `;
                } else if (img.status === 'failed') {
                    if (img.isQuotaError) {
                        statusBadgeHtml = `
                            <div class="mt-1.5 flex items-center gap-1 text-[11px] text-amber-700 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>AI service busy</span>
                            </div>
                            <p class="text-[10px] text-amber-800 leading-tight mt-0.5">Your image was uploaded successfully, but Raflora's AI service is currently busy. Please try again shortly.</p>
                            <button type="button" onclick="event.stopPropagation(); retryUploadedImage('${img.id}')" class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded-lg border border-amber-300 transition">
                                <span>↻ Retry Analysis</span>
                            </button>
                        `;
                    } else if (img.errorType === 'connection') {
                        statusBadgeHtml = `
                            <div class="mt-1.5 flex items-center gap-1 text-[11px] text-amber-700 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>Connection problem</span>
                            </div>
                            <p class="text-[10px] text-slate-500 leading-tight mt-0.5">AI analysis could not be completed because of a connection problem. Please try again.</p>
                            <button type="button" onclick="event.stopPropagation(); retryUploadedImage('${img.id}')" class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded-lg border border-emerald-200 transition">
                                <span>↻ Retry</span>
                            </button>
                        `;
                    } else {
                        statusBadgeHtml = `
                            <div class="mt-1.5 flex items-center gap-1 text-[11px] text-rose-600 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>Analysis failed</span>
                            </div>
                            <p class="text-[10px] text-slate-500 leading-tight mt-0.5">${img.errorMessage || 'Unable to analyze this image'}</p>
                            <button type="button" onclick="event.stopPropagation(); retryUploadedImage('${img.id}')" class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded-lg border border-emerald-200 transition">
                                <span>↻ Retry</span>
                            </button>
                        `;
                    }
                } else {
                    statusBadgeHtml = `
                        <div class="mt-1.5 flex items-center gap-1 text-[11px] text-amber-600 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>Analyzing...</span>
                        </div>
                        <div class="text-[10px] text-slate-400">Est. 30–60 seconds</div>
                    `;
                }

                card.innerHTML = `
                    <div class="relative w-full aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                        <img src="${img.previewUrl}" alt="Inspiration Image ${idx + 1}" class="w-full h-full object-cover">
                        <button type="button" onclick="event.stopPropagation(); removeUploadedImage('${img.id}')" class="absolute top-1.5 right-1.5 z-20 w-6 h-6 rounded-full bg-slate-900/70 hover:bg-rose-600 text-white flex items-center justify-center font-bold text-xs shadow-xs transition" title="Remove Inspiration Image ${idx + 1}" aria-label="Remove Inspiration Image ${idx + 1}">
                            ✕
                        </button>
                    </div>
                    <div class="mt-2 cursor-pointer flex-1 flex flex-col justify-between" onclick="${img.status === 'completed' ? `selectViewingImage(${idx})` : ''}">
                        <div>
                            <div class="text-xs font-bold text-[#0B1E43]">Image ${idx + 1}</div>
                            ${statusBadgeHtml}
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });

            // "Add Another Image" button in grid if < 5
            if (uploadedImages.length < 5) {
                const addCard = document.createElement('button');
                addCard.type = 'button';
                addCard.onclick = () => document.getElementById('inspiration_image_input')?.click();
                addCard.className = 'border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-4 flex flex-col items-center justify-center text-center bg-slate-50/50 hover:bg-emerald-50/20 transition min-h-[140px]';
                addCard.innerHTML = `
                    <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mb-2 font-bold text-lg">+</div>
                    <span class="text-xs font-bold text-slate-700">Add Another Image</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Up to 5 total</span>
                `;
                grid.appendChild(addCard);
            }
        }

        window.removeUploadedImage = function(id) {
            const idx = uploadedImages.findIndex(i => i.id === id);
            if (idx === -1) return;
            
            uploadedImages.splice(idx, 1);
            if (viewingImageIndex >= uploadedImages.length) {
                viewingImageIndex = Math.max(0, uploadedImages.length - 1);
            }
            
            syncAnalysisHiddenInputs();
            renderUploadedGrid();
            renderActiveImageAnalysis();
            saveGuestDraft();
        };

        window.retryUploadedImage = function(id) {
            const img = uploadedImages.find(i => i.id === id);
            if (!img) return;
            if (img.status === 'uploading') return;
            
            img.status = 'pending';
            img.errorMessage = null;
            img.isQuotaError = false;
            renderUploadedGrid();
            saveGuestDraft();
            processUploadQueue();
        };

        // Design brief edited after analysis: re-run every analyzed photo that is still in memory.
        // Photos restored from a saved draft have no file left to send, so those must be re-uploaded.
        document.getElementById('design-brief')?.addEventListener('design-brief:reanalyze', function () {
            const brief = window.RafloraDesignBrief?.get('design-brief');
            const rerunnable = uploadedImages.filter(img => img.file && ['completed', 'failed'].includes(img.status));
            if (!rerunnable.length) {
                brief?.setStaleMessage('These photos were restored from your saved draft. Remove and re-upload them so the AI can use your updated brief.');
                return;
            }
            rerunnable.forEach(img => {
                img.status = 'pending';
                img.errorMessage = null;
                img.isQuotaError = false;
            });
            brief?.resetAnalyzed();
            syncAnalysisHiddenInputs();
            renderUploadedGrid();
            saveGuestDraft();
            processUploadQueue();
        });

        function setTruthfulProgressStep(stepNum, label) {
            [1, 2, 3, 4].forEach(s => {
                const stepEl = document.getElementById(`progress-step-${s}`);
                if (!stepEl) return;
                const icon = stepEl.querySelector('.step-icon');
                const text = stepEl.querySelector('.step-label');

                if (s < stepNum) {
                    if (icon) {
                        icon.className = 'step-icon w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px]';
                        icon.textContent = '✓';
                    }
                    if (text) text.className = 'step-label text-slate-700 font-medium';
                } else if (s === stepNum) {
                    if (icon) {
                        icon.className = 'step-icon w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-[10px] animate-pulse';
                        icon.textContent = s;
                    }
                    if (text) {
                        text.className = 'step-label text-emerald-700 font-bold';
                        if (label) text.textContent = label;
                    }
                } else {
                    if (icon) {
                        icon.className = 'step-icon w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold text-[10px]';
                        icon.textContent = '○';
                    }
                    if (text) text.className = 'step-label text-slate-400';
                }
            });
        }

        function analyzeSingleImage(imageItem) {
            const progressCard = document.getElementById('ai-progress-card');
            const progressTitle = document.getElementById('progress-card-title');
            const progressThumb = document.getElementById('progress-img-thumb');
            const btnNext = document.getElementById('btn-next');

            if (progressCard) progressCard.classList.remove('hidden');
            if (progressThumb) progressThumb.src = imageItem.previewUrl;
            
            const currentQueueNum = uploadedImages.indexOf(imageItem) + 1;
            if (progressTitle) {
                progressTitle.textContent = `Analyzing Image ${currentQueueNum} of ${uploadedImages.length}`;
            }

            if (btnNext) btnNext.disabled = true;
            isAiAnalyzing = true;

            // Step 1: Uploaded
            setTruthfulProgressStep(1);

            // Step 2: Validating image
            setTimeout(() => {
                if (imageItem.status !== 'uploading') return;
                setTruthfulProgressStep(2);
            }, 700);

            // Step 3: Identifying floral materials
            setTimeout(() => {
                if (imageItem.status !== 'uploading') return;
                setTruthfulProgressStep(3, 'Identifying floral materials...');
            }, 1800);

            const formData = new FormData();
            formData.append('inspiration_image', imageItem.file);
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('event_type', document.getElementById('event_type')?.value || '');
            formData.append('event_date', document.getElementById('event_date')?.value || '');
            formData.append('event_time', document.getElementById('event_time')?.value || '');
            formData.append('end_time', document.getElementById('end_time')?.value || '');
            formData.append('venue_city', document.getElementById('venue_city')?.value || '');
            formData.append('venue_specific', document.getElementById('venue_specific')?.value || '');
            formData.append('venue', document.getElementById('venue')?.value || '');
            formData.append('guest_count', document.getElementById('guest_count')?.value || '');
            formData.append('table_count', document.getElementById('table_count')?.value || '');
            formData.append('special_requests', document.getElementById('special_requests')?.value || '');
            formData.append('venue_setting', document.querySelector('input[name="venue_setting"]:checked')?.value || '');
            window.RafloraDesignBrief?.get('design-brief')?.appendTo(formData);

            fetch('{{ route("guest.bookings.analyze-temp-image") }}', {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setTruthfulProgressStep(4, 'Preparing results');
                    imageItem.status = 'completed';
                    imageItem.analysisToken = data.analysis_token || '';
                    imageItem.tempPath = data.analysis_temp_path || '';
                    imageItem.analysisData = data.analysis_data || '';
                    imageItem.nonce = data.analysis_nonce || '';
                    imageItem.analysis = data.analysis || {};
                    imageItem.suggestedMaterials = data.analysis?.suggested_materials || [];
                    imageItem.pricingSummary = data.analysis?.pricing_summary || {};
                    imageItem.visualAnalysis = data.analysis?.visual_analysis || {};
                    window.RafloraDesignBrief?.get('design-brief')?.markAnalyzed();

                    // Default viewing image to first completed
                    if (!uploadedImages[viewingImageIndex] || uploadedImages[viewingImageIndex].status !== 'completed') {
                        viewingImageIndex = uploadedImages.indexOf(imageItem);
                    }

                    syncAnalysisHiddenInputs();
                    renderUploadedGrid();
                    renderActiveImageAnalysis();
                    saveGuestDraft();
                } else {
                    imageItem.status = 'failed';
                    imageItem.isQuotaError = Boolean(data.is_quota_error || data.error_type === 'service' || data.error_type === 'rate_limit');
                    imageItem.errorType = data.error_type || (imageItem.isQuotaError ? 'service' : 'analysis');
                    imageItem.errorMessage = data.message || (imageItem.isQuotaError ? "Your image was uploaded successfully, but Raflora's AI service is currently busy. Please try again shortly." : 'Image analysis failed.');
                    syncAnalysisHiddenInputs();
                    renderUploadedGrid();
                    saveGuestDraft();
                }
            })
            .catch(err => {
                imageItem.status = 'failed';
                imageItem.isQuotaError = false;
                imageItem.errorType = 'connection';
                imageItem.errorMessage = 'AI analysis could not be completed because of a connection problem. Please try again.';
                syncAnalysisHiddenInputs();
                renderUploadedGrid();
                saveGuestDraft();
            })
            .finally(() => {
                isAiAnalyzing = false;
                if (btnNext) btnNext.disabled = false;
                // Continue queue for remaining pending images
                processUploadQueue();
            });
        }

        function processUploadQueue() {
            if (isAiAnalyzing) return;

            const nextPending = uploadedImages.find(i => i.status === 'pending');
            const progressCard = document.getElementById('ai-progress-card');

            if (nextPending) {
                nextPending.status = 'uploading';
                renderUploadedGrid();
                analyzeSingleImage(nextPending);
            } else {
                if (progressCard) progressCard.classList.add('hidden');
            }
        }

        function handleFilesSelected(files) {
            if (!files || files.length === 0) return;

            const aiErr = document.getElementById('step1-ai-error');
            if (aiErr) aiErr.classList.add('hidden');
            
            const dropzone = document.getElementById('dropzone_container');
            if (dropzone) {
                dropzone.classList.remove('border-rose-400', 'bg-rose-50/20');
                dropzone.classList.add('border-emerald-400', 'bg-emerald-50/10');
            }

            const currentCount = uploadedImages.length;
            const remainingSlots = 5 - currentCount;
            if (remainingSlots <= 0) {
                showValidationModal(['You have already added the maximum limit of 5 inspiration photos.']);
                return;
            }

            const filesToAdd = Array.from(files).slice(0, remainingSlots);
            if (files.length > remainingSlots) {
                showValidationModal([`You can only add up to 5 inspiration photos. Adding the first ${remainingSlots} photo(s).`]);
            }

            filesToAdd.forEach(file => {
                // Prevent duplicate upload of identical file already in list
                const isDuplicate = uploadedImages.some(i => i.file && i.file.name === file.name && i.file.size === file.size && i.file.lastModified === file.lastModified);
                if (isDuplicate) return;

                const item = {
                    id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8),
                    file: file,
                    filename: file.name,
                    previewUrl: URL.createObjectURL(file),
                    thumbnailUrl: null,
                    status: 'pending',
                    statusText: 'Waiting in queue...',
                    errorMessage: null,
                    isQuotaError: false,
                    errorType: null,
                    analysisToken: null,
                    tempPath: null,
                    nonce: null,
                    analysisData: null,
                    analysis: null,
                    suggestedMaterials: [],
                    pricingSummary: {},
                    visualAnalysis: null
                };
                uploadedImages.push(item);

                generateThumbnail(file, function(thumb) {
                    if (thumb) {
                        item.thumbnailUrl = thumb;
                        saveGuestDraft();
                    }
                });
            });

            renderUploadedGrid();
            saveGuestDraft();
            processUploadQueue();
        }

        // Init image upload dropzone
        const dropzone = document.getElementById('dropzone_container');
        const fileInput = document.getElementById('inspiration_image_input');
        
        if (dropzone && fileInput) {
            dropzone.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT' && !e.target.closest('button')) {
                    fileInput.click();
                }
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-emerald-600', 'bg-emerald-50/40');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-emerald-600', 'bg-emerald-50/40');
                });
            });
            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt?.files;
                if (files && files.length > 0) {
                    handleFilesSelected(files);
                }
            });
            
            fileInput.addEventListener('change', function(e) {
                const files = e.target.files;
                if (files && files.length > 0) {
                    handleFilesSelected(files);
                    fileInput.value = ''; // reset so same files can be re-selected if needed
                }
            });
        }

        let packageCurrentPage = 1;
        const packagesPerPage = 6;
        
        function renderPackagePagination() {
            const packageCards = document.querySelectorAll('.guest-package-card');
            const totalPages = Math.ceil(packageCards.length / packagesPerPage);
            
            // Show/hide cards based on page
            packageCards.forEach((card, index) => {
                const start = (packageCurrentPage - 1) * packagesPerPage;
                const end = start + packagesPerPage;
                if (index >= start && index < end) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Render pagination buttons
            const paginationContainer = document.getElementById('package-pagination');
            if (!paginationContainer) return;
            
            paginationContainer.innerHTML = '';
            if (totalPages <= 1) return;
            
            // Prev button
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>';
            prevBtn.className = `w-8 h-8 flex items-center justify-center rounded-lg border ${packageCurrentPage === 1 ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors'}`;
            prevBtn.onclick = () => {
                if (packageCurrentPage > 1) {
                    packageCurrentPage--;
                    renderPackagePagination();
                }
            };
            paginationContainer.appendChild(prevBtn);
            
            // Page buttons
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = i;
                btn.className = `w-8 h-8 flex items-center justify-center rounded-lg border text-sm font-semibold transition-colors ${packageCurrentPage === i ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm' : 'border-slate-300 text-slate-600 hover:bg-slate-50'}`;
                btn.onclick = () => {
                    packageCurrentPage = i;
                    renderPackagePagination();
                };
                paginationContainer.appendChild(btn);
            }
            
            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            nextBtn.className = `w-8 h-8 flex items-center justify-center rounded-lg border ${packageCurrentPage === totalPages ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors'}`;
            nextBtn.onclick = () => {
                if (packageCurrentPage < totalPages) {
                    packageCurrentPage++;
                    renderPackagePagination();
                }
            };
            paginationContainer.appendChild(nextBtn);
        }

        function updateVenueField() {
            const city = document.getElementById('venue_city').value.trim();
            const specific = document.getElementById('venue_specific').value.trim();
            document.getElementById('venue').value = specific ? specific + ', ' + city : city;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Real-time clearing of field errors & draft auto-save
            ['guest_name', 'guest_email', 'guest_phone', 'guest_address', 'other_event_type', 'event_date', 'event_time', 'end_time', 'guest_count', 'table_count', 'venue_city', 'venue_specific', 'special_requests'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', function() {
                        this.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
                        this.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                        this.setAttribute('aria-invalid', 'false');
                        const errEl = document.getElementById(id + '_error');
                        if (errEl) errEl.classList.add('hidden');
                        if (id === 'venue_city' || id === 'venue_specific') {
                            updateVenueField();
                        }
                        saveGuestDraft();
                    });
                    el.addEventListener('change', function() {
                        this.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
                        this.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                        this.setAttribute('aria-invalid', 'false');
                        const errEl = document.getElementById(id + '_error');
                        if (errEl) errEl.classList.add('hidden');
                        if (id === 'venue_city' || id === 'venue_specific') {
                            updateVenueField();
                        }
                        saveGuestDraft();
                    });
                }
            });

            // Location finder: a chosen place fills the venue fields (the 'input' events above
            // then clear their errors, rebuild the combined venue, and save the draft).
            const venueFinder = document.getElementById('venue-finder');
            if (venueFinder) {
                venueFinder.addEventListener('location-finder:select', function (e) {
                    const fills = { venue_city: e.detail.city, venue_specific: e.detail.specific };
                    Object.keys(fills).forEach(function (id) {
                        const el = document.getElementById(id);
                        if (el && fills[id]) {
                            el.value = fills[id];
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                    saveGuestDraft();
                });
                venueFinder.addEventListener('location-finder:clear', saveGuestDraft);
            }

            // Venue setting and design brief are saved with the draft as they change.
            document.querySelectorAll('input[name="venue_setting"]').forEach(el => el.addEventListener('change', saveGuestDraft));
            const designBriefEl = document.getElementById('design-brief');
            if (designBriefEl) {
                designBriefEl.addEventListener('input', saveGuestDraft);
                designBriefEl.addEventListener('change', saveGuestDraft);
            }

            const eventTypeEl = document.getElementById('event_type');
            if (eventTypeEl) {
                eventTypeEl.addEventListener('change', function() {
                    this.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
                    this.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                    this.setAttribute('aria-invalid', 'false');
                    const errEl = document.getElementById('event_type_error');
                    if (errEl) errEl.classList.add('hidden');
                    saveGuestDraft();
                });
            }

            @if($guestDisplayErrors->isNotEmpty())
                const serverValidationErrors = [
                    @foreach($guestDisplayErrors as $err)
                        "{{ addslashes($err) }}",
                    @endforeach
                ];
                showValidationModal(serverValidationErrors, document.querySelector('[aria-invalid="true"]') || document.getElementById('event_type'));
            @endif

            // Initialize pagination
            renderPackagePagination();

            let restored = false;
            @if($guestDisplayErrors->isEmpty())
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('reset') || urlParams.has('new')) {
                    clearGuestDraft();
                } else {
                    restored = restoreGuestDraft();
                }
            @endif

            if (!restored) {
                // Restore image analysis state if token(s) already exist in server session
                const existingToken = document.getElementById('guest_analysis_token')?.value.trim();
                const existingData = document.getElementById('guest_analysis_data')?.value.trim();
                const existingPath = document.getElementById('guest_analysis_temp_path')?.value.trim();
                const existingNonce = document.getElementById('guest_analysis_nonce')?.value.trim();

                if (existingToken && uploadedImages.length === 0) {
                    isImageValid = true;
                    let parsed = null;
                    if (existingData) {
                        try { parsed = JSON.parse(existingData); } catch(e){}
                    }
                    uploadedImages.push({
                        id: 'img_restored_' + Date.now(),
                        file: null,
                        filename: 'Restored Inspiration Photo',
                        previewUrl: '/guest/bookings/analysis-image/' + encodeURIComponent(existingToken),
                        thumbnailUrl: null,
                        status: 'completed',
                        statusText: 'Analyzed',
                        errorMessage: null,
                        analysisToken: existingToken,
                        tempPath: existingPath,
                        nonce: existingNonce,
                        analysisData: existingData,
                        analysis: parsed || {},
                        suggestedMaterials: parsed?.suggested_materials || [],
                        pricingSummary: parsed?.pricing_summary || {},
                        visualAnalysis: parsed?.visual_analysis || {}
                    });
                    renderUploadedGrid();
                    renderActiveImageAnalysis();
                }

                // Initialize display to specific step if server returned validation errors or default
                const initialGuestStep = {{ $initialStep }};
                showGuestStep(initialGuestStep);
                
                // Hydrate venue fields if validation failed
                const oldVenue = document.getElementById('venue').value;
                if (oldVenue) {
                    const lastComma = oldVenue.lastIndexOf(', ');
                    if (lastComma !== -1) {
                        if (!document.getElementById('venue_specific').value) {
                            document.getElementById('venue_specific').value = oldVenue.substring(0, lastComma);
                        }
                        if (!document.getElementById('venue_city').value) {
                            document.getElementById('venue_city').value = oldVenue.substring(lastComma + 2);
                        }
                    } else if (!document.getElementById('venue_city').value) {
                        document.getElementById('venue_city').value = oldVenue;
                    }
                }

                // Sync scale fields with current event_type value
                const eventSelect = document.getElementById('event_type');
                if (eventSelect && eventSelect.value) {
                    eventSelect.dispatchEvent(new Event('change'));
                }
                
                // If preset is requested via URL or old input
                const urlParams = new URLSearchParams(window.location.search);
                const oldBookingType = "{{ old('booking_type', request('package_id') ? 'preset' : 'custom_ai') }}";
                const hasOldPackage = {{ (old('package_id') || request('package_id')) ? 'true' : 'false' }};
                if (oldBookingType === 'preset' || hasOldPackage || urlParams.has('package_id')) {
                    switchGuestMode('preset');
                    const checkedRadio = document.querySelector('input[name="package_id"]:checked');
                    if (checkedRadio) {
                        const card = checkedRadio.closest('.guest-package-card');
                        if (card) {
                            card.classList.add('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                            card.querySelector('.package-badge')?.classList.remove('hidden');
                        }
                    }
                } else {
                    switchGuestMode('ai');
                }
            } else {
                if (currentStep === 4) {
                    populateReview();
                }
            }

            // Duplicate submission prevention & draft cleanup
            const guestForm = document.getElementById('guest-booking-form');
            const submitBtn = document.getElementById('btn-submit');
            if (guestForm) {
                guestForm.addEventListener('submit', function (e) {
                    if (guestForm.dataset.submitting === 'true') {
                        e.preventDefault();
                        return false;
                    }
                    clearGuestDraft();
                    guestForm.dataset.submitting = 'true';
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
                    guestForm.dataset.submitting = 'false';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        submitBtn.innerHTML = `
                            Submit Booking Request
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>`;
                    }
                });
            }
        });

    </script>
</x-app-layout>
