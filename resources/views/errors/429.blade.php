<x-app-layout title="Too Many Requests">
    <x-auth-layout :hide-branding-on-mobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        429 Too Many Requests

        Actor:
        Any

        Purpose:
        Displays an error when the user has exceeded rate limits.

        Current Phase:
        UI-FIRST

        Expected Backend Action:
        Rendered automatically by Laravel's rate limiter.
        --}}
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-hand text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">429</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4 uppercase">Too Many Requests</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                You have made too many requests in a short period of time. Please wait a moment before trying again.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ url('/') }}" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-house mr-2"></i> Go Home
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
