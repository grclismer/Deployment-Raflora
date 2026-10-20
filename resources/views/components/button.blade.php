@props([
    'variant' => null,
    'type' => null,
    'size' => 'md',
    'href' => null,
    'disabled' => false,
    'block' => false,
    'loading' => false,
    'actionType' => 'button',
])

@php
    $resolvedVariant = $variant ?? $type ?? 'primary';
    
    $variantClasses = match ($resolvedVariant) {
        'primary' => 'rf-btn rf-btn-primary',
        'secondary' => 'rf-btn rf-btn-secondary',
        'outline' => 'rf-btn rf-btn-outline',
        'ghost' => 'rf-btn rf-btn-ghost',
        'danger' => 'rf-btn rf-btn-danger',
        default => 'rf-btn rf-btn-primary',
    };

    $sizeClasses = match ($size) {
        'sm' => 'rf-btn-sm',
        'lg' => 'rf-btn-lg',
        default => 'rf-btn-md',
    };

    $classes = trim(implode(' ', array_filter([
        $variantClasses,
        $sizeClasses,
        $block ? 'rf-btn-block' : '',
        $loading ? 'is-loading' : '',
    ])));
@endphp

@if ($href)
    <a
        href="{{ $disabled ? '#' : $href }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if($disabled)
            aria-disabled="true"
            tabindex="-1"
            onclick="return false;"
        @endif
    >
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $attributes->get('type', $actionType) }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if($loading)
            <i class="fa-solid fa-circle-notch animate-spin mr-2" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </button>
@endif
