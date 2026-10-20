<x-app-layout title="Admin Emergency Account Recovery">
    <x-auth-layout 
        bgImage="raflora-auth-emergency-recovery.jpg"
        brandStyle="plain"
        :hideBrandingOnMobile="true"
        formWidth="max-w-lg"
    >
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Admin Emergency Account Recovery

        Actor:
        Admin

        Purpose:
        Allows the master admin to recover account access, change email, and reset password using a secure 16-character emergency code when standard login is unavailable.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses a 5-step process (Code -> Email -> OTP -> Password -> New Code).

        Expected Backend Action:
        Validate recovery code, send OTP to new email, validate OTP, update password, revoke all old sessions, and generate a new recovery code.

        Required Conditions:
        Valid old recovery code to begin.

        Success Feedback:
        Progress through steps and display of new replacement code.

        Error/Validation Feedback:
        Session status, errors for invalid code/OTP/email/password.

        Next UI State:
        Advances step or returns to Login on completion.

        Allowed Next Actions:
        Submit code/email/OTP/password, or return to login.

        Restrictions:
        5 attempts max. All old sessions terminated upon completion. Previous code is invalidated.

        Backend Dependency:
        Existing Laravel admin recovery controllers and session states.
        --}}
        <div class="w-full max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-raflora-border p-6 sm:p-8 lg:p-10">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-raflora-danger-50 text-raflora-danger mb-3 border border-red-200">
                    <i class="fa-solid fa-life-ring text-2xl"></i>
                </div>
                <h1 class="serif text-2xl md:text-3xl font-bold text-raflora-heading tracking-wide">EMERGENCY RECOVERY</h1>
                <p class="text-raflora-text font-medium text-sm mt-2">
                    Recover your administrator account when your verified email is unavailable.
                </p>
                <!-- Step progress indicator -->
                <div class="flex items-center justify-center space-x-2 mt-4 text-xs font-semibold uppercase tracking-wider">
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'code' ? 'bg-red-100 text-red-800' : 'bg-raflora-soft text-raflora-muted' }}">1. Code</span>
                    <span class="text-raflora-border">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'email' ? 'bg-red-100 text-red-800' : 'bg-raflora-soft text-raflora-muted' }}">2. Email</span>
                    <span class="text-raflora-border">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'otp' ? 'bg-red-100 text-red-800' : 'bg-raflora-soft text-raflora-muted' }}">3. OTP</span>
                    <span class="text-raflora-border">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'password' ? 'bg-red-100 text-red-800' : 'bg-raflora-soft text-raflora-muted' }}">4. Password</span>
                    <span class="text-raflora-border">&rarr;</span>
                    <span class="px-2.5 py-1 rounded-full {{ $step === 'replacement_code' ? 'bg-raflora-primary-100 text-raflora-primary-800' : 'bg-raflora-soft text-raflora-muted' }}">5. New Code</span>
                </div>
            </div>

            @if(session('status'))
                <div class="mb-6 p-3 rounded-lg bg-raflora-primary-50 border border-raflora-primary-200 text-raflora-primary-800 text-sm text-center" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('info'))
                <div class="mb-6 p-3 rounded-lg bg-raflora-navy-50 border border-raflora-navy-100 text-raflora-navy-800 text-sm text-center" aria-live="polite">
                    {{ session('info') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-3 rounded-lg bg-raflora-danger-50 border border-red-200 text-raflora-danger text-sm text-center" aria-live="assertive">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-3 rounded-lg bg-raflora-danger-50 border border-red-200 text-raflora-danger text-sm" aria-live="assertive">
                    <ul class="space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- STEP 1: EMERGENCY RECOVERY CODE --}}
            @if($step === 'code')
                <form method="POST" action="{{ route('admin.recovery.code') }}" class="space-y-5 mt-4">
                    @csrf
                    <div class="rf-field">
                        <label for="recovery_code" class="rf-label text-center mb-2 block">
                            16-Character Emergency Recovery Code
                        </label>
                        <input
                            type="text"
                            name="recovery_code"
                            id="recovery_code"
                            required
                            autofocus
                            placeholder="XXXX-XXXX-XXXX-XXXX"
                            autocomplete="off"
                            class="rf-input w-full text-center text-xl tracking-widest font-mono py-3"
                        >
                        <p class="text-sm text-raflora-muted text-center mt-2">
                            Enter the code generated during bootstrap or your previous recovery. 5 attempts permitted.
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mt-6">
                        <a href="{{ route('login') }}" class="w-full sm:w-1/2 inline-flex items-center justify-center bg-brand-700 border-2 border-brand-700 text-white font-bold py-3 rounded-full hover:bg-white hover:text-brand-700 transition-all text-sm tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-500/25">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Login
                        </a>
                        <button
                            type="submit"
                            class="w-full sm:w-1/2 bg-[#DC2626] text-white border-2 border-[#DC2626] serif font-bold py-3 rounded-full hover:bg-white hover:text-[#DC2626] transition-all text-lg tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/25"
                        >
                            Verify Recovery Code
                        </button>
                    </div>
                </form>
            @endif

            {{-- STEP 2: NEW ACTIVE EMAIL --}}
            @if($step === 'email')
                <div class="mb-4 text-center">
                    <p class="text-sm font-medium text-raflora-text">
                        Recovery code accepted. Enter your new operational email address.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.recovery.email') }}" class="space-y-5">
                    @csrf
                    <div class="rf-field">
                        <label for="email" class="rf-label">
                            New Operational Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            required
                            autofocus
                            value="{{ old('email') }}"
                            placeholder="newadmin@yourcompany.com"
                            class="rf-input w-full"
                        >
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mt-6">
                        <a href="{{ route('login') }}" class="w-full sm:w-1/2 inline-flex items-center justify-center bg-brand-700 border-2 border-brand-700 text-white font-bold py-3 rounded-full hover:bg-white hover:text-brand-700 transition-all text-sm tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-500/25">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Login
                        </a>
                        <button
                            type="submit"
                            class="w-full sm:w-1/2 bg-[#DC2626] text-white border-2 border-[#DC2626] serif font-bold py-3 rounded-full hover:bg-white hover:text-[#DC2626] transition-all text-lg tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/25"
                        >
                            Send Verification Code
                        </button>
                    </div>
                </form>
            @endif

            {{-- STEP 3: OTP VERIFICATION --}}
            @if($step === 'otp')
                <div class="mb-4 text-center">
                    <p class="text-sm text-raflora-text">
                        A 6-digit code was sent to <strong class="text-raflora-heading font-semibold">{{ $email }}</strong>.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.recovery.otp') }}" class="space-y-5">
                    @csrf
                    <div class="rf-field">
                        <label for="otp" class="rf-label text-center mb-2 block">
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
                            class="rf-input w-full text-center text-2xl tracking-[0.4em] font-mono py-3"
                        >
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mt-6">
                        <a href="{{ route('login') }}" class="w-full sm:w-1/2 inline-flex items-center justify-center bg-brand-700 border-2 border-brand-700 text-white font-bold py-3 rounded-full hover:bg-white hover:text-brand-700 transition-all text-sm tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-500/25">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Login
                        </a>
                        <button
                            type="submit"
                            class="w-full sm:w-1/2 bg-[#DC2626] text-white border-2 border-[#DC2626] serif font-bold py-3 rounded-full hover:bg-white hover:text-[#DC2626] transition-all text-lg tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/25"
                        >
                            Verify New Email
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.recovery.resend') }}" class="mt-4 text-center">
                    @csrf
                    <button
                        type="submit"
                        id="resend-btn"
                        class="text-sm font-medium text-raflora-danger hover:text-red-700 transition underline underline-offset-4 disabled:opacity-50 disabled:no-underline disabled:cursor-not-allowed"
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

            {{-- STEP 4: NEW PASSWORD --}}
            @if($step === 'password')
                <div class="mb-4 text-center">
                    <p class="text-sm font-medium text-raflora-text">
                        New email verified. Set a new secure password for your administrator account.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.recovery.password') }}" class="space-y-5">
                    @csrf
                    <div class="rf-field">
                        <label for="password" class="rf-label">
                            New Password
                        </label>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            autofocus
                            class="rf-input w-full"
                        >
                        <p class="text-xs text-raflora-muted mt-1 pl-1">
                            Minimum 8 characters. Must differ from previous passwords.
                        </p>
                    </div>

                    <div class="rf-field">
                        <label for="password_confirmation" class="rf-label">
                            Confirm New Password
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            required
                            class="rf-input w-full"
                        >
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mt-6">
                        <a href="{{ route('login') }}" class="w-full sm:w-1/2 inline-flex items-center justify-center bg-brand-700 border-2 border-brand-700 text-white font-bold py-3 rounded-full hover:bg-white hover:text-brand-700 transition-all text-sm tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-500/25">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Login
                        </a>
                        <button
                            type="submit"
                            class="w-full sm:w-1/2 bg-[#DC2626] text-white border-2 border-[#DC2626] serif font-bold py-3 rounded-full hover:bg-white hover:text-[#DC2626] transition-all text-lg tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/25"
                        >
                            Save Password &amp; Revoke Old Sessions
                        </button>
                    </div>
                </form>
            @endif

            {{-- STEP 5: REPLACEMENT RECOVERY CODE DISPLAY --}}
            @if($step === 'replacement_code')
                <div class="space-y-6 text-center">
                    <div class="p-4 rounded-xl bg-raflora-warning-50 border border-raflora-warning-200 text-raflora-warning-800 text-sm">
                        <i class="fa-solid fa-triangle-exclamation text-raflora-warning-600 mr-1.5"></i>
                        <strong>Important:</strong> Your previous recovery code is invalidated. Save your replacement code now. It is shown only once.
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-widest text-raflora-muted mb-2 font-medium">
                            Replacement Emergency Recovery Code
                        </label>
                        <div class="p-4 bg-raflora-soft border border-raflora-border rounded-xl text-center select-all">
                            <span class="font-mono text-2xl font-bold tracking-widest text-raflora-heading">
                                {{ $replacementCode }}
                            </span>
                        </div>
                        <p class="text-xs text-raflora-muted mt-2">
                            All previous sessions have been terminated. Fresh login is required.
                        </p>
                    </div>

                    <a
                        href="{{ route('login') }}"
                        class="block w-full text-center bg-brand-700 text-white border-2 border-brand-700 serif font-bold py-3 rounded-full hover:bg-white hover:text-brand-700 transition-all text-lg tracking-wide focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-500/25 mt-4"
                    >
                        I Have Saved My New Code &mdash; Log In &rarr;
                    </a>
                </div>
            @endif


        </div>
    </x-auth-layout>
</x-app-layout>
