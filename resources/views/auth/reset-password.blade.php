<x-app-layout title="Reset Password">
    <x-auth-layout bgImage="raflora-auth-forgot-password.jpg" brandStyle="plain" :hideBrandingOnMobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Reset Password

        Actor:
        Guest / Client / Admin / Staff

        Purpose:
        Completes the password reset process after clicking the email link.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses existing reset-password flow.

        Expected Backend Action:
        Validate the reset token against the user's email and securely hash/store the new password.

        Required Conditions:
        Valid unexpired token, matching email, and secure matching password.

        Success Feedback:
        Redirect to login with success message.

        Error/Validation Feedback:
        Validation errors for password mismatch, weak password, or invalid token.

        Next UI State:
        Login page on success.

        Allowed Next Actions:
        Login with new password.

        Restrictions:
        Do not expose why a token is invalid (expired vs wrong email) beyond standard safe messages.

        Backend Dependency:
        Existing Laravel password broker.

        Notes:
        None.
        --}}
        <div class="w-full max-w-md mx-auto bg-white rounded-2xl shadow-sm border border-raflora-border p-6 sm:p-8 lg:p-10">
            <h1 class="serif text-3xl font-bold text-raflora-heading text-center mb-2">Reset Password</h1>
            <p class="text-raflora-muted text-center text-sm mb-8">Set a new password for your account.</p>

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

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="rf-field">
                    <label for="password" class="rf-label">New Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" placeholder="••••••••" class="rf-input w-full pr-10" required>
                        <div class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center text-raflora-muted">
                            <button type="button" class="inline-flex items-center justify-center hover:text-raflora-text focus-visible:outline-none" aria-label="Show password" onclick="togglePassword('password')">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rf-field">
                    <label for="password_confirmation" class="rf-label">Confirm Password</label>
                    <div class="relative">
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••" class="rf-input w-full pr-10" required>
                        <div class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center text-raflora-muted">
                            <button type="button" class="inline-flex items-center justify-center hover:text-raflora-text focus-visible:outline-none" aria-label="Show password" onclick="togglePassword('password_confirmation')">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full rf-btn-primary py-3 rounded-full text-base mt-2">
                    Save New Password
                </button>

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

                <p class="text-center text-raflora-text text-sm mt-4">
                    Remembered your password?
                    <a href="{{ route('login') }}" class="text-raflora-primary-600 font-semibold hover:text-raflora-primary-700 transition ml-1">Login</a>
                </p>
            </form>
        </div>
    </x-auth-layout>
</x-app-layout>
