@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => true,
    'inline' => false,
])

@php
    $normalizedType = match ($type) {
        'error' => 'danger',
        'information' => 'info',
        default => $type,
    };

    $palettes = [
        'info' => [
            'bg' => '#eff6ff',
            'border' => '#93c5fd',
            'text' => '#1e3a8a',
            'icon' => 'fa-circle-info text-blue-600',
            'classes' => 'rf-toast-info bg-blue-50 border-blue-300 text-blue-900',
        ],
        'success' => [
            'bg' => '#ecfdf5',
            'border' => '#6ee7b7',
            'text' => '#064e3b',
            'icon' => 'fa-circle-check text-emerald-600',
            'classes' => 'rf-toast-success bg-emerald-50 border-emerald-300 text-emerald-900',
        ],
        'warning' => [
            'bg' => '#fffbeb',
            'border' => '#fcd34d',
            'text' => '#78350f',
            'icon' => 'fa-triangle-exclamation text-amber-600',
            'classes' => 'rf-toast-warning bg-amber-50 border-amber-300 text-amber-900',
        ],
        'danger' => [
            'bg' => '#fff1f2',
            'border' => '#fda4af',
            'text' => '#881337',
            'icon' => 'fa-circle-xmark text-rose-600',
            'classes' => 'rf-toast-danger bg-rose-50 border-rose-300 text-rose-900',
        ],
    ];

    $palette = $palettes[$normalizedType] ?? $palettes['info'];
    $iconClass = $palette['icon'];
    $colorClass = $palette['classes'];
    $role = in_array($normalizedType, ['danger', 'warning']) ? 'alert' : 'status';

    // Fully opaque solid surface styling to guarantee zero visual bleed-through of underlying content or buttons
    $surfaceStyle = "background: {$palette['bg']} !important; background-color: {$palette['bg']} !important; background-image: none !important; border-color: {$palette['border']} !important; color: {$palette['text']} !important; opacity: 1 !important; isolation: isolate; backdrop-filter: none !important; -webkit-backdrop-filter: none !important; mix-blend-mode: normal !important;";

    $containerClasses = $inline
        ? "rf-alert w-full rounded-2xl border p-4 shadow-sm flex items-start gap-3 {$colorClass}"
        : "rf-toast-alert pointer-events-auto cursor-pointer w-full rounded-2xl border shadow-2xl hover:shadow-xl opacity-100 flex items-start p-4 gap-3 select-none {$colorClass}";
@endphp

<div
    role="{{ $role }}"
    class="{{ $containerClasses }}"
    style="{{ $surfaceStyle }}"
    @if(!$inline)
        tabindex="0"
        title="Click to dismiss"
        aria-label="Notification - Click or press Enter to dismiss"
        onclick="if(window.dismissRfToast){window.dismissRfToast(this, event);}else{this.remove();}"
        onkeydown="if(event.key==='Enter'||event.key===' '||event.key==='Escape'){event.preventDefault();if(window.dismissRfToast){window.dismissRfToast(this, event);}else{this.remove();}}"
    @endif
>
    <i class="fa-solid {{ $iconClass }} text-lg mt-0.5 shrink-0" aria-hidden="true"></i>
    <div class="flex-1 text-sm font-medium leading-5">
        @if($title)
            <div class="font-bold text-sm tracking-wide mb-1">{{ $title }}</div>
        @endif
        <div>{{ $slot }}</div>
    </div>
    @if($inline && $dismissible)
        <button
            type="button"
            class="shrink-0 rounded-lg p-1 opacity-70 hover:opacity-100 hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-black/20 transition cursor-pointer"
            aria-label="Dismiss alert"
            onclick="this.closest('.rf-toast-alert, .rf-alert').remove()"
        >
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if(!$inline)
    <script>
        if (!window.rfToastInitialized) {
            window.rfToastInitialized = true;

            window.dismissRfToast = function(element, event) {
                if (event && event.target && (event.target.tagName === 'A' || event.target.tagName === 'BUTTON')) {
                    return;
                }
                const toast = element ? (element.classList.contains('rf-toast-alert') || element.classList.contains('rf-alert') ? element : element.closest('.rf-toast-alert, .rf-alert')) : null;
                if (!toast || toast.dataset.dismissing === 'true') return;
                toast.dataset.dismissing = 'true';
                if (toast._rfTimeout) {
                    clearTimeout(toast._rfTimeout);
                    toast._rfTimeout = null;
                }
                toast.style.transition = 'all 0.25s ease-out';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.remove();
                    }
                }, 250);
            };

            const ensureToastContainer = () => {
                let container = document.getElementById('rf-toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'rf-toast-container';
                    container.className = 'fixed top-4 right-4 sm:top-6 sm:right-6 z-[99999] flex flex-col gap-3 pointer-events-none w-[calc(100vw-2rem)] sm:w-96 max-w-full';
                    container.style.isolation = 'isolate';
                    document.body.appendChild(container);
                }
                return container;
            };

            const initToasts = () => {
                const container = ensureToastContainer();
                const toasts = document.querySelectorAll('.rf-toast-alert:not([data-initialized="true"])');
                toasts.forEach(toast => {
                    toast.setAttribute('data-initialized', 'true');
                    if (toast.parentElement !== container) {
                        container.appendChild(toast);
                    }
                    // Auto-dismiss every visible toast notification after 12 seconds
                    toast._rfTimeout = setTimeout(() => {
                        if (toast && document.body.contains(toast)) {
                            window.dismissRfToast(toast);
                        }
                    }, 12000);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initToasts);
            } else {
                initToasts();
            }

            const observer = new MutationObserver((mutations) => {
                let hasNewToast = false;
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1 && (node.classList.contains('rf-toast-alert') || node.querySelector('.rf-toast-alert'))) {
                            hasNewToast = true;
                        }
                    });
                });
                if (hasNewToast) initToasts();
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }
    </script>
@endif
