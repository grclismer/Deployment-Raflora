<x-app-layout title="Admin Initial Setup">
    <x-auth-layout brandStyle="plain" :hideBrandingOnMobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Admin Initial Setup (Bootstrap)

        Actor:
        Admin

        Purpose:
        Forces the master admin to provision their account securely upon first login (replacing default email/password and saving the initial recovery code).

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses a 4-step sequential process (Email -> OTP -> Password -> Recovery Code).

        Expected Backend Action:
        Send OTP, validate OTP, update admin password, generate and display first recovery code, mark setup as complete upon acknowledgment.

        Required Conditions:
        Admin user logged in but setup not completed.

        Success Feedback:
        Progress through steps, display of recovery code, and redirect to dashboard after acknowledgment.

        Error/Validation Feedback:
        Validation errors for email, OTP, and password constraints.

        Next UI State:
        Advances step or redirects to Admin Dashboard upon completion.

        Allowed Next Actions:
        Submit data per step, Logout.

        Restrictions:
        User cannot bypass this screen to access the admin panel until complete.

        Backend Dependency:
        Existing Laravel admin bootstrap controllers.
        --}}
        <div class="w-full max-w-lg mx-auto bg-white rounded-3xl shadow-lg border border-raflora-border p-6 sm:p-8 lg:p-10">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-raflora-primary-50 text-brand-700 mb-3 border border-raflora-primary-200">
                    <i class="fa-solid fa-shield-halved text-2xl"></i>
                </div>
                <h1 class="font-serif text-2xl md:text-3xl font-bold text-raflora-heading tracking-wide">ADMINISTRATOR SETUP</h1>
                <p class="text-raflora-muted text-sm mt-2">
                    Complete initial security provisioning to access the administration panel.
                </p>
                <!-- Step progress indicator -->
                <div class="flex items-center justify-center space-x-2 mt-4 text-xs font-semibold uppercase tracking-wider">
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'email' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-500' }}">1. Email</span>
                    <span class="text-gray-300">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'otp' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-500' }}">2. Verify</span>
                    <span class="text-gray-300">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'password' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-500' }}">3. Password</span>
                    <span class="text-gray-300">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'recovery_code' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-500' }}">4. Recovery</span>
                </div>
            </div>

            @if(session('status'))
                <x-alert type="success">{{ session('status') }}</x-alert>
            @endif

            @if(session('info'))
                <x-alert type="info">{{ session('info') }}</x-alert>
            @endif

            @if(session('error'))
                <x-alert type="danger">{{ session('error') }}</x-alert>
            @endif

            @if($errors->any())
                <x-alert type="danger">
                    <ul class="space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            {{-- STEP 1: ACTIVE EMAIL --}}
            @if($step === 'email')
                <form method="POST" action="{{ route('admin.setup.email') }}" class="space-y-5 mt-4">
                    @csrf
                    <div class="rf-field">
                        <label for="email" class="rf-label block text-xs uppercase tracking-widest text-raflora-heading mb-2 font-bold">
                            Active Operational Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            required
                            autofocus
                            value="{{ old('email') }}"
                            placeholder="admin@yourcompany.com"
                            class="rf-input w-full py-3 px-4 rounded-xl text-sm md:text-base border-raflora-border"
                        >
                        <p class="text-xs text-raflora-muted mt-2">
                            The default placeholder email must be replaced with your active operational address.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                    >
                        Send Verification Code
                    </button>
                </form>
            @endif

            {{-- STEP 2: OTP VERIFICATION --}}
            @if($step === 'otp')
                <div class="mb-4 text-center">
                    <p class="text-xs text-raflora-muted">
                        A 6-digit code was sent to <strong class="text-raflora-heading">{{ $email }}</strong>.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.setup.otp') }}" class="space-y-5">
                    @csrf
                    <div class="rf-field">
                        <label for="otp" class="rf-label block text-xs uppercase tracking-widest text-raflora-heading mb-2 font-bold text-center">
                            6-Digit Verification Code
                        </label>
                        <input
                            type="text"
                            name="otp"
                            id="otp"
                            maxlength="6"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            placeholder="------"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            class="rf-input w-full text-center text-2xl tracking-[0.4em] font-mono py-3 rounded-xl border-raflora-border"
                        >
                        <p class="text-xs text-raflora-muted text-center mt-2">
                            Code expires in 10 minutes.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                    >
                        Verify Email
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.setup.resend') }}" class="mt-4 text-center">
                    @csrf
                    <button
                        type="submit"
                        id="resend-btn"
                        class="text-xs text-brand-700 hover:text-brand-800 underline disabled:opacity-50 disabled:no-underline font-semibold"
                        @if($cooldownSeconds > 0) disabled @endif
                    >
                        Resend Code <span id="cooldown-timer">@if($cooldownSeconds > 0)({{ $cooldownSeconds }}s)@endif</span>
                    </button>
                </form>

                @if($cooldownSeconds > 0)
                    <script>
                        (function() {
                            let remaining = {{ $cooldownSeconds }};
                            const btn = document.getElementById('resend-btn');
                            const timer = document.getElementById('cooldown-timer');
                            const interval = setInterval(() => {
                                remaining--;
                                if (remaining <= 0) {
                                    clearInterval(interval);
                                    btn.disabled = false;
                                    timer.textContent = '';
                                } else {
                                    timer.textContent = `(${remaining}s)`;
                                }
                            }, 1000);
                        })();
                    </script>
                @endif
            @endif

            {{-- STEP 3: MANDATORY NEW PASSWORD --}}
            @if($step === 'password')
                <div class="mb-4">
                    <p class="text-xs text-raflora-muted">
                        Email verified. You must now replace the default bootstrap password with a secure password.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.setup.password') }}" class="space-y-4">
                    @csrf
                    <div class="rf-field">
                        <label for="password" class="rf-label block text-xs uppercase tracking-widest text-raflora-heading mb-1 font-bold">
                            New Password
                        </label>
                        <div class="relative">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autofocus
                                class="rf-input w-full py-3 px-4 pr-12 rounded-xl text-sm md:text-base border-raflora-border"
                            >
                            <div class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-raflora-muted text-base">
                                <button type="button" class="inline-flex items-center justify-center hover:text-raflora-heading focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('password')">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-raflora-muted mt-1">
                            Minimum 8 characters. Must differ from the default bootstrap password.
                        </p>
                    </div>

                    <div class="rf-field">
                        <label for="password_confirmation" class="rf-label block text-xs uppercase tracking-widest text-raflora-heading mb-1 font-bold">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <input
                                type="password"
                                name="password_confirmation"
                                id="password_confirmation"
                                required
                                class="rf-input w-full py-3 px-4 pr-12 rounded-xl text-sm md:text-base border-raflora-border"
                            >
                            <div class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-raflora-muted text-base">
                                <button type="button" class="inline-flex items-center justify-center hover:text-raflora-heading focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('password_confirmation')">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                    >
                        Save Password & Complete Setup
                    </button>
                </form>

                <script>
                    function togglePassword(id) {
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
                    }
                </script>
            @endif

            {{-- STEP 4: EMERGENCY RECOVERY CODE DISPLAY --}}
            @if($step === 'recovery_code')
                <div class="space-y-6 text-center">
                    <div class="p-4 rounded-2xl bg-raflora-danger-50 border border-red-200 text-raflora-danger text-sm">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                        <strong>Save this code immediately.</strong> It is shown only once and cannot be retrieved again.
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-widest text-raflora-heading mb-2 font-bold">
                            Emergency Recovery Code
                        </label>
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-2xl text-center select-all">
                            <span class="font-mono text-2xl font-bold tracking-widest text-brand-700">
                                {{ $recoveryCode }}
                            </span>
                        </div>
                        <p class="text-xs text-raflora-muted mt-2">
                            Use this code to recover your account if you ever lose access to your operational email.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.setup.acknowledge') }}">
                        @csrf
                        <div class="flex items-center gap-2 mb-4 justify-center">
                            <input type="checkbox" id="saved_code" required class="rf-checkbox w-4 h-4 cursor-pointer rounded border-raflora-border text-brand-700 focus:ring-brand-700">
                            <label for="saved_code" class="text-sm text-raflora-heading font-semibold cursor-pointer select-none">
                                I have safely stored my recovery code
                            </label>
                        </div>
                        <button
                            type="submit"
                            class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                        >
                            Complete Setup &rarr;
                        </button>
                    </form>
                </div>
            @endif

            {{-- STEP 5: REISSUE RECOVERY CODE IF SESSION DROPPED BEFORE ACKNOWLEDGMENT --}}
            @if($step === 'reissue')
                <div class="space-y-6 text-center">
                    <div class="p-4 rounded-2xl bg-raflora-danger-50 border border-red-200 text-raflora-danger text-sm">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                        <strong>Action Required:</strong> Your password was updated, but the emergency recovery code was not acknowledged before leaving. For security, previously generated codes cannot be retrieved. Please generate a fresh recovery code and save it to complete setup.
                    </div>

                    <form method="POST" action="{{ route('admin.setup.reissue-code') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                        >
                            Generate Fresh Recovery Code &rarr;
                        </button>
                    </form>
                </div>
            @endif

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-brand-700 font-bold hover:text-brand-800 transition underline bg-transparent border-0 p-0 cursor-pointer">
                        Log Out &amp; Return Later
                    </button>
                </form>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
