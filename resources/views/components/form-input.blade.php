@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'error' => null,
    'help' => null,
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $hasError = !empty($errorMessage);
    $inputId = $attributes->get('id', $name);

    $describedBy = [];
    if ($hasError) {
        $describedBy[] = $inputId . '-error';
    }
    if ($help) {
        $describedBy[] = $inputId . '-help';
    }
    $describedByString = implode(' ', $describedBy);

    $baseClasses = 'w-full rounded-xl border px-4 py-2.5 text-sm transition min-h-[44px] shadow-sm';
    $stateClasses = $hasError
        ? 'border-rose-500 bg-rose-50/20 text-slate-900 focus:border-rose-600 focus:ring-2 focus:ring-rose-100'
        : 'border-slate-300 bg-white text-slate-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100';
    $disabledClasses = 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed';
    $readonlyClasses = 'read-only:bg-slate-50 read-only:cursor-default';

    $inputClasses = trim("{$baseClasses} {$stateClasses} {$disabledClasses} {$readonlyClasses}");
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $inputId }}" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
            {{ $label }}
            @if($required)
                <span class="text-rose-600 ml-0.5" aria-hidden="true" title="Required">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {{ $readonly ? 'readonly' : '' }}
        @if($hasError)
            aria-invalid="true"
        @endif
        @if($required)
            aria-required="true"
        @endif
        @if(!empty($describedByString))
            aria-describedby="{{ $describedByString }}"
        @endif
        {{ $attributes->merge(['class' => $inputClasses]) }}
    />

    @if($help)
        <p id="{{ $inputId }}-help" class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @if($hasError)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-center gap-1.5 text-xs font-semibold text-rose-600">
            <i class="fa-solid fa-circle-exclamation shrink-0" aria-hidden="true"></i>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>
