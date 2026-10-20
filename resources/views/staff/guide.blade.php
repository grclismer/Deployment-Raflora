<x-staff-layout title="Resource Guide">
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs">
            <h3 class="font-serif text-lg font-bold text-navy-900">14-Stage Booking Progress Workflow</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                Raflora's end-to-end lifecycle ensures rigorous separation of responsibilities. Staff handles on-the-ground operational execution (Preparation, Dispatch, Event Setup, and Material Returns) while Admin maintains authority over pricing, client approvals, and inventory reconciliation.
            </p>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs">
            <ol class="space-y-4 relative border-l-2 border-slate-100 ml-4 pl-6" role="list">
                @foreach($stages as $idx => $stage)
                    @php
                        $phaseBadge = match($stage['phase']) {
                            'staff' => ['Staff Operational Phase', 'bg-pink-50 text-rose-700 border-pink-200'],
                            'admin' => ['Admin Authorization Phase', 'bg-purple-50 text-purple-700 border-purple-200'],
                            default => ['Client & Proposal Phase', 'bg-sky-50 text-sky-700 border-sky-200']
                        };
                    @endphp
                    <li class="relative">
                        <span class="absolute -left-[33px] top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white ring-2 ring-slate-200 text-xs font-bold text-navy-900 tabular-nums">
                            {{ $idx + 1 }}
                        </span>
                        <div class="flex items-center gap-3">
                            <h4 class="text-sm font-bold text-navy-900">{{ $stage['label'] }}</h4>
                            <span class="rounded-md border px-2 py-0.2 text-[10px] font-bold {{ $phaseBadge[1] }}">
                                {{ $phaseBadge[0] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            @if($stage['phase'] === 'staff')
                                Staff prepares materials, verifies setups, records dispatches, or performs material return inspections.
                            @elseif($stage['phase'] === 'admin')
                                Requires Admin verification, pricing approval, financial review, or inventory reconciliation.
                            @else
                                Client submits request, receives quotation, accepts proposal, or submits downpayment.
                            @endif
                        </p>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-staff-layout>
