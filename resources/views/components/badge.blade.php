@props([
    'variant' => null,
    'status' => null,
    'dot' => true,
    'icon' => null,
    'label' => null,
])

@php
    $resolvedVariant = $variant;

    if (!$resolvedVariant && $status) {
        $norm = strtolower(trim($status));
        $resolvedVariant = match (true) {
            in_array($norm, [
                'pending', 'inquiry', 'inquiry_received', 'ai_analysis_pending',
                'draft', 'unassigned', 'info'
            ], true) => 'neutral',

            in_array($norm, [
                'quotation_sent', 'quotation_issued', 'payment_pending',
                'cancellation_requested', 'change_requested', 'pending_resolution',
                'review', 'price_reconfirmation_due'
            ], true) => 'warning',

            in_array($norm, [
                'confirmed', 'ready', 'downpayment_received', 'fully_paid',
                'completed', 'event_completed', 'approved', 'reconciled'
            ], true) => 'success',

            in_array($norm, [
                'preparation', 'in_preparation', 'event_in_progress',
                'in_progress', 'dispatched', 'returned', 'pending_return',
                'active', 'payment_submitted'
            ], true) => 'primary',

            in_array($norm, [
                'cancelled', 'declined', 'rejected', 'overdue',
                'damage_reported', 'shortage', 'danger'
            ], true) => 'danger',

            default => 'neutral',
        };
    }

    $resolvedVariant = $resolvedVariant ?? 'neutral';

    $validVariants = ['neutral', 'primary', 'success', 'warning', 'danger', 'info'];
    if (!in_array($resolvedVariant, $validVariants, true)) {
        $resolvedVariant = 'neutral';
    }
@endphp

<span {{ $attributes->merge(['class' => "rf-badge rf-badge--{$resolvedVariant}"]) }}>
    @if($icon)
        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    @elseif($dot)
        <span class="rf-badge__dot" aria-hidden="true"></span>
    @endif
    <span>{{ $slot->isNotEmpty() ? $slot : ($label ?? $status ?? '') }}</span>
</span>
