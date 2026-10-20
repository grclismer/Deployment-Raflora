<x-admin-layout title="Account Settings">
    @php
        $isAdmin = auth()->user()?->role === 'admin';
        $user = $user ?? auth()->user();
        $hasProfileImage = $user->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_image);
        $userInitials = strtoupper(substr($user->name ?? 'A', 0, 1));

        // Authoritative active tab selection: query param, validation errors, or session state
        $validTabs = ['profile', 'security', 'administration'];
        $requestedTab = request()->query('tab');
        // A failed Add Account keeps its errors in the createAccount bag; its old input (name, email)
        // belongs to that dialog only and must not refill the Admin's own profile form.
        $accountFormFailed = $isAdmin && $errors->createAccount->any();
        if ($accountFormFailed) {
            $activeTab = 'administration';
        } elseif (in_array($requestedTab, $validTabs)) {
            $activeTab = $requestedTab;
        } elseif (session('impact_preview')) {
            $activeTab = 'administration';
        } elseif ($errors->has('current_password') || $errors->has('password') || $errors->has('password_confirmation')) {
            $activeTab = 'security';
        } elseif (str_contains(session('success') ?? '', 'password') || str_contains(session('success') ?? '', 'sessions') || str_contains(session('success') ?? '', 'Trusted device')) {
            $activeTab = 'security';
        } elseif (str_contains(session('success') ?? '', 'Account created')) {
            $activeTab = 'administration';
        } else {
            $activeTab = 'profile';
        }
    @endphp

    <div class="mx-auto max-w-[1600px] space-y-4 sm:space-y-5">
        <!-- Compact Page Header -->
        <div class="border-b border-slate-200/80 pb-3">
            <h1 class="serif text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Account Settings</h1>
            <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">Manage your account information, security, and administrative settings.</p>
        </div>

        <!-- Global Flash Alerts -->
        @if(session('success'))
            <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-800 shadow-2xs sm:text-sm" role="alert">
                <i class="fa-solid fa-circle-check text-sm text-emerald-600 shrink-0" aria-hidden="true"></i>
                <div class="font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs text-rose-800 shadow-2xs sm:text-sm" role="alert">
                <i class="fa-solid fa-circle-exclamation text-sm text-rose-600 shrink-0" aria-hidden="true"></i>
                <div class="font-medium">{{ session('error') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs text-rose-800 shadow-2xs" role="alert">
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-rose-600 shrink-0" aria-hidden="true"></i>
                    <div>
                        <strong class="font-bold">Please correct the following errors:</strong>
                        <ul class="mt-0.5 list-disc pl-4 space-y-0.5 text-[11px]">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Compact Tab Navigation Bar -->
        <div class="border-b border-slate-200/80 bg-white rounded-xl px-3 pt-1 shadow-2xs">
            <nav class="flex space-x-2 sm:space-x-5" role="tablist" aria-label="Account Settings Sections">
                <button
                    type="button"
                    onclick="switchSettingsTab('profile')"
                    id="tab-btn-profile"
                    role="tab"
                    aria-controls="tab-pane-profile"
                    aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}"
                    class="tab-btn group inline-flex items-center gap-2 border-b-2 px-2.5 py-2.5 text-xs sm:text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 rounded-t-md {{ $activeTab === 'profile' ? 'border-rose-600 text-rose-600 font-bold' : 'border-transparent text-slate-500 font-semibold hover:border-slate-300 hover:text-slate-800' }}"
                    @if($activeTab === 'profile') aria-current="page" @endif
                >
                    <i class="fa-regular fa-user {{ $activeTab === 'profile' ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' }}" aria-hidden="true"></i>
                    <span>Profile</span>
                </button>
                <button
                    type="button"
                    onclick="switchSettingsTab('security')"
                    id="tab-btn-security"
                    role="tab"
                    aria-controls="tab-pane-security"
                    aria-selected="{{ $activeTab === 'security' ? 'true' : 'false' }}"
                    class="tab-btn group inline-flex items-center gap-2 border-b-2 px-2.5 py-2.5 text-xs sm:text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 rounded-t-md {{ $activeTab === 'security' ? 'border-rose-600 text-rose-600 font-bold' : 'border-transparent text-slate-500 font-semibold hover:border-slate-300 hover:text-slate-800' }}"
                    @if($activeTab === 'security') aria-current="page" @endif
                >
                    <i class="fa-solid fa-shield-halved {{ $activeTab === 'security' ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' }}" aria-hidden="true"></i>
                    <span>Security</span>
                </button>
                <button
                    type="button"
                    onclick="switchSettingsTab('administration')"
                    id="tab-btn-administration"
                    role="tab"
                    aria-controls="tab-pane-administration"
                    aria-selected="{{ $activeTab === 'administration' ? 'true' : 'false' }}"
                    class="tab-btn group inline-flex items-center gap-2 border-b-2 px-2.5 py-2.5 text-xs sm:text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 rounded-t-md {{ $activeTab === 'administration' ? 'border-rose-600 text-rose-600 font-bold' : 'border-transparent text-slate-500 font-semibold hover:border-slate-300 hover:text-slate-800' }}"
                    @if($activeTab === 'administration') aria-current="page" @endif
                >
                    <i class="fa-solid fa-sliders {{ $activeTab === 'administration' ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600' }}" aria-hidden="true"></i>
                    <span>Administration</span>
                </button>
            </nav>
        </div>

        <!-- ======================================================= -->
        <!-- TAB PANE 1: PROFILE                                      -->
        <!-- ======================================================= -->
        <div id="tab-pane-profile" role="tabpanel" aria-labelledby="tab-btn-profile" class="tab-pane {{ $activeTab === 'profile' ? '' : 'hidden' }}">
            <div class="grid grid-cols-1 gap-4 sm:gap-5 xl:grid-cols-12">
                <!-- Main Content (Left: 8 cols) -->
                <div class="space-y-4 xl:col-span-8 sm:space-y-5">
                    <!-- Profile Information Card -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                    <i class="fa-regular fa-user text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 sm:text-base">Profile Information</h2>
                                    <p class="text-[11px] text-slate-500 sm:text-xs">Update your personal and professional information.</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200/60">
                                <i class="fa-solid fa-circle text-[6px] text-emerald-500" aria-hidden="true"></i> Active Account
                            </span>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.profile.update') }}" enctype="multipart/form-data" id="adminProfileForm" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="remove_profile_image" id="remove_profile_image" value="0">

                            <!-- Avatar Upload Section (Matches Visual Mockup) -->
                            <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-5 py-1">
                                <div class="relative shrink-0">
                                    <div id="avatarPreviewContainer" class="h-20 w-20 rounded-full overflow-hidden border-2 border-rose-200 bg-rose-50 flex items-center justify-center shadow-xs">
                                        @if($hasProfileImage)
                                            <img id="avatarPreviewImg" src="{{ asset('storage/' . $user->profile_image) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                                        @else
                                            <div id="avatarInitialsFallback" class="text-2xl font-bold text-rose-700">{{ $userInitials }}</div>
                                            <img id="avatarPreviewImg" src="" alt="Profile Preview" class="h-full w-full object-cover hidden">
                                        @endif
                                    </div>
                                    <div class="absolute bottom-0 right-0 flex h-6 w-6 items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500 shadow-2xs">
                                        <i class="fa-solid fa-camera text-[10px]" aria-hidden="true"></i>
                                    </div>
                                </div>

                                <div class="flex-1 text-center sm:text-left">
                                    <label for="profile_image_input" class="group flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200/90 bg-slate-50/60 px-4 py-3 cursor-pointer hover:border-rose-400 hover:bg-rose-50/30 transition">
                                        <i class="fa-solid fa-cloud-arrow-up text-base text-slate-400 group-hover:text-rose-500 transition" aria-hidden="true"></i>
                                        <span class="mt-1 text-xs font-bold text-slate-700 group-hover:text-rose-700 transition">Upload New Photo</span>
                                        <span class="text-[10px] text-slate-400">JPG, PNG or WebP. Max size 2MB.</span>
                                        <input type="file" id="profile_image_input" name="profile_image" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">
                                    </label>
                                    @if($hasProfileImage)
                                        <div class="mt-2 text-center sm:text-left">
                                            <button type="button" id="removePhotoBtn" class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-800 transition">
                                                <i class="fa-solid fa-trash-can text-[10px]" aria-hidden="true"></i> Remove Photo
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Compact Form Fields Grid -->
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                                <!-- Full Name -->
                                <div>
                                    <label for="profile_name" class="block text-xs font-semibold text-slate-700">Full Name <span class="text-rose-500">*</span></label>
                                    <input
                                        type="text"
                                        id="profile_name"
                                        name="name"
                                        value="{{ $accountFormFailed ? $user->name : old('name', $user->name) }}"
                                        required
                                        maxlength="255"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 shadow-2xs"
                                        placeholder="e.g. Maria Santos"
                                    >
                                </div>

                                <!-- Email Address (Read-only with OTP link) -->
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label for="profile_email" class="block text-xs font-semibold text-slate-700">Email Address <span class="text-rose-500">*</span></label>
                                        <a href="{{ route('admin.email-change.show') }}" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:underline">
                                            Change Email <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-0.5" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                    <input
                                        type="email"
                                        id="profile_email"
                                        value="{{ $user->email }}"
                                        readonly
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs sm:text-sm text-slate-500 cursor-not-allowed shadow-2xs"
                                        title="Email is protected. Use the Change Email OTP verification flow."
                                    >
                                    <p class="mt-0.5 text-[10px] text-slate-400">Email updates require identity verification via 6-digit OTP.</p>
                                </div>

                                <!-- Role (Read-only) -->
                                <div>
                                    <label for="profile_role" class="block text-xs font-semibold text-slate-700">Role</label>
                                    <input
                                        type="text"
                                        id="profile_role"
                                        value="{{ ucfirst($user->role) }}"
                                        readonly
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs sm:text-sm text-slate-500 cursor-not-allowed capitalize shadow-2xs"
                                    >
                                    <p class="mt-0.5 text-[10px] text-slate-400">Administrative access level is administratively governed.</p>
                                </div>

                                <!-- Phone Number -->
                                <div>
                                    <label for="profile_mobile" class="block text-xs font-semibold text-slate-700">Phone Number</label>
                                    <input
                                        type="text"
                                        id="profile_mobile"
                                        name="mobile_number"
                                        value="{{ old('mobile_number', $user->mobile_number) }}"
                                        maxlength="20"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 shadow-2xs"
                                        placeholder="0917 123 4567"
                                    >
                                </div>

                                <!-- Address -->
                                <div class="sm:col-span-2">
                                    <label for="profile_address" class="block text-xs font-semibold text-slate-700">Address</label>
                                    <textarea
                                        id="profile_address"
                                        name="address"
                                        rows="2"
                                        maxlength="500"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 shadow-2xs"
                                        placeholder="Quezon City, Metro Manila"
                                    >{{ old('address', $user->address) }}</textarea>
                                </div>
                            </div>

                            <div class="flex items-center justify-end border-t border-slate-100 pt-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-xs font-bold text-white shadow-2xs hover:bg-rose-700 transition"
                                >
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

                <!-- Side Content (Right: 4 cols) -->
                <div class="space-y-4 xl:col-span-4 sm:space-y-5">
                    <!-- Compact Account Overview Card (Matches Visual Mockup) -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                <i class="fa-regular fa-id-badge text-xs" aria-hidden="true"></i>
                            </span>
                            <h3 class="text-sm font-bold text-slate-900">Account Overview</h3>
                        </div>

                        <dl class="mt-3 space-y-2 text-[11px]">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="flex items-center gap-2 text-slate-600"><i class="fa-solid fa-building text-[10px] text-slate-400" aria-hidden="true"></i> Company</dt>
                                <dd class="font-semibold text-slate-800">Raflora Enterprises</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-1.5">
                                <dt class="flex items-center gap-2 text-slate-600"><i class="fa-regular fa-id-badge text-[10px] text-slate-400" aria-hidden="true"></i> Role</dt>
                                <dd><span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold capitalize text-rose-700 border border-rose-100">{{ $user->role === 'admin' ? 'Administrator' : ucfirst($user->role) }}</span></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-1.5">
                                <dt class="flex items-center gap-2 text-slate-600"><i class="fa-regular fa-calendar text-[10px] text-slate-400" aria-hidden="true"></i> Joined</dt>
                                <dd class="font-semibold text-slate-800">{{ optional($user->created_at)->format('M d, Y') ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-3.5 border-t border-slate-100 pt-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400 text-[11px] font-semibold">Account Status</span>
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-700 text-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </div>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                Your account is active and has full access to the system.
                            </p>
                        </div>
                    </div>

                    <!-- Compact Security Summary Card -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                    <i class="fa-solid fa-shield-halved text-xs" aria-hidden="true"></i>
                                </span>
                                <h3 class="text-sm font-bold text-slate-900">Security Summary</h3>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div class="flex items-center justify-between py-0.5">
                                <span class="text-slate-600 flex items-center gap-2 text-[11px]">
                                    <i class="fa-solid fa-key text-slate-400 text-[10px]" aria-hidden="true"></i> Password
                                </span>
                                <span class="font-semibold text-emerald-700 text-[11px]">Protected</span>
                            </div>
                            <div class="flex items-center justify-between py-0.5 border-t border-slate-100 pt-1.5">
                                <span class="text-slate-600 flex items-center gap-2 text-[11px]">
                                    <i class="fa-solid fa-envelope-circle-check text-slate-400 text-[10px]" aria-hidden="true"></i> Email Verification
                                </span>
                                <span class="font-semibold {{ $user->email_verified_at ? 'text-emerald-700' : 'text-amber-700' }} text-[11px]">
                                    {{ $user->email_verified_at ? 'Verified' : 'Unverified' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-0.5 border-t border-slate-100 pt-1.5">
                                <span class="text-slate-600 flex items-center gap-2 text-[11px]">
                                    <i class="fa-solid fa-laptop text-slate-400 text-[10px]" aria-hidden="true"></i> Trusted Devices
                                </span>
                                <span class="font-semibold text-slate-800 text-[11px]">
                                    {{ $trustedDevices->where('is_usable', true)->count() }} active
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-0.5 border-t border-slate-100 pt-1.5">
                                <span class="text-slate-600 flex items-center gap-2 text-[11px]">
                                    <i class="fa-solid fa-desktop text-slate-400 text-[10px]" aria-hidden="true"></i> Active Sessions
                                </span>
                                <span class="font-semibold text-slate-800 text-[11px]">
                                    {{ $activeSessions->count() }} active
                                </span>
                            </div>
                        </div>

                        <div class="mt-3.5 border-t border-slate-100 pt-3">
                            <button
                                type="button"
                                onclick="switchSettingsTab('security')"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                            >
                                <span>Manage Security Settings</span>
                                <i class="fa-solid fa-chevron-right text-[9px]" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- TAB PANE 2: SECURITY                                     -->
        <!-- ======================================================= -->
        <div id="tab-pane-security" role="tabpanel" aria-labelledby="tab-btn-security" class="tab-pane {{ $activeTab === 'security' ? '' : 'hidden' }}">
            {{-- Change Password and Email Security side by side; devices and sessions share one card below. --}}
            <div class="grid grid-cols-1 gap-4 sm:gap-5 xl:grid-cols-2 xl:items-start">
                    <!-- Change Password Card (Matches Visual Mockup) -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
                        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                <i class="fa-solid fa-lock text-sm" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 sm:text-base">Change Password</h2>
                                <p class="text-[11px] text-slate-500 sm:text-xs">Keep your account secure with a strong password.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.password.update') }}" class="mt-4 space-y-3">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                                <div class="space-y-3 lg:col-span-7">
                                    <div>
                                        <label for="sec_current_password" class="block text-xs font-semibold text-slate-700">Current Password <span class="text-rose-500">*</span></label>
                                        <div class="relative mt-1">
                                            <input
                                                type="password"
                                                id="sec_current_password"
                                                name="current_password"
                                                required
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs"
                                                placeholder="Enter current password"
                                            >
                                            <button type="button" class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600" aria-label="Show password" onclick="togglePassword('sec_current_password')">
                                                <i class="fa-solid fa-eye-slash text-xs" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="sec_new_password" class="block text-xs font-semibold text-slate-700">New Password <span class="text-rose-500">*</span></label>
                                        <div class="relative mt-1">
                                            <input
                                                type="password"
                                                id="sec_new_password"
                                                name="password"
                                                required
                                                minlength="8"
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs"
                                                placeholder="Enter new password"
                                            >
                                            <button type="button" class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600" aria-label="Show password" onclick="togglePassword('sec_new_password')">
                                                <i class="fa-solid fa-eye-slash text-xs" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="sec_password_confirmation" class="block text-xs font-semibold text-slate-700">Confirm New Password <span class="text-rose-500">*</span></label>
                                        <div class="relative mt-1">
                                            <input
                                                type="password"
                                                id="sec_password_confirmation"
                                                name="password_confirmation"
                                                required
                                                minlength="8"
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs"
                                                placeholder="Confirm new password"
                                            >
                                            <button type="button" class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-slate-600" aria-label="Show password" onclick="togglePassword('sec_password_confirmation')">
                                                <i class="fa-solid fa-eye-slash text-xs" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="pt-1">
                                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-rose-700 transition">
                                            <i class="fa-solid fa-key" aria-hidden="true"></i> Update Password
                                        </button>
                                    </div>
                                </div>

                                <div class="lg:col-span-5">
                                    <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-3.5 text-xs text-slate-700 space-y-2">
                                        <h3 class="font-bold text-rose-900 uppercase tracking-wider text-[10px]">Password Requirements</h3>
                                        <ul class="space-y-1.5 text-slate-600 text-[11px]">
                                            <li class="flex items-center gap-2">
                                                <i class="fa-solid fa-circle-check text-rose-600 text-[10px]" aria-hidden="true"></i>
                                                <span>At least 8 characters in length</span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <i class="fa-solid fa-circle-check text-rose-600 text-[10px]" aria-hidden="true"></i>
                                                <span>Confirmation must match exactly</span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <i class="fa-solid fa-circle-check text-rose-600 text-[10px]" aria-hidden="true"></i>
                                                <span>Must verify current password</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Email Security Card -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                    <i class="fa-solid fa-envelope-circle-check text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 sm:text-base">Email Security</h2>
                                    <p class="text-[11px] text-slate-500 sm:text-xs">Your account email and verification workflow.</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200/60">
                                <i class="fa-solid fa-check text-[9px]" aria-hidden="true"></i> Verified
                            </span>
                        </div>

                        <div class="mt-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl bg-slate-50/70 p-3.5 border border-slate-100">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Administrator Email</span>
                                <p class="mt-0.5 text-sm font-bold text-slate-800">{{ $user->email }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">Email changes require current password authentication and 6-digit OTP verification.</p>
                            </div>
                            <a
                                href="{{ route('admin.email-change.show') }}"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-800 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-700 transition shrink-0 shadow-2xs"
                            >
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Change Email
                            </a>
                        </div>
                    </div>

                    <!-- Trusted Devices / Active Sessions: one at a time -->
                    @php $deviceTab = str_contains(session('success') ?? '', 'sessions') ? 'sessions' : 'devices'; @endphp
                    <div class="space-y-3 xl:col-span-2" data-device-panels>
                        <div class="inline-flex rounded-xl border border-slate-200/80 bg-white p-1 shadow-2xs" role="tablist" aria-label="Devices and sessions">
                            <button type="button" role="tab" data-device-tab="devices" aria-selected="{{ $deviceTab === 'devices' ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:text-slate-900 aria-selected:bg-rose-600 aria-selected:text-white">
                                <i class="fa-solid fa-laptop-code" aria-hidden="true"></i> Trusted Devices
                                <span class="rounded-full bg-black/10 px-1.5 text-[10px]">{{ $trustedDevices->where('is_usable', true)->count() }}</span>
                            </button>
                            <button type="button" role="tab" data-device-tab="sessions" aria-selected="{{ $deviceTab === 'sessions' ? 'true' : 'false' }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:text-slate-900 aria-selected:bg-rose-600 aria-selected:text-white">
                                <i class="fa-solid fa-network-wired" aria-hidden="true"></i> Active Sessions
                                <span class="rounded-full bg-black/10 px-1.5 text-[10px]">{{ $activeSessions->count() }}</span>
                            </button>
                        </div>
                    <!-- Trusted Devices Card -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $deviceTab === 'devices' ? '' : 'hidden' }}" data-device-panel="devices">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                    <i class="fa-solid fa-laptop-code text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 sm:text-base">Trusted Devices</h2>
                                    <p class="text-[11px] text-slate-500 sm:text-xs">Devices verified for trusted-device login path.</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">
                                {{ $trustedDevices->where('is_usable', true)->count() }} active
                            </span>
                        </div>

                        <div class="mt-2.5 text-[11px] text-slate-500">
                            <i class="fa-solid fa-circle-info text-rose-600 mr-1" aria-hidden="true"></i> Device trust expires automatically after a fixed 30-day window from creation.
                        </div>

                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full min-w-[520px] text-left text-xs" aria-label="Trusted devices table">
                                <thead class="border-y border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5">Device Name</th>
                                        <th scope="col" class="px-3 py-2.5">Last Used</th>
                                        <th scope="col" class="px-3 py-2.5">Expires At</th>
                                        <th scope="col" class="px-3 py-2.5">Status</th>
                                        <th scope="col" class="px-3 py-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($trustedDevices as $device)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="px-3 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <i class="fa-solid fa-desktop text-slate-400 text-xs"></i>
                                                    <span class="font-semibold text-slate-800">{{ $device->device_name }}</span>
                                                    @if($device->is_current)
                                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-800">This Device</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5 text-[11px] text-slate-600">
                                                {{ $device->last_used_at ? $device->last_used_at->timezone('Asia/Manila')->format('M d, Y h:i A') : 'Never' }}
                                            </td>
                                            <td class="px-3 py-2.5 text-[11px] text-slate-600">
                                                {{ $device->expires_at ? $device->expires_at->timezone('Asia/Manila')->format('M d, Y h:i A') : 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2.5">
                                                @if($device->status === 'Active')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                        <i class="fa-solid fa-circle text-[5px]"></i> Active
                                                    </span>
                                                @elseif($device->status === 'Expired')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                                        <i class="fa-solid fa-circle text-[5px]"></i> Expired
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                        <i class="fa-solid fa-circle text-[5px]"></i> Revoked
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right">
                                                @if($device->is_usable)
                                                    <form method="POST" action="{{ route('admin.settings.trusted-devices.revoke') }}" data-confirm="Revoke trust for this device?" data-confirm-message="You will need OTP verification on future logins from that device." data-confirm-label="Revoke trust" data-confirm-tone="danger" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="device_id" value="{{ $device->id }}">
                                                        <button type="submit" class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 hover:underline">
                                                            Revoke
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[11px] text-slate-400">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-500">
                                                No trusted devices registered yet. Devices are registered upon successful OTP verification.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Active Sessions Card -->
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $deviceTab === 'sessions' ? '' : 'hidden' }}" data-device-panel="sessions">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                    <i class="fa-solid fa-network-wired text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 sm:text-base">Active Sessions</h2>
                                    <p class="text-[11px] text-slate-500 sm:text-xs">Manage your active login sessions across devices.</p>
                                </div>
                            </div>
                            @if($activeSessions->count() > 1)
                                <form method="POST" action="{{ route('admin.settings.sessions.revoke-others') }}" data-confirm="Sign out of all other sessions?" data-confirm-message="Your current session stays active." data-confirm-label="Sign out others" data-confirm-tone="danger">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50/60 px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out Other Sessions
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse($activeSessions as $session)
                                <div class="flex items-center justify-between py-2.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 shrink-0">
                                            <i class="fa-solid {{ $session->device_icon }} text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-semibold text-slate-800">{{ $session->device_label }}</span>
                                                @if($session->is_current)
                                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800">Current Session</span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-500">
                                                {{ $session->ip_address }} &bull;
                                                {{ $session->is_current ? 'Active now' : $session->last_activity->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right text-[11px] text-slate-400">
                                        {{ $session->last_activity->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                    </div>
                                </div>
                            @empty
                                <div class="py-5 text-center text-xs text-slate-500">
                                    No active sessions found.
                                </div>
                            @endforelse
                        </div>
                    </div>
                    </div>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- TAB PANE 3: ADMINISTRATION                               -->
        <!-- ======================================================= -->
        <div id="tab-pane-administration" role="tabpanel" aria-labelledby="tab-btn-administration" class="tab-pane {{ $activeTab === 'administration' ? '' : 'hidden' }} space-y-4 sm:space-y-5">
            {{-- Administration sections, one at a time. A failed save opens its own section; otherwise the
                 last section used (or a link such as #audit-trail) is reopened by the script below. --}}
            @php
                $adminSections = array_filter([
                    'team' => ['Team Accounts', 'fa-users'],
                    'business' => $isAdmin ? ['Business Configuration', 'fa-sliders'] : null,
                    'ai' => $isAdmin ? ['AI Estimate Pricing', 'fa-wand-magic-sparkles'] : null,
                    'audit' => ['Audit Trail', 'fa-list-check'],
                    'data' => $isAdmin ? ['System Data', 'fa-database'] : null,
                ]);
                $forcedAdminSection = match (true) {
                    $accountFormFailed || str_contains(session('success') ?? '', 'Account created') => 'team',
                    $isAdmin && (session('impact_preview') || $errors->hasAny(['downpayment_percentage', 'long_term_booking_threshold_days', 'price_reconfirmation_threshold_days'])) => 'business',
                    $isAdmin && $errors->hasAny(['ai_estimate_markup', 'ai_labour_type', 'ai_labour_value']) => 'ai',
                    default => null,
                };
                $adminSection = $forcedAdminSection ?? 'team';
            @endphp
            <nav class="flex gap-1.5 overflow-x-auto rounded-xl border border-slate-200/80 bg-white p-1.5 shadow-2xs" role="tablist" aria-label="Administration sections" data-admin-nav data-forced="{{ $forcedAdminSection ? '1' : '0' }}">
                @foreach($adminSections as $sectionKey => [$sectionLabel, $sectionIcon])
                    <button type="button" role="tab" data-admin-section-tab="{{ $sectionKey }}" aria-selected="{{ $sectionKey === $adminSection ? 'true' : 'false' }}"
                            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 aria-selected:bg-rose-600 aria-selected:text-white aria-selected:hover:bg-rose-600">
                        <i class="fa-solid {{ $sectionIcon }}" aria-hidden="true"></i>{{ $sectionLabel }}
                    </button>
                @endforeach
            </nav>

            <!-- Team Accounts Card -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $adminSection === 'team' ? '' : 'hidden' }}" data-admin-section="team">
                <div class="mb-3.5 flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                            <i class="fa-solid fa-users text-sm" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 sm:text-base">Team Accounts</h2>
                            <p class="text-[11px] text-slate-500 sm:text-xs">Admin and staff accounts with operations panel access.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ $accounts->count() }} account{{ $accounts->count() === 1 ? '' : 's' }}</span>
                        @if($isAdmin)
                            <button type="button" data-open-modal="accountModal" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800 transition shadow-2xs">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Account
                            </button>
                        @endif
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-xs" aria-label="Admin team accounts">
                        <thead class="border-y border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-3 py-2.5">Name</th>
                                <th scope="col" class="px-3 py-2.5">Email</th>
                                <th scope="col" class="px-3 py-2.5">Role</th>
                                <th scope="col" class="px-3 py-2.5">Verification</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($accounts as $account)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2.5 font-semibold text-slate-800">{{ $account->name }}</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ $account->email }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-full {{ $account->role === 'admin' ? 'bg-rose-50 text-rose-700 border border-rose-100' : 'bg-sky-50 text-sky-700 border border-sky-100' }} px-2 py-0.5 text-[10px] font-semibold capitalize">
                                            {{ $account->role }}
                                        </span>
                                    </td>
                                    {{-- Only email verification is recorded; new Staff verify their email at first sign-in. --}}
                                    @if($account->email_verified_at)
                                        <td class="px-3 py-2.5 text-xs font-semibold text-emerald-700">
                                            <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i> Email verified
                                        </td>
                                    @else
                                        <td class="px-3 py-2.5 text-xs font-semibold text-amber-700">
                                            <i class="fa-solid fa-clock mr-1" aria-hidden="true"></i> Awaiting first sign-in verification
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-slate-500">No admin or staff accounts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Business Configuration Card -->
            @if($isAdmin)
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $adminSection === 'business' ? '' : 'hidden' }}" data-admin-section="business">
                <div class="mb-3.5 flex items-center gap-2.5 border-b border-slate-100 pb-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <i class="fa-solid fa-sliders text-sm" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 sm:text-base">Business Configuration</h2>
                        <p class="text-[11px] text-slate-500 sm:text-xs">Secured operational thresholds controlling quotations &amp; reconfirmation.</p>
                    </div>
                </div>

                @if(session('impact_preview'))
                    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3.5 text-amber-900 text-xs" role="alert">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600 shrink-0" aria-hidden="true"></i>
                            <div>
                                <h3 class="text-xs font-bold text-amber-800">Explicit Confirmation Required</h3>
                                <p class="mt-0.5 text-[11px] text-amber-700">Please review operational impact of requested threshold change:</p>
                                <ul class="mt-1.5 list-disc pl-4 text-[11px] space-y-0.5 text-amber-800">
                                    @foreach(session('impact_preview')['impacts'] as $impact)
                                        <li>{{ $impact }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-3 pt-2.5 border-t border-amber-200 space-y-2">
                            @csrf
                            <input type="hidden" name="confirmed" value="1">
                            <input type="hidden" name="downpayment_percentage" value="{{ session('impact_preview')['pending_values']['downpayment_percentage'] }}">
                            <input type="hidden" name="long_term_booking_threshold_days" value="{{ session('impact_preview')['pending_values']['long_term_booking_threshold_days'] }}">
                            <input type="hidden" name="price_reconfirmation_threshold_days" value="{{ session('impact_preview')['pending_values']['price_reconfirmation_threshold_days'] }}">
                            <input type="hidden" name="change_reason" value="{{ session('impact_preview')['pending_values']['change_reason'] }}">

                            <div class="flex items-center gap-2">
                                <button type="submit" class="w-full rounded-xl bg-amber-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-800 transition shadow-2xs">
                                    <i class="fa-solid fa-check mr-1" aria-hidden="true"></i> Confirm &amp; Save
                                </button>
                                <a href="{{ route('admin.settings', ['tab' => 'administration']) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                            </div>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-3">
                    @csrf
                    <div>
                        <div class="flex justify-between items-center">
                            <label for="downpayment_percentage" class="block text-xs font-semibold text-slate-700">Downpayment (%)</label>
                            <span class="text-[11px] text-rose-600 font-bold">Current: {{ \App\Models\Setting::getSetting('downpayment_percentage', 50.0) }}%</span>
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-400">Applies to newly issued quotations.</p>
                        <input type="number" id="downpayment_percentage" name="downpayment_percentage" value="{{ old('downpayment_percentage', \App\Models\Setting::getSetting('downpayment_percentage', 50.0)) }}" step="0.01" min="0" max="100" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                    </div>

                    <div class="border-t border-slate-100 pt-2.5">
                        <div class="flex justify-between items-center">
                            <label for="long_term_booking_threshold_days" class="block text-xs font-semibold text-slate-700">Long-term Booking Threshold (days)</label>
                            <span class="text-[11px] text-rose-600 font-bold">Current: {{ \App\Models\Setting::getLongTermBookingThresholdDays() }}d</span>
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-400">Events scheduled farther than this are marked tentative.</p>
                        <input type="number" id="long_term_booking_threshold_days" name="long_term_booking_threshold_days" value="{{ old('long_term_booking_threshold_days', \App\Models\Setting::getLongTermBookingThresholdDays()) }}" min="1" max="730" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                    </div>

                    <div class="border-t border-slate-100 pt-2.5">
                        <div class="flex justify-between items-center">
                            <label for="price_reconfirmation_threshold_days" class="block text-xs font-semibold text-slate-700">Price Reconfirmation Due (days before event)</label>
                            <span class="text-[11px] text-rose-600 font-bold">Current: {{ \App\Models\Setting::getPriceReconfirmationThresholdDays() }}d</span>
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-400">Scheduled alert triggers when event is within this window.</p>
                        <input type="number" id="price_reconfirmation_threshold_days" name="price_reconfirmation_threshold_days" value="{{ old('price_reconfirmation_threshold_days', \App\Models\Setting::getPriceReconfirmationThresholdDays()) }}" min="1" max="365" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                    </div>

                    <div class="border-t border-slate-100 pt-2.5">
                        <label for="change_reason" class="block text-xs font-semibold text-slate-700">Change Justification / Reason (Optional)</label>
                        <input type="text" id="change_reason" name="change_reason" placeholder="e.g., Seasonal supplier price update window adjustment" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-brand-700 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-800 transition shadow-2xs">
                        <i class="fa-solid fa-sliders mr-1" aria-hidden="true"></i> Review &amp; Update Configuration
                    </button>
                </form>
            </div>

            {{-- AI estimate pricing: indicative ranges guests and clients see per area; never official quotations --}}
            @php
                $aiMarkup = \App\Models\Setting::aiEstimateMarkup();
                $aiLabour = \App\Models\Setting::aiLabourAllowance();
                $aiLabourMode = old('ai_labour_mode', in_array($aiLabour['mode'], ['fixed', 'percentage'], true) ? $aiLabour['mode'] : 'fixed');
            @endphp
            <div id="ai-estimate-settings" class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $adminSection === 'ai' ? '' : 'hidden' }}" data-admin-section="ai">
                <div class="mb-3.5 flex items-center gap-2.5 border-b border-slate-100 pb-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 sm:text-base">AI Estimate Pricing</h2>
                        <p class="text-[11px] text-slate-500 sm:text-xs">Shapes the indicative price ranges guests and clients see per area. Official quotations keep their own markup and are never changed by these settings.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-3">
                    @csrf
                    <div>
                        <div class="flex justify-between items-center">
                            <label for="ai_estimate_markup" class="block text-xs font-semibold text-slate-700">AI markup (×)</label>
                            <span class="text-[11px] text-rose-600 font-bold">Current: {{ $aiMarkup !== null ? $aiMarkup . '×' : 'Invalid — please set' }}</span>
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-400">Applied to the AI's raw material prices. Allowed {{ \App\Models\Setting::MIN_AI_ESTIMATE_MARKUP }}× to {{ \App\Models\Setting::MAX_AI_ESTIMATE_MARKUP }}×, two decimals.</p>
                        <input type="number" id="ai_estimate_markup" name="ai_estimate_markup" value="{{ old('ai_estimate_markup', $aiMarkup ?? \App\Models\Setting::DEFAULT_AI_ESTIMATE_MARKUP) }}" step="0.01" min="{{ \App\Models\Setting::MIN_AI_ESTIMATE_MARKUP }}" max="{{ \App\Models\Setting::MAX_AI_ESTIMATE_MARKUP }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                        @error('ai_estimate_markup')<p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <fieldset class="border-t border-slate-100 pt-2.5">
                        <div class="flex justify-between items-center">
                            <legend class="block text-xs font-semibold text-slate-700">Labour &amp; setup</legend>
                            <span class="text-[11px] text-rose-600 font-bold">Current: {{ match ($aiLabour['mode']) {
                                null => 'Not set (excluded)',
                                'invalid' => 'Invalid — please set',
                                'percentage' => $aiLabour['value'] . '%',
                                default => '₱' . number_format((float) $aiLabour['value'], 2),
                            } }}</span>
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-400">Added once per booking estimate and shared across its areas. A percentage is taken of the AI material cost before markup, the same basis official quotations use.</p>
                        <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach(\App\Models\Setting::AI_LABOUR_MODES as $modeKey => $modeLabel)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50/40">
                                    <input type="radio" name="ai_labour_mode" value="{{ $modeKey }}" @checked($aiLabourMode === $modeKey) class="text-rose-600 focus:ring-rose-500">
                                    {{ $modeLabel }}
                                </label>
                            @endforeach
                        </div>
                        <label for="ai_labour_value" class="mt-2 block text-xs font-semibold text-slate-700">Amount or percentage</label>
                        <input type="number" id="ai_labour_value" name="ai_labour_value" value="{{ old('ai_labour_value', $aiLabour['value']) }}" step="0.01" min="0" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                        @error('ai_labour_mode')<p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                        @error('ai_labour_value')<p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="border-t border-slate-100 pt-2.5">
                        <label for="ai_change_reason" class="block text-xs font-semibold text-slate-700">Change Justification / Reason (Optional)</label>
                        <input type="text" id="ai_change_reason" name="change_reason" maxlength="500" placeholder="e.g., Supplier prices rose this season" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-900 shadow-2xs">
                    </div>

                    <p class="text-[10px] text-slate-500">Estimates are indicative: they change when these settings or the analysed photos change, and they never replace or alter an issued quotation.</p>

                    <button type="submit" class="w-full rounded-xl bg-brand-700 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-800 transition shadow-2xs">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i> Save AI Estimate Pricing
                    </button>
                </form>
            </div>
            @endif

            <!-- Audit Trail Card -->
            <div id="audit-trail" class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $adminSection === 'audit' ? '' : 'hidden' }}" data-admin-section="audit">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                            <i class="fa-solid fa-list-check text-sm" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 sm:text-base">Audit Trail</h2>
                            <p class="text-[11px] text-slate-500 sm:text-xs">Latest recorded system events and administrative changes.</p>
                        </div>
                    </div>
                    <button type="button" data-open-modal="auditModal" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-slate-700 transition">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i> View Full Audit Log ({{ $auditLogs->count() }})
                    </button>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-xs" aria-label="Recent Audit Logs">
                        <thead class="border-y border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-3 py-2">Time</th>
                                <th scope="col" class="px-3 py-2">User</th>
                                <th scope="col" class="px-3 py-2">Action</th>
                                <th scope="col" class="px-3 py-2">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            @forelse($auditLogs->take(10) as $log)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">
                                        {{ optional($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                    </td>
                                    <td class="px-3 py-2 font-medium text-slate-700">
                                        {{ $log->user?->name ?? 'System' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ ucwords(str_replace('_', ' ', $log->action ?? 'Activity')) }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600 max-w-xs truncate">
                                        {{ is_array($log->details) ? ($log->details['message'] ?? json_encode($log->details)) : ($log->details ?? 'No additional details') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-5 text-center text-slate-500">No recent audit logs available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Data Management Card -->
            @if($isAdmin)
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs {{ $adminSection === 'data' ? '' : 'hidden' }}" data-admin-section="data">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                            <i class="fa-solid fa-database text-sm" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 sm:text-base">System Data Management</h2>
                            <p class="text-[11px] text-slate-500 sm:text-xs">
                                Import or export Raflora business data for staging and controlled demo setup.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.system-data.export') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-slate-700 transition">
                            <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export Data
                        </a>
                        <a href="{{ route('admin.system-data.demo') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                            <i class="fa-solid fa-cloud-arrow-down text-rose-600" aria-hidden="true"></i> Demo Dataset
                        </a>
                        <button type="button" data-open-modal="systemDataImportModal" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-rose-700 transition">
                            <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import Data
                        </button>
                    </div>
                </div>

                <div class="mt-3.5 grid grid-cols-1 gap-2.5 rounded-xl bg-slate-50/70 p-3 text-[11px] text-slate-600 md:grid-cols-3">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-shield-halved mt-0.5 text-rose-600 shrink-0" aria-hidden="true"></i>
                        <div>
                            <strong class="text-slate-800">Sensitive Protected:</strong>
                            <p class="mt-0.5 text-slate-500">Credentials, passwords, hashes, and sessions are never exported.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-diagram-project mt-0.5 text-rose-600 shrink-0" aria-hidden="true"></i>
                        <div>
                            <strong class="text-slate-800">Relational Mapping:</strong>
                            <p class="mt-0.5 text-slate-500">Foreign keys resolved via natural business identifiers.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-rotate mt-0.5 text-rose-600 shrink-0" aria-hidden="true"></i>
                        <div>
                            <strong class="text-slate-800">Atomic Transactions:</strong>
                            <p class="mt-0.5 text-slate-500">Rollback safeguards ensure zero partial imports.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Modals -->

    <!-- Quick Reset My Password Modal (Preserved trigger compatibility) -->
    <div id="passwordModal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
        <div class="absolute inset-0 bg-slate-950/50" data-close-modal="passwordModal"></div>
        <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center p-4">
            <div class="w-full rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 id="passwordModalTitle" class="text-base font-bold text-slate-900 sm:text-lg">Reset My Password</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Update password for {{ $user->email }}.</p>
                    </div>
                    <button type="button" data-close-modal="passwordModal" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.account.password') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-xs font-semibold text-slate-700">Current password</label>
                        <div class="relative mt-1">
                            <input type="password" id="reset_current_password" name="current_password" required class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm">
                            <div class="absolute inset-y-0 right-0 w-9 flex items-center justify-center text-slate-400">
                                <button type="button" class="inline-flex items-center justify-center hover:text-slate-600 focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('reset_current_password')">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700">New password</label>
                        <div class="relative mt-1">
                            <input type="password" id="reset_password" name="password" required minlength="8" class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm">
                            <div class="absolute inset-y-0 right-0 w-9 flex items-center justify-center text-slate-400">
                                <button type="button" class="inline-flex items-center justify-center hover:text-slate-600 focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('reset_password')">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700">Confirm new password</label>
                        <div class="relative mt-1">
                            <input type="password" id="reset_password_confirmation" name="password_confirmation" required minlength="8" class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm">
                            <div class="absolute inset-y-0 right-0 w-9 flex items-center justify-center text-slate-400">
                                <button type="button" class="inline-flex items-center justify-center hover:text-slate-600 focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('reset_password_confirmation')">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button class="w-full rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 transition">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Modal (Add Team Account) -->
    @if($isAdmin)
        @php $accountErrors = $errors->createAccount; @endphp
        <div id="accountModal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle" @if($accountFormFailed) data-open-on-load="true" @endif>
            <div class="absolute inset-0 bg-slate-950/50" data-close-modal="accountModal"></div>
            <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center p-4">
                <div class="w-full rounded-2xl bg-white p-5 shadow-2xl">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 id="accountModalTitle" class="text-base font-bold text-slate-900 sm:text-lg">Add Account</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Create an admin or staff account for operations panel.</p>
                        </div>
                        <button type="button" data-close-modal="accountModal" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('admin.account.accounts.store') }}" class="mt-4 space-y-3">
                        @csrf
                        @if($accountFormFailed)
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] text-rose-800" role="alert">
                                <strong class="font-bold">The account was not created. Please correct the following:</strong>
                                <ul class="mt-0.5 list-disc pl-4 space-y-0.5">
                                    @foreach($accountErrors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div>
                            <label for="account_name" class="text-xs font-semibold text-slate-700">Full name</label>
                            <input type="text" id="account_name" name="name" required value="{{ $accountFormFailed ? old('name') : '' }}" @if($accountErrors->has('name')) aria-invalid="true" aria-describedby="account_name_error" @endif class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm">
                            @if($accountErrors->has('name'))<p id="account_name_error" class="mt-1 text-[11px] font-semibold text-rose-600">{{ $accountErrors->first('name') }}</p>@endif
                        </div>
                        <div>
                            <label for="account_email" class="text-xs font-semibold text-slate-700">Email</label>
                            <input type="email" id="account_email" name="email" required value="{{ $accountFormFailed ? old('email') : '' }}" @if($accountErrors->has('email')) aria-invalid="true" aria-describedby="account_email_error" @endif class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm">
                            @if($accountErrors->has('email'))<p id="account_email_error" class="mt-1 text-[11px] font-semibold text-rose-600">{{ $accountErrors->first('email') }}</p>@endif
                        </div>
                        <div>
                            <label for="account_role" class="text-xs font-semibold text-slate-700">Role</label>
                            <select id="account_role" name="role" required @if($accountErrors->has('role')) aria-invalid="true" aria-describedby="account_role_error" @endif class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm">
                                {{-- Raflora supports a single Admin account; the panel creates Staff accounts only. --}}
                                <option value="staff">Staff</option>
                            </select>
                            @if($accountErrors->has('role'))<p id="account_role_error" class="mt-1 text-[11px] font-semibold text-rose-600">{{ $accountErrors->first('role') }}</p>@endif
                        </div>
                        <div>
                            <label for="temp_password" class="text-xs font-semibold text-slate-700">Temporary password</label>
                            <div class="relative mt-1">
                                <input type="password" id="temp_password" name="password" required minlength="8" @if($accountErrors->has('password')) aria-invalid="true" aria-describedby="account_password_error" @endif class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm">
                                <div class="absolute inset-y-0 right-0 w-9 flex items-center justify-center text-slate-400">
                                    <button type="button" class="inline-flex items-center justify-center hover:text-slate-600 focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('temp_password')">
                                        <i class="fa-solid fa-eye-slash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            @if($accountErrors->has('password'))<p id="account_password_error" class="mt-1 text-[11px] font-semibold text-rose-600">{{ $accountErrors->first('password') }}</p>@endif
                        </div>
                        <div>
                            <label for="temp_password_confirmation" class="text-xs font-semibold text-slate-700">Confirm password</label>
                            <div class="relative mt-1">
                                <input type="password" id="temp_password_confirmation" name="password_confirmation" required minlength="8" class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-9 text-xs sm:text-sm">
                                <div class="absolute inset-y-0 right-0 w-9 flex items-center justify-center text-slate-400">
                                    <button type="button" class="inline-flex items-center justify-center hover:text-slate-600 focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('temp_password_confirmation')">
                                        <i class="fa-solid fa-eye-slash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class="w-full rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold text-white hover:bg-brand-800 transition">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- System Data Import Modal -->
    @if($isAdmin)
        <div id="systemDataImportModal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="systemDataImportModalTitle">
            <div class="absolute inset-0 bg-slate-950/50" data-close-modal="systemDataImportModal"></div>
            <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center p-4">
                <div class="w-full max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 id="systemDataImportModalTitle" class="text-base font-bold text-slate-900 sm:text-lg">Import System Data</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Upload system data archive (.zip) to preview before importing.</p>
                        </div>
                        <button type="button" data-close-modal="systemDataImportModal" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label for="dataset_zip_file" class="block text-xs font-semibold text-slate-700">Select Dataset Archive (.zip)</label>
                            <input type="file" id="dataset_zip_file" accept=".zip,application/zip" class="mt-1 block w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-700 file:mr-2.5 file:rounded-lg file:border-0 file:bg-rose-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-rose-700 hover:file:bg-rose-100">
                            <p class="mt-0.5 text-[10px] text-slate-400">Accepted format: Raflora System Data (.zip) with manifest.json and entity files.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" id="btnPreviewDataset" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-rose-700 transition">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Upload &amp; Preview
                            </button>
                            <span id="previewLoadingIndicator" class="hidden text-xs text-slate-500">
                                <i class="fa-solid fa-circle-notch fa-spin text-rose-600 mr-1" aria-hidden="true"></i> Validating archive...
                            </span>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <div id="importErrorsContainer" class="hidden mt-4 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-700" role="alert">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-600 shrink-0"></i>
                            <div>
                                <strong class="font-bold text-red-800">Validation Errors:</strong>
                                <ul id="importErrorsList" class="mt-1 list-disc pl-4 space-y-0.5 text-[11px]"></ul>
                            </div>
                        </div>
                    </div>

                    <!-- Warnings Alert -->
                    <div id="importWarningsContainer" class="hidden mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700" role="alert">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-circle-exclamation mt-0.5 text-amber-600 shrink-0"></i>
                            <div>
                                <strong class="font-bold text-amber-800">Warnings:</strong>
                                <ul id="importWarningsList" class="mt-1 list-disc pl-4 space-y-0.5 text-[11px]"></ul>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Results -->
                    <div id="importPreviewContainer" class="hidden mt-4 space-y-3.5 border-t border-slate-100 pt-3.5">
                        <div class="grid grid-cols-2 gap-2.5 rounded-xl bg-slate-50 p-2.5 text-xs md:grid-cols-4">
                            <div>
                                <span class="text-slate-400 text-[10px]">Dataset ID:</span>
                                <p id="previewDatasetId" class="font-semibold text-slate-800 truncate">-</p>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px]">Mode:</span>
                                <p id="previewDataMode" class="font-semibold capitalize text-rose-700">-</p>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px]">Version:</span>
                                <p id="previewFormatVersion" class="font-semibold text-slate-800">-</p>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px]">Created:</span>
                                <p id="previewCreatedAt" class="font-semibold text-slate-800 truncate">-</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Entity Record Counts</h4>
                            <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-xs" aria-label="Entity record counts">
                                    <thead class="bg-slate-50 text-slate-500 uppercase text-[9px]">
                                        <tr>
                                            <th class="px-2.5 py-1.5">Entity</th>
                                            <th class="px-2.5 py-1.5 text-right">Records</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewCountsBody" class="divide-y divide-slate-100 text-slate-700 text-[11px]"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Confirmation Form -->
                        <form method="POST" action="{{ route('admin.system-data.import') }}" id="confirmImportForm" class="mt-3.5 pt-2.5 border-t border-slate-100 flex items-center justify-end gap-2">
                            @csrf
                            <input type="hidden" name="preview_token" id="import_preview_token" value="">
                            <button type="button" data-close-modal="systemDataImportModal" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" id="btnConfirmImport" class="rounded-xl bg-brand-700 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-brand-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-check mr-1" aria-hidden="true"></i> Confirm &amp; Import Dataset
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Audit Modal -->
    <div id="auditModal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
        <div class="absolute inset-0 bg-slate-950/50" data-close-modal="auditModal"></div>
        <div class="relative mx-auto flex min-h-full max-w-5xl items-center justify-center p-4">
            <div class="max-h-[85vh] w-full overflow-hidden rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 id="auditModalTitle" class="text-base font-bold text-slate-900 sm:text-lg">Audit Trail</h3>
                        <p class="mt-0.5 text-xs text-slate-500">The latest {{ $auditLogs->count() }} recorded system events. The CSV has the whole log.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <a href="{{ route('admin.settings.audit-log.export') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-slate-700">
                            <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
                        </a>
                        <button type="button" data-close-modal="auditModal" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="mt-4 max-h-[65vh] overflow-auto">
                    <table class="w-full min-w-[640px] text-left text-xs">
                        <thead class="sticky top-0 border-b border-slate-200 bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3 py-2.5">Time</th>
                                <th class="px-3 py-2.5">User</th>
                                <th class="px-3 py-2.5">Action</th>
                                <th class="px-3 py-2.5">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            @foreach($auditLogs as $log)
                                <tr class="align-top hover:bg-slate-50/50">
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ optional($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ ucwords(str_replace('_', ' ', $log->action ?? 'Activity')) }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ is_array($log->details) ? ($log->details['message'] ?? json_encode($log->details)) : ($log->details ?? 'No additional details') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const togglePassword = (id) => {
            const el = document.getElementById(id);
            if (!el) return;

            const button = el.parentElement?.querySelector('button');
            const icon = button?.querySelector('i');

            if (!button || !icon) return;

            const isHidden = el.type === 'password';
            el.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            icon.classList.toggle('fa-eye-slash', !isHidden);
            icon.classList.toggle('fa-eye', isHidden);
        };

        const switchSettingsTab = (tabName, updateHistory = true) => {
            const tabs = ['profile', 'security', 'administration'];
            if (!tabs.includes(tabName)) tabName = 'profile';

            tabs.forEach(t => {
                const pane = document.getElementById('tab-pane-' + t);
                const btn = document.getElementById('tab-btn-' + t);
                if (pane) {
                    if (t === tabName) {
                        pane.classList.remove('hidden');
                    } else {
                        pane.classList.add('hidden');
                    }
                }
                if (btn) {
                    const icon = btn.querySelector('i');
                    if (t === tabName) {
                        btn.classList.add('border-rose-600', 'text-rose-600', 'font-bold');
                        btn.classList.remove('border-transparent', 'text-slate-500', 'font-semibold');
                        btn.setAttribute('aria-selected', 'true');
                        btn.setAttribute('aria-current', 'page');
                        if (icon) {
                            icon.classList.add('text-rose-600');
                            icon.classList.remove('text-slate-400');
                        }
                    } else {
                        btn.classList.remove('border-rose-600', 'text-rose-600', 'font-bold');
                        btn.classList.add('border-transparent', 'text-slate-500', 'font-semibold');
                        btn.setAttribute('aria-selected', 'false');
                        btn.removeAttribute('aria-current');
                        if (icon) {
                            icon.classList.remove('text-rose-600');
                            icon.classList.add('text-slate-400');
                        }
                    }
                }
            });

            // Update browser URL query parameter (?tab=...) cleanly without reloading
            if (updateHistory && window.history && window.history.pushState) {
                const currentUrl = new URL(window.location.href);
                if (currentUrl.searchParams.get('tab') !== tabName) {
                    currentUrl.searchParams.set('tab', tabName);
                    currentUrl.hash = '';
                    window.history.pushState({ tab: tabName }, '', currentUrl.toString());
                }
            }
        };

        // Handle Browser Back & Forward buttons
        window.addEventListener('popstate', (event) => {
            const currentUrl = new URL(window.location.href);
            const queryTab = currentUrl.searchParams.get('tab');
            const hashTab = window.location.hash.replace('#', '');
            const targetTab = queryTab || hashTab || 'profile';
            switchSettingsTab(targetTab, false);
        });

        // Accessible Keyboard Navigation for Tabs (Left / Right / Home / End)
        const setupTabKeyboardNavigation = () => {
            const tabButtons = Array.from(document.querySelectorAll('[role="tablist"] [role="tab"]'));
            tabButtons.forEach((tabBtn, index) => {
                tabBtn.addEventListener('keydown', (e) => {
                    let nextIndex = null;
                    if (e.key === 'ArrowRight') {
                        nextIndex = (index + 1) % tabButtons.length;
                    } else if (e.key === 'ArrowLeft') {
                        nextIndex = (index - 1 + tabButtons.length) % tabButtons.length;
                    } else if (e.key === 'Home') {
                        nextIndex = 0;
                    } else if (e.key === 'End') {
                        nextIndex = tabButtons.length - 1;
                    }

                    if (nextIndex !== null) {
                        e.preventDefault();
                        const nextBtn = tabButtons[nextIndex];
                        nextBtn.focus();
                        const tabName = nextBtn.id.replace('tab-btn-', '');
                        switchSettingsTab(tabName, true);
                    }
                });
            });
        };

        const initializeAccountModals = () => {
            const closeModal = (id) => {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                window.setTimeout(() => modal.classList.add('hidden'), 200);
                document.body.classList.remove('overflow-hidden');
            };
            document.querySelectorAll('[data-open-modal]').forEach((button) => button.addEventListener('click', () => {
                const modal = document.getElementById(button.dataset.openModal);
                if (!modal) return;
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                window.requestAnimationFrame(() => modal.classList.add('opacity-100'));
            }));
            document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.dataset.closeModal)));
            // A dialog whose submission failed validation reopens with its errors, focused on the first invalid field.
            document.querySelectorAll('[role="dialog"][data-open-on-load]').forEach((modal) => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                window.requestAnimationFrame(() => modal.classList.add('opacity-100'));
                (modal.querySelector('[aria-invalid="true"]') || modal.querySelector('input:not([type="hidden"])'))?.focus();
            });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') document.querySelectorAll('[role="dialog"]:not(.hidden)').forEach((modal) => closeModal(modal.id)); });
        };

        const initializeProfileImageUpload = () => {
            const fileInput = document.getElementById('profile_image_input');
            const previewImg = document.getElementById('avatarPreviewImg');
            const initialsFallback = document.getElementById('avatarInitialsFallback');
            const removeInput = document.getElementById('remove_profile_image');
            const removeBtn = document.getElementById('removePhotoBtn');

            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) return;

                    if (file.size > 2 * 1024 * 1024) {
                        showNoticeModal('Image too large', 'The profile photo must be 2 MB or less. Choose a smaller image.', 'danger', this);
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.classList.remove('hidden');
                        }
                        if (initialsFallback) {
                            initialsFallback.classList.add('hidden');
                        }
                        if (removeInput) {
                            removeInput.value = '0';
                        }
                    };
                    reader.readAsDataURL(file);
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    openConfirmAction('Remove profile photo?', 'Your profile photo will be removed and your initials shown instead.', 'Remove photo', 'danger', () => {
                        if (removeInput) removeInput.value = '1';
                        if (fileInput) fileInput.value = '';
                        const form = document.getElementById('adminProfileForm');
                        if (form) form.submit();
                    }, removeBtn);
                });
            }
        };

        // Security: Trusted Devices / Active Sessions, one at a time.
        const initDevicePanels = () => {
            const buttons = [...document.querySelectorAll('[data-device-tab]')];
            buttons.forEach((button) => button.addEventListener('click', () => {
                buttons.forEach((b) => b.setAttribute('aria-selected', b === button ? 'true' : 'false'));
                document.querySelectorAll('[data-device-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.devicePanel !== button.dataset.deviceTab));
            }));
        };

        // Administration: one section at a time. A failed save opens its section (data-forced); otherwise
        // a link to a section (e.g. #audit-trail) or the last section used in this browser tab.
        const initAdminSections = () => {
            const nav = document.querySelector('[data-admin-nav]');
            if (!nav) return;
            const buttons = [...nav.querySelectorAll('[data-admin-section-tab]')];
            const show = (key) => {
                if (!buttons.some((b) => b.dataset.adminSectionTab === key)) return;
                buttons.forEach((b) => b.setAttribute('aria-selected', b.dataset.adminSectionTab === key ? 'true' : 'false'));
                document.querySelectorAll('[data-admin-section]').forEach((section) => section.classList.toggle('hidden', section.dataset.adminSection !== key));
                try { sessionStorage.setItem('raflora.adminSettingsSection', key); } catch (e) { /* storage unavailable */ }
            };
            buttons.forEach((button) => button.addEventListener('click', () => show(button.dataset.adminSectionTab)));
            if (nav.dataset.forced === '1') return;
            const linked = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1)))?.closest('[data-admin-section]') : null;
            if (linked) {
                switchSettingsTab('administration', false);
                show(linked.dataset.adminSection);
                linked.scrollIntoView({ block: 'start' });
                return;
            }
            let remembered = null;
            try { remembered = sessionStorage.getItem('raflora.adminSettingsSection'); } catch (e) { /* storage unavailable */ }
            if (remembered) show(remembered);
        };

        const initSystemDataImport = () => {
            const btnPreview = document.getElementById('btnPreviewDataset');
            const fileInput = document.getElementById('dataset_zip_file');
            const indicator = document.getElementById('previewLoadingIndicator');
            const errorsContainer = document.getElementById('importErrorsContainer');
            const errorsList = document.getElementById('importErrorsList');
            const warningsContainer = document.getElementById('importWarningsContainer');
            const warningsList = document.getElementById('importWarningsList');
            const previewContainer = document.getElementById('importPreviewContainer');
            const countsBody = document.getElementById('previewCountsBody');
            const previewTokenInput = document.getElementById('import_preview_token');
            const btnConfirm = document.getElementById('btnConfirmImport');

            if (!btnPreview || !fileInput) return;

            btnPreview.addEventListener('click', async () => {
                if (!fileInput.files || fileInput.files.length === 0) {
                    showNoticeModal('No file selected', 'Choose a system data ZIP archive first, then preview it.', 'danger', btnPreview);
                    return;
                }

                const formData = new FormData();
                formData.append('dataset_file', fileInput.files[0]);
                formData.append('_token', '{{ csrf_token() }}');

                btnPreview.disabled = true;
                indicator.classList.remove('hidden');
                errorsContainer.classList.add('hidden');
                warningsContainer.classList.add('hidden');
                previewContainer.classList.add('hidden');

                try {
                    const response = await fetch('{{ route("admin.system-data.preview") }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        const errs = data.errors || [data.message || 'Validation failed.'];
                        errorsList.innerHTML = errs.map(e => `<li>${e}</li>`).join('');
                        errorsContainer.classList.remove('hidden');
                        if (btnConfirm) btnConfirm.disabled = true;
                        return;
                    }

                    const preview = data.preview;

                    document.getElementById('previewDatasetId').textContent = preview.manifest?.dataset_id || '-';
                    document.getElementById('previewDataMode').textContent = preview.manifest?.data_mode || '-';
                    document.getElementById('previewFormatVersion').textContent = preview.manifest?.format_version || '1';
                    document.getElementById('previewCreatedAt').textContent = preview.manifest?.created_at ? new Date(preview.manifest.created_at).toLocaleString() : '-';

                    if (preview.warnings && preview.warnings.length > 0) {
                        warningsList.innerHTML = preview.warnings.map(w => `<li>${w}</li>`).join('');
                        warningsContainer.classList.remove('hidden');
                    }

                    countsBody.innerHTML = '';
                    const counts = preview.record_counts || {};
                    for (const [entity, count] of Object.entries(counts)) {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td class="px-2.5 py-1.5 font-medium capitalize">${entity.replace(/_/g, ' ')}</td><td class="px-2.5 py-1.5 text-right font-mono">${count}</td>`;
                        countsBody.appendChild(row);
                    }

                    if (previewTokenInput && preview.preview_token) {
                        previewTokenInput.value = preview.preview_token;
                    }

                    if (btnConfirm) {
                        btnConfirm.disabled = !preview.isValid;
                    }

                    previewContainer.classList.remove('hidden');
                } catch (err) {
                    errorsList.innerHTML = `<li>Failed to inspect archive: ${err.message}</li>`;
                    errorsContainer.classList.remove('hidden');
                } finally {
                    btnPreview.disabled = false;
                    indicator.classList.add('hidden');
                }
            });
        };

        const setupInitialTab = () => {
            const currentUrl = new URL(window.location.href);
            const queryTab = currentUrl.searchParams.get('tab');
            const hash = window.location.hash.replace('#', '');

            if (queryTab && ['profile', 'security', 'administration'].includes(queryTab)) {
                switchSettingsTab(queryTab, false);
            } else if (hash === 'security') {
                switchSettingsTab('security', false);
            } else if (hash === 'administration' || hash === 'audit-trail') {
                switchSettingsTab('administration', false);
                if (hash === 'audit-trail') {
                    const el = document.getElementById('audit-trail');
                    if (el) el.scrollIntoView({ behavior: 'smooth' });
                }
            }
        };

        const initAll = () => {
            initializeAccountModals();
            initializeProfileImageUpload();
            initSystemDataImport();
            initDevicePanels();
            initAdminSections();
            setupTabKeyboardNavigation();
            setupInitialTab();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll, { once: true });
        } else {
            initAll();
        }
    </script>
</x-admin-layout>
