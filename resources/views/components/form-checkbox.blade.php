@props([
    'label' => null,
    'name',
    'value' => '1',
    'checked' => false,
    'required' => false,
    'disabled' => false,
    'error' => null,
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $hasError = !empty($errorMessage);
    $checkboxId = $attributes->get('id', $name);
@endphp

<div class="space-y-1">
    <div class="flex items-start gap-3">
        <input
            id="{{ $checkboxId }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            {{ old($name, $checked) ? 'checked' : '' }}
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            @if($hasError)
                aria-invalid="true"
                aria-describedby="{{ $checkboxId }}-error"
            @endif
            {{ $attributes->merge(['class' => 'mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 transition disabled:opacity-50 disabled:cursor-not-allowed']) }}
        />
        @if($label)
            <label for="{{ $checkboxId }}" class="text-sm font-medium text-slate-700 select-none">
                {{ $label }}
                @if($required)
                    <span class="text-rose-600 ml-0.5" aria-hidden="true" title="Required">*</span>
                @endif
            </label>
        @endif
    </div>

    @if($hasError)
        <p id="{{ $checkboxId }}-error" role="alert" class="flex items-center gap-1.5 text-xs font-semibold text-rose-600 pl-7">
            <i class="fa-solid fa-circle-exclamation shrink-0" aria-hidden="true"></i>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>
