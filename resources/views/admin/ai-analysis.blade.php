<x-admin-layout title="AI Material Plan">
    <div class="mb-6">
        <h1 class="page-title text-3xl">Recent AI Analyses</h1>
        <p class="section-subtitle mt-2 max-w-2xl">Review automated floral material suggestions generated for client bookings. These are decision-support inputs and still require operational review.</p>
    </div>

    @if($aiBookings->isEmpty())
        <div class="rf-panel p-12 text-center">
            <div class="w-16 h-16 bg-brand-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-brain text-brand-300 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-1">No AI Analyses Yet</h3>
            <p class="text-gray-500 text-sm max-w-sm mx-auto">When clients upload inspiration photos during booking, our AI will automatically suggest floral materials here.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($aiBookings as $booking)
                @php
                    $analysis = is_string($booking->ai_analysis_data) ? json_decode($booking->ai_analysis_data) : (object)$booking->ai_analysis_data;
                    $suggestedItems = $analysis->suggested_materials ?? [];
                @endphp
                
                <article class="rf-panel overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                        <div>
                            <span class="text-xs uppercase tracking-wider font-bold text-brand-700">Booking #{{ $booking->id }}</span>
                            <h3 class="text-lg font-bold text-gray-800">{{ $booking->event_type }} - {{ $booking->client?->full_name ?? 'Guest' }}</h3>
                            <p class="text-sm text-gray-500">{{ $booking->updated_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('admin.bookings.show', $booking->id) }}" class="rf-btn rf-btn-outline text-sm">
                            View Booking &rarr;
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2 lg:grid-cols-3 sm:p-6">
                        <!-- Image Reference -->
                        <div>
                            <p class="text-sm font-semibold text-gray-800 mb-2">Reference Image</p>
                            @if($booking->event_reference_image)
                                <div class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                                    <img src="{{ Storage::url($booking->event_reference_image) }}" alt="Reference Image" class="w-full h-48 object-cover">
                                </div>
                            @else
                                <div class="rounded-xl border border-gray-200 bg-gray-50 w-full h-48 flex items-center justify-center text-gray-400">
                                    <span class="text-sm">No Image</span>
                                </div>
                            @endif
                        </div>
                        
                        <!-- AI Suggested Materials -->
                        <div class="lg:col-span-2">
                            <p class="mb-2 text-sm font-semibold text-gray-800">AI Suggested Materials <span class="rf-badge rf-badge--warning ml-2 align-middle">Review required</span></p>
                            <div class="bg-brand-50 rounded-xl p-4 h-48 overflow-y-auto border border-brand-100">
                                @if(empty($suggestedItems))
                                    <p class="text-sm text-brand-700 italic">No specific materials identified by AI.</p>
                                @else
                                    <ul class="space-y-2">
                                        @foreach($suggestedItems as $item)
                                            <li class="text-sm text-gray-700 flex justify-between items-center bg-white px-3 py-2 rounded shadow-sm">
                                                <span class="font-medium text-gray-800">{{ $item->item_name ?? 'Unknown Item' }}</span>
                                                <span class="text-xs text-gray-500">Qty: {{ $item->estimated_quantity ?? 1 }} {{ $item->unit_type ?? 'pcs' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-admin-layout>
