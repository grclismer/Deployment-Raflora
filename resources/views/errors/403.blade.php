<x-app-layout title="Access Denied">
    <x-auth-layout :hide-branding-on-mobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        403 Forbidden

        Actor:
        Any

        Purpose:
        Displays a user-friendly error when the authenticated user attempts to access an unauthorized resource.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Displays a custom 403 page.

        Expected Backend Action:
        None. Rendered automatically by Laravel's exception handler upon authorization failure.

        Required Conditions:
        HttpException (403).

        Success Feedback:
        N/A

        Error/Validation Feedback:
        N/A

        Next UI State:
        Home or Login page.

        Allowed Next Actions:
        Go Home or Go to Login.

        Restrictions:
        None.

        Backend Dependency:
        Laravel Exception Handler / Authorization gates.

        Notes:
        Kept visually distinct but on-brand for Raflora.
        --}}
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-lock text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">403</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4 uppercase">Access Denied</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                You do not have permission to access this page. If you believe this is a mistake, please contact the Raflora admin team.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ url('/') }}" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-house mr-2"></i> Go Home
                </a>
                <a href="{{ route('login') }}" class="bg-white/10 text-white serif font-bold py-2.5 px-6 rounded-full hover:bg-white/20 transition tracking-wide border border-white/20 focus-visible:outline-none">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Login
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
