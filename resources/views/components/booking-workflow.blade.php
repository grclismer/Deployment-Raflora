{{--
    README end-to-end booking workflow tracker (Guest → Client → Staff).
    Stage resolution is owned by App\Services\BookingWorkflowService; this component only renders it.
    Layout: summary header → "Now" card → 14-segment progress strip → collapsible phase lists
    (current phase open on phones; every phase open on large screens).
--}}
@props([
    'booking' => null,
    'workflow' => null,
    'heading' => 'Booking Workflow',
    'description' => 'End-to-end booking process from Guest to Client to Staff.',
    'accent' => 'emerald',
    'id' => 'booking-workflow',
    'audience' => null,
    // False where the page already shows the current status prominently (the client booking page).
    'showCurrent' => true,
])

@php
    $workflow = $workflow ?? app(\App\Services\BookingWorkflowService::class)->resolve($booking);

    // Staff only receive operational (Staff Workflow) details; other phases show progress state only.
    if ($audience === 'staff') {
        foreach ($workflow['stages'] as $stageKey => $stageData) {
            if ($stageData['phase'] !== 'staff') {
                $workflow['stages'][$stageKey]['detail'] = null;
            }
        }
        if ($workflow['current_phase'] && $workflow['current_phase'] !== 'staff') {
            $workflow['current_detail'] = 'This booking has not reached the Staff Workflow yet.';
        }
        if (!empty($workflow['terminal'])) {
            $workflow['terminal_detail'] = 'This booking process has been stopped and is no longer active.';
        }
    }

    $isTerminal = !empty($workflow['terminal']);
    $isFinished = !empty($workflow['finished']);

    // Guests and clients never see Staff-only operational detail. Guests, and clients whose booking
    // is not yet confirmed (the service's "Confirmed" stage, reached only by a verified payment),
    // see no Staff stages; confirmed clients see them with client wording instead of stock counts.
    if (in_array($audience, ['guest', 'client'], true)) {
        $confirmedReached = $isFinished || in_array($workflow['stages']['confirmed']['state'] ?? null, ['complete', 'current'], true);
        if ($audience === 'guest' || !$confirmedReached) {
            $workflow['stages'] = array_filter($workflow['stages'], fn (array $stage) => $stage['phase'] !== 'staff');
            unset($workflow['phases']['staff']);
            $workflow['total'] = count($workflow['stages']);
            if (($workflow['current_phase'] ?? null) === 'staff') {
                $workflow['current'] = null;
            }
        } else {
            $clientWording = [
                'preparation_reservation' => 'Raflora is preparing the materials for your event.',
                'dispatch' => 'Your event materials are on their way and being set up.',
                'material_return' => 'Raflora is collecting the reusable materials after your event.',
            ];
            foreach ($clientWording as $stageKey => $text) {
                if (!empty($workflow['stages'][$stageKey]['detail'])) {
                    $workflow['stages'][$stageKey]['detail'] = $text;
                }
                if (($workflow['current'] ?? null) === $stageKey) {
                    $workflow['current_detail'] = $text;
                }
            }
        }
    }
    $phaseCount = max(1, count($workflow['phases']));

    $palette = match ($accent) {
        'purple' => [
            'badge' => 'border-purple-200 bg-purple-50 text-purple-800',
            'now' => 'border-purple-200 bg-gradient-to-br from-purple-50 to-white',
            'nowIcon' => 'bg-purple-700 text-white',
            'nowLabel' => 'text-purple-700',
            'phaseCurrent' => 'border-purple-300 bg-purple-50/40 ring-1 ring-purple-100',
            'dotComplete' => 'bg-purple-700 text-white',
            'dotCurrent' => 'border-2 border-purple-700 bg-white ring-4 ring-purple-100',
            'dotCurrentInner' => 'bg-purple-700',
            'labelCurrent' => 'text-purple-800 font-bold',
            'currentTag' => 'bg-purple-700 text-white',
            'segComplete' => 'bg-purple-600',
            'segCurrent' => 'bg-purple-300 ring-2 ring-purple-600 ring-offset-1',
        ],
        // Raflora Green, used by the admin workspace.
        'brand' => [
            'badge' => 'border-brand-200 bg-brand-50 text-brand-800',
            'now' => 'border-brand-200 bg-gradient-to-br from-brand-50 to-white',
            'nowIcon' => 'bg-brand-700 text-white',
            'nowLabel' => 'text-brand-700',
            'phaseCurrent' => 'border-brand-300 bg-brand-50/40 ring-1 ring-brand-100',
            'dotComplete' => 'bg-brand-700 text-white',
            'dotCurrent' => 'border-2 border-brand-700 bg-white ring-4 ring-brand-100',
            'dotCurrentInner' => 'bg-brand-700',
            'labelCurrent' => 'text-brand-800 font-bold',
            'currentTag' => 'bg-brand-700 text-white',
            'segComplete' => 'bg-brand-600',
            'segCurrent' => 'bg-brand-300 ring-2 ring-brand-600 ring-offset-1',
        ],
        default => [
            'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'now' => 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white',
            'nowIcon' => 'bg-emerald-600 text-white',
            'nowLabel' => 'text-emerald-700',
            'phaseCurrent' => 'border-emerald-300 bg-emerald-50/40 ring-1 ring-emerald-100',
            'dotComplete' => 'bg-emerald-600 text-white',
            'dotCurrent' => 'border-2 border-emerald-600 bg-white ring-4 ring-emerald-100',
            'dotCurrentInner' => 'bg-emerald-600',
            'labelCurrent' => 'text-emerald-800 font-bold',
            'currentTag' => 'bg-emerald-600 text-white',
            'segComplete' => 'bg-emerald-500',
            'segCurrent' => 'bg-emerald-200 ring-2 ring-emerald-500 ring-offset-1',
        ],
    };

    $phaseActors = [
        'guest' => 'Guest',
        'client' => 'Client & Raflora Admin',
        'staff' => 'Raflora Staff & Admin',
    ];

    $phaseIcons = [
        'guest' => 'fa-user',
        'client' => 'fa-handshake',
        'staff' => 'fa-truck-fast',
    ];

    $stateText = [
        'complete' => 'Completed: ',
        'current' => 'Current: ',
        'upcoming' => 'Upcoming: ',
        'stopped' => 'Stopped: ',
    ];

    $completedStages = collect($workflow['stages'])->where('state', 'complete')->count();
@endphp

<section id="{{ $id }}" aria-labelledby="{{ $id }}-heading" data-booking-workflow {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6']) }}>
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 id="{{ $id }}-heading" class="text-lg font-bold text-slate-900">{{ $heading }}</h2>
            @if($description)
                <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>

        @if($isTerminal)
            <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-800">
                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>{{ $workflow['terminal_label'] }}
            </span>
        @elseif($isFinished)
            <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>Workflow complete · {{ $workflow['total'] }} of {{ $workflow['total'] }}
            </span>
        @elseif($workflow['current'] && isset($action) && $action->isNotEmpty())
            {{-- Optional shortcut (Admin booking page) shown in place of the stage badge. --}}
            <div class="shrink-0">{{ $action }}</div>
        @elseif($workflow['current'])
            <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold {{ $palette['badge'] }}">
                Stage {{ $workflow['current_number'] }} of {{ $workflow['total'] }} · {{ $workflow['current_label'] }}
            </span>
        @endif
    </div>

    {{-- Now / stopped --}}
    @if($isTerminal)
        <div class="mt-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4" role="status">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-600 text-white" aria-hidden="true"><i class="fa-solid fa-ban text-sm"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-rose-800">{{ $workflow['terminal_label'] }}</p>
                <p class="mt-1 text-xs leading-relaxed text-rose-700">{{ $workflow['terminal_detail'] }}</p>
            </div>
        </div>
    @elseif($workflow['current'] && !$isFinished && $showCurrent)
        <div class="mt-4 flex items-start gap-3 rounded-xl border p-4 {{ $palette['now'] }}" role="status">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $palette['nowIcon'] }}" aria-hidden="true">
                <i class="fa-solid {{ $phaseIcons[$workflow['current_phase']] ?? 'fa-location-dot' }} text-sm"></i>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider {{ $palette['nowLabel'] }}">
                    Now · {{ \App\Services\BookingWorkflowService::PHASES[$workflow['current_phase']] ?? '' }}
                </p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $workflow['current_label'] }}</p>
                @if($workflow['current_detail'])
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-600">{{ $workflow['current_detail'] }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- 14-segment progress strip, grouped by phase --}}
    <div class="mt-5 grid {{ $phaseCount === 3 ? 'grid-cols-[2fr_6fr_6fr]' : 'grid-cols-[2fr_6fr]' }} gap-2 sm:gap-3" aria-hidden="true">
        @foreach($workflow['phases'] as $phase)
            <div class="min-w-0">
                <div class="mb-1.5 flex items-baseline justify-between gap-1">
                    <span class="truncate text-[9px] font-bold uppercase tracking-wide sm:text-[10px] sm:tracking-wider {{ $phase['state'] === 'current' ? 'text-slate-900' : 'text-slate-400' }}">{{ \Illuminate\Support\Str::before($phase['label'], ' Workflow') }}</span>
                    <span class="hidden shrink-0 text-[10px] font-semibold text-slate-400 sm:inline">{{ $phase['completed'] }}/{{ $phase['total'] }}</span>
                </div>
                <div class="flex gap-0.5 sm:gap-1">
                    @foreach($phase['stages'] as $stageKey)
                        @php $segmentState = $workflow['stages'][$stageKey]['state']; @endphp
                        <span class="h-1.5 flex-1 rounded-full {{ match ($segmentState) {
                            'complete' => $palette['segComplete'],
                            'current' => $palette['segCurrent'],
                            'stopped' => 'bg-slate-100',
                            default => 'bg-slate-200',
                        } }}" title="{{ $workflow['stages'][$stageKey]['label'] }}"></span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <p class="sr-only">{{ $completedStages }} of {{ $workflow['total'] }} workflow stages complete.</p>

    {{-- Phase lists: current phase open on phones, every phase open on large screens --}}
    <div class="mt-5 grid grid-cols-1 gap-3 {{ $phaseCount === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} lg:gap-4">
        @foreach($workflow['phases'] as $phase)
            @php $openByDefault = $phase['state'] === 'current' || ($isTerminal && $phase['key'] === 'guest'); @endphp
            <details class="group min-w-0 rounded-xl border {{ $phase['state'] === 'current' ? $palette['phaseCurrent'] : 'border-slate-200 bg-slate-50/50' }}" data-workflow-phase="{{ $phase['key'] }}" data-workflow-phase-details @if($openByDefault) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-xl p-4 [&::-webkit-details-marker]:hidden">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $phase['state'] === 'complete' ? $palette['dotComplete'] : ($phase['state'] === 'current' ? $palette['nowIcon'] : 'bg-white text-slate-400 ring-1 ring-slate-200') }}" aria-hidden="true">
                            <i class="fa-solid {{ $phase['state'] === 'complete' ? 'fa-check' : ($phaseIcons[$phase['key']] ?? 'fa-circle') }} text-xs"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">{{ $phase['label'] }}</span>
                            <span class="block truncate text-[11px] text-slate-500">{{ $phaseActors[$phase['key']] ?? '' }}</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">
                            {{ $phase['completed'] }}/{{ $phase['total'] }}<span class="sr-only"> stages complete</span>
                        </span>
                        <span class="inline-flex text-slate-400 transition-transform duration-200 group-open:rotate-180 lg:hidden" aria-hidden="true"><i class="fa-solid fa-chevron-down text-[10px]"></i></span>
                    </span>
                </summary>

                <ol class="space-y-2.5 px-4 pb-4" role="list">
                    @foreach($phase['stages'] as $stageKey)
                        @php
                            $stage = $workflow['stages'][$stageKey];
                            $state = $stage['state'];
                        @endphp
                        <li class="flex items-start gap-2.5" data-workflow-stage="{{ $stageKey }}" data-workflow-state="{{ $state }}" @if($state === 'current') aria-current="step" @endif>
                            @if($state === 'complete')
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $palette['dotComplete'] }}" aria-hidden="true">
                                    <i class="fa-solid fa-check text-[10px]"></i>
                                </span>
                            @elseif($state === 'current')
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $palette['dotCurrent'] }}" aria-hidden="true">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $palette['dotCurrentInner'] }}"></span>
                                </span>
                            @elseif($state === 'stopped')
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 bg-slate-100 text-slate-400" aria-hidden="true">
                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                </span>
                            @else
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 bg-white text-[10px] font-bold text-slate-400" aria-hidden="true">
                                    {{ $stage['number'] }}
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-snug {{ $state === 'current' ? $palette['labelCurrent'] : ($state === 'complete' ? 'font-semibold text-slate-800' : 'font-medium text-slate-400') }}">
                                    <span class="sr-only">{{ $stateText[$state] ?? '' }}</span>{{ $stage['label'] }}
                                    @if($state === 'current')
                                        <span class="ml-1 inline-block rounded-full px-1.5 py-0.5 align-middle text-[9px] font-extrabold uppercase tracking-wider {{ $palette['currentTag'] }}" aria-hidden="true">Now</span>
                                    @endif
                                </p>
                                @if(!empty($stage['detail']))
                                    <p class="mt-0.5 break-words text-xs leading-relaxed text-slate-500">{{ $stage['detail'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endforeach
    </div>
</section>

@once
    <script>
        // Large screens show every workflow phase side by side; phones keep only the current phase open.
        (function () {
            var openAllOnDesktop = function () {
                if (!window.matchMedia('(min-width: 1024px)').matches) {
                    return;
                }
                document.querySelectorAll('[data-workflow-phase-details]').forEach(function (phase) {
                    phase.open = true;
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openAllOnDesktop);
            } else {
                openAllOnDesktop();
            }
        })();
    </script>
@endonce
