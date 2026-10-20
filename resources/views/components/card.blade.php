@props([
    'title' => null,
    'subtitle' => null,
    'headerAction' => null,
])

<section {{ $attributes->merge(['class' => 'rf-panel p-5 sm:p-6 lg:p-8']) }}>
    @if($title || $headerAction)
        <div class="rf-section-heading mb-4 pb-3 border-b border-slate-100 flex items-start justify-between gap-4">
            <div>
                @if($title)
                    <h2 class="rf-section-heading__title text-lg sm:text-xl font-bold text-slate-900">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="text-sm text-slate-500 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @if($headerAction)
                <div class="shrink-0">
                    {{ $headerAction }}
                </div>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
