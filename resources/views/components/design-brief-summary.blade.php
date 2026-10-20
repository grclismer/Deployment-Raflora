{{--
    What the client told us about the event's setting and design (the same details the Smart AI
    analysis received), for the Admin booking page and the Staff event page.
--}}
@props(['booking'])

@php
    $venueSetting = \App\Support\EventDesignBrief::VENUE_SETTINGS[$booking->venue_setting ?? ''] ?? null;
    $rows = ($venueSetting ? ['Venue setting' => $venueSetting] : [])
        + \App\Support\EventDesignBrief::displayRows($booking->design_brief);
@endphp

@if($rows !== [])
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-slate-50/60 p-4']) }}>
        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Client design brief</h4>
        <p class="mt-0.5 text-[11px] text-slate-500">Given with the booking and sent to the AI analysis.</p>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2.5 sm:grid-cols-2">
            @foreach($rows as $label => $value)
                <div class="min-w-0 {{ in_array($label, ['Areas to decorate', 'Flowers to avoid'], true) ? 'sm:col-span-2' : '' }}">
                    <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words text-sm font-medium {{ $label === 'Flowers to avoid' ? 'text-rose-700' : 'text-slate-900' }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
