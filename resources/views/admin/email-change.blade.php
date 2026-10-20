<x-admin-layout title="Change Admin Email">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="border-b border-slate-200 pb-5">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Account Security</p>
            <h2 class="serif mt-1 text-3xl font-bold text-slate-900">Change Administrator Email</h2>
            <p class="mt-1 text-sm text-slate-500">
                Update your active operational email address. Current password and verification OTP are required.
            </p>
        </div>

        @if(session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                <i class="fa-solid fa-circle-check text-emerald-600 mr-2"></i> {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                <i class="fa-solid fa-circle-exclamation text-rose-600 mr-2"></i> {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @if(! $pendingEmail)
                {{-- STEP 1: Enter Current Password + New Email --}}
                <div class="mb-6 rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm text-slate-700">
                    <span class="text-slate-500">Current Operational Email:</span>
                    <strong class="text-slate-900 block mt-0.5">{{ $admin->email }}</strong>
                </div>

                <form method="POST" action="{{ route('admin.email-change.submit') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="current_password" class="block text-sm font-semibold text-slate-700">
                            Current Admin Password
                        </label>
                        <p class="text-xs text-slate-500 mb-1">Verify your identity before changing credentials.</p>
                        <input
                            type="password"
                            name="current_password"
                            id="current_password"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 focus:border-brand-600 focus:ring-brand-500"
                        >
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700">
                            New Operational Email Address
                        </label>
                        <p class="text-xs text-slate-500 mb-1">A verification code will be sent to this new address.</p>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            required
                            value="{{ old('email') }}"
                            placeholder="newadmin@yourcompany.com"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 focus:border-brand-600 focus:ring-brand-500"
                        >
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <a href="{{ route('admin.settings') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="rounded-lg bg-brand-700 px-5 py-2.5 font-semibold text-white hover:bg-brand-800 transition shadow-sm"
                        >
                            Send Verification Code &rarr;
                        </button>
                    </div>
                </form>
            @else
                {{-- STEP 2: Verify OTP Sent to New Email --}}
                <div class="mb-6 rounded-lg bg-brand-50 border border-brand-200 p-4 text-sm text-brand-900">
                    <p>A 6-digit verification code was sent to your proposed new email:</p>
                    <strong class="block text-base mt-1 text-brand-950">{{ $pendingEmail }}</strong>
                    <p class="text-xs text-brand-700 mt-2">
                        Your account email will only be updated after entering this code.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.email-change.verify') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="otp" class="block text-sm font-semibold text-slate-700 text-center mb-1">
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
                            class="w-full max-w-xs mx-auto block text-center text-2xl tracking-[0.4em] font-mono py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:border-brand-600 focus:ring-brand-500"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-brand-700 px-5 py-2.5 font-semibold text-white hover:bg-brand-800 transition shadow-sm"
                    >
                        Confirm &amp; Update Email
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.email-change.resend') }}" class="mt-4 text-center">
                    @csrf
                    <button
                        type="submit"
                        id="resend-btn"
                        class="text-xs text-brand-700 hover:text-brand-900 underline disabled:opacity-50 disabled:no-underline"
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

                <div class="mt-6 pt-4 border-t border-slate-100 text-center">
                    <a href="{{ route('admin.settings') }}" class="text-xs text-slate-500 hover:text-slate-800">
                        Cancel &amp; Discard Email Change
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
