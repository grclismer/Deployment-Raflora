<x-app-layout title="Phase 4 Mock UI Hub">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-slate-900">Phase 4 UI Mock Hub</h1>
            <p class="mt-3 text-lg text-slate-600">Temporary access to inspectable UI states for Client, Admin, and Staff.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Client Scenarios -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-purple-700 mb-4 border-b border-purple-100 pb-2">Client Experience</h2>
                <ul class="space-y-3">
                    <li><a href="{{ route('mock.client', 'bookings_list') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">My Bookings List</a></li>
                    <li><a href="{{ route('mock.client', 'pending') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">1-2. Booking Submitted / Under Review</a></li>
                    <li><a href="{{ route('mock.client', 'quotation_sent') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">3. Quotation Ready</a></li>
                    <li><a href="{{ route('mock.client', 'change_requested') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">4. Change Requested</a></li>
                    <li><a href="{{ route('mock.client', 'approved') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">5. Awaiting Acceptance (Admin Approval)</a></li>
                    <li><a href="{{ route('mock.client', 'admin_approved') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">6. Payment Required</a></li>
                    <li><a href="{{ route('mock.client', 'payment_submitted') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">7. Payment Submitted</a></li>
                    <li><a href="{{ route('mock.client', 'confirmed') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">8. Confirmed / Preparation</a></li>
                    <li><a href="{{ route('mock.client', 'damaged_charge') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">10. Post-Event Charge</a></li>
                    <li><a href="{{ route('mock.client', 'completed') }}" class="text-slate-700 hover:text-purple-600 font-medium transition">11. Completed</a></li>
                </ul>
            </div>

            <!-- Admin Scenarios -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-emerald-700 mb-4 border-b border-emerald-100 pb-2">Admin Experience</h2>
                <ul class="space-y-3">
                    <li><a href="{{ route('mock.admin', 'bookings_list') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">Bookings Queue</a></li>
                    <li><a href="{{ route('mock.admin', 'pending') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">1-2. New Booking / Material Review</a></li>
                    <li><a href="{{ route('mock.admin', 'quotation_sent') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">3. Quotation Preparation</a></li>
                    <li><a href="{{ route('mock.admin', 'change_requested') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">4. Negotiation</a></li>
                    <li><a href="{{ route('mock.admin', 'approved') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">5. Awaiting Acceptance</a></li>
                    <li><a href="{{ route('mock.admin', 'payment_submitted') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">6. Payment Verification</a></li>
                    <li><a href="{{ route('mock.admin', 'confirmed') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">7. Staff Assignment</a></li>
                    <li><a href="{{ route('mock.admin', 'event_in_progress') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">8. Event Monitoring</a></li>
                    <li><a href="{{ route('mock.admin', 'return_review') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">9. Return Review</a></li>
                    <li><a href="{{ route('mock.admin', 'damaged_charge') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">10. Damaged / Charge Decision</a></li>
                    <li><a href="{{ route('mock.admin', 'completed') }}" class="text-slate-700 hover:text-emerald-600 font-medium transition">11. Completed</a></li>
                </ul>
            </div>

            <!-- Staff Scenarios -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-sky-700 mb-4 border-b border-sky-100 pb-2">Staff Experience</h2>
                <ul class="space-y-3">
                    <li><a href="{{ route('mock.staff', 'assigned_list') }}" class="text-slate-700 hover:text-sky-600 font-medium transition">Assigned Events List</a></li>
                    <li><a href="{{ route('mock.staff', 'confirmed') }}" class="text-slate-700 hover:text-sky-600 font-medium transition">1-2. Assigned / Preparation</a></li>
                    <li><a href="{{ route('mock.staff', 'event_in_progress') }}" class="text-slate-700 hover:text-sky-600 font-medium transition">3. Event Execution</a></li>
                    <li><a href="{{ route('mock.staff', 'pending_return') }}" class="text-slate-700 hover:text-sky-600 font-medium transition">4-6. Return & Condition Assessment</a></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-12 rounded-xl bg-amber-50 border border-amber-200 p-6 text-amber-900">
            <h3 class="font-bold mb-2">Phase 4 Backend Dependency Warning</h3>
            <p class="text-sm">
                Actions taken on these mock pages will not persist to the database. They are for UI structural review only.
                <br>In Phase 5, these flows will be connected to the real Laravel routing and database records.
            </p>
        </div>
    </div>
</x-app-layout>
