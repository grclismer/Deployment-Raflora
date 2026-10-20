<x-app-layout title="Page Not Found">
    <x-auth-layout :hide-branding-on-mobile="true">
        <div class="w-full max-w-md mx-auto glass-card p-6 sm:p-8 lg:p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6 border border-white/20">
                <i class="fa-solid fa-leaf text-3xl"></i>
            </div>
            
            <div class="font-mono text-6xl font-bold text-white/20 mb-2">404</div>
            <h1 class="serif text-2xl md:text-3xl font-bold text-white tracking-wide mb-4">PAGE NOT FOUND</h1>
            
            <p class="text-white/70 text-sm mb-8 leading-relaxed">
                The page you are looking for seems to have wilted away. It may have been moved, deleted, or never existed.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ url('/') }}" class="bg-[#E8E8E8] text-gray-900 serif font-bold py-2.5 px-6 rounded-full hover:bg-white transition tracking-wide focus-visible:outline-none">
                    <i class="fa-solid fa-house mr-2"></i> Go Home
                </a>
                <a href="javascript:history.back()" class="bg-white/10 text-white serif font-bold py-2.5 px-6 rounded-full hover:bg-white/20 transition tracking-wide border border-white/20 focus-visible:outline-none">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Go Back
                </a>
            </div>
        </div>
    </x-auth-layout>
</x-app-layout>
