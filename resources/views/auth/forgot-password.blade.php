<x-app-layout title="Forgot Password">
    <x-auth-layout 
        bgImage="raflora-auth-forgot-password.jpg"
        brandStyle="plain"
        :hideBrandingOnMobile="true"
        formWidth="max-w-lg"
    >
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Forgot Password

        Actor:
        Guest / Client / Admin / Staff

        Purpose:
        Initiates the password reset process by sending a reset link to the user's email.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses existing forgot-password flow.

        Expected Backend Action:
        Verify email existence (safely) and dispatch a reset token via email.

        Required Conditions:
        Valid email format.

        Success Feedback:
        Success message confirming a reset link was sent (without confirming account existence if avoiding enumeration).

        Error/Validation Feedback:
        Validation errors for the email field.

        Next UI State:
        Same view with success notification, or redirect to a confirmation page.

        Allowed Next Actions:
        Return to login, or use Emergency Recovery if Admin.

        Restrictions:
        Do not expose whether an arbitrary email is registered if preventing enumeration.

        Backend Dependency:
        Existing Laravel password broker.

        Notes:
        Includes a link for Admin Emergency Recovery.
        --}}
        <div class="w-full mx-auto bg-white rounded-3xl shadow-lg border border-raflora-border p-6 lg:p-8">
            <div class="text-center mb-6">
                <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora Logo" class="h-12 md:h-16 w-auto mx-auto mb-3 object-contain">
                <h1 class="font-serif text-raflora-heading text-3xl lg:text-4xl font-bold mb-2">Forgot Password</h1>
                <p class="font-sans text-raflora-muted text-base lg:text-lg px-2">No worries. Enter your email and we'll send you a reset link.</p>
            </div>

            @if(session('status'))
                <div class="mb-6 p-3 rounded-lg bg-raflora-primary-50 border border-raflora-primary-200 text-raflora-primary-800 text-sm text-center" aria-live="polite">
                    {{ session('status') }}
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

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4 font-sans">
                @csrf
                <div class="rf-field">
                    <label for="email" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <input type="email" name="email" id="email" placeholder="you@example.com" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" value="{{ old('email') }}" required autofocus autocomplete="email">
                    </div>
                </div>

                <button type="submit" class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 group font-semibold shadow-md">
                    <span>Send Reset Link</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:translate-x-1 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>

                <p class="text-center text-raflora-text text-sm md:text-base mt-6">
                    Remember your password?
                    <a href="{{ route('login') }}" class="text-[#1E7E34] font-bold hover:text-[#155b25] transition ml-1 underline">Return to login</a>
                </p>

                <div class="pt-6 mt-6 border-t border-raflora-border text-center">
                    <p class="text-xs text-raflora-muted">
                        Admin cannot access email?
                        <a href="{{ route('admin.recovery.show') }}" class="text-raflora-danger font-bold hover:text-red-700 transition ml-1 underline">
                            Emergency Recovery
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </x-auth-layout>
</x-app-layout>
