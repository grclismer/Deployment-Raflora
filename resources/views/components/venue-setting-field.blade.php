{{-- Indoor / outdoor choice for the event venue (optional). Options: App\Support\EventDesignBrief::VENUE_SETTINGS --}}
@props(['value' => null])

@php
    $options = ['' => 'Not sure yet'] + \App\Support\EventDesignBrief::VENUE_SETTINGS;
    $selected = (string) ($value ?? '');
@endphp

<fieldset {{ $attributes }}>
    <legend class="mb-1 block text-xs font-semibold text-[#0B1E43]">Indoor or outdoor? <span class="font-normal text-slate-400">(optional)</span></legend>
    <div class="flex flex-wrap gap-2">
        @foreach($options as $key => $label)
            <label class="cursor-pointer">
                <input type="radio" name="venue_setting" id="venue_setting_{{ $key ?: 'unset' }}" value="{{ $key }}" class="peer sr-only" @checked($selected === (string) $key)>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">
                    @if($key === 'indoor')<i class="fa-solid fa-house" aria-hidden="true"></i>@elseif($key === 'outdoor')<i class="fa-solid fa-sun" aria-hidden="true"></i>@elseif($key === 'both')<i class="fa-solid fa-tree-city" aria-hidden="true"></i>@endif
                    {{ $label }}
                </span>
            </label>
        @endforeach
    </div>
    <p class="mt-1 text-[11px] text-slate-500">Helps us choose heat- and wind-tolerant flowers and sturdier setups for outdoor areas.</p>
</fieldset>
