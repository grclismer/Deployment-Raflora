<x-app-layout title="Account Settings">
    <x-client-layout active="account-settings">
        @php
            $profileImage = $user->profile_image && \Storage::disk('public')->exists($user->profile_image)
                ? asset('storage/' . $user->profile_image)
                : null;
            $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
            $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
            $firstName = $user->first_name ?: ($nameParts[0] ?? '');
            $lastName = $user->last_name ?: ($nameParts[1] ?? '');
            $openSecurity = $errors->hasAny(['current_password', 'new_password']);
        @endphp

        <div class="mx-auto max-w-4xl space-y-6">
            <section class="rf-panel p-5 sm:p-8" aria-labelledby="account-heading">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        @if($profileImage)
                            <img src="{{ $profileImage }}" alt="Profile image for {{ $user->name }}" class="h-16 w-16 shrink-0 rounded-full border border-slate-200 object-cover shadow-sm">
                        @else
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-lg font-bold text-emerald-700 ring-1 ring-emerald-100" aria-hidden="true">
                                {{ $initials ?: 'R' }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Your account</p>
                            <h1 id="account-heading" class="rf-page-title truncate text-2xl font-bold text-slate-900 sm:text-3xl">{{ $user->name }}</h1>
                            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                <span class="break-all">{{ $user->email }}</span>
                                @if($user->email_verified_at)
                                    <span class="rf-badge rf-badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 sm:w-auto">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            <span>Log Out</span>
                        </button>
                    </form>
                </div>
            </section>

            @if($errors->any())
                <div class="rf-alert rf-alert--danger" role="alert">
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account-settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <section class="rf-panel p-5 sm:p-8" aria-labelledby="profile-heading">
                    <div class="mb-5">
                        <h2 id="profile-heading" class="text-lg font-semibold text-slate-900">Profile details</h2>
                        <p class="text-sm text-slate-500">Raflora uses these details for your bookings and event coordination.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <label class="rf-field">
                            <span class="rf-label">First name</span>
                            <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $firstName) }}" required autocomplete="given-name" class="form-control" @error('first_name') aria-invalid="true" @enderror />
                        </label>

                        <label class="rf-field">
                            <span class="rf-label">Last name</span>
                            <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $lastName) }}" required autocomplete="family-name" class="form-control" @error('last_name') aria-invalid="true" @enderror />
                        </label>

                        <label class="rf-field">
                            <span class="rf-label">Email</span>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email" class="form-control" @error('email') aria-invalid="true" @enderror />
                            <span class="rf-help-text">If you change your email, you'll verify the new address with a code before continuing.</span>
                        </label>

                        <label class="rf-field">
                            <span class="rf-label">Contact number</span>
                            <input id="mobile_number" type="tel" name="mobile_number" value="{{ old('mobile_number', $user->mobile_number) }}" autocomplete="tel" inputmode="tel" maxlength="20" class="form-control" @error('mobile_number') aria-invalid="true" @enderror />
                        </label>

                        <label class="rf-field md:col-span-2">
                            <span class="rf-label">Address</span>
                            <input id="address" type="text" name="address" value="{{ old('address', $user->address) }}" autocomplete="street-address" maxlength="500" class="form-control" @error('address') aria-invalid="true" @enderror />
                        </label>

                        <label class="rf-field md:col-span-2">
                            <span class="rf-label">Profile photo</span>
                            <input id="profile_image" type="file" name="profile_image" accept="image/*" class="form-control file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-700" @error('profile_image') aria-invalid="true" @enderror />
                            <span class="rf-help-text">Optional. JPG, PNG or WEBP up to 2 MB.</span>
                        </label>
                    </div>
                </section>

                <details class="rf-panel group p-5 sm:p-8" @if($openSecurity) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
                        <span>
                            <span class="block text-lg font-semibold text-slate-900">Password</span>
                            <span class="block text-sm text-slate-500">Leave these blank to keep your current password.</span>
                        </span>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition group-open:rotate-180" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </span>
                    </summary>

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
                        <label class="rf-field">
                            <span class="rf-label">Current password</span>
                            <input id="current_password" type="password" name="current_password" autocomplete="current-password" class="form-control" @error('current_password') aria-invalid="true" @enderror />
                        </label>
                        <label class="rf-field">
                            <span class="rf-label">New password</span>
                            <input id="new_password" type="password" name="new_password" autocomplete="new-password" minlength="8" class="form-control" @error('new_password') aria-invalid="true" @enderror />
                            <span class="rf-help-text">At least 8 characters.</span>
                        </label>
                        <label class="rf-field">
                            <span class="rf-label">Confirm new password</span>
                            <input id="new_password_confirmation" type="password" name="new_password_confirmation" autocomplete="new-password" class="form-control" />
                        </label>
                    </div>
                </details>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('client.dashboard') }}" class="rf-btn rf-btn-outline justify-center">Cancel</a>
                    <button type="submit" class="btn-primary justify-center">Save changes</button>
                </div>
            </form>
        </div>
    </x-client-layout>
</x-app-layout>
