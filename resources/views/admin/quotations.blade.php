<x-admin-layout title="Quotations">
    <!-- Filter & Search Controls -->
    <form method="GET" action="{{ route('admin.quotations') }}" class="mb-6 flex flex-col items-start gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
            <!-- Status Filter -->
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <label for="status" class="text-xs font-semibold text-gray-700 sm:text-sm">Status</label>
                <select id="status" name="status" class="rf-select w-full sm:w-auto sm:min-w-44 rounded-lg px-3 py-2 text-sm">
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active (Pending & Issued)</option>
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="issued" {{ $statusFilter === 'issued' ? 'selected' : '' }}>Issued</option>
                    <option value="accepted" {{ $statusFilter === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="superseded" {{ $statusFilter === 'superseded' ? 'selected' : '' }}>Superseded</option>
                </select>
            </div>

            <!-- Sort Option -->
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <label for="sort" class="text-xs font-semibold text-gray-700 sm:text-sm whitespace-nowrap">Sort by</label>
                <select id="sort" name="sort" class="rf-select w-full sm:w-auto sm:min-w-36 rounded-lg px-3 py-2 text-sm">
                    <option value="latest" {{ $sortOrder === 'latest' ? 'selected' : '' }}>Newest First</option>
                    <option value="oldest" {{ $sortOrder === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                    <option value="amount_high" {{ $sortOrder === 'amount_high' ? 'selected' : '' }}>Highest Amount</option>
                    <option value="amount_low" {{ $sortOrder === 'amount_low' ? 'selected' : '' }}>Lowest Amount</option>
                    <option value="valid_until" {{ $sortOrder === 'valid_until' ? 'selected' : '' }}>Validity Date</option>
                </select>
            </div>
        </div>

        <!-- Search Input -->
        <div class="flex w-full items-center gap-2 sm:w-auto">
            <div class="relative w-full min-w-0 sm:w-64">
                <input type="text" 
                       id="search"
                       name="search" 
                       value="{{ $searchTerm }}" 
                       placeholder="Search client, event, ID..." 
                       class="rf-input w-full rounded-lg px-3 py-2 text-sm"
                       aria-label="Search quotations"
                />
            </div>
            <button type="submit" class="btn-primary text-sm whitespace-nowrap">Filter</button>
            @if($statusFilter !== 'active' || !empty($searchTerm) || $sortOrder !== 'latest')
                <a href="{{ route('admin.quotations') }}" class="rf-btn rf-btn-outline text-sm whitespace-nowrap">Reset</a>
            @endif
        </div>
    </form>

    <section class="rf-panel mb-6 p-5 sm:p-6" aria-labelledby="pending-quotations-heading">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100">
            <div>
                <h1 id="pending-quotations-heading" class="page-title text-2xl sm:text-3xl">Pending Quotations</h1>
                <p class="section-subtitle mt-1">Review estimates and return to the booking review screen for quotation actions.</p>
            </div>
            <div class="mt-2 sm:mt-0 text-xs font-medium text-slate-500">
                Showing {{ $quotations->total() }} {{ \Illuminate\Support\Str::plural('quotation', $quotations->total()) }}
            </div>
        </div>

        <div class="overflow-x-auto mt-4 w-full">
            <table class="rf-table--stack w-full">
                <thead class="bg-brand-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Booking</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Client</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Version</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Estimate</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Validity</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-100">
                    @forelse($quotations as $quotation)
                        @php
                            $isExpired = $quotation->valid_until && $quotation->valid_until->endOfDay()->isPast();
                            $statusClass = match($quotation->status) {
                                'pending' => 'rf-badge--warning',
                                'issued' => $isExpired ? 'rf-badge--danger' : 'rf-badge--primary',
                                'accepted' => 'rf-badge--success',
                                'superseded' => 'rf-badge--neutral',
                                default => 'rf-badge--neutral',
                            };
                            $displayStatus = match($quotation->status) {
                                'pending' => 'Pending',
                                'issued' => $isExpired ? 'Issued (Expired)' : 'Issued',
                                'accepted' => 'Accepted',
                                'superseded' => 'Superseded',
                                default => ucfirst($quotation->status),
                            };
                            $clientDisplayName = $quotation->booking?->client?->full_name 
                                ?? $quotation->booking?->client?->name 
                                ?? $quotation->booking?->guest_name 
                                ?? 'N/A';
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4 text-sm text-gray-800">
                                <div class="font-medium text-slate-800">{{ $quotation->booking?->event_type ? ucwords(str_replace('_', ' ', $quotation->booking->event_type)) : 'N/A' }}</div>
                                @if($quotation->booking)
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        Booking #{{ $quotation->booking_id }}
                                        @if($quotation->booking->event_date)
                                            · {{ $quotation->booking->event_date->format('M j, Y') }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td data-label="Client" class="px-6 py-4 text-sm text-gray-600">
                                <div class="font-medium text-slate-800">{{ $clientDisplayName }}</div>
                                @php
                                    $clientEmail = $quotation->booking?->client?->email ?? $quotation->booking?->guest_email;
                                @endphp
                                @if($clientEmail)
                                    <div class="text-xs text-slate-400 mt-0.5">{{ $clientEmail }}</div>
                                @endif
                            </td>
                            <td data-label="Version" class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-100 text-brand-800">
                                    v{{ $quotation->version ?? 1 }}
                                </span>
                            </td>
                            <td data-label="Estimate" class="px-6 py-4 text-sm text-gray-600">
                                <div class="font-semibold text-slate-800">
                                    ₱{{ number_format((float) ($quotation->final_quoted_price ?? $quotation->recommended_price ?? 0), 2) }}
                                </div>
                                @if($quotation->downpayment_percentage)
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        {{ number_format((float) $quotation->downpayment_percentage, 0) }}% Downpayment
                                    </div>
                                @endif
                            </td>
                            <td data-label="Validity" class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                @if($quotation->valid_until)
                                    <div class="{{ $isExpired ? 'text-rose-600 font-medium' : 'text-slate-700' }}">
                                        {{ $quotation->valid_until->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs {{ $isExpired ? 'text-rose-500' : 'text-slate-400' }}">
                                        {{ $isExpired ? 'Expired' : $quotation->valid_until->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td data-label="Status" class="px-6 py-4 whitespace-nowrap">
                                <span class="rf-badge {{ $statusClass }}">
                                    <span aria-hidden="true">•</span>{{ $displayStatus }}
                                </span>
                                @if($quotation->is_tentative && is_null($quotation->reconfirmed_at))
                                    <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 ml-1">
                                        Tentative
                                    </span>
                                @elseif($quotation->reconfirmed_at)
                                    <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 ml-1">
                                        Reconfirmed
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('admin.bookings.show', $quotation->booking_id) }}" class="rf-btn rf-btn-outline text-sm">Review quotation</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                @if(!empty($searchTerm) || $statusFilter !== 'active')
                                    No quotations found matching your filter criteria.
                                    <div class="mt-2">
                                        <a href="{{ route('admin.quotations') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800 underline">Clear filters</a>
                                    </div>
                                @else
                                    No pending quotations found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($quotations->hasPages())
            <div class="mt-6 border-t border-slate-100 pt-4">
                {{ $quotations->links() }}
            </div>
        @endif
    </section>

    <section class="rf-panel p-5 sm:p-6" aria-labelledby="reconfirmation-heading">
        <h2 id="reconfirmation-heading" class="text-sm font-semibold uppercase text-brand-700 mb-3">Price Reconfirmation Notes</h2>
        <p class="text-gray-600 text-sm">Review current stock availability and adjust quotation details before sending a final confirmation to the client.</p>
    </section>
</x-admin-layout>

