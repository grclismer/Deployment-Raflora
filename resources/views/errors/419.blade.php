<x-app-layout title="Page Expired">
    <x-auth-layout :hide-branding-on-mobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        419 Page Expired

        Actor:
        Any

        Purpose:
        Displays an error when CSRF token expires.

        Current Phase:
        UI-FIRST

        Expected Backend Action:
        Rendered automatically by Laravel exception handler on CSRF mismatch.
        --}}
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-hourglass-end text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">419</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4 uppercase">Page Expired</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                Your session has expired due to inactivity. Please refresh the page and try again.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="javascript:location.reload()" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-rotate-right mr-2"></i> Refresh Page
                </a>
                <a href="{{ url('/') }}" class="bg-white/10 text-white serif font-bold py-2.5 px-6 rounded-full hover:bg-white/20 transition tracking-wide border border-white/20 focus-visible:outline-none">
                    <i class="fa-solid fa-house mr-2"></i> Go Home
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
