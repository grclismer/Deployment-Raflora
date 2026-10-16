{{-- Indoor / outdoor choice for the event venue (optional). Options: App\Support\EventDesignBrief::VENUE_SETTINGS --}}
@props(['value' => null])

@php
    $options = [
        'indoor' => 'Indoor',
        'outdoor' => 'Outdoor',
        'both' => 'Indoor & outdoor',
        '' => 'Not sure',
    ];
    $selected = (string) ($value ?? '');
@endphp

<fieldset {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <legend class="mb-1 block text-xs font-semibold text-[#0B1E43]">
        Indoor or outdoor? <span class="font-normal text-slate-400">(optional)</span>
    </legend>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        @foreach($options as $key => $label)
            <label class="cursor-pointer select-none">
                <input type="radio" name="venue_setting" id="venue_setting_{{ $key ?: 'unset' }}" value="{{ $key }}" class="peer sr-only" @checked($selected === (string) $key)>
                <span class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-1.5 px-2 text-xs font-semibold text-slate-700 transition-all hover:border-slate-300 hover:bg-slate-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 peer-checked:text-emerald-900 peer-checked:ring-2 peer-checked:ring-emerald-500/20 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 shadow-2xs">
                    @if($key === 'indoor')
                        <svg class="w-3.5 h-3.5 text-slate-500 peer-checked:text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    @elseif($key === 'outdoor')
                        <svg class="w-3.5 h-3.5 text-slate-500 peer-checked:text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    @elseif($key === 'both')
                        <svg class="w-3.5 h-3.5 text-slate-500 peer-checked:text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    @else
                        <svg class="w-3.5 h-3.5 text-slate-500 peer-checked:text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    @endif
                    <span>{{ $label }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <p class="mt-1 text-[10px] text-slate-500">Helps us choose heat- and wind-tolerant flowers and sturdier setups for outdoor areas.</p>
</fieldset>
