<x-app-layout title="Admin Password Reset - Verify Code">
    <x-auth-layout brandStyle="plain" :hideBrandingOnMobile="true">
        <div class="w-full max-w-md mx-auto bg-white rounded-3xl shadow-lg border border-raflora-border p-6 sm:p-8 lg:p-10">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-raflora-primary-50 text-brand-700 mb-3 border border-raflora-primary-200">
                    <i class="fa-solid fa-envelope-circle-check text-2xl"></i>
                </div>
                <h1 class="font-serif text-2xl md:text-3xl font-bold text-raflora-heading tracking-wide">ENTER RESET CODE</h1>
                <p class="text-raflora-muted text-sm mt-2">
                    If the administrator account exists, a 6-digit verification code was sent to:
                    <span class="block font-semibold text-raflora-heading mt-0.5 tracking-wider">{{ $maskedEmail }}</span>
                </p>
            </div>

            @if(session('status'))
                <x-alert type="success">{{ session('status') }}</x-alert>
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

            <form method="POST" action="{{ route('admin.password.otp.verify') }}" class="space-y-6 mt-4">
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
                        Code expires in 10 minutes. 5 attempts permitted.
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rf-btn-primary py-3 md:py-3.5 rounded-full text-base md:text-lg mt-4 flex items-center justify-center gap-2.5 font-semibold shadow-md transition-colors"
                >
                    Verify &amp; Continue
                </button>
            </form>

            <form method="POST" action="{{ route('admin.password.otp.resend') }}" class="mt-4 text-center">
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

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <a href="{{ route('admin.recovery.show') }}" class="text-xs text-red-500 font-semibold hover:text-red-700 transition block mb-2">
                    <i class="fa-solid fa-life-ring mr-1"></i> Cannot access your email? Emergency Recovery
                </a>
                <a href="{{ route('login') }}" class="text-sm text-brand-700 font-bold hover:text-brand-800 transition underline">
                    &larr; Back to Login
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
