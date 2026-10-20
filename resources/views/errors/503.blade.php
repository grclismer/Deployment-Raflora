<x-app-layout title="Under Maintenance">
    <x-auth-layout :hide-branding-on-mobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        503 Service Unavailable

        Actor:
        Any

        Purpose:
        Displays a user-friendly error when the application is down for maintenance.

        Current Phase:
        UI-FIRST

        Expected Backend Action:
        Rendered automatically by Laravel's exception handler (e.g., php artisan down).
        --}}
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-person-digging text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">503</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4 uppercase">Under Maintenance</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                Raflora Enterprises is currently undergoing scheduled maintenance to serve you better. We'll be back shortly.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="javascript:location.reload()" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-rotate-right mr-2"></i> Try Again
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
