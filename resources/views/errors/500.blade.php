<x-app-layout title="Something Went Wrong">
    <x-auth-layout :hide-branding-on-mobile="true">
        {{-- 
        RAFLORA UI FUNCTION

        Function:
        500 Internal Server Error

        Actor:
        Any

        Purpose:
        Displays a user-friendly error when the server encounters an unexpected condition.

        Current Phase:
        UI-FIRST

        Current Behavior:
        Displays a custom 500 page.

        Expected Backend Action:
        None. Rendered automatically by Laravel's exception handler upon application error.

        Required Conditions:
        HttpException (500) or other unhandled exceptions in production.

        Success Feedback:
        N/A

        Error/Validation Feedback:
        N/A

        Next UI State:
        Home or Reload.

        Allowed Next Actions:
        Go Home or Try Again.

        Restrictions:
        Must not expose stack traces, SQL, or sensitive info.

        Backend Dependency:
        Laravel Exception Handler.

        Notes:
        Kept visually distinct but on-brand for Raflora.
        --}}
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">500</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4 uppercase">Something Went Wrong</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                We encountered an unexpected issue on our end. Our team has been notified and we're working to fix it. Please try again in a moment.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ url('/') }}" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-house mr-2"></i> Go Home
                </a>
                <a href="javascript:location.reload()" class="bg-white/10 text-white serif font-bold py-2.5 px-6 rounded-full hover:bg-white/20 transition tracking-wide border border-white/20 focus-visible:outline-none">
                    <i class="fa-solid fa-rotate-right mr-2"></i> Try Again
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
