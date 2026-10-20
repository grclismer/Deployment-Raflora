@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])

@php
    $errorMessage = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $hasError = !empty($errorMessage);
    $groupId = $attributes->get('id', $name);
@endphp

<fieldset class="space-y-2">
    @if($label)
        <legend class="text-xs font-bold uppercase tracking-wider text-slate-700">
            {{ $label }}
            @if($required)
                <span class="text-rose-600 ml-0.5" aria-hidden="true" title="Required">*</span>
            @endif
        </legend>
    @endif
    <div class="space-y-2">
        @foreach($options as $optionValue => $optionLabel)
            @php
                $radioId = "{$groupId}_{$loop->index}";
            @endphp
            <label for="{{ $radioId }}" class="flex items-center gap-3 text-sm text-slate-700 select-none cursor-pointer">
                <input
                    type="radio"
                    id="{{ $radioId }}"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    {{ old($name, $value) == $optionValue ? 'checked' : '' }}
                    {{ $required ? 'required' : '' }}
                    {{ $disabled ? 'disabled' : '' }}
                    class="h-4 w-4 text-emerald-600 border-slate-300 focus:ring-emerald-500 disabled:opacity-50"
                />
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
    @if($hasError)
        <p id="{{ $groupId }}-error" role="alert" class="flex items-center gap-1.5 text-xs font-semibold text-rose-600">
            <i class="fa-solid fa-circle-exclamation shrink-0" aria-hidden="true"></i>
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</fieldset>
