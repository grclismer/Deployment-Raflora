<x-staff-layout title="Requests & Issues">
    <div class="space-y-6">
        {{-- Forms Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Form A: Request Inventory --}}
            <section id="inventory-request" class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-pink-50 text-rose-600 mb-2">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </span>
                    <h3 class="font-serif text-base font-bold text-navy-900">Request Materials from Inventory</h3>
                    <p class="text-xs text-slate-500">Ask Admin to procure or release inventory materials. Stock is not mutated until Admin acts.</p>
                </div>

                @if($events->isEmpty())
                    <p class="text-xs text-slate-400 py-4">You have no active events to request materials for.</p>
                @else
                    <form method="POST" action="{{ route('staff.requests.inventory') }}" class="space-y-3.5 text-xs">
                        @csrf
                        <div>
                            <label for="inv_booking_id" class="block font-bold text-slate-700 mb-1">Target Assigned Event</label>
                            <select id="inv_booking_id" name="booking_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none">
                                <option value="">Select Event...</option>
                                @foreach($events as $e)
                                    <option value="{{ $e->id }}" {{ $selectedEvent == $e->id ? 'selected' : '' }}>
                                        Booking #{{ $e->id }} · {{ $e->client?->full_name ?? $e->guest_name ?? 'Client' }} ({{ ucfirst((string) $e->event_type) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="inv_item_id" class="block font-bold text-slate-700 mb-1">Inventory Material Item</label>
                            <select id="inv_item_id" name="inventory_item_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none">
                                <option value="">Select Item...</option>
                                @foreach($inventoryItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="inv_quantity" class="block font-bold text-slate-700 mb-1">Quantity Needed</label>
                            <input id="inv_quantity" type="number" step="any" min="0.01" name="quantity" required placeholder="e.g. 20"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none">
                        </div>

                        <div>
                            <label for="inv_note" class="block font-bold text-slate-700 mb-1">Operational Note (Optional)</label>
                            <textarea id="inv_note" name="note" rows="2" placeholder="Explain why additional material is required..."
                                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none"></textarea>
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-navy-900 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-navy-800 transition">
                            Submit Material Request to Admin
                        </button>
                    </form>
                @endif
            </section>

            {{-- Form B: Report Issue --}}
            <section id="report-issue" class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 mb-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>
                    <h3 class="font-serif text-base font-bold text-navy-900">Report Operational Issue</h3>
                    <p class="text-xs text-slate-500">Log venue access delays, equipment defects, or shortages directly to Admin alerts.</p>
                </div>

                @if($events->isEmpty())
                    <p class="text-xs text-slate-400 py-4">You have no active events to report issues for.</p>
                @else
                    <form method="POST" action="{{ route('staff.requests.issue') }}" class="space-y-3.5 text-xs">
                        @csrf
                        <div>
                            <label for="issue_booking_id" class="block font-bold text-slate-700 mb-1">Target Assigned Event</label>
                            <select id="issue_booking_id" name="booking_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none">
                                <option value="">Select Event...</option>
                                @foreach($events as $e)
                                    <option value="{{ $e->id }}" {{ $selectedEvent == $e->id ? 'selected' : '' }}>
                                        Booking #{{ $e->id }} · {{ $e->client?->full_name ?? $e->guest_name ?? 'Client' }} ({{ ucfirst((string) $e->event_type) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="issue_category" class="block font-bold text-slate-700 mb-1">Issue Category</label>
                            <select id="issue_category" name="category" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none">
                                <option value="">Select Category...</option>
                                @foreach($issueCategories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="issue_description" class="block font-bold text-slate-700 mb-1">Detailed Description</label>
                            <textarea id="issue_description" name="description" rows="3" required placeholder="Describe the operational impediment and recommended assistance..."
                                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-2xs focus:border-pink-500 focus:outline-none"></textarea>
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-amber-600 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-amber-700 transition">
                            Report Issue to Admin
                        </button>
                    </form>
                @endif
            </section>
        </div>

        {{-- Requests & Issues List --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-navy-900">Submitted Requests & Alerts History</h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700 tabular-nums">{{ $requests->count() }} records</span>
            </div>

            @if($requests->isEmpty())
                <p class="py-6 text-center text-xs text-slate-400">No requests or issues currently open with Admin.</p>
            @else
                <ul class="divide-y divide-slate-100 text-xs" role="list">
                    @foreach($requests as $r)
                        <li class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $r->type === 'inventory_request' ? 'bg-pink-50 text-rose-700' : 'bg-amber-50 text-amber-800' }}">
                                        {{ $r->type === 'inventory_request' ? 'Material Request' : 'Operational Issue' }}
                                    </span>
                                    <span class="text-slate-400 font-medium">{{ $r->created_at->format('M d, Y · g:i A') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-navy-900 mt-1">{{ $r->title }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ $r->message }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold shrink-0 {{ $r->is_read ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800' }}">
                                {{ $r->is_read ? 'Reviewed by Admin' : 'Awaiting Review' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-staff-layout>
