<x-admin-layout title="Reports & Analytics">
    <x-slot:actions>
        <a href="{{ route('admin.reports.export') }}" class="inline-flex items-center gap-2 h-9 px-4 bg-brand-700 hover:bg-brand-800 text-white font-semibold text-sm rounded-lg shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2" title="Download this report as a CSV file">
            <i class="fa-solid fa-file-arrow-down text-xs" aria-hidden="true"></i>Export Report (CSV)
        </a>
    </x-slot:actions>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <div class="mx-auto max-w-[1600px] space-y-6">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Management report</p>
                <h2 class="serif mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">Business performance</h2>
                <p class="mt-1 text-sm text-slate-500">A decision-ready view of bookings, revenue pipeline, demand, and stock risk.</p>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <i class="fa-regular fa-calendar"></i>
                <span>Reporting period: <strong class="text-slate-800">{{ $reportPeriod }}</strong></span>
            </div>
        </div>

        <section aria-label="Key performance indicators" class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 border-l-4 border-l-brand-600 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 sm:text-xs">New bookings</p>
                <p class="serif mt-2 break-words text-xl font-bold text-slate-900 sm:text-3xl">{{ $bookingsThisMonth }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $confirmedThisMonth }} confirmed or active this month</p>
            </div>
            <div class="rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 sm:text-xs">Verified revenue</p>
                <p class="serif mt-2 break-words text-xl font-bold text-slate-900 sm:text-3xl">₱{{ number_format($revenueEstimate, 2) }}</p>
                <p class="mt-2 text-xs text-slate-500">Verified payments collected this month</p>
            </div>
            <div class="rounded-xl border border-slate-200 border-l-4 border-l-sky-500 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 sm:text-xs">Average booking value</p>
                <p class="serif mt-2 break-words text-xl font-bold text-slate-900 sm:text-3xl">₱{{ number_format($averageBookingValue, 2) }}</p>
                <p class="mt-2 text-xs text-slate-500">Based on confirmed monthly bookings</p>
            </div>
            <div class="rounded-xl border border-slate-200 border-l-4 border-l-rose-500 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 sm:text-xs">Stock exceptions</p>
                <p class="serif mt-2 break-words text-xl font-bold text-slate-900 sm:text-3xl">{{ $stockAlerts }}</p>
                <p class="mt-2 text-xs text-slate-500">Items at or below reorder level</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div><h3 class="font-bold text-slate-900">Revenue and booking volume</h3><p class="mt-1 text-xs text-slate-500">Six-month trend of verified revenue and new bookings; cancelled bookings excluded.</p></div>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">₱{{ number_format($pipelineValue, 0) }} pipeline</span>
                </div>
                <div class="relative h-72"><canvas id="salesChart" aria-label="Revenue and booking trend"></canvas></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="mb-5"><h3 class="font-bold text-slate-900">Booking pipeline</h3><p class="mt-1 text-xs text-slate-500">Current distribution across all booking statuses.</p></div>
                <div class="relative h-72"><canvas id="statusChart" aria-label="Booking status distribution"></canvas></div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Material demand</h3><p class="mt-1 text-xs text-slate-500">Top confirmed materials by total quantity.</p></div><i class="fa-solid fa-boxes-stacked text-slate-300"></i></div>
                <div class="relative h-64"><canvas id="materialsChart" aria-label="Top requested materials"></canvas></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Inventory attention</h3><p class="mt-1 text-xs text-slate-500">Prioritised by distance below the reorder level.</p></div><i class="fa-solid fa-triangle-exclamation text-rose-400"></i></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm"><thead class="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400"><tr><th class="pb-3">Material</th><th class="pb-3">On hand</th><th class="pb-3">Minimum</th><th class="pb-3 text-right">Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">@forelse($lowStockItems as $item)<tr><td class="py-3 font-medium text-slate-700">{{ $item->name }}</td><td class="py-3 text-slate-600">{{ rtrim(rtrim(number_format((float) $item->current_stock, 2), '0'), '.') }} {{ $item->unit }}</td><td class="py-3 text-slate-600">{{ rtrim(rtrim(number_format((float) $item->min_stock, 2), '0'), '.') }}</td><td class="py-3 text-right"><span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">Reorder</span></td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-sm text-slate-500">No stock exceptions.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Recent activity</h3><p class="mt-1 text-xs text-slate-500">Latest audited changes across the system.</p></div><a href="{{ route('admin.settings') }}#audit-trail" class="text-xs font-bold text-brand-700 hover:text-brand-900">View account activity <span aria-hidden="true">→</span></a></div>
            <div class="overflow-x-auto"><table class="rf-table--stack w-full min-w-[680px] text-left"><thead class="border-y border-slate-100 bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-3 py-3">Date</th><th class="px-3 py-3">User</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">Details</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($activities as $activity)<tr class="hover:bg-slate-50"><td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500">{{ $activity['date'] }}</td><td data-label="User" class="px-3 py-3 text-sm font-medium text-slate-700">{{ $activity['user'] }}</td><td data-label="Action" class="px-3 py-3 text-sm text-slate-600">{{ $activity['action'] }}</td><td class="px-3 py-3 text-sm text-slate-600">{{ $activity['details'] }}</td></tr>@empty<tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No recent activity found.</td></tr>@endforelse</tbody></table></div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Build charts once web fonts are ready so Chart.js measures axis labels with the final font.
            (document.fonts ? document.fonts.ready : Promise.resolve()).then(renderReportCharts);
        });

        function renderReportCharts() {
            const labels = @json($salesLabels);
            const revenue = @json(array_values($salesData));
            const bookings = @json(array_values($bookingTrend));
            const chartFont = { family: 'Inter, ui-sans-serif, system-ui, sans-serif', size: 11 };
            new Chart(document.getElementById('salesChart'), { type: 'line', data: { labels, datasets: [
                { label: 'Revenue', data: revenue, borderColor: '#7c3aed', backgroundColor: 'rgba(124, 58, 237, .10)', fill: true, tension: .35, yAxisID: 'revenue' },
                { label: 'Bookings', data: bookings, borderColor: '#0f766e', backgroundColor: 'transparent', tension: .35, yAxisID: 'bookings' }
            ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { labels: { usePointStyle: true, font: chartFont } } }, scales: { revenue: { type: 'linear', axis: 'y', position: 'left', beginAtZero: true, ticks: { font: chartFont, callback: value => '₱' + Number(value).toLocaleString() }, grid: { color: '#f1f5f9' } }, bookings: { type: 'linear', axis: 'y', beginAtZero: true, position: 'right', ticks: { precision: 0, font: chartFont }, grid: { display: false } }, x: { ticks: { font: chartFont }, grid: { display: false } } } } });
            new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: @json($statusLabels), datasets: [{ data: @json($statusData), backgroundColor: ['#7c3aed', '#0f766e', '#f59e0b', '#0284c7', '#e11d48', '#94a3b8'], borderWidth: 3, borderColor: '#fff' }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14, font: chartFont } } } } });
            new Chart(document.getElementById('materialsChart'), { type: 'bar', data: { labels: @json($materialLabels), datasets: [{ data: @json($materialData), backgroundColor: '#0f766e', borderRadius: 5, barThickness: 18 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, layout: { padding: { left: 8 } }, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0, font: chartFont }, grid: { color: '#f1f5f9' } }, y: { ticks: { font: chartFont }, grid: { display: false } } } } });
        }
    </script>
</x-admin-layout>
