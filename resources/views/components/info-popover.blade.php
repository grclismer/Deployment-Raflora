@props(['title' => 'Information'])

<div x-data="{ open: false }" class="relative inline-block ml-3 z-50">
    <button 
        @click="open = !open" 
        @click.outside="open = false" 
        @keydown.escape.window="open = false"
        type="button" 
        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-1 transition align-middle"
        aria-label="More information about {{ $title }}"
        :aria-expanded="open"
    >
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
    </button>

    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        style="display: none;"
        class="absolute left-0 top-full mt-2 w-[calc(100vw-3rem)] max-w-sm sm:w-80 rounded-xl bg-white shadow-xl border border-slate-200 p-4 text-sm text-slate-600 ring-1 ring-black/5 z-[60]"
        x-ref="popover"
        x-init="$watch('open', value => {
            if (value) {
                $nextTick(() => {
                    const btnRect = $el.parentElement.getBoundingClientRect();
                    const popoverRect = $refs.popover.getBoundingClientRect();
                    
                    // Reset styling first
                    $refs.popover.style.left = '0';
                    $refs.popover.style.right = 'auto';
                    $refs.popover.style.transform = 'none';

                    if (window.innerWidth < 640) {
                        // Check if it goes off screen on mobile when left-aligned
                        if (btnRect.left + popoverRect.width > window.innerWidth - 16) {
                            $refs.popover.style.left = 'auto';
                            $refs.popover.style.right = '0';
                        }
                    } else {
                        // Desktop
                        // Check right edge collision
                        if (btnRect.left + popoverRect.width > window.innerWidth - 24) {
                            $refs.popover.style.left = 'auto';
                            $refs.popover.style.right = '0';
                        }
                        
                        // Check left edge collision if right aligned
                        if ($refs.popover.style.right === '0px' && btnRect.right - popoverRect.width < 24) {
                             $refs.popover.style.left = '50%';
                             $refs.popover.style.right = 'auto';
                             $refs.popover.style.transform = 'translateX(-50%)';
                        }
                    }
                });
            }
        })"
    >
        <div class="flex items-start justify-between mb-2">
            <h4 class="font-bold text-slate-900">{{ $title }}</h4>
            <button @click="open = false" type="button" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Close information">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="leading-relaxed">
            {{ $slot }}
        </div>
    </div>
</div>
