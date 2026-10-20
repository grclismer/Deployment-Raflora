<x-app-layout title="Register">
    <x-auth-layout 
        bgImage="raflora-auth-register.jpg"
        leftTitle="Join Our<br>Floral Community"
        leftSubtitle="Create an account and start planning your event with Raflora."
        brandStyle="plain"
        :hideBrandingOnMobile="true"
        formWidth="max-w-2xl"
    >
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Register

        Actor:
        Guest / Client

        Purpose:
        Creates a new user account.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses existing registration flow. Do not replace backend registration.

        Expected Backend Action:
        Validate fields and create the user record using the existing Laravel registration implementation.

        Required Conditions:
        Unique email, matching password, valid phone format.

        Success Feedback:
        Redirect to email verification notification or appropriate authenticated destination.

        Error/Validation Feedback:
        Display field validation errors or safe registration error message.

        Next UI State:
        Email Verification state or Authenticated role-specific page.

        Allowed Next Actions:
        Verify email, or continue to authenticated system if no verification is required.

        Restrictions:
        Do not silently log in without completing required verification steps.

        Backend Dependency:
        Existing registration implementation.

        Notes:
        UI must not determine authorization or auto-verify independently.
        --}}
        <div class="w-full mx-auto bg-white rounded-3xl shadow-lg border border-raflora-border p-6 lg:p-8">
            <div class="text-center mb-6">
                <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora" class="h-12 md:h-16 w-auto mx-auto mb-3 object-contain">
                <h1 class="font-serif text-3xl lg:text-4xl font-bold text-raflora-heading">Create an Account</h1>
                <p class="text-raflora-muted text-base lg:text-lg mt-2">Join Raflora and start planning your event.</p>
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

            <form method="POST" action="{{ route('register.attempt') }}" class="space-y-4">
                @csrf
                @if(isset($guestToken) && $guestToken)
                    <input type="hidden" name="guest_token" value="{{ $guestToken }}">
                @endif
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rf-field">
                        <label for="first_name" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">First Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                                <i class="fa-regular fa-user"></i>
                            </div>
                            <input type="text" name="first_name" id="first_name" placeholder="John" value="{{ old('first_name', $firstName ?? '') }}" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" required>
                        </div>
                    </div>

                    <div class="rf-field">
                        <label for="last_name" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Last Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                                <i class="fa-regular fa-user"></i>
                            </div>
                            <input type="text" name="last_name" id="last_name" placeholder="Doe" value="{{ old('last_name', $lastName ?? '') }}" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" required>
                        </div>
                    </div>
                </div>

                <div class="rf-field">
                    <label for="email" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <input type="email" name="email" id="email" placeholder="you@example.com" value="{{ old('email', $email ?? '') }}" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" required>
                    </div>
                </div>

                <div class="rf-field">
                    <label for="mobile_number" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Mobile Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <input type="tel" name="mobile_number" id="mobile_number" placeholder="09XXXXXXXXX" value="{{ old('mobile_number') }}" maxlength="11" inputmode="numeric" autocomplete="tel" pattern="09[0-9]{9}" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" required>
                    </div>
                    <p class="text-raflora-muted text-xs mt-1 pl-1">Exactly 11 digits starting with 09.</p>
                </div>

                <div class="rf-field">
                    <label for="password" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" name="password" id="password" placeholder="••••••••" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 40px 12.8px 40px;" required minlength="8">
                        <div class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-raflora-muted text-base">
                            <button type="button" class="inline-flex items-center justify-center hover:text-raflora-heading focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('password')">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-raflora-muted text-xs mt-1 pl-1">Must be at least 8 characters.</p>
                </div>

                <div class="rf-field">
                    <label for="password_confirmation" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 40px 12.8px 40px;" required minlength="8">
                        <div class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-raflora-muted text-base">
                            <button type="button" class="inline-flex items-center justify-center hover:text-raflora-heading focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('password_confirmation')">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>


                <div class="flex items-center gap-3 mt-4">
                    <input type="checkbox" name="terms" id="terms" class="rf-checkbox w-4 h-4 cursor-pointer rounded border-raflora-border text-[#1E7E34] focus:ring-[#1E7E34] m-0" required>
                    <label for="terms" class="text-sm md:text-base text-raflora-text cursor-pointer leading-none m-0 pt-0.5">
                        I agree to the <span class="font-semibold underline text-[#1E7E34]">Terms and Conditions</span>
                    </label>
                </div>

                <button type="submit" class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 group font-semibold shadow-md">
                    Create Account
                    <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
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

                    document.addEventListener('DOMContentLoaded', function() {
                        const mobileInput = document.getElementById('mobile_number');
                        if (mobileInput) {
                            mobileInput.addEventListener('input', function() {
                                this.value = this.value.replace(/\D/g, '').slice(0, 11);
                            });
                        }
                    });
                </script>

                <p class="text-center text-raflora-text text-sm md:text-base mt-6">
                    Already have an account?
                    <a href="{{ isset($guestToken) && $guestToken ? route('login', ['guest_token' => $guestToken, 'email' => $email ?? null]) : route('login') }}" class="text-[#1E7E34] font-bold hover:text-[#155b25] transition ml-1 underline">Log in</a>
                </p>
            </form>
        </div>
    </x-auth-layout>
</x-app-layout>
