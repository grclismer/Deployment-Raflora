@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => '',
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'help' => null,
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $hasError = !empty($errorMessage);
    $selectId = $attributes->get('id', $name);

    $describedBy = [];
    if ($hasError) {
        $describedBy[] = $selectId . '-error';
    }
    if ($help) {
        $describedBy[] = $selectId . '-help';
    }
    $describedByString = implode(' ', $describedBy);

    $baseClasses = 'w-full rounded-xl border px-4 py-2.5 text-sm transition min-h-[44px] shadow-sm appearance-none bg-no-repeat pr-10';
    $stateClasses = $hasError
        ? 'border-rose-500 bg-rose-50/20 text-slate-900 focus:border-rose-600 focus:ring-2 focus:ring-rose-100'
        : 'border-slate-300 bg-white text-slate-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100';
    $disabledClasses = 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed';

    $selectClasses = trim("{$baseClasses} {$stateClasses} {$disabledClasses}");
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $selectId }}" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
            {{ $label }}
            @if($required)
                <span class="text-rose-600 ml-0.5" aria-hidden="true" title="Required">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <select
            id="{{ $selectId }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            @if($hasError)
                aria-invalid="true"
            @endif
            @if($required)
                aria-required="true"
            @endif
            @if(!empty($describedByString))
                aria-describedby="{{ $describedByString }}"
            @endif
            {{ $attributes->merge(['class' => $selectClasses]) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500" aria-hidden="true">
            <i class="fa-solid fa-chevron-down text-xs"></i>
        </div>
    </div>

    @if($help)
        <p id="{{ $selectId }}-help" class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @if($hasError)
        <p id="{{ $selectId }}-error" role="alert" class="flex items-center gap-1.5 text-xs font-semibold text-rose-600">
            <i class="fa-solid fa-circle-exclamation shrink-0" aria-hidden="true"></i>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>
