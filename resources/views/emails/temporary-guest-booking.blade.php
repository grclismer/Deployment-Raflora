<x-mail::message>
# Action Required: Claim Your Booking Request

Hi {{ $tempBooking->guest_name }},

Your booking request has been received. Raflora begins reviewing it as soon as you claim it with a registered account.

**Reference Number:** REF-{{ strtoupper(substr($rawToken, 0, 8)) }}  
**Event Type:** {{ ucfirst($tempBooking->event_type) }}  
**Event Date:** {{ \Carbon\Carbon::parse($tempBooking->event_date)->format('M d, Y') }}  
**Venue:** {{ $tempBooking->venue }}

---

### Status & Next Steps
Raflora will review your event details and, where applicable, analyze your inspiration image before preparing your quotation.

**At this stage, no payment is required.**

You can securely track the progress of your booking request at any time using your personal tracking link below:

<x-mail::button :url="route('guest.bookings.show', ['token' => $rawToken])">
Track My Booking
</x-mail::button>

---

### How to Claim Your Request
**Please note:** This request is currently temporary and will expire in **24 hours** if not claimed.

To formally review and accept your final quotation once prepared, you must claim it by creating a verified account:

1. Register for an account using **this exact email address** ({{ $tempBooking->guest_email }}).
2. Verify your email address.
3. Once verified, you will be directed to the claim page where you can permanently link this request to your account.

<x-mail::button :url="route('register', ['email' => $tempBooking->guest_email, 'name' => $tempBooking->guest_name, 'guest_token' => $rawToken])">
Register & Claim Request
</x-mail::button>

If you already have an account, you can log in and claim your request here:  
[{{ route('client.claim-guest-booking.show', ['token' => $rawToken]) }}]({{ route('client.claim-guest-booking.show', ['token' => $rawToken]) }})

If you do not claim this request within 24 hours, it will be automatically deleted.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
