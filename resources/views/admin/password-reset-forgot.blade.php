<x-app-layout title="Admin Password Reset">
    <x-auth-layout brandStyle="plain" :hideBrandingOnMobile="true">
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-amber-500/20 text-amber-400 mb-3 border border-amber-500/30">
                    <i class="fa-solid fa-key text-2xl"></i>
                </div>
                <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide">ADMIN PASSWORD RESET</h1>
                <p class="text-white/70 text-sm mt-2">
                    Enter your administrator email to receive a secure password reset code.
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

            <form method="POST" action="{{ route('admin.password.send') }}" class="space-y-5 mt-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs uppercase tracking-widest text-white/70 mb-2 font-medium">
                        Administrator Email Address
                    </label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        required
                        autofocus
                        value="{{ old('email') }}"
                        placeholder="admin@yourcompany.com"
                        class="auth-input w-full py-3 px-4 rounded-xl text-white bg-white/5 border border-white/20 focus:border-amber-400 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-amber-400 text-gray-900 serif font-bold py-3 rounded-full hover:bg-amber-300 transition text-lg tracking-wide focus-visible:outline-none"
                >
                    Send Verification Code
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/10 flex flex-col items-center space-y-2 text-xs text-white/50">
                <a href="{{ route('admin.recovery.show') }}" class="text-rose-300 hover:text-rose-200 transition">
                    <i class="fa-solid fa-life-ring mr-1"></i> Cannot access your email? Emergency Recovery
                </a>
                <a href="{{ route('login') }}" class="hover:text-white/80 transition">
                    &larr; Return to Login
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
