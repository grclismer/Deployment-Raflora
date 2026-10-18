{{--
    Delivery orders (Booking::isDelivery()) are sold, not rented: there is no Material Return or
    return audit. The booking closes once the client has the items (App\Services\DeliveryOrderService).
    Admin sees the flag; the client also gets the "Item Received" button while the order is out for delivery.
--}}
@props(['booking', 'audience' => 'admin'])

@php
    $canMarkReceived = app(\App\Services\DeliveryOrderService::class)->canMarkReceived($booking);
    $receivedAt = $booking->delivery_received_at;
    $receivedBy = $booking->delivery_received_via === \App\Services\DeliveryOrderService::VIA_CLIENT
        ? ($audience === 'client' ? 'you' : 'the client')
        : 'Raflora';
@endphp

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-sky-200 bg-sky-50/70 p-4 sm:p-5']) }} aria-labelledby="delivery-order-notice-heading" data-delivery-order-notice>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white" aria-hidden="true"><i class="fa-solid fa-truck-fast text-sm"></i></span>
            <div class="min-w-0">
                <h2 id="delivery-order-notice-heading" class="text-sm font-semibold text-sky-950">
                    @if($audience === 'client')
                        Delivery order
                    @else
                        Delivery order · No return audit
                    @endif
                </h2>
                <p class="mt-0.5 text-sm text-sky-900/80">
                    @if($receivedAt)
                        Received on {{ $receivedAt->format('M j, Y g:i A') }}, confirmed by {{ $receivedBy }}.
                    @elseif($audience === 'client')
                        Your items are yours to keep, so nothing needs to be returned. Once they arrive, let us know with the button{{ $canMarkReceived ? '' : ' that appears when your order is out for delivery' }}.
                    @else
                        Items are sold, not rented: nothing comes back, so there is no Material Return or return audit. The booking completes once the client receives the items, either from the client's portal or with "Mark Item Received" when the client confirms by message or call.
                    @endif
                </p>
            </div>
        </div>

        @if($audience === 'client' && $canMarkReceived)
            <button type="button" onclick="document.getElementById('deliveryReceivedModal').classList.remove('hidden')" class="rf-btn rf-btn-primary w-full shrink-0 sm:w-auto">
                <i class="fa-solid fa-box-open" aria-hidden="true"></i>Item Received
            </button>
        @endif
    </div>
</section>

@if($audience === 'client' && $canMarkReceived)
    <div id="deliveryReceivedModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="deliveryReceivedModalTitle">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-100 bg-slate-50 p-5">
                <h3 id="deliveryReceivedModalTitle" class="text-lg font-bold text-slate-900">Did you receive your order?</h3>
            </div>
            <form method="POST" action="{{ route('bookings.delivery-received', $booking) }}" class="space-y-5 p-5">
                @csrf
                <p class="text-sm text-slate-600">Confirm only once the items have arrived. Raflora is notified right away and your booking moves to completion.</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" onclick="document.getElementById('deliveryReceivedModal').classList.add('hidden')" class="rf-btn rf-btn-secondary">Not yet</button>
                    <button type="submit" class="rf-btn rf-btn-primary">Yes, I received it</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // The layout's content wrapper (relative z-10) would keep the pop-up under the sticky header.
        (() => { const modal = document.getElementById('deliveryReceivedModal'); if (modal) document.body.appendChild(modal); })();
    </script>
@endif
