@props([
    'bgImage' => 'raflora-auth-login.jpg',
    'leftTitle' => 'Crafting Beautiful<br>Moments',
    'leftSubtitle' => 'Elegant floral arrangements for weddings, events, and life\'s special celebrations.',
    'brandStyle' => 'card', // 'card' or 'plain'
    'hideBrandingOnMobile' => false,
    'formWidth' => 'max-w-md',
])

<div class="min-h-screen w-full relative flex items-center justify-center p-4 lg:p-12 font-sans overflow-hidden bg-gray-50">
    
    {{-- Full Page Background Image --}}
    <img src="{{ asset('assets/images/' . $bgImage) }}" alt="" class="absolute inset-0 w-full h-full object-cover z-0">
    
    {{-- Subtle full-page overlay to ensure white text readability without harsh splits --}}
    <div class="absolute inset-0 bg-black/50 pointer-events-none z-0"></div>



    {{-- Main Inner Content Wrapper --}}
    <div class="relative z-10 w-full max-w-7xl flex flex-col lg:flex-row items-stretch justify-between gap-8 lg:gap-16">
        
        {{-- Left Presentation (Desktop) --}}
        <div class="w-full lg:w-2/5 flex-col items-center text-center mt-8 lg:mt-0 {{ $hideBrandingOnMobile ? 'hidden lg:flex' : 'flex' }} justify-center relative z-10 px-4 lg:pr-12">
            @if($brandStyle === 'card')
                {{-- Unified Translucent Branding Panel --}}
                <div class="bg-white/85 backdrop-blur-md p-8 sm:p-10 rounded-3xl shadow-lg max-w-lg border border-white/40 flex flex-col items-start w-full">
                    <a href="{{ route('home') }}" class="mb-8 block">
                        <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora Logo" class="h-16 md:h-20 lg:h-24 w-auto object-contain">
                    </a>
                    <div class="mb-6">
                        <h1 class="font-serif text-[#0F2E58] text-3xl font-extrabold tracking-widest uppercase">RAFLORA</h1>
                        <p class="font-sans text-[#1E7E34] text-xs sm:text-sm font-bold tracking-[0.2em] uppercase mt-1">Flowers & Events</p>
                    </div>
                    <h2 class="font-serif text-[#0F2E58] text-4xl sm:text-5xl lg:text-5xl xl:text-6xl font-bold mb-4 leading-tight">{!! $leftTitle !!}</h2>
                    <p class="font-sans text-[#0F2E58] text-lg sm:text-xl font-medium leading-relaxed opacity-95">{{ $leftSubtitle }}</p>
                </div>
            @else
                <div class="flex justify-center w-full mb-6 lg:mb-8">
                    <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora" class="h-28 lg:h-32 xl:h-44 w-auto object-contain">
                </div>
                {{-- Plain Text Branding Panel --}}
                <div class="w-full text-white drop-shadow-md flex flex-col items-center text-center mx-auto relative z-20">
                    <h2 class="font-serif text-white font-bold mb-4 xl:mb-6 leading-tight break-words hyphens-auto w-full" style="font-size: clamp(2.5rem, 5vw, 4.5rem);">{!! $leftTitle !!}</h2>
                    <p class="font-sans text-white/90 font-light leading-relaxed mb-6 xl:mb-8 px-2" style="font-size: clamp(1.25rem, 2.5vw, 1.75rem);">{{ $leftSubtitle }}</p>
                    <p class="font-sans text-green-400 font-bold tracking-widest uppercase break-words w-full" style="font-size: clamp(0.875rem, 1.5vw, 1.125rem);">PEOPLE | FLOWERS | MEMORIES | ALWAYS</p>
                </div>
            @endif
        </div>

        {{-- Right Floating Auth Area --}}
        <div class="w-full lg:w-3/5 flex justify-center lg:justify-end">
            <div class="w-full {{ $formWidth }}">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
