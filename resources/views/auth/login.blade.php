<x-app-layout title="Login">
    <x-auth-layout 
        bgImage="raflora-auth-login.jpg"
        brandStyle="plain"
        :hideBrandingOnMobile="true"
        formWidth="max-w-lg"
    >
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        Login

        Actor:
        Guest / Client / Admin / Staff

        Purpose:
        Authenticates the user and starts the appropriate authenticated session.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Uses existing authentication flow. Do not replace backend authentication.

        Expected Backend Action:
        Authenticate credentials using the existing Laravel authentication implementation.

        Required Conditions:
        Valid credentials and any existing verification requirements.

        Success Feedback:
        Redirect to the correct authenticated destination.

        Error/Validation Feedback:
        Display field validation errors or safe authentication error message.

        Next UI State:
        Authenticated role-specific page.

        Allowed Next Actions:
        Continue to authenticated system.

        Restrictions:
        Do not expose authentication details or credentials.

        Backend Dependency:
        Existing authentication implementation.

        Notes:
        UI must not determine authorization independently.
        --}}
        <div class="w-full mx-auto bg-white rounded-3xl shadow-lg border border-raflora-border p-6 lg:p-8 relative">
            {{-- Back Button --}}
            <a href="{{ route('home') }}" class="absolute top-6 left-6 lg:top-8 lg:left-8 text-raflora-muted hover:text-[#1E7E34] transition-colors" aria-label="Go back home" title="Back to Home">
                <i class="fa-solid fa-arrow-left text-lg md:text-xl"></i>
            </a>

            <div class="text-center mb-6">
                <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora Logo" class="h-12 md:h-16 w-auto mx-auto mb-3 object-contain">
                <h1 class="font-serif text-raflora-heading text-3xl lg:text-4xl font-bold mb-2">Welcome Back</h1>
                <p class="font-sans text-raflora-muted text-base lg:text-lg">Sign in to your account</p>
            </div>

            @if(session('status'))
                <div class="mb-6 p-3 rounded-lg bg-raflora-primary-50 border border-raflora-primary-200 text-raflora-primary-800 text-sm text-center" aria-live="polite">
                    {{ session('status') }}
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

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4 font-sans">
                @csrf
                @if(isset($guestToken) && $guestToken)
                    <input type="hidden" name="guest_token" value="{{ $guestToken }}">
                @endif
                <div class="rf-field">
                    <label for="email" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Email or Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <input type="text" name="email" id="email" placeholder="you@example.com" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 16px 12.8px 40px;" value="{{ old('email', $email ?? '') }}" required autofocus autocomplete="email">
                    </div>
                </div>

                <div class="rf-field">
                    <label for="password" class="rf-label text-sm md:text-base mb-1.5 block text-raflora-heading font-bold">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-raflora-muted text-base pointer-events-none">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" name="password" id="password" placeholder="••••••••" class="rf-input w-full text-sm md:text-base rounded-xl border-raflora-border" style="padding: 12.8px 40px 12.8px 40px;" required autocomplete="current-password">
                        <div class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-raflora-muted text-base">
                            <button type="button" class="inline-flex items-center justify-center hover:text-raflora-heading focus-visible:outline-none transition-colors w-full h-full" aria-label="Show password" onclick="togglePassword('password')">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-2">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="remember" id="remember" value="1" class="rf-checkbox w-4 h-4 cursor-pointer rounded border-raflora-border text-[#1E7E34] focus:ring-[#1E7E34] m-0">
                        <label for="remember" class="text-sm md:text-base text-raflora-text cursor-pointer leading-none m-0 pt-0.5">
                            Remember me
                        </label>
                    </div>
                    <a href="{{ route('forgot-password') }}" class="text-sm md:text-base text-[#1E7E34] font-bold hover:text-[#155b25] transition underline">Forgot Password?</a>
                </div>

                <button type="submit" class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 group font-semibold shadow-md">
                    <span id="btn-text">Log In</span>
                    <svg id="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:translate-x-1 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
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

                // Prevent duplicate submission and show loading state
                document.querySelector('form').addEventListener('submit', function(e) {
                    const btn = this.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.classList.add('opacity-75', 'cursor-not-allowed');
                    
                    const btnText = btn.querySelector('#btn-text');
                    const btnIcon = btn.querySelector('#btn-icon');
                    
                    if(btnText) btnText.textContent = 'Authenticating...';
                    if(btnIcon) {
                        btnIcon.outerHTML = `
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        `;
                    }
                });
            </script>

            {{-- Divider --}}
            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-gray-400 text-xs uppercase tracking-wider font-semibold">or</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            {{-- Google OAuth - Existing Route --}}
            <a href="{{ route('auth.google') }}" id="google-login-btn" class="w-full flex items-center justify-center gap-3 bg-white border border-gray-300 text-gray-800 font-semibold py-3 md:py-3.5 rounded-full hover:bg-gray-50 transition shadow-sm font-sans">
                <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continue with Google
            </a>

            <p class="text-center text-raflora-text text-sm md:text-base mt-6">
                Don't have an account?
                <a href="{{ isset($guestToken) && $guestToken ? route('register', ['guest_token' => $guestToken, 'email' => $email ?? null]) : route('register') }}" class="text-[#1E7E34] font-bold hover:text-[#155b25] transition ml-1 underline">Register</a>
            </p>
        </div>
    </x-auth-layout>
</x-app-layout>
