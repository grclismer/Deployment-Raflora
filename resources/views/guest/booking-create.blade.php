<x-app-layout title="Guest Booking">
    <x-navbar title="GUEST BOOKING" />
    <div class="py-1 sm:py-2 bg-slate-50">
        <div class="max-w-[1536px] mx-auto w-full px-3 sm:px-6 lg:px-8">
            <div class="bg-white p-3 sm:p-4 lg:p-4 rounded-2xl shadow-md border border-slate-100">
                <!-- Header -->
                <div class="mb-2 sm:mb-2.5 max-w-3xl">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-block bg-emerald-100 text-emerald-800 text-[11px] font-bold px-2 py-0.5 rounded-full w-max tracking-wide">GUEST BOOKING</span>
                        <h1 class="text-lg sm:text-xl font-extrabold text-[#0B1E43] tracking-tight font-serif" id="booking-page-title">Create Event Booking</h1>
                    </div>
                    <p class="text-xs text-slate-500 leading-snug" id="step-description">Tell us about your event. Provide the basic details, schedule, and venue so we can prepare the best floral arrangements for you.</p>
                </div>

                <!-- Progress Indicator (4 Steps) -->
                <div class="mb-2.5 sm:mb-3 flex items-center w-full" aria-label="Booking progress">
                    <!-- Step 1: Event Details -->
                    <div class="flex items-center cursor-pointer group shrink-0" id="nav-step-1" onclick="showGuestStep(1)">
                        <div id="icon-step-1" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 transition-all duration-300 shadow-xs">
                            1
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-1" class="text-xs sm:text-sm font-bold text-emerald-800 whitespace-nowrap transition-colors">Event Details</p>
                        </div>
                    </div>
                    
                    <!-- Line 1 -->
                    <div class="flex-1 min-w-[12px] sm:min-w-[20px] mx-1 sm:mx-2.5 h-1 bg-[#EBF0F9] relative overflow-hidden rounded-full shrink">
                        <div id="progress-line-1" class="absolute top-0 left-0 h-full w-[45%] bg-emerald-500 transition-all duration-700 ease-out"></div>
                    </div>
                    
                    <!-- Step 2: Design (Booking Method) -->
                    <div class="flex items-center cursor-pointer group shrink-0 opacity-60 hover:opacity-100 transition-all duration-300" id="nav-step-2" onclick="showGuestStep(2)">
                        <div id="icon-step-2" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300">
                            2
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-2" class="text-xs sm:text-sm font-bold text-slate-500 whitespace-nowrap transition-colors"><span class="hidden md:inline">Booking Method &amp; Design</span><span class="md:hidden">Design</span></p>
                        </div>
                    </div>

                    <!-- Line 2 -->
                    <div class="flex-1 min-w-[12px] sm:min-w-[20px] mx-1 sm:mx-2.5 h-1 bg-[#EBF0F9] relative overflow-hidden rounded-full shrink">
                        <div id="progress-line-2" class="absolute top-0 left-0 h-full w-0 bg-emerald-500 transition-all duration-700 ease-out"></div>
                    </div>

                    <!-- Step 3: Contact Info / Contact Information -->
                    <div class="flex items-center cursor-pointer group shrink-0 opacity-60 hover:opacity-100 transition-all duration-300" id="nav-step-3" onclick="showGuestStep(3)">
                        <div id="icon-step-3" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300">
                            3
                        </div>
                        <div class="ml-2 sm:ml-2.5 hidden sm:block">
                            <p id="title-step-3" class="text-xs sm:text-sm font-bold text-slate-500 whitespace-nowrap transition-colors"><span class="hidden md:inline">Contact Information</span><span class="md:hidden">Contact</span> <span class="sr-only">Contact Info</span></p>
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
                            <p id="title-step-4" class="text-xs sm:text-sm font-bold text-slate-500 whitespace-nowrap transition-colors"><span class="hidden md:inline">Review & Submit</span><span class="md:hidden">Review</span></p>
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
                    if (request()->has('step') && in_array(request('step'), ['1', '2', '3', '4'])) {
                        $initialStep = (int) request('step');
                    } elseif ($hasStep1Errors) {
                        $initialStep = 1;
                    } elseif ($hasStep2Errors || session('analysis_error')) {
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


                <form id="guest-booking-form" action="{{ route('guest.booking.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf

                    <!-- ========================================== -->
                    <!-- STEP 1: EVENT DETAILS                     -->
                    <!-- ========================================== -->
                    <div id="guest-step-1" class="space-y-3 sm:space-y-3.5 block animate-fade-in">
                        <!-- Two-Column Layout for Desktop, Stacks gracefully on Mobile / Tablet -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5 items-start">
                            
                            <!-- LEFT COLUMN: Event Information & Additional Details -->
                            <div class="space-y-3 sm:space-y-3.5">
                                <!-- Card 1: Event Information -->
                                <div class="bg-white rounded-xl border border-slate-200/80 p-3.5 sm:p-4 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60">
                                            <svg class="w-4 h-4 sm:w-4.5 sm:h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div>
                                            <h2 class="text-sm sm:text-base font-bold text-[#0B1E43] font-serif tracking-tight">Event Information</h2>
                                            <p class="text-[11px] text-slate-500 mt-0.5">Enter event type, date, schedule, and guest count.</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 sm:gap-2.5">
                                        <!-- Event Type -->
                                        <div class="col-span-2 sm:col-span-1">
                                            <label for="event_type" class="block text-xs font-semibold text-[#0B1E43] mb-1">Event Type <span class="text-rose-500">*</span></label>
                                            <div class="relative">
                                                <select name="event_type" id="event_type" required class="w-full py-1.5 sm:py-2 px-2.5 bg-white border {{ $errors->has('event_type') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-[#0B1E43] appearance-none pr-8 cursor-pointer shadow-2xs transition" aria-describedby="event_type_error" aria-invalid="{{ $errors->has('event_type') ? 'true' : 'false' }}">
                                                    <option value="">Select Event Type</option>
                                                    <option value="wedding" {{ old('event_type') === 'wedding' ? 'selected' : '' }}>Wedding</option>
                                                    <option value="birthday" {{ old('event_type') === 'birthday' ? 'selected' : '' }}>Birthday</option>
                                                    <option value="corporate" {{ old('event_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                                                    <option value="delivery" {{ old('event_type') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                                                    <option value="other" {{ old('event_type') === 'other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                                <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            <p id="event_type_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('event_type') ? '' : 'hidden' }}" role="alert">
                                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span class="error-msg">{{ $errors->first('event_type') }}</span>
                                            </p>
                                            <div id="otherEventTypeContainer" class="mt-2 {{ old('event_type') === 'other' ? '' : 'hidden' }}">
                                                <label for="other_event_type" class="block text-xs font-semibold text-slate-500 mb-1">Please specify <span class="text-rose-500">*</span></label>
                                                <input type="text" name="other_event_type" id="other_event_type" value="{{ old('other_event_type') }}" placeholder="e.g. Delivery, Engagement" class="w-full py-1.5 sm:py-2 px-2.5 bg-white border {{ $errors->has('other_event_type') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43]" {{ old('event_type') === 'other' ? 'required' : '' }} aria-describedby="other_event_type_error" aria-invalid="{{ $errors->has('other_event_type') ? 'true' : 'false' }}">
                                                <p id="other_event_type_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('other_event_type') ? '' : 'hidden' }}" role="alert">
                                                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                    <span class="error-msg">{{ $errors->first('other_event_type') }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Event Date -->
                                        <div class="col-span-2 sm:col-span-1">
                                            <label for="event_date" class="block text-xs font-semibold text-[#0B1E43] mb-1">Event Date <span class="text-rose-500">*</span></label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <input type="date" name="event_date" id="event_date" required min="{{ date('Y-m-d') }}" value="{{ old('event_date') }}" class="w-full pl-8 py-1.5 sm:py-2 pr-2.5 bg-white border {{ $errors->has('event_date') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] shadow-2xs" aria-describedby="event_date_error" aria-invalid="{{ $errors->has('event_date') ? 'true' : 'false' }}">
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
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <input type="time" name="event_time" id="event_time" required value="{{ old('event_time') }}" class="w-full pl-8 py-1.5 sm:py-2 pr-2 bg-white border {{ $errors->has('event_time') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] shadow-2xs" aria-describedby="event_time_error" aria-invalid="{{ $errors->has('event_time') ? 'true' : 'false' }}">
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
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <input type="time" name="end_time" id="end_time" required value="{{ old('end_time') }}" class="w-full pl-8 py-1.5 sm:py-2 pr-2 bg-white border {{ $errors->has('end_time') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] shadow-2xs" aria-describedby="end_time_error" aria-invalid="{{ $errors->has('end_time') ? 'true' : 'false' }}">
                                            </div>
                                            <p id="end_time_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('end_time') ? '' : 'hidden' }}" role="alert">
                                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span class="error-msg">{{ $errors->first('end_time') }}</span>
                                            </p>
                                        </div>

                                        <!-- Scale Fields (Expected Guests & Number of Tables side-by-side on Row 3) -->
                                        <div class="col-span-1 {{ old('event_type') === 'delivery' ? 'hidden' : '' }}" id="scale-fields-container" data-span="lg:col-span-2" data-delivery-hidden>
                                            <label for="guest_count" class="block text-xs font-semibold text-[#0B1E43] mb-1">Expected Guests</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                </div>
                                                <input type="text" inputmode="numeric" name="guest_count" id="guest_count" value="{{ old('guest_count') }}" placeholder="100" class="w-full pl-8 py-1.5 sm:py-2 pr-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] shadow-2xs placeholder-slate-400">
                                            </div>
                                            @error('guest_count')
                                                <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="col-span-1 {{ old('event_type') === 'delivery' ? 'hidden' : '' }}" id="scale-tables-container" data-delivery-hidden>
                                            <label for="table_count" class="block text-xs font-semibold text-[#0B1E43] mb-1">Number of Tables</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M3 14h18M6 14v5M18 14v5M5 10V6a1 1 0 011-1h12a1 1 0 011 1v4"></path></svg>
                                                </div>
                                                <input type="text" inputmode="numeric" pattern="[0-9]*" name="table_count" id="table_count" value="{{ old('table_count') }}" placeholder="20" class="w-full pl-8 py-1.5 sm:py-2 pr-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] shadow-2xs placeholder-slate-400">
                                            </div>
                                            @error('table_count')
                                                <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: Additional Details -->
                                <div class="bg-white rounded-xl border border-slate-200/80 p-3 sm:p-3.5 shadow-2xs space-y-1.5">
                                    <div class="flex items-center gap-2.5 pb-1.5 border-b border-slate-100">
                                        <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                        </div>
                                        <div>
                                            <h2 class="text-xs sm:text-sm font-bold text-[#0B1E43] font-serif tracking-tight">Additional Details</h2>
                                            <p class="text-[10px] sm:text-[11px] text-slate-500">Help us understand more about your event setup.</p>
                                        </div>
                                    </div>

                                    <!-- Indoor / Outdoor Pill Selection -->
                                    <x-venue-setting-field :value="old('venue_setting')" />

                                    <!-- Additional Notes -->
                                    <div class="relative pt-0.5">
                                        <label for="special_requests" class="block text-xs font-semibold text-slate-700 mb-0.5">Additional Notes <span class="text-slate-400 font-normal">(Optional)</span></label>
                                        <textarea name="special_requests" id="special_requests" rows="2" class="w-full p-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] resize-none placeholder-slate-400 shadow-2xs leading-normal" oninput="document.getElementById('char-count').innerText = this.value.length + '/500'" placeholder="e.g. theme, color motif, specific requests, other important details...">{{ old('special_requests') }}</textarea>
                                        <div class="absolute bottom-1.5 right-2 text-[10px] text-slate-400 font-medium" id="char-count">0/500</div>
                                        @error('special_requests')
                                            <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: Event Venue & Next Action in Red Area -->
                            <div class="space-y-3 sm:space-y-3.5 flex flex-col justify-between">
                                <div class="bg-white rounded-xl border border-slate-200/80 p-3.5 sm:p-4 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60">
                                            <svg class="w-4 h-4 sm:w-4.5 sm:h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </div>
                                        <div>
                                            <h2 class="text-sm sm:text-base font-bold text-[#0B1E43] font-serif tracking-tight">Event Venue</h2>
                                            <p class="text-[11px] text-slate-500 mt-0.5">Provide the venue location for flower quantities and travel logistics.</p>
                                        </div>
                                    </div>

                                    <!-- City & Specific Venue grouped on one row for compact layout -->
                                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 sm:gap-2.5">
                                        <!-- City / Municipality -->
                                        <div class="col-span-1 sm:col-span-1 lg:col-span-1">
                                            <label for="venue_city" class="block text-xs font-semibold text-[#0B1E43] mb-1">City / Municipality <span class="text-rose-500">*</span></label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                </div>
                                                <input list="metro_manila_cities" name="venue_city" id="venue_city" required placeholder="TAGUIG" value="{{ old('venue_city') }}" class="w-full pl-8 py-1.5 sm:py-2 pr-7 bg-white border {{ $errors->has('venue_city') || $errors->has('venue') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] uppercase tracking-wide shadow-2xs font-medium" oninput="updateVenueField()" aria-describedby="venue_city_error" aria-invalid="{{ $errors->has('venue_city') || $errors->has('venue') ? 'true' : 'false' }}">
                                                <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                                <datalist id="metro_manila_cities">
                                                    <option value="CALOOCAN"></option>
                                                    <option value="LAS PIÑAS"></option>
                                                    <option value="MAKATI"></option>
                                                    <option value="MALABON"></option>
                                                    <option value="MANDALUYONG"></option>
                                                    <option value="MANILA"></option>
                                                    <option value="MARIKINA"></option>
                                                    <option value="MUNTINLUPA"></option>
                                                    <option value="NAVOTAS"></option>
                                                    <option value="PARAÑAQUE"></option>
                                                    <option value="PASAY"></option>
                                                    <option value="PASIG"></option>
                                                    <option value="QUEZON CITY"></option>
                                                    <option value="SAN JUAN"></option>
                                                    <option value="TAGUIG"></option>
                                                    <option value="VALENZUELA"></option>
                                                    <option value="PATEROS"></option>
                                                </datalist>
                                            </div>
                                            <p id="venue_city_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('venue_city') || $errors->has('venue') ? '' : 'hidden' }}" role="alert">
                                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span class="error-msg">{{ $errors->first('venue_city') ?: $errors->first('venue') }}</span>
                                            </p>
                                        </div>

                                        <!-- Specific Venue / Address -->
                                        <div class="col-span-1 sm:col-span-3 lg:col-span-3">
                                            <label for="venue_specific" class="block text-xs font-semibold text-[#0B1E43] mb-1">Venue Name / Address <span class="text-rose-500">*</span></label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                                </div>
                                                <input type="text" name="venue_specific" id="venue_specific" required placeholder="Venice Grand Canal Mall, Upper McKinley Road, Pinagsama" value="{{ old('venue_specific') }}" class="w-full pl-8 py-1.5 sm:py-2 pr-3 bg-white border {{ $errors->has('venue_specific') ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200' }} rounded-xl text-xs sm:text-sm focus:ring-2 focus:ring-emerald-500 outline-none text-[#0B1E43] placeholder-slate-400 shadow-2xs font-normal" oninput="updateVenueField()" aria-describedby="venue_specific_error" aria-invalid="{{ $errors->has('venue_specific') ? 'true' : 'false' }}">
                                            </div>
                                            <p id="venue_specific_error" class="text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1 {{ $errors->has('venue_specific') ? '' : 'hidden' }}" role="alert">
                                                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span class="error-msg">{{ $errors->first('venue_specific') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="venue" id="venue" value="{{ old('venue') }}">

                                    <!-- Venue Location Finder (embedded Leaflet map; syncs with coordinates & address) -->
                                    <x-location-finder id="venue-finder"
                                        :latitude="old('venue_latitude')" :longitude="old('venue_longitude')"
                                        :embedded="true" :open="false" />
                                </div>

                                <!-- Step 1 Primary Action (Next) inside Right Column in the Red Area -->
                                <div class="flex justify-end pt-1">
                                    <button type="button" id="step1-right-col-next" onclick="navigateGuest(1)" class="w-full sm:w-auto py-2.5 px-8 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow hover:shadow-md transition-all flex items-center justify-center gap-2 text-sm sm:text-base cursor-pointer">
                                        <span>Next</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- STEP 2: CHOOSE BOOKING METHOD             -->
                    <!-- ========================================== -->
                    <div id="guest-step-2" class="space-y-4 hidden animate-fade-in">
                        <div class="space-y-1">
                            <h2 class="text-base sm:text-lg font-bold text-[#0B1E43] font-serif tracking-tight" id="step-2-title">1. Choose Booking Method</h2>
                        </div>
                        
                        <div id="method-selection-view">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-4">
                                <!-- AI Card -->
                                <label id="guest-card-ai" onclick="switchGuestMode('ai')" class="relative flex flex-col p-4 sm:p-4.5 border-2 border-emerald-600 bg-emerald-50/20 rounded-2xl cursor-pointer transition-all hover:bg-emerald-50/30 shadow-2xs">
                                    <input type="radio" name="booking_type" value="custom_ai" {{ old('booking_type', request('package_id') ? 'preset' : 'custom_ai') === 'custom_ai' ? 'checked' : '' }} class="sr-only">
                                    <div class="absolute top-3 right-3" id="ai-check">
                                        <div class="w-5 h-5 rounded-full bg-emerald-600 flex items-center justify-center text-white shadow-2xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </div>
                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    <div class="font-bold text-[#0B1E43] text-sm sm:text-base mb-1 flex items-center gap-2">
                                        Smart AI Custom Design
                                        <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">POPULAR</span>
                                    </div>
                                    <p class="text-xs text-slate-500 leading-relaxed">Upload up to 5 inspiration photos. The AI suggests floral materials for each one, and Raflora reviews them before your official quotation.</p>
                                </label>

                                <!-- Preset Card -->
                                <label id="guest-card-preset" onclick="switchGuestMode('preset')" class="relative flex flex-col p-4 sm:p-4.5 border-2 border-slate-200 bg-white rounded-2xl cursor-pointer transition-all hover:border-slate-300 shadow-2xs">
                                    <input type="radio" name="booking_type" value="preset" {{ old('booking_type', request('package_id') ? 'preset' : 'custom_ai') === 'preset' ? 'checked' : '' }} class="sr-only">
                                    <div class="absolute top-3 right-3 hidden" id="preset-check">
                                        <div class="w-5 h-5 rounded-full bg-emerald-600 flex items-center justify-center text-white shadow-2xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </div>
                                    <div id="preset-icon-box" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center mb-2 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    </div>
                                    <div class="font-bold text-[#0B1E43] text-sm sm:text-base mb-1">Pre-Set Curated Package</div>
                                    <p class="text-xs text-slate-500 leading-relaxed">Choose a ready-made floral package with a fixed catalog price set by our florists.</p>
                                </label>
                            </div>
                        </div>

                        <!-- Smart AI custom design: preferences, photos and their analysis (shared with the client form) -->
                        <div id="guest-section-ai" class="hidden mt-4 space-y-3">
                            @if(session('analysis_error'))
                                <div id="guest-analysis-error" class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs font-medium text-rose-800" role="alert">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                    <span>{{ session('analysis_error') }}</span>
                                </div>
                            @endif
                            <p id="step1-ai-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800" role="alert" tabindex="-1"></p>
                            <x-inspiration-photos prefix="guest" :analyze-url="route('guest.bookings.analyze-temp-image')" />
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
                            
                            {{-- These same fields are shown in the Step 4 "Review Contact Information" dialog while it is open. --}}
                            <div id="guest-contact-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
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
                    <div id="guest-step-4" class="space-y-5 hidden animate-fade-in">
                        
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
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden transition-all" id="review-card-event">
                                    <div class="p-4 sm:p-5 flex items-start gap-3.5">
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 mt-0.5">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">Event Details</h4>
                                                    <span id="review-event-mobile-summary" class="sm:hidden text-xs text-slate-500 font-normal truncate block mt-0.5">-</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" onclick="showGuestStep(1)" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shadow-2xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit
                                                    </button>
                                                    <button type="button" onclick="toggleReviewCard('review-card-event')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle Event Details">
                                                        <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                                                                <!-- Detailed Content Grid -->
                                            <div id="review-card-event-body" class="mt-4 pt-3.5 border-t border-slate-100 hidden sm:grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-3.5 gap-x-3">
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Type</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-event-type">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Date</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-event-date">-</div>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Event Time</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-event-time">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Venue / Municipality</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-venue-city">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Specific Venue / Address</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-venue-specific">-</div>
                                                    <div class="mt-1 hidden text-[11px] font-semibold text-emerald-700 items-center gap-1" id="review-venue-pin">
                                                        <svg class="w-3.5 h-3.5 text-emerald-600 inline shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                                        Pinned on map
                                                    </div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Indoor / Outdoor</div>
                                                    <div class="text-xs sm:text-sm font-bold text-[#0B1E43] mt-0.5" id="review-venue-setting">-</div>
                                                </div>
                                                <div class="sm:col-span-2 md:col-span-3">
                                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Additional Notes</div>
                                                    <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100 mt-1 leading-relaxed" id="review-notes">-</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review Card 3: Inspiration Images / Package (also names the booking method) -->
                                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden transition-all" id="review-card-images">
                                    <div class="p-4 sm:p-5 flex items-start gap-3.5">
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 mt-0.5">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif" id="review-media-title">Inspiration Images</h4>
                                                    <span id="review-media-mobile-summary" class="sm:hidden text-xs text-slate-500 font-normal truncate block mt-0.5">Photos analyzed</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" onclick="showGuestStep(2)" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shadow-2xs">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit
                                                    </button>
                                                    <button type="button" onclick="toggleReviewCard('review-card-images')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle Inspiration Images">
                                                        <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Booking Method & Design (moved here from its own card): "Smart AI Custom Design"
                                                 in Inspiration Images, "Pre-Set Curated Package" in Selected Package -->
                                            <div id="review-method-row" class="mb-3 pb-3 border-b border-slate-100">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Booking Method &amp; Design</span>
                                                <div class="flex items-center gap-1.5 text-xs sm:text-sm font-bold text-[#0B1E43]">
                                                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                    <span id="review-method-title">Smart AI Custom Design</span>
                                                </div>
                                            </div>

                                            <div id="review-card-images-body" class="space-y-3.5 hidden sm:block">
                                                <!-- Single image / Package preview (backward compatible) -->
                                                <div class="flex flex-col sm:flex-row gap-4 items-start">
                                                    <div id="review-media-single-wrapper" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden bg-slate-100 shrink-0 border border-slate-200 flex items-center justify-center">
                                                        <img src="" id="review-media-img" class="w-full h-full object-cover hidden" alt="Uploaded or Package Preview">
                                                        <div id="review-media-placeholder" class="text-slate-400 text-xs flex flex-col items-center justify-center p-2 text-center">
                                                            <svg class="w-6 h-6 text-emerald-600/50 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                            <span>Floral Concept</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-bold text-sm text-[#0B1E43]" id="review-media-subtitle">Uploaded Floral Images</div>
                                                        <div class="text-xs font-bold text-emerald-600 mt-0.5" id="review-media-status">Ready</div>
                                                        <div class="text-xs text-slate-500 mt-1 leading-relaxed" id="review-media-desc">Custom floral design based on your inspiration photos.</div>
                                                        
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
                                                    <div id="review-multi-thumbnails" class="flex flex-wrap gap-2.5"></div>
                                                </div>

                                                <!-- Design brief (custom AI, only when something was filled in) -->
                                                <div id="review-design-brief-row" class="hidden pt-3 border-t border-slate-100">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Design Brief</span>
                                                    <dl id="review-design-brief" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs"></dl>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review Card 5: Contact Information, reviewed and edited in a dialog opened from the sidebar.
                                     The review script still writes these values; they are not shown here. -->
                                <div id="review-card-contact" class="hidden" aria-hidden="true">
                                    <span id="review-contact-name">-</span>
                                    <span id="review-contact-email">-</span>
                                    <span id="review-contact-phone">-</span>
                                    <span id="review-contact-address">-</span>
                                </div>
                            </div>
                            
                            <!-- Right Sidebar (Sticky on Desktop) -->
                            <div class="lg:col-span-1 space-y-4 lg:sticky lg:top-6" id="review-card-summary">
                                <!-- Booking Summary Card -->
                                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/90 shadow-2xs">
                                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <h4 class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">Booking Summary</h4>
                                    </div>
                                    
                                    <div class="space-y-2.5 text-xs mt-3.5">
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="text-slate-500 font-medium">Method</span>
                                            <span class="font-bold text-[#0B1E43] text-right truncate" id="summary-method">Smart AI Custom Design</span>
                                        </div>
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="text-slate-500 font-medium">Event Type</span>
                                            <span class="font-bold text-[#0B1E43] text-right" id="summary-type">-</span>
                                        </div>
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="text-slate-500 font-medium">Event Date</span>
                                            <span class="font-bold text-[#0B1E43] text-right" id="summary-date">-</span>
                                        </div>

                                        <!-- Selected Areas List -->
                                        <div id="summary-selected-areas-wrapper" class="pt-2 border-t border-slate-100 space-y-1">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Selected Areas</span>
                                            <ul id="summary-selected-areas" class="space-y-1 text-slate-700 text-xs pl-3 list-disc">
                                                <li>Stage or Backdrop</li>
                                            </ul>
                                        </div>

                                        <!-- Violet Price Estimate Card -->
                                        <div class="rounded-xl border border-violet-200/90 bg-violet-50/70 p-3.5 mt-2 space-y-1.5 shadow-2xs">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="text-xs font-bold uppercase tracking-wide text-violet-900" id="summary-price-label">Price</span>
                                                    <span class="text-lg sm:text-xl font-extrabold text-[#0B1E43] font-mono tabular-nums tracking-tight" id="summary-price">In your official quotation</span>
                                                </div>
                                                <span class="text-violet-500 cursor-help" title="Indicative AI price range per area. Final official price is prepared after Raflora review.">
                                                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                                </span>
                                            </div>
                                            <p class="text-[10.5px] text-slate-500 leading-snug">Final price will be confirmed in the official quotation.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Before You Submit Card -->
                                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/90 shadow-2xs space-y-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"></path></svg>
                                        </div>
                                        <h4 class="font-bold text-[#0B1E43] text-xs sm:text-sm font-serif">Before You Submit</h4>
                                    </div>
                                    <ul class="space-y-2 text-xs text-slate-600 leading-snug">
                                        <li class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Please review all information on this page.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>No payment is required today.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Raflora will prepare an official quotation after staff review.</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <svg class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>You may edit any section before submitting.</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Review shortcuts: open the AI Analysis Summary and Contact Information dialogs -->
                                <button type="button" id="open-review-ai-summary" data-review-dialog-open="review-ai-summary-modal" class="hidden w-full text-left bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-2xs hover:border-emerald-300 hover:bg-emerald-50/40 transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-haspopup="dialog">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-bold text-[#0B1E43] text-xs sm:text-sm font-serif">View AI Analysis Summary</span>
                                        <span class="block text-[11px] text-slate-500 truncate">Indicative ranges and materials per area</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>

                                <button type="button" id="open-review-contact" data-review-dialog-open="review-contact-modal" class="w-full text-left bg-white rounded-2xl p-3.5 border border-slate-200/90 shadow-2xs hover:border-emerald-300 hover:bg-emerald-50/40 transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-haspopup="dialog">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100/60 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-bold text-[#0B1E43] text-xs sm:text-sm font-serif">Review Contact Information</span>
                                        <span id="review-contact-mobile-summary" class="block text-[11px] text-slate-500 truncate">-</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>

                                <!-- Ready to Submit Card (Mobile Only) -->
                                <div class="sm:hidden bg-emerald-50 rounded-2xl p-4 border border-emerald-100 flex gap-3 items-start">
                                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-[#0B1E43] text-xs font-serif">Ready to Submit?</h4>
                                        <p class="text-[11px] text-slate-600 leading-relaxed mt-0.5">This creates a booking request for Raflora review. It does not confirm your booking.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                                <!-- Review Card 4: AI Analysis Summary (Custom AI Only), shown in a dialog opened from the sidebar.
                                     The section itself is unchanged; the review script still fills it and shows/hides it. -->
                                <div id="review-ai-summary-modal" class="fixed inset-0 z-[45] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-label="AI Analysis Summary" tabindex="-1" data-review-dialog>
                                    <div class="relative w-full sm:max-w-3xl lg:max-w-4xl max-h-[92dvh] sm:max-h-[calc(100dvh-2rem)] bg-slate-50 rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
                                        <div class="flex items-center justify-end gap-2 px-3 py-2 sm:px-4 border-b border-slate-200/80 bg-white shrink-0">
                                            <button type="button" data-review-dialog-close class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition" aria-label="Close AI Analysis Summary">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                <span>Close</span>
                                            </button>
                                        </div>
                                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3 sm:p-4">
                                <div id="review-ai-materials-section" class="bg-white rounded-2xl border border-slate-200/90 p-4 sm:p-5 shadow-2xs space-y-3.5 hidden">
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
                                            <button type="button" onclick="showGuestStep(2)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 transition shadow-2xs">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                <span class="hidden sm:inline">View AI Analysis</span>
                                            </button>
                                            <button type="button" onclick="toggleReviewCard('review-ai-materials-section')" class="sm:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-lg" aria-label="Toggle AI Analysis Summary">
                                                <svg data-accordion-chevron class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div id="review-ai-materials-section-body" class="space-y-3.5">
                                        <!-- Compact Multi-Image AI Summary Metrics -->
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-emerald-50/40 rounded-xl border border-emerald-100 text-xs">
                                            <div>
                                                <span class="text-[10px] text-slate-400 font-semibold uppercase block">Inspiration Images</span>
                                                <span class="font-bold text-slate-800" id="review-images-count-text">1 image</span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-slate-400 font-semibold uppercase block">Detected Materials</span>
                                                <span class="font-bold text-emerald-700" id="review-materials-count-text">0 items</span>
                                            </div>
                                            <div class="col-span-2 sm:col-span-1">
                                                <span class="text-[10px] text-slate-400 font-semibold uppercase block">Price</span>
                                                <span class="font-bold text-slate-800">In your official quotation</span>
                                            </div>
                                        </div>

                                        {{-- All photos combined on the server: per-area AI ranges and quantities, with no figure counted twice --}}
                                        <p id="review-estimate-status" class="hidden text-[11px] text-slate-500" role="status"></p>
                                        <x-ai-estimate id="review-ai-estimate" class="mb-3 hidden" />
                                        <x-decor-area-breakdown-live id="review-area-breakdown" class="mb-3" />

                                        <div id="review-materials-list" class="space-y-1.5 max-h-48 overflow-y-auto pr-1 hidden"></div>

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

                        <!-- Review Contact Information dialog: shows the Step 3 fields themselves (moved in while open) -->
                        <div id="review-contact-modal" class="fixed inset-0 z-[45] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs hidden" role="dialog" aria-modal="true" aria-labelledby="review-contact-modal-title" tabindex="-1" data-review-dialog>
                            <div class="relative w-full sm:max-w-2xl max-h-[92dvh] sm:max-h-[calc(100dvh-2rem)] bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
                                <header class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5 border-b border-slate-100 bg-slate-50/90 shrink-0">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 id="review-contact-modal-title" class="text-base sm:text-lg font-bold text-[#0B1E43] font-serif tracking-tight">Contact Information</h3>
                                            <p class="text-xs text-slate-500 mt-0.5">Used for your booking tracking link and quotation.</p>
                                        </div>
                                    </div>
                                    <button type="button" data-review-dialog-close class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-white transition" aria-label="Cancel and close Contact Information">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </header>
                                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5 sm:py-5" data-contact-fields-slot></div>
                                <footer class="flex items-center justify-end gap-2 px-4 py-3 sm:px-5 border-t border-slate-100 bg-slate-50/90 shrink-0">
                                    <button type="button" data-review-dialog-close class="flex-1 sm:flex-none px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                                    <button type="button" data-contact-save class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-2xs transition">Save changes</button>
                                </footer>
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

                            <div class="relative w-full sm:max-w-4xl lg:max-w-5xl max-h-[88vh] sm:max-h-[90vh] bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden animate-slide-up sm:animate-fade-in"
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

                                <!-- Scrollable 2-Column Body (Desktop) -->
                                <div class="flex-1 overflow-y-auto p-4 sm:p-5 text-xs">
                                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                                        
                                        <!-- Left Column: Carousel & Price Estimate Card -->
                                        <div class="lg:col-span-5 space-y-4">
                                            <!-- Inspiration Photos Carousel -->
                                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 shadow-2xs space-y-2">
                                                <div id="area-modal-carousel-container" class="space-y-2">
                                                    <div class="relative w-full h-48 sm:h-52 rounded-xl overflow-hidden bg-slate-900 group shadow-inner">
                                                        <img id="area-modal-carousel-img" src="" alt="Inspiration preview" class="w-full h-full object-cover transition-opacity duration-200 cursor-pointer" onclick="openBookingLightbox(modalPhotoIndex)">
                                                        
                                                        <!-- Left / Right Overlaid Nav Buttons -->
                                                        <button type="button" onclick="prevModalPhoto()" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white flex items-center justify-center transition shadow-md outline-none" aria-label="Previous photo">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                                        </button>
                                                        <button type="button" onclick="nextModalPhoto()" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white flex items-center justify-center transition shadow-md outline-none" aria-label="Next photo">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                                        </button>

                                                        <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded-md bg-slate-900/70 text-white text-[10px] font-semibold backdrop-blur-xs flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                                                            <span>Click to enlarge</span>
                                                        </div>
                                                    </div>

                                                    <!-- Thumbnail strip -->
                                                    <div id="area-modal-thumbs" class="flex gap-2 overflow-x-auto pt-1 pb-1"></div>
                                                    <p id="area-modal-photos-caption" class="text-[11px] text-slate-500 text-center">Inspiration photos analyzed for AI suggestions</p>
                                                </div>

                                                <div id="area-modal-carousel-placeholder" class="hidden p-6 rounded-xl border border-dashed border-slate-200 bg-white text-center text-slate-400">
                                                    <svg class="w-8 h-8 mx-auto text-slate-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <p class="text-xs">No inspiration photos uploaded for this area</p>
                                                </div>
                                            </div>

                                            <!-- AI Indicative Price Estimate Card -->
                                            <section class="rounded-xl border border-violet-200 bg-violet-50/50 p-3.5 sm:p-4 shadow-2xs space-y-2">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-violet-600" aria-hidden="true"></span>
                                                        <h4 class="text-xs font-bold uppercase tracking-wide text-violet-950">AI Price Estimate (<span id="area-modal-estimate-label">Stage or Backdrop</span>)</h4>
                                                    </div>
                                                    <span class="rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-800 shadow-2xs">
                                                        INDICATIVE · NOT AN OFFICIAL
                                                    </span>
                                                </div>

                                                <div class="flex items-baseline justify-between gap-3 pt-0.5">
                                                    <div class="flex items-center gap-2">
                                                        <span id="area-modal-estimate-price" class="text-lg sm:text-2xl font-extrabold text-[#0B1E43] font-mono tabular-nums tracking-tight">
                                                            ₱107,214 – ₱145,056
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
                                                    Indicative range (±15%) for AI suggested materials; labour &amp; setup are not included. Final price is confirmed in your official quotation.
                                                </p>
                                            </section>
                                        </div>

                                        <!-- Right Column: Client Note & Suggested Materials -->
                                        <div class="lg:col-span-7 space-y-4">
                                            <!-- 1. Client Note for This Area -->
                                            <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 shadow-2xs space-y-2" id="area-modal-note-section">
                                                <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-slate-200/70">
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Client-Specific Notes</h4>
                                                    </div>
                                                    <button type="button"
                                                            id="area-modal-edit-note-btn"
                                                            class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        Edit note
                                                    </button>
                                                </div>

                                                <!-- Read Note View -->
                                                <div id="area-modal-note-display" class="text-slate-800 leading-relaxed text-xs">
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

                                            <!-- 2. AI Material Suggestions -->
                                            <section class="space-y-2.5">
                                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">
                                                        Suggested Materials (<span id="area-modal-mat-count">14 items</span>)
                                                    </h4>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button"
                                                                onclick="toggleCalculationBasis()"
                                                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 transition">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                            <span>View Calculation Basis</span>
                                                        </button>
                                                        <span class="text-slate-300">·</span>
                                                        <div id="area-modal-count-badges" class="flex items-center gap-1.5 text-[11px]">
                                                            <span id="area-modal-detected-badge" class="text-emerald-700 font-semibold">10 Detected</span>
                                                            <span class="text-slate-300">·</span>
                                                            <span id="area-modal-suggested-badge" class="text-amber-700 font-semibold">4 Suggested</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Missing Area Notice -->
                                                <div id="area-modal-missing-notice" class="hidden p-3.5 rounded-xl border border-dashed border-amber-300 bg-amber-50/70 text-center">
                                                    <p class="text-xs font-medium text-amber-900">
                                                        No materials suggested — staff will review
                                                    </p>
                                                </div>

                                                <!-- Materials Table (Desktop) & Cards (Mobile) -->
                                                <div id="area-modal-materials-container" class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
                                                    <!-- Desktop Table View -->
                                                    <div class="hidden sm:block overflow-x-auto max-h-72 overflow-y-auto">
                                                        <table class="w-full text-left border-collapse text-xs">
                                                            <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-xs">
                                                                <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                                    <th class="py-2.5 px-3 w-8">#</th>
                                                                    <th class="py-2.5 px-3">Material / Item</th>
                                                                    <th class="py-2.5 px-3 text-center">Est. Qty</th>
                                                                    <th class="py-2.5 px-3">Unit</th>
                                                                    <th class="py-2.5 px-3 text-right">Est. Price</th>
                                                                    <th class="py-2.5 px-3 text-center">Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="area-modal-table-body" class="divide-y divide-slate-100 text-slate-700">
                                                                <!-- Populated dynamically by JS -->
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <!-- Mobile Cards List View -->
                                                    <div id="area-modal-cards-list" class="sm:hidden divide-y divide-slate-100 max-h-72 overflow-y-auto">
                                                        <!-- Populated dynamically by JS -->
                                                    </div>
                                                </div>

                                                <!-- Show More / Show Less Toggle Button -->
                                                <button type="button"
                                                        id="area-modal-toggle-more-btn"
                                                        class="hidden w-full py-2 px-3 text-center text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-xl transition flex items-center justify-center gap-1">
                                                    <span id="area-modal-toggle-more-text">Show 10 more materials</span>
                                                    <svg id="area-modal-toggle-more-icon" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>

                                                <p class="text-[11px] text-slate-500 italic pt-1">
                                                    <strong class="text-slate-600 not-italic">(i)</strong> AI suggestions are decision support. Quantities, availability, and final pricing will be confirmed in your official quotation.
                                                </p>
                                            </section>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <footer class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/90 text-[11px] text-slate-500 shrink-0">
                                    <span class="truncate hidden sm:inline">
                                        AI decision support. Official pricing confirmed in Raflora quotation.
                                    </span>
                                    <div class="flex-1 sm:hidden"></div>
                                    <button type="button"
                                            onclick="closeAreaDetailsModal()"
                                            data-action="close-modal"
                                            class="w-full sm:w-auto px-5 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 font-bold text-slate-700 text-xs transition">
                                        Close
                                    </button>
                                </footer>
                            </div>
                        </div>

                        <!-- Standalone Inspiration Images Lightbox Dialog -->
                        <div id="booking-images-lightbox-dialog"
                             class="fixed inset-0 z-60 flex items-center justify-center p-3 sm:p-6 bg-slate-950/85 backdrop-blur-md transition-opacity duration-300 hidden"
                             role="dialog"
                             aria-modal="true"
                             aria-labelledby="lightbox-title"
                             tabindex="-1">
                            <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col">
                                <!-- Lightbox Header -->
                                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <h3 id="lightbox-title" class="font-bold text-[#0B1E43] text-sm sm:text-base font-serif">Inspiration Images</h3>
                                    </div>
                                    <button type="button" onclick="closeBookingLightbox()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition" aria-label="Close Lightbox">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                
                                <!-- Lightbox Image Area -->
                                <div class="relative w-full h-[55vh] sm:h-[65vh] bg-slate-950 flex items-center justify-center">
                                    <img id="lightbox-active-img" src="" alt="Enlarged inspiration image" class="max-w-full max-h-full object-contain">
                                    
                                    <!-- Prev / Next Controls -->
                                    <button type="button" onclick="prevLightboxPhoto()" class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/50 hover:bg-black/80 text-white flex items-center justify-center transition" aria-label="Previous image">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                    </button>
                                    <button type="button" onclick="nextLightboxPhoto()" class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/50 hover:bg-black/80 text-white flex items-center justify-center transition" aria-label="Next image">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                </div>

                                <!-- Lightbox Footer with Thumbs & Counter -->
                                <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                                    <div id="lightbox-thumbs" class="flex gap-2 overflow-x-auto"></div>
                                    <span id="lightbox-counter" class="text-xs font-semibold text-slate-600 shrink-0">Image 1 of 2</span>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Navigation Buttons (Steps 2-4) -->
                    <div id="wizard-nav-footer" style="display: none;" class="mt-5 pt-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:justify-between items-center gap-3">
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button type="button" id="btn-back" onclick="navigateGuest(-1)" class="hidden w-full sm:w-auto py-2.5 px-5 rounded-xl border border-slate-300 bg-white text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 text-sm shadow-2xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Back
                            </button>
                            
                            <!-- Pagination Controls (Preset Packages) -->
                            <div id="package-pagination" class="flex gap-2 hidden"></div>
                        </div>
                        <div class="flex-1"></div>
                        <button type="button" id="btn-next" onclick="navigateGuest(1)" class="w-full sm:w-auto py-2.5 px-6 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 text-sm">
                            Next
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                        <button type="submit" id="btn-submit" class="hidden w-full sm:w-auto py-2.5 px-6 rounded-xl bg-emerald-600 text-white font-bold shadow-sm hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
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
        let guestPhotos = null; // window.RafloraInspirationPhotos controller (components.inspiration-photos)

        const DRAFT_STORAGE_KEY = 'raflora_guest_booking_draft_v1';
        let isRestoringDraft = false;
        // The server refused the analyzed photos at submission (session analysis_error).
        const serverAnalysisError = @json(session('analysis_error'));
        const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

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
                    // Step 2: Booking Method & Inspiration Photos (no file can be saved; see importState)
                    booking_type: methodInput ? methodInput.value : 'custom_ai',
                    package_id: selectedPkgInput ? selectedPkgInput.value : '',
                    uploadedImages: guestPhotos ? guestPhotos.exportState() : [],
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

        function readGuestDraft() {
            try {
                const raw = sessionStorage.getItem(DRAFT_STORAGE_KEY);
                if (!raw) return null;
                const draft = JSON.parse(raw);
                if (!draft || typeof draft !== 'object') return null;
                // Ignore drafts older than 24 hours
                if (draft.timestamp && (Date.now() - draft.timestamp > 24 * 60 * 60 * 1000)) {
                    clearGuestDraft();
                    return null;
                }
                return draft;
            } catch (e) {
                return null;
            }
        }

        function restoreGuestDraft() {
            const draft = readGuestDraft();
            if (!draft) return false;
            try {
                isRestoringDraft = true;

                // 1. Restore Step 1 Fields
                ['event_type', 'other_event_type', 'event_date', 'event_time', 'end_time', 'guest_count', 'table_count', 'venue_city', 'venue_specific', 'venue', 'special_requests'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && draft[id]) el.value = draft[id];
                });
                document.getElementById('event_type')?.dispatchEvent(new Event('change'));
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

                // 2. Restore Step 2 Method and photos
                if (draft.booking_type === 'preset') {
                    switchGuestMode('preset');
                    if (draft.package_id) {
                        const radio = document.querySelector(`input[name="package_id"][value="${CSS.escape(String(draft.package_id))}"]`);
                        if (radio) {
                            radio.checked = true;
                            highlightPackageCard(radio.closest('.guest-package-card'));
                        }
                    }
                } else {
                    switchGuestMode('ai');
                }
                guestPhotos?.importState(draft.uploadedImages);

                // 3. Restore Step 3 Fields
                ['guest_name', 'guest_email', 'guest_phone', 'guest_address'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && draft[id]) el.value = draft[id];
                });

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

        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
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
        let validationOpener = null;

        function showValidationModal(items, focusEl) {
            if (isRestoringDraft) return;
            pendingFocusEl = focusEl || null;
            validationOpener = document.activeElement;
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
            const target = pendingFocusEl || validationOpener;
            pendingFocusEl = null;
            validationOpener = null;
            if (target && target.offsetParent !== null) {
                try {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.focus({ preventScroll: true });
                } catch (e) {}
            }
        }

        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('raflora-validation-modal');
            if (!modal || modal.classList.contains('hidden')) return;
            if (e.key === 'Escape') closeValidationModal();
            // The dialog has one control: keep keyboard focus on it.
            if (e.key === 'Tab') { e.preventDefault(); document.getElementById('validation-modal-close-btn')?.focus(); }
        });

        // The review step's "AI estimate per area" and "Materials per area": the server combines the
        // photos analyzed in this session (photos of the same areas are views of one design, so
        // nothing is counted twice) and returns display ranges and quantities only.
        let reviewEstimateRequest = 0;
        window.latestEstimateData = null;
        window.latestBreakdownData = null;
        function loadReviewEstimate(images) {
            const statusEl = document.getElementById('review-estimate-status');
            const tokens = images.map(img => img.token).filter(Boolean);
            const requestId = ++reviewEstimateRequest;
            window.latestEstimateData = null;
            window.latestBreakdownData = null;
            window.RafloraAiEstimate?.render('review-ai-estimate', null);
            window.RafloraAreaBreakdown?.render('review-area-breakdown', null);
            if (tokens.length === 0) return;
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
                if (requestId !== reviewEstimateRequest) return;
                if (!ok) {
                    if (statusEl) statusEl.textContent = data.message || 'The AI estimate is unavailable right now. Raflora will price your design in the official quotation.';
                    return;
                }
                statusEl?.classList.add('hidden');
                window.latestEstimateData = data.estimate || null;
                window.latestBreakdownData = data.area_breakdown || null;
                window.RafloraAiEstimate?.render('review-ai-estimate', data.estimate);
                window.RafloraAreaBreakdown?.render('review-area-breakdown', data.area_breakdown, data.estimate);
                document.getElementById('review-materials-list')?.classList.toggle('hidden', Boolean(data.area_breakdown));
                const matsCountText = document.getElementById('review-materials-count-text');
                if (matsCountText && Number.isInteger(data.material_count)) matsCountText.textContent = `${data.material_count} items`;
            })
            .catch(() => {
                if (requestId === reviewEstimateRequest && statusEl) statusEl.textContent = 'The AI estimate could not be loaded. Raflora will price your design in the official quotation.';
            });
        }

        function reviewRow(label, value) {
            const row = document.createElement('div');
            row.className = 'p-2 rounded-lg border border-slate-100 bg-slate-50 text-[11px] flex items-center justify-between gap-2';
            const name = document.createElement('span');
            name.className = 'font-bold text-slate-800 truncate';
            name.textContent = label;
            const qty = document.createElement('span');
            qty.className = 'text-slate-500 font-medium shrink-0';
            qty.textContent = value;
            row.append(name, qty);
            return row;
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

                // Only photos whose analysis is current are sent with the request.
                const allPhotos = guestPhotos ? guestPhotos.photos() : [];
                const completedImages = guestPhotos ? guestPhotos.readyPhotos() : [];
                const imgCount = completedImages.length;
                const leftOut = allPhotos.length - imgCount;
                let totalMaterialsCount = 0;
                completedImages.forEach(img => { totalMaterialsCount += (img.suggestedMaterials || []).length; });

                document.getElementById('review-media-status').textContent = imgCount > 0
                    ? `${imgCount} of ${allPhotos.length} photo${allPhotos.length === 1 ? '' : 's'} analyzed and included` + (leftOut > 0 ? ` · ${leftOut} could not be analyzed and will be left out` : '')
                    : 'Pending Analysis';
                if (reviewImg && completedImages[0]) {
                    reviewImg.src = completedImages[0].thumbnailUrl || completedImages[0].previewUrl || '';
                    reviewImg.classList.remove('hidden');
                    reviewPlaceholder?.classList.add('hidden');
                }

                // Render multi-thumbnails strip
                if (multiRow && multiThumbnails) {
                    multiThumbnails.innerHTML = '';
                    completedImages.forEach((img) => {
                        const number = allPhotos.indexOf(img) + 1;
                        const thumb = document.createElement('div');
                        thumb.className = 'w-16 h-16 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 relative shadow-2xs';
                        const image = document.createElement('img');
                        image.src = img.thumbnailUrl || img.previewUrl || '';
                        image.alt = `Inspiration ${number}`;
                        image.className = 'w-full h-full object-cover';
                        const label = document.createElement('span');
                        label.className = 'absolute bottom-0 inset-x-0 bg-slate-900/60 text-white text-[9px] text-center font-bold py-0.5';
                        label.textContent = `Image ${number}`;
                        thumb.append(image, label);
                        multiThumbnails.appendChild(thumb);
                    });
                    multiRow.classList.toggle('hidden', completedImages.length === 0);
                }

                // A custom design has no price until Raflora issues the official quotation.
                document.getElementById('summary-price').textContent = 'In your official quotation';
                document.getElementById('summary-price-label').textContent = 'Price';

                const imgCountText = document.getElementById('review-images-count-text');
                if (imgCountText) imgCountText.textContent = `${imgCount} image${imgCount === 1 ? '' : 's'}`;
                const matsCountText = document.getElementById('review-materials-count-text');
                if (matsCountText) matsCountText.textContent = `${totalMaterialsCount} items`;

                // Materials and AI ranges per area for all analyzed photos together, combined on the server.
                loadReviewEstimate(completedImages);

                // Render AI materials in Review step (shown when no areas were picked)
                if (reviewAiSection && reviewMaterialsList) {
                    reviewMaterialsList.innerHTML = '';
                    completedImages.forEach((img) => {
                        const number = allPhotos.indexOf(img) + 1;
                        (img.suggestedMaterials || []).forEach((mat) => {
                            const qty = mat.quantity ?? mat.estimated_quantity ?? null;
                            reviewMaterialsList.appendChild(reviewRow(`[Img ${number}] ${mat.item_name || 'Floral Material'}`, qty !== null ? `${qty} ${mat.unit_type || 'pcs'}` : 'Qty to confirm'));
                        });
                    });
                    reviewAiSection.classList.toggle('hidden', completedImages.length === 0);
                }
            } else {
                document.getElementById('review-method-title').textContent = 'Pre-Set Curated Package';
                document.getElementById('review-media-title').textContent = 'Selected Package';
                if (reviewAiSection) reviewAiSection.classList.add('hidden');
                multiRow?.classList.add('hidden');

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

                            // Render inclusions (Admin-entered text: inserted as text, never as HTML)
                            if (inclusionsWrapper && inclusionsList) {
                                inclusionsList.innerHTML = '';
                                (Array.isArray(pkgData.included_items) ? pkgData.included_items : []).forEach(item => {
                                    const li = document.createElement('li');
                                    li.className = 'flex items-start gap-1.5';
                                    li.innerHTML = '<svg class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>';
                                    const text = document.createElement('span');
                                    text.textContent = item;
                                    li.appendChild(text);
                                    inclusionsList.appendChild(li);
                                });
                                inclusionsWrapper.classList.toggle('hidden', inclusionsList.childElementCount === 0);
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
            const notes = document.getElementById('special_requests')?.value.trim() || '';

            document.getElementById('review-event-type').textContent = typeText;
            document.getElementById('review-event-date').textContent = formatDateTime(dateStr);
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
            const guestNameVal = document.getElementById('guest_name')?.value.trim() || '-';
            const guestEmailVal = document.getElementById('guest_email')?.value.trim() || '-';
            const guestPhoneVal = document.getElementById('guest_phone')?.value.trim() || '-';
            const guestAddressVal = document.getElementById('guest_address')?.value.trim() || '-';

            document.getElementById('review-contact-name').textContent = guestNameVal;
            document.getElementById('review-contact-email').textContent = guestEmailVal;
            document.getElementById('review-contact-phone').textContent = guestPhoneVal;
            document.getElementById('review-contact-address').textContent = guestAddressVal;

            document.getElementById('summary-type').textContent = typeText;
            document.getElementById('summary-date').textContent = formatDateTime(dateStr);

            // Mobile summary indicators for collapsible cards
            const mobileEvent = document.getElementById('review-event-mobile-summary');
            if (mobileEvent) {
                const parts = [typeText !== '-' ? typeText : '', formatDateTime(dateStr) !== '-' ? formatDateTime(dateStr) : '', timeSpan !== '-' ? timeSpan : ''].filter(Boolean);
                mobileEvent.textContent = parts.length ? parts.join(' · ') : '-';
            }

            const mobileMethod = document.getElementById('review-method-mobile-summary');
            if (mobileMethod) {
                mobileMethod.textContent = method === 'custom_ai' ? 'Smart AI Custom Design' : 'Curated Package';
            }

            const mobileMedia = document.getElementById('review-media-mobile-summary');
            if (mobileMedia) {
                if (method === 'custom_ai') {
                    const count = guestPhotos ? guestPhotos.readyPhotos().length : 0;
                    mobileMedia.textContent = count > 0 ? `${count} photo${count === 1 ? '' : 's'} analyzed` : 'Pending analysis';
                } else {
                    mobileMedia.textContent = document.getElementById('review-media-subtitle')?.textContent || 'Package selected';
                }
            }

            const mobileContact = document.getElementById('review-contact-mobile-summary');
            if (mobileContact) {
                const contactParts = [guestNameVal !== '-' ? guestNameVal : '', guestPhoneVal !== '-' ? guestPhoneVal : ''].filter(Boolean);
                mobileContact.textContent = contactParts.length ? contactParts.join(' · ') : '-';
            }
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
                const presetErrorEl = document.getElementById('step1-preset-error');

                if (bookingType === 'custom_ai') {
                    if (presetErrorEl) presetErrorEl.classList.add('hidden');
                    // Every photo must be analyzed for the current event details and preferences.
                    const issue = guestPhotos ? guestPhotos.blockingIssue() : 'Add at least one inspiration photo.';
                    const aiErrorEl = document.getElementById('step1-ai-error');
                    if (aiErrorEl) {
                        aiErrorEl.textContent = issue || '';
                        aiErrorEl.classList.toggle('hidden', !issue);
                    }
                    if (issue) {
                        showValidationModal([{ label: 'Inspiration Photos', message: issue }], document.getElementById('uploaded-images-section'));
                        return false;
                    }
                } else if (bookingType === 'preset') {
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
                        nav.removeAttribute('aria-current');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300 shadow-md';
                        icon.innerHTML = '<svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg><span class="sr-only">Step ' + s + ' done</span>';
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-emerald-800 transition-colors';
                    } else if (s === step) {
                        // Current step
                        nav.classList.remove('opacity-60');
                        nav.setAttribute('aria-current', 'step');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300 shadow-md ring-4 ring-emerald-100';
                        icon.textContent = s;
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-[#0B1E43] transition-colors';
                    } else {
                        // Future step
                        nav.classList.add('opacity-60');
                        nav.removeAttribute('aria-current');
                        icon.className = 'w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#EBF0F9] text-[#4A6084] flex items-center justify-center font-bold text-sm sm:text-base shrink-0 transition-all duration-300';
                        icon.textContent = s;
                        if (title) title.className = 'text-xs sm:text-sm font-bold whitespace-nowrap text-slate-500 transition-colors';
                    }
                }
            });

            // Show current step container
            const activeStepEl = document.getElementById(`guest-step-${step}`);
            if (activeStepEl) activeStepEl.classList.remove('hidden');

            // Update page header title
            const pageTitleEl = document.getElementById('booking-page-title');
            if (pageTitleEl) {
                pageTitleEl.textContent = step === 4 ? 'Review your booking request' : 'Create Event Booking';
            }

            // Update step description
            const stepDescriptions = {
                1: 'Tell us about your event. Provide the basic details, schedule, and venue so we can prepare the best floral arrangements for you.',
                2: 'Choose how to design your florals. For a custom design, add your preferences and inspiration photos; the AI suggests materials for Raflora to review.',
                3: 'Provide your contact details so our team can coordinate with you.',
                4: 'Please review all information below before submitting your booking request.'
            };
            const descEl = document.getElementById('step-description');
            if (descEl && stepDescriptions[step]) {
                descEl.textContent = stepDescriptions[step];
            }

            // Progress lines
            [1, 2, 3].forEach(line => {
                const el = document.getElementById(`progress-line-${line}`);
                if (el) el.style.width = step > line ? '100%' : '0%';
            });

            // Navigation Buttons
            const navFooter = document.getElementById('wizard-nav-footer');
            const btnBack = document.getElementById('btn-back');
            const btnNext = document.getElementById('btn-next');
            const btnSubmit = document.getElementById('btn-submit');
            const pagination = document.getElementById('package-pagination');
            const isPreset = document.querySelector('input[name="booking_type"][value="preset"]')?.checked;

            if (navFooter) {
                navFooter.style.display = step === 1 ? 'none' : 'flex';
                navFooter.classList.toggle('hidden', step === 1);
            }
            btnBack.classList.toggle('hidden', step === 1);
            btnNext.classList.toggle('hidden', step === 4);
            btnSubmit.classList.toggle('hidden', step !== 4);
            if (pagination) pagination.classList.toggle('hidden', !(step === 2 && isPreset));

            // Photos are analyzed while Step 2 is shown; queued ones wait while the guest edits elsewhere.
            if (step === 2) guestPhotos?.resume();

            window.scrollTo({ top: 0, behavior: 'smooth' });
            saveGuestDraft();
        }

        window.navigateGuest = function(dir) {
            showGuestStep(currentStep + dir);
        }

        // ==========================================
        // AREA DETAILS MODAL & ACCORDION CONTROLLERS
        // ==========================================
        let currentModalAreaKey = 'stage';
        let modalOpenerEl = null;
        let modalItems = [];
        let modalShowAll = false;

        window.toggleReviewCard = function(cardId) {
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

        window.switchGuestMode = function(mode) {
            const cardAi = document.getElementById('guest-card-ai');
            const cardPreset = document.getElementById('guest-card-preset');
            const aiCheck = document.getElementById('ai-check');
            const presetCheck = document.getElementById('preset-check');
            const presetIconBox = document.getElementById('preset-icon-box');
            const secAi = document.getElementById('guest-section-ai');
            const secPreset = document.getElementById('guest-section-preset');
            const presetErrorEl = document.getElementById('step1-preset-error');
            const isAi = mode === 'ai';

            const radio = document.querySelector(`input[name="booking_type"][value="${isAi ? 'custom_ai' : 'preset'}"]`);
            if (radio) radio.checked = true;

            cardAi.classList.toggle('border-emerald-600', isAi);
            cardAi.classList.toggle('bg-emerald-50/20', isAi);
            cardAi.classList.toggle('border-slate-200', !isAi);
            cardAi.classList.toggle('bg-white', !isAi);
            cardPreset.classList.toggle('border-emerald-600', !isAi);
            cardPreset.classList.toggle('bg-emerald-50/20', !isAi);
            cardPreset.classList.toggle('border-slate-200', isAi);
            cardPreset.classList.toggle('bg-white', isAi);
            cardAi.setAttribute('aria-checked', isAi ? 'true' : 'false');
            cardPreset.setAttribute('aria-checked', isAi ? 'false' : 'true');
            aiCheck.classList.toggle('hidden', !isAi);
            presetCheck.classList.toggle('hidden', isAi);
            if (presetIconBox) {
                presetIconBox.classList.toggle('bg-emerald-100', !isAi);
                presetIconBox.classList.toggle('text-emerald-600', !isAi);
                presetIconBox.classList.toggle('bg-slate-100', isAi);
                presetIconBox.classList.toggle('text-slate-500', isAi);
            }

            // Photos stay when switching: a package request ignores them, and switching back keeps them.
            secAi.classList.toggle('hidden', !isAi);
            secPreset.classList.toggle('hidden', isAi);
            if (isAi && presetErrorEl) presetErrorEl.classList.add('hidden');

            const pagination = document.getElementById('package-pagination');
            if (pagination) pagination.classList.toggle('hidden', isAi || currentStep !== 2);
            if (isAi) guestPhotos?.refresh();
            saveGuestDraft();
        }

        function highlightPackageCard(card) {
            document.querySelectorAll('.guest-package-card').forEach(c => {
                c.classList.remove('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
                c.querySelector('.package-badge')?.classList.add('hidden');
            });
            if (!card) return;
            card.classList.add('ring-2', 'ring-emerald-600', 'border-emerald-600', 'shadow-lg');
            card.querySelector('.package-badge')?.classList.remove('hidden');
        }

        // Setup package cards selection logic
        document.querySelectorAll('.guest-package-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if(e.target.closest('.view-package-btn')) return;

                const presetErr = document.getElementById('step1-preset-error');
                if (presetErr) presetErr.classList.add('hidden');

                const radio = this.querySelector('input[type="radio"]');
                if(radio) {
                    radio.checked = true;
                    highlightPackageCard(this);

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
        // Guest and table counts anchor the AI's quantities; a delivery order has neither, so they are
        // hidden, cleared and left out of the submission (disabled).
        function syncDeliveryFields() {
            const isDelivery = eventSelect?.value === 'delivery';
            document.querySelectorAll('[data-delivery-hidden]').forEach(el => el.classList.toggle('hidden', isDelivery));
            ['guest_count', 'table_count'].map(id => document.getElementById(id)).filter(Boolean).forEach(input => {
                if (isDelivery) input.value = '';
                input.disabled = isDelivery;
            });
        }
        if(eventSelect) {
            syncDeliveryFields();
            eventSelect.addEventListener('change', syncDeliveryFields);
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

        // The event details an analysis depends on (the server hashes the same details and refuses
        // photos analyzed with different ones when the booking is submitted).
        function getEventContextFingerprint() {
            const eventType = document.getElementById('event_type')?.value || '';
            const otherEventType = document.getElementById('other_event_type')?.value || '';
            return [
                eventType === 'other' ? (otherEventType.trim() || 'other') : eventType.trim(),
                ...['event_date', 'event_time', 'end_time', 'venue_city', 'venue_specific', 'venue', 'guest_count', 'table_count', 'special_requests']
                    .map(id => (document.getElementById(id)?.value || '').trim()),
                (document.querySelector('input[name="venue_setting"]:checked')?.value || '').trim(),
            ].join('|');
        }

        // The same details, sent with each photo to the analysis endpoint.
        function appendGuestEventContext(formData) {
            formData.append('event_type', document.getElementById('event_type')?.value || '');
            formData.append('other_event_type', document.getElementById('other_event_type')?.value || '');
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
        }

        // Event details changed: an analysis in flight for the old details is cancelled, and finished
        // photos show as out of date until they are re-analyzed (or the details are changed back).
        function handleEventContextChanged() {
            guestPhotos?.handleContextChanged();
        }

        window.cancelActiveAnalysis = function() {
            guestPhotos?.cancel();
        };

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
            guestPhotos = window.RafloraInspirationPhotos.create(document.getElementById('guest-inspiration-photos'), {
                contextFingerprint: getEventContextFingerprint,
                appendContext: appendGuestEventContext,
                isActive: () => currentStep === 2,
                onChange: () => {
                    saveGuestDraft();
                    // The inline photo error goes away once the photos are fine again.
                    if (guestPhotos && !guestPhotos.blockingIssue()) document.getElementById('step1-ai-error')?.classList.add('hidden');
                },
            });

            // Real-time clearing of field errors & draft auto-save for contact fields (non-context affecting)
            ['guest_name', 'guest_email', 'guest_phone', 'guest_address'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    ['input', 'change'].forEach(evt => {
                        el.addEventListener(evt, function() {
                            this.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
                            this.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                            this.setAttribute('aria-invalid', 'false');
                            const errEl = document.getElementById(id + '_error');
                            if (errEl) errEl.classList.add('hidden');
                            saveGuestDraft();
                        });
                    });
                }
            });

            // Context-affecting Step 1 fields: invalidate in-flight or completed analyses when changed
            ['other_event_type', 'event_date', 'event_time', 'end_time', 'guest_count', 'table_count', 'venue_city', 'venue_specific', 'special_requests'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    ['input', 'change'].forEach(evt => {
                        el.addEventListener(evt, function() {
                            this.classList.remove('border-rose-400', 'bg-rose-50/20', 'focus:border-rose-500', 'focus:ring-rose-500/20');
                            this.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                            this.setAttribute('aria-invalid', 'false');
                            const errEl = document.getElementById(id + '_error');
                            if (errEl) errEl.classList.add('hidden');
                            if (id === 'venue_city' || id === 'venue_specific') {
                                updateVenueField();
                            }
                            handleEventContextChanged();
                            saveGuestDraft();
                        });
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
                    handleEventContextChanged();
                    saveGuestDraft();
                });
                venueFinder.addEventListener('location-finder:clear', function() {
                    handleEventContextChanged();
                    saveGuestDraft();
                });
            }

            // Venue setting and design brief are saved with the draft as they change.
            document.querySelectorAll('input[name="venue_setting"]').forEach(el => {
                el.addEventListener('change', function() {
                    handleEventContextChanged();
                    saveGuestDraft();
                });
            });
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
                    handleEventContextChanged();
                    saveGuestDraft();
                });
            }

            @if($guestDisplayErrors->isNotEmpty())
                const serverValidationErrors = @json($guestDisplayErrors->values());
                showValidationModal(serverValidationErrors, document.querySelector('[aria-invalid="true"]') || document.getElementById('event_type'));
            @endif

            // Initialize package pagination
            renderPackagePagination();

            let restored = false;
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('reset') || urlParams.has('new')) {
                clearGuestDraft();
            } else if (@json($guestDisplayErrors->isEmpty())) {
                restored = restoreGuestDraft();
            } else {
                // The server sent the form back with errors: its fields come from the submission,
                // and the photos from the saved draft (which has their previews and analysis).
                guestPhotos.importState(readGuestDraft()?.uploadedImages || []);
            }

            if (!restored) {
                // Photos analyzed in this session that came back with the form, when no draft has them.
                if (guestPhotos.photos().length === 0) {
                    const sent = guestPhotos.initialInputs();
                    let tokens = [];
                    try { tokens = JSON.parse(sent.tokens || '[]'); } catch (e) {}
                    if ((!Array.isArray(tokens) || tokens.length === 0) && sent.token.trim()) tokens = [sent.token.trim()];
                    let analysis = null;
                    try { analysis = JSON.parse(sent.data || 'null'); } catch (e) {}
                    if (Array.isArray(tokens) && tokens.length) guestPhotos.restoreTokens(tokens, { analysis });
                }

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
                const oldBookingType = @json(old('booking_type', request('package_id') ? 'preset' : 'custom_ai'));
                const hasOldPackage = {{ (old('package_id') || request('package_id')) ? 'true' : 'false' }};
                if (oldBookingType === 'preset' || hasOldPackage || urlParams.has('package_id')) {
                    switchGuestMode('preset');
                    highlightPackageCard(document.querySelector('input[name="package_id"]:checked')?.closest('.guest-package-card'));
                } else {
                    switchGuestMode('ai');
                }

                // Initialize display to specific step if server returned validation errors or default
                const initialGuestStep = {{ $initialStep }};
                showGuestStep(initialGuestStep, true);
            }

            // Photos the server refused at submission are marked, and the guest lands where they can fix them.
            if (serverAnalysisError) {
                guestPhotos.markServerRejected(serverAnalysisError);
                switchGuestMode('ai');
                showGuestStep(2, true);
            }
            handleEventContextChanged();

            // Duplicate submission prevention. The draft is kept until the booking is saved (the
            // tracking page clears it), so a refused submission does not lose the guest's work.
            const guestForm = document.getElementById('guest-booking-form');
            const submitBtn = document.getElementById('btn-submit');
            if (guestForm) {
                guestForm.addEventListener('submit', function (e) {
                    if (guestForm.dataset.submitting === 'true') {
                        e.preventDefault();
                        return false;
                    }
                    saveGuestDraft();
                    guestForm.dataset.submitting = 'true';
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
                    guestForm.dataset.submitting = 'false';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        submitBtn.innerHTML = `
                            Submit Booking Request
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>`;
                    }
                });
            }

            // Area Details Modal Event Listeners
            document.getElementById('area-modal-edit-note-btn')?.addEventListener('click', function() {
                document.getElementById('area-modal-note-display')?.classList.add('hidden');
                document.getElementById('area-modal-note-editor')?.classList.remove('hidden');
                document.getElementById('area-modal-note-input')?.focus();
            });

            document.getElementById('area-modal-cancel-note-btn')?.addEventListener('click', function() {
                syncModalNoteUI();
            });

            document.getElementById('area-modal-note-input')?.addEventListener('input', function() {
                const charCount = document.getElementById('area-modal-note-char-count');
                if (charCount) charCount.textContent = `${this.value.length} / 500 characters`;
            });

            document.getElementById('area-modal-save-note-btn')?.addEventListener('click', function() {
                const textarea = document.getElementById('area-modal-note-input');
                const val = (textarea?.value || '').trim();

                let input = document.querySelector(`input[name="decor_area_notes[${currentModalAreaKey}]"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `decor_area_notes[${currentModalAreaKey}]`;
                    input.setAttribute('data-area-note-input', currentModalAreaKey);
                    document.getElementById('guest-booking-form')?.appendChild(input);
                }
                input.value = val;
                input.dispatchEvent(new Event('change', { bubbles: true }));

                const briefInstance = window.RafloraDesignBrief?.get('design-brief');
                if (briefInstance) {
                    const st = briefInstance.getState() || {};
                    st.decor_area_notes = st.decor_area_notes || {};
                    st.decor_area_notes[currentModalAreaKey] = val;
                    briefInstance.setState(st);
                }

                saveGuestDraft();
                syncModalNoteUI();
            });

            document.getElementById('area-modal-toggle-more-btn')?.addEventListener('click', function() {
                modalShowAll = !modalShowAll;
                renderModalMaterials();
            });

            document.addEventListener('keydown', function(e) {
                const modal = document.getElementById('booking-area-details-modal');
                if (!modal || modal.classList.contains('hidden')) return;
                if (e.key === 'Escape') closeAreaDetailsModal();
            });

            document.getElementById('booking-area-details-modal')?.addEventListener('click', function(e) {
                if (e.target === this) closeAreaDetailsModal();
            });
        });

        // Step 4 review dialogs. AI Analysis Summary: the existing section, filled and shown/hidden by
        // populateReview() as before. Contact Information: the Step 3 fields themselves are moved into the
        // dialog while it is open, so there is still one form, one validation and one draft.
        document.addEventListener('DOMContentLoaded', function() {
            const aiSection = document.getElementById('review-ai-materials-section');
            const aiOpenBtn = document.getElementById('open-review-ai-summary');
            const contactModal = document.getElementById('review-contact-modal');
            const contactFields = document.getElementById('guest-contact-fields');
            const contactSlot = contactModal?.querySelector('[data-contact-fields-slot]');
            const contactIds = ['guest_name', 'guest_email', 'guest_phone', 'guest_address'];
            let fieldsHome = null;
            let snapshot = null;
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

            function moveContactFieldsIn() {
                if (!contactFields || !contactSlot || fieldsHome) return;
                snapshot = Object.fromEntries(contactIds.map(id => [id, document.getElementById(id)?.value ?? '']));
                fieldsHome = document.createComment('guest-contact-fields');
                contactFields.parentNode.insertBefore(fieldsHome, contactFields);
                contactSlot.appendChild(contactFields);
            }

            function moveContactFieldsBack() {
                if (!fieldsHome) return;
                fieldsHome.parentNode.insertBefore(contactFields, fieldsHome);
                fieldsHome.remove();
                fieldsHome = null;
                snapshot = null;
            }

            // Cancel: put back the values the dialog opened with (the existing listeners re-save the draft).
            function restoreContactValues() {
                if (!snapshot) return;
                contactIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (el.value !== snapshot[id]) {
                        el.value = snapshot[id];
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    clearField(el, id + '_error');
                });
            }

            function openDialog(id, trigger) {
                const modal = document.getElementById(id);
                if (!modal) return;
                opener = trigger || document.activeElement;
                if (modal === contactModal) moveContactFieldsIn();
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                (modal === contactModal ? document.getElementById('guest_name') : modal.querySelector('[data-review-dialog-close]'))?.focus();
            }

            function closeDialog(modal, restore = true) {
                if (!modal || modal.classList.contains('hidden')) return;
                if (modal === contactModal) {
                    if (restore) restoreContactValues();
                    moveContactFieldsBack();
                }
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

            // Save: the same Step 3 validation; on errors the dialog stays open with messages beside the fields.
            contactModal?.querySelector('[data-contact-save]')?.addEventListener('click', function() {
                if (!validateStep(3)) return;
                closeDialog(contactModal, false);
                populateReview();
            });

            // Escape closes the top review dialog, unless the validation or area-details dialog is on top of it.
            document.addEventListener('keydown', function(e) {
                if (e.key !== 'Escape' || isOpen('raflora-validation-modal') || isOpen('booking-area-details-modal')) return;
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
</x-app-layout>
