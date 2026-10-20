<x-app-layout title="Admin Set New Password">
    <x-auth-layout brandStyle="plain" :hideBrandingOnMobile="true">
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-500/20 text-emerald-400 mb-3 border border-emerald-500/30">
                    <i class="fa-solid fa-lock text-2xl"></i>
                </div>
                <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide">SET NEW PASSWORD</h1>
                <p class="text-white/70 text-sm mt-2">
                    Enter a secure new password for your administrator account.
                </p>
            </div>

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

            <form method="POST" action="{{ route('admin.password.new.submit') }}" class="space-y-4 mt-4">
                @csrf
                <div>
                    <label for="password" class="block text-xs uppercase tracking-widest text-white/70 mb-1 font-medium">
                        New Password
                    </label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        autofocus
                        class="auth-input w-full py-3 px-4 rounded-xl text-white bg-white/5 border border-white/20 focus:border-emerald-400 focus:outline-none"
                    >
                    <p class="text-xs text-white/50 mt-1">
                        Minimum 8 characters. Must differ from current and bootstrap passwords.
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs uppercase tracking-widest text-white/70 mb-1 font-medium">
                        Confirm New Password
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        required
                        class="auth-input w-full py-3 px-4 rounded-xl text-white bg-white/5 border border-white/20 focus:border-emerald-400 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-emerald-400 text-gray-900 serif font-bold py-3 rounded-full hover:bg-emerald-300 transition text-lg tracking-wide focus-visible:outline-none mt-2"
                >
                    Reset Password &amp; Terminate Other Sessions
                </button>
            </form>
        </div>
    </x-auth-layout>
</x-app-layout>
