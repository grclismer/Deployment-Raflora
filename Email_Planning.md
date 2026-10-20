# Enhanced Guest Booking & Email Notification Workflow

This plan outlines the steps to implement a secure, token-based guest view for bookings, and comprehensive email notifications for all admin updates, along with an optional account conversion flow.

## Open Questions
- Do you want to use the existing Laravel `Mail` facade with custom Blade views for sending the emails, or do you prefer the existing `PhpMailerService` (which is used in the AuthController)? *Assumption: We will use standard Laravel Mailable classes rendering Blade views, which can be sent using either standard Mail or PhpMailerService.*
- For "Convert Account", is it sufficient to link *all* guest bookings matching the registered email, or strictly just the booking that was accessed via the `guest_token`? *Assumption: We will link the booking matching the `guest_token`, and optionally also any other guest bookings matching the same email.*

## Proposed Changes

---

### Database & Models

#### [MODIFY] `app/Models/Booking.php`
- Ensure `guest_access_token` is generated automatically when a guest booking is created (if not already handled reliably). We'll tap into the model's `creating` event to set `guest_access_token = Str::uuid()` if `client_id` is null.
- Or we can just explicitly generate it inside `GuestBookingController::store()`.

---

### Guest Token-Based Persistent View

#### [MODIFY] `app/Http/Controllers/GuestBookingController.php`
- Update `store` method to explicitly set `guest_access_token = Str::random(32)` (or use UUID) instead of the `hash()` trick.
- The route `/guest/bookings/{token}` is already mapped to `show`. We need to implement `show(string $token)` in `GuestBookingController`.
- `show` method will:
  - Find booking by `guest_access_token`. If not found, abort 404.
  - Return the guest view `guest.booking-analysis` (or similar status view) without requiring `Auth`.

#### [MODIFY] `routes/web.php`
- Verify the route `/guest/bookings/{token}` points to `GuestBookingController@show`. (It does, but we'll ensure it is placed correctly).

---

### Email Notifications on Admin Updates

#### [NEW] `app/Mail/GuestBookingNotificationMail.php`
- A standard Laravel Mailable class that accepts a `$booking` object and an `$updateContext` (string like 'confirmation', 'quote_updated', 'approved', 'payment_verified').
- Will pass the booking and dynamic message to the view.

#### [NEW] `resources/views/emails/guest-booking-notification.blade.php`
- Implements the UI provided in the screenshot:
  - Purple header block with logo.
  - Dynamic greeting (e.g., "Hello Juan Dela Cruz").
  - Dynamic status block ("Quotation Approved & Sent").
  - Quotation Overview table showing itemized breakdown and Total Estimate.
  - "Note from Florist" block.
  - "View Live Booking & Pay Deposit ->" button pointing to `route('guest.bookings.show', $booking->guest_access_token)`.
  - "Create Account to Save Booking" button pointing to `/register?email=...&name=...&guest_token=...`.

#### [MODIFY] `app/Models/Booking.php` (Boot method)
- In the `booted()` method (where `AuditLog` is already created for status/quote changes), add logic:
  - If `client_id` is null (guest) and status or quote changes, queue the `GuestBookingNotificationMail` to `guest_email`.
  - Handle initial creation as well (maybe in the controller or model `created` event) for "Initial Guest Booking Confirmation".

---

### Optional Account Conversion

#### [MODIFY] `app/Http/Controllers/AuthController.php`
- Update `showRegister` to accept `Request $request` and pass `email`, `first_name`, `last_name`, and `guest_token` query parameters to the view.
- Update `register` method to check for `guest_token` in the request. If present, and a user is created successfully, find the booking with that `guest_token` and set its `client_id` to the new user's ID.

#### [MODIFY] `resources/views/auth/register.blade.php`
- Add a hidden input for `guest_token`.
- Pre-fill `first_name`, `last_name`, and `email` if passed from query parameters.

## Verification Plan

### Automated Tests
- N/A - Testing will be done manually or using existing test suites.

### Manual Verification
1. Submit a guest booking and verify the `guest_access_token` is generated and saved.
2. Verify an initial confirmation email is generated (check Laravel logs if mail is set to `log`).
3. Access the token link (`/guest/bookings/{token}`) in an incognito window and verify the booking status is visible without logging in.
4. Update the booking status as an Admin (e.g., approve quote). Verify the "status updated" email is generated with the breakdown and CTA links.
5. Click "Create Account to Save Booking" from the email, verify the registration page is pre-filled. Complete registration and verify the booking is now assigned to the newly created user account.
