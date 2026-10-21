<x-admin-layout
    title="Client Records"
    description="Manage client information and review their booking activity."
>
    <div class="mx-auto max-w-[1600px] space-y-6">

        <!-- Primary Toolbar: Search & Collapsible Filters -->
        <div class="bg-white rounded-2xl shadow-xs border border-gray-100 p-4 sm:p-5">
            <form method="GET" action="{{ route('admin.client-records') }}" id="clientFilterForm" class="space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <!-- Compact Search Area -->
                    <div class="flex flex-wrap items-center gap-2.5 flex-1">
                        <!-- Search Box -->
                        <div class="relative flex-1 min-w-[240px] max-w-md">
                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input
                                type="text"
                                name="search"
                                value="{{ $currentSearch ?? '' }}"
                                placeholder="Search client name, email, or phone..."
                                class="w-full pl-9 pr-3.5 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs"
                            >
                        </div>

                        <!-- Show Filters Toggle Button -->
                        <button
                            type="button"
                            id="toggleFiltersBtn"
                            onclick="toggleFilterPanel()"
                            aria-expanded="{{ !empty($hasActiveFilters) ? 'true' : 'false' }}"
                            aria-controls="clientFilterPanel"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 border rounded-xl text-sm font-medium transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer {{ !empty($hasActiveFilters) ? 'border-brand-300 bg-brand-50 text-brand-700 font-semibold' : 'border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800' }}"
                        >
                            <i class="fa-solid fa-sliders text-xs {{ !empty($hasActiveFilters) ? 'text-brand-700' : 'text-gray-500' }}"></i>
                            <span id="toggleFiltersText">{{ !empty($hasActiveFilters) ? 'Hide Filters' : 'Show Filters' }}</span>
                            @if(!empty($hasActiveFilters))
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-700 inline-block" title="Filters are active"></span>
                            @endif
                            <i id="filtersChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200 {{ !empty($hasActiveFilters) ? 'rotate-180' : '' }}"></i>
                        </button>

                        <!-- Search Submit Button -->
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                        >
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            <span>Search</span>
                        </button>

                        @if(!empty($currentSearch) || !empty($hasActiveFilters))
                            <a
                                href="{{ route('admin.client-records') }}"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                            >
                                Clear Filters
                            </a>
                        @endif
                    </div>

                    <!-- Client Count Badge -->
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center rounded-full bg-brand-50 text-brand-700 border border-brand-200/60 px-3.5 py-1.5 text-xs font-bold tracking-wide">
                            {{ $clients->total() }} {{ Str::plural('Client', $clients->total()) }}
                        </span>
                    </div>
                </div>

                <!-- Secondary Filters Collapsible Panel -->
                <div id="clientFilterPanel" style="{{ !empty($hasActiveFilters) ? 'display: block;' : 'display: none;' }}" class="pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Booking Activity Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Booking Activity</label>
                            <div class="relative">
                                <select name="activity" onchange="this.form.submit()" class="w-full py-2 pl-3 pr-8 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="all" {{ ($currentActivity ?? 'all') === 'all' ? 'selected' : '' }}>All Clients</option>
                                    <option value="has_bookings" {{ ($currentActivity ?? '') === 'has_bookings' ? 'selected' : '' }}>Has Bookings</option>
                                    <option value="no_bookings" {{ ($currentActivity ?? '') === 'no_bookings' ? 'selected' : '' }}>No Bookings</option>
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Sort Filter -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1.5">Sort By</label>
                            <div class="relative">
                                <select name="sort" onchange="this.form.submit()" class="w-full py-2 pl-3 pr-8 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer">
                                    <option value="name_asc" {{ ($currentSort ?? 'name_asc') === 'name_asc' ? 'selected' : '' }}>Name A–Z</option>
                                    <option value="name_desc" {{ ($currentSort ?? '') === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
                                    <option value="latest_activity" {{ ($currentSort ?? '') === 'latest_activity' ? 'selected' : '' }}>Latest Activity</option>
                                    <option value="most_bookings" {{ ($currentSort ?? '') === 'most_bookings' ? 'selected' : '' }}>Most Bookings</option>
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Reset Link -->
                        <div class="flex items-end">
                            <a href="{{ route('admin.client-records') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-brand-700 hover:text-brand-900 transition">
                                <i class="fa-solid fa-rotate-left text-xs"></i>
                                <span>Reset to default</span>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Client Data Table -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden" aria-label="Client records directory">
            <div class="overflow-x-auto w-full">
                <table class="rf-table--stack w-full min-w-[760px] text-left text-sm border-collapse" aria-label="Client records table">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] uppercase tracking-[0.14em] text-slate-500">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-semibold">Client</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Contact</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Booking Activity</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Last Activity</th>
                            <th scope="col" class="px-6 py-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($clients as $client)
                            @php
                                $nameParts = array_values(array_filter(explode(' ', trim($client->full_name ?? $client->name ?? 'Client'))));
                                if (count($nameParts) >= 2) {
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
                                } else {
                                    $initials = strtoupper(substr($nameParts[0] ?? 'C', 0, 2));
                                }
                                $paletteIndex = abs(crc32($client->email ?? (string)$client->id)) % 6;
                                $avatarThemes = [
                                    ['bg' => 'bg-brand-100', 'text' => 'text-brand-700', 'border' => 'border-brand-200'],
                                    ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200'],
                                    ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200'],
                                    ['bg' => 'bg-sky-100', 'text' => 'text-sky-700', 'border' => 'border-sky-200'],
                                    ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'border' => 'border-amber-200'],
                                    ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200'],
                                ];
                                $theme = $avatarThemes[$paletteIndex];
                            @endphp
                            <tr class="align-middle hover:bg-slate-50/80 transition">
                                <!-- CLIENT Column -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl {{ $theme['bg'] }} {{ $theme['text'] }} border {{ $theme['border'] }} flex items-center justify-center font-bold text-xs shrink-0 select-none shadow-2xs">
                                            {{ $initials }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="max-w-xs break-words text-sm font-bold leading-tight text-slate-900 [overflow-wrap:anywhere]">
                                                {{ $client->full_name ?? $client->name ?? 'Unnamed client' }}
                                            </div>
                                            <div class="text-xs text-slate-400 font-mono mt-0.5">
                                                ID #{{ $client->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- CONTACT Column -->
                                <td data-label="Contact" class="px-6 py-4">
                                    <div class="flex min-w-0 items-start gap-1.5 text-sm font-medium text-slate-700">
                                        <i class="fa-regular fa-envelope mt-0.5 shrink-0 text-xs text-slate-400"></i>
                                        <span class="min-w-0 break-all">{{ $client->email }}</span>
                                    </div>
                                    @if(!empty($client->phone))
                                        <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                            <i class="fa-solid fa-phone text-slate-400 text-[10px]"></i>
                                            <span>{{ $client->phone }}</span>
                                        </div>
                                    @else
                                        <div class="text-xs text-slate-400 italic mt-1 flex items-center gap-1.5">
                                            <i class="fa-solid fa-phone text-slate-300 text-[10px]"></i>
                                            <span>No phone provided</span>
                                        </div>
                                    @endif
                                </td>

                                <!-- BOOKING ACTIVITY Column -->
                                <td data-label="Booking Activity" class="px-6 py-4">
                                    @if($client->bookings_count > 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 text-brand-700 border border-brand-200/70 px-3 py-1 text-xs font-semibold">
                                            <i class="fa-solid fa-calendar-check text-[10px]"></i>
                                            {{ $client->bookings_count }} {{ Str::plural('booking', $client->bookings_count) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200/60 px-3 py-1 text-xs font-medium">
                                            <i class="fa-regular fa-calendar text-[10px]"></i>
                                            0 bookings
                                        </span>
                                    @endif
                                </td>

                                <!-- LAST ACTIVITY Column -->
                                <td data-label="Last Activity" class="px-6 py-4">
                                    @if($client->bookings->first())
                                        <div class="text-sm font-semibold text-slate-800">
                                            {{ $client->bookings->first()->created_at?->format('M d, Y') }}
                                        </div>
                                        <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                            <span>Latest booking</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">
                                            No activity
                                        </span>
                                    @endif
                                </td>

                                <!-- ACTION Column -->
                                <td class="px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        onclick="openClientDrawer({{ $client->id }})"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 hover:text-brand-800 border border-brand-200 text-xs font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                                        title="View details for {{ $client->full_name }}"
                                    >
                                        <i class="fa-regular fa-eye text-xs"></i>
                                        <span>View</span>
                                    </button>

                                    <!-- Hidden template for Client Details Drawer -->
                                    <template id="client-drawer-template-{{ $client->id }}">
                                        <div class="h-full flex flex-col justify-between">
                                            <!-- CLIENT HEADER -->
                                            <div class="p-6 border-b border-slate-100 flex items-start justify-between gap-4 bg-white sticky top-0 z-10">
                                                <div class="flex items-center gap-3.5 min-w-0">
                                                    <div class="w-12 h-12 rounded-2xl {{ $theme['bg'] }} {{ $theme['text'] }} border {{ $theme['border'] }} flex items-center justify-center font-bold text-base shrink-0 select-none shadow-2xs">
                                                        {{ $initials }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <h2 class="text-lg font-bold text-slate-900 truncate" title="{{ $client->full_name }}">
                                                            {{ $client->full_name }}
                                                        </h2>
                                                        <p class="text-xs font-mono text-slate-400 mt-0.5">
                                                            Client ID #{{ $client->id }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    onclick="closeClientDrawer()"
                                                    class="rounded-xl p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer shrink-0"
                                                    aria-label="Close client drawer"
                                                >
                                                    <i class="fa-solid fa-xmark text-lg"></i>
                                                </button>
                                            </div>

                                            <!-- DRAWER BODY -->
                                            <div class="p-6 space-y-6 flex-1 overflow-y-auto">
                                                <!-- CONTACT INFORMATION -->
                                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-3">
                                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                                        <i class="fa-regular fa-address-card text-brand-700"></i>
                                                        <span>Contact Information</span>
                                                    </h3>
                                                    <div class="space-y-2.5 pt-1">
                                                        <div class="flex items-center gap-3 text-sm text-slate-700">
                                                            <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0">
                                                                <i class="fa-regular fa-envelope text-xs"></i>
                                                            </div>
                                                            <span class="break-all font-medium select-all">{{ $client->email }}</span>
                                                        </div>

                                                        <div class="flex items-center gap-3 text-sm text-slate-700">
                                                            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                                                                <i class="fa-solid fa-phone text-xs"></i>
                                                            </div>
                                                            <span>{{ $client->phone ?: 'No phone provided' }}</span>
                                                        </div>

                                                        @if(!empty($client->address))
                                                            <div class="flex items-start gap-3 text-sm text-slate-700">
                                                                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 mt-0.5">
                                                                    <i class="fa-solid fa-location-dot text-xs"></i>
                                                                </div>
                                                                <span class="leading-snug">{{ $client->address }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- BOOKING SUMMARY -->
                                                <div>
                                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5 flex items-center gap-2">
                                                        <i class="fa-solid fa-chart-pie text-brand-700"></i>
                                                        <span>Booking Summary</span>
                                                    </h3>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                            <p class="text-xs font-semibold text-slate-500">Total Bookings</p>
                                                            <p class="mt-1 text-2xl font-bold text-brand-700">{{ $client->bookings_count }}</p>
                                                        </div>
                                                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                            <p class="text-xs font-semibold text-slate-500">Latest Activity</p>
                                                            @if($client->bookings->first())
                                                                <p class="mt-1 text-sm font-bold text-slate-800">{{ $client->bookings->first()->created_at?->format('M d, Y') }}</p>
                                                                <p class="text-[11px] text-emerald-600 font-medium flex items-center gap-1 mt-0.5">
                                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                                                    <span>Latest booking</span>
                                                                </p>
                                                            @else
                                                                <p class="mt-1 text-sm text-slate-400 italic">No activity</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- RECENT BOOKINGS -->
                                                <div class="space-y-3">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                                            <i class="fa-solid fa-calendar-days text-brand-700"></i>
                                                            <span>Recent Bookings</span>
                                                        </h3>
                                                        @if($client->bookings_count > 0)
                                                            <span class="text-xs text-slate-500 font-medium">
                                                                Showing {{ $client->bookings->take(5)->count() }} of {{ $client->bookings_count }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    @forelse($client->bookings->take(5) as $booking)
                                                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-brand-200 transition space-y-2">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div>
                                                                    <span class="text-xs font-bold text-brand-700 font-mono">
                                                                        Booking #{{ $booking->id }}
                                                                    </span>
                                                                    <h4 class="text-sm font-bold text-slate-900 mt-0.5">
                                                                        {{ $booking->event_type ? ucwords(str_replace('_', ' ', $booking->event_type)) : 'General Event' }}
                                                                    </h4>
                                                                </div>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ in_array($booking->status, ['confirmed', 'completed'], true) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (in_array($booking->status, ['cancelled', 'declined'], true) ? 'bg-slate-100 text-slate-600 border border-slate-200' : 'bg-brand-50 text-brand-700 border border-brand-200') }}">
                                                                    {{ $booking->status_display_label }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-4 text-xs text-slate-500 pt-1 border-t border-slate-100">
                                                                <span class="flex items-center gap-1.5">
                                                                    <i class="fa-regular fa-calendar text-slate-400"></i>
                                                                    <span>Event: {{ $booking->event_date ? \Carbon\Carbon::parse($booking->event_date)->format('M d, Y') : 'Date not set' }}</span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400 bg-slate-50/50">
                                                            <i class="fa-regular fa-calendar-xmark text-lg text-slate-300 mb-1.5 block"></i>
                                                            <span>No bookings on record for this client.</span>
                                                        </div>
                                                    @endforelse
                                                </div>

                                                <!-- CLIENT NOTES (if present on model) -->
                                                @if(!empty($client->notes))
                                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-1.5">
                                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                                            <i class="fa-regular fa-note-sticky text-slate-400"></i>
                                                            <span>Client Notes</span>
                                                        </p>
                                                        <p class="text-xs text-slate-700 whitespace-pre-line leading-relaxed">{{ $client->notes }}</p>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- DRAWER ACTION -->
                                            <div class="p-6 border-t border-slate-100 bg-slate-50/80 sticky bottom-0 z-10">
                                                <a
                                                    href="{{ route('admin.bookings', ['search' => $client->email]) }}"
                                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-xl shadow-xs transition focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                                                >
                                                    <span>View Booking History</span>
                                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-14 text-center">
                                    @if(!empty($currentSearch) || !empty($hasActiveFilters))
                                        <div class="max-w-md mx-auto text-slate-500">
                                            <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto mb-3">
                                                <i class="fa-solid fa-magnifying-glass text-xl"></i>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800">No matching clients found</p>
                                            <p class="text-xs text-slate-500 mt-1">Try adjusting your search or clearing the filters.</p>
                                            <div class="mt-4">
                                                <a href="{{ route('admin.client-records') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold rounded-lg transition shadow-2xs">
                                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                                    <span>Clear Filters</span>
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="max-w-md mx-auto text-slate-500">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                                <i class="fa-solid fa-user-group text-xl"></i>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800">No clients found</p>
                                            <p class="text-xs text-slate-500 mt-1">Clients will appear here once client records exist.</p>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($clients->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $clients->links() }}
                </div>
            @endif
        </section>
    </div>

    <!-- Client Detail Slide-Over Drawer Container -->
    <div id="clientDrawerContainer" class="fixed inset-0 z-50 overflow-hidden hidden" aria-labelledby="clientDrawerTitle" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div
            id="clientDrawerBackdrop"
            onclick="closeClientDrawer()"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px] transition-opacity duration-300 opacity-0 cursor-pointer"
            aria-hidden="true"
        ></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <!-- Slide-over panel -->
            <aside
                id="clientDrawerPanel"
                class="w-screen max-w-md md:max-w-lg bg-white shadow-2xl border-l border-slate-200 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col justify-between"
            >
                <div id="clientDrawerContent" class="h-full flex flex-col justify-between overflow-hidden">
                    <!-- Populated dynamically from template -->
                </div>
            </aside>
        </div>
    </div>

    <!-- Interactive Scripts: Filters & Detail Drawer -->
    <script>
        function toggleFilterPanel() {
            const panel = document.getElementById('clientFilterPanel');
            const text = document.getElementById('toggleFiltersText');
            const chevron = document.getElementById('filtersChevron');
            const btn = document.getElementById('toggleFiltersBtn');
            if (!panel) return;

            const isHidden = panel.style.display === 'none' || panel.classList.contains('hidden');
            if (isHidden) {
                panel.style.display = 'block';
                panel.classList.remove('hidden');
                if (text) text.textContent = 'Hide Filters';
                if (chevron) chevron.classList.add('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            } else {
                panel.style.display = 'none';
                panel.classList.add('hidden');
                if (text) text.textContent = 'Show Filters';
                if (chevron) chevron.classList.remove('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }

        function openClientDrawer(clientId) {
            const template = document.getElementById('client-drawer-template-' + clientId);
            const container = document.getElementById('clientDrawerContainer');
            const backdrop = document.getElementById('clientDrawerBackdrop');
            const panel = document.getElementById('clientDrawerPanel');
            const content = document.getElementById('clientDrawerContent');

            if (!template || !container || !panel || !content) return;

            content.innerHTML = template.innerHTML;
            container.classList.remove('hidden');

            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
            });

            document.body.style.overflow = 'hidden';
        }

        function closeClientDrawer() {
            const container = document.getElementById('clientDrawerContainer');
            const backdrop = document.getElementById('clientDrawerBackdrop');
            const panel = document.getElementById('clientDrawerPanel');

            if (!container || !panel) return;

            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            panel.classList.remove('translate-x-0');
            panel.classList.add('translate-x-full');

            setTimeout(() => {
                container.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeClientDrawer();
            }
        });
    </script>
</x-admin-layout>
