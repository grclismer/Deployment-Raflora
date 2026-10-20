# Raflora System - Operations & Complete Progress Report

## 1. Executive Summary & Architecture Overview

Raflora Enterprises is a booking, quotation, inventory, and return tracking system for floral and event services. The platform supports both guest and registered client booking workflows, AI-powered material analysis, admin review cycles, proposal versioning, client notifications, inventory allocation, and post-event return reconciliation.

Tech stack details:
- Laravel PHP framework for backend routing, controllers, models, middleware, and request validation.
- Blade templates for server-rendered frontend views and admin interfaces.
- MySQL-compatible database through Laravel migrations and Eloquent models.
- AI integration through a custom `GeminiVisionService` for image validation and BOM suggestions.
- JavaScript assets are served via Vite / Blade assets, with UI interactivity in admin booking screens and client forms.
- Email support includes both Laravel mail and a custom `PhpMailerService` fallback for password reset delivery.

High-level lifecycle:
1. Client Request: A client or guest submits a booking form with event details, special requests, and optional inspiration image.
2. AI Analysis: The system validates the image, runs Gemini vision analysis, and generates a Bill of Materials (BOM) of suggested floral materials and costs.
3. Admin Review / Quote: Admins review or edit the AI-suggested booking items, can override pricing, upload proposal versions, and send quotation updates.
4. Client Decision: The client accepts the quotation, submits payment reference details, and receives notification updates.
5. Return Tracking: After event completion, returned items are logged, damage/loss is assessed, inventory is reconciled, and return charges are applied.

## 2. System Status Breakdown

### DONE (Completed Modules & Features)

- **AUTH & USER MANAGEMENT:**
  - Registered user login and registration are implemented in `App\Http\Controllers\AuthController.php`.
  - Session lifecycle is handled through Laravel authentication, with regeneration on login and logout.
  - Role-based access is enforced in `routes/web.php` via `auth` middleware for clients and `['auth', 'admin']` middleware for admin area routes.
  - Admins/staff are separated from clients through `User::role` and route redirect logic in `AuthController::login` and `GoogleController::handleGoogleCallback`.
  - Password reset flows are supported with Laravel broker integration and a custom `PhpMailerService` for HTML reset emails if `USE_PHPMAILER` is enabled.
  - Google OAuth is integrated through `App\Http\Controllers\Auth\GoogleController.php` with redirect and callback routes.

- **ADMIN INTERFACES (All 4 Core Areas):**
  1. Overview & Analytics (Dashboard, Reports)
     - Admin dashboard route `admin/dashboard` returns aggregated totals for bookings, pending work, recent bookings, and active alerts.
     - `App\Http\Controllers\Admin\ReportController.php` supports KPI pages including monthly sales trend, status distribution, revenue estimates, stock alerts, AI analysis summary, and audit logs.
     - Audit trail data is surfaced via `admin/audit-logs`.
  2. Bookings & Operations (All Bookings, Booking Review, Return Tracking)
     - `App\Http\Controllers\Admin\BookingController.php` manages booking listing, review, edit, update, payment verification, and booking shortage resolution.
     - Admin booking routes include `admin/bookings`, `admin/bookings/{booking}`, `admin/bookings/{booking}/edit`, and update actions.
     - Notifications and shortage reports are accessible at `admin/notifications`.
     - `App\Http\Controllers\Admin\ReturnTrackingController.php` provides return cycle pages for completed bookings and individual return management.
  3. Catalog & Resources (Inventory Management, Packages)
     - Inventory management is implemented in `App\Http\Controllers\Admin\InventoryController.php` with index, store, update, and destroy actions.
     - Package management is implemented in `App\Http\Controllers\Admin\PackageController.php` as a resource controller for package creation, updating, and deletion.
     - Packages can sync physical BOM inventory items and are surfaced in guest/client booking flows.
  4. Client & System Management (Client Records, Settings, Audit Logs)
     - Admin client records are available via `admin/client-records`.
     - System settings page exists at `admin/settings`.
     - User listing is provided at `admin/users`.
     - Audit logs capture booking status changes, quotes, inventory updates, and other administrative actions.

- **AI BOM & CATALOG PROMOTIONS:**
  - AI analysis is performed by `App\Services\GeminiVisionService.php`.
  - Image uploads are first validated with `validateImage()` to ensure floral/event relevance, and failed validation is fail-open only if the service cannot determine relevance.
  - `analyzeImageFromPath()` returns structured JSON with `suggested_materials` including `item_name`, `estimated_quantity`, `unit_type`, and `estimated_unit_cost_php`.
  - Booking controllers persist analysis in `AiAnalysisResult` and create booking-level item suggestions with `persistAiSuggestedMaterials()`.
  - When the AI item name matches an existing `InventoryItem`, it is linked automatically to a master catalog item.
  - Admins can manually link AI items to inventory (`admin.bookings.ai.link`) or promote them into master inventory (`admin.bookings.ai.promote`).
  - Promotion flow supports fallback auto-creation of inventory master records and booking item pivot sync.
  - Inventory and stock warning logic are present in admin notifications and inventory dashboards, with low stock and shortage alerts based on `current_stock`, `reserved_stock`, and `min_stock`.

- **PROPOSALS & CLIENT FEEDBACK:**
  - Proposal upload and versioning is supported by `Admin\BookingController::storeProposal()`.
  - Proposal versions are stored in `presentations` and labeled with `v1`, `v2`, etc., based on existing count.
  - Uploaded proposals are tied to bookings and retain file path, file name, status, sent_at, and sent_by metadata.
  - Clients submit proposal feedback via `BookingController::submitProposalFeedback()`.
  - Feedback supports `approval_status`, `feedback_text`, and updates presentation status to `reviewed`.

- **CLIENT NOTIFICATIONS:**
  - Client notifications are stored in `client_notifications` and managed through `App\Http\Controllers\ClientNotificationController.php`.
  - The controller delivers clean text summaries, parses structured update messages, and avoids raw JSON rendering.
  - Users can mark notifications as read individually or mark all as read.
  - Notification payloads are generated in `Admin\BookingController::createClientNotificationForBooking()` using safe message text.

- **UI & LAYOUT STABILITY:**
  - The admin booking review screen in `resources/views/admin/booking-show.blade.php` is organized into responsive sections for event details, proposal uploads, item/pricing breakdown, and admin actions.
  - The booking review page uses clean grid containers, stable table layouts, and action forms separated to prevent validation collisions.
  - Sidebar alignment and responsive card structure are present in the Blade markup, supporting desktop and mobile views.

### IN PROGRESS (Active Development)

- **BOOKING CONTROLLER & QUOTE OVERRIDE PIPELINE (`BookingController.php` / `Admin\BookingController.php`):**
  - The admin booking update pipeline handles booking state transitions across statuses such as `pending`, `quotation_sent`, `payment_pending`, `downpayment_received`, `event_in_progress`, `completed`, and `declined`.
  - `Admin\BookingController::update()` includes logic for item adjustments, AI suggestion linking/promoting, raw material recalculation, and admin note generation.
  - `final_quoted_price` persistence is explicitly handled in update logic through `array_key_exists('final_quoted_price', $data)` and direct request checks.
  - *Active Issue / Priority Fix:* Guaranteeing that manual price overrides (`final_quoted_price`) and item adjustments consistently persist to the `bookings` table upon form submission without reverting after page redirects.

- **RETURN TRACKING OPERATIONS:**
  - Post-event item collection and return logging are managed by `Admin\ReturnTrackingController.php`.
  - The system auto-initializes return records for completed bookings without existing returns.
  - Admins can record returned quantity, item condition (`good`, `damaged`, `lost`), damage charges, and notes.
  - Inventory reconciliation is performed by adjusting inventory totals and logging `InventoryTransaction` records for return inflows or adjustments.
  - The return workflow is active and being stabilized for complete damage/loss reconciliation.

## 3. Comprehensive Data & Operational Flows

1. **Client Booking Request:**
   - A user reaches `/booking/start` and is redirected to either `client/bookings/create` or `/guest/booking` depending on authentication.
   - The booking form is submitted to `BookingController::store()` for authenticated clients or `GuestBookingController::store()` for guests.
   - Image uploads are stored in `storage/app/public/bookings/inspiration-images` and validated through `GeminiVisionService::validateImage()`.
   - The booking row is created in `bookings` with `status = pending`, `price_valid_until` = 7 days, `suggested_procurement_date` = event date minus 7 days, and initial `final_quoted_price` based on package selection or AI analysis.
   - The system runs Gemini image analysis, persists raw response JSON into `ai_analysis_results`, and stores suggested items to `booking_items` when matched with inventory masters.

2. **Admin Review & Quotation:**
   - Admins review bookings through `admin/bookings/{booking}` and can open edits in `admin/bookings/{booking}/edit`.
   - In the booking review form, admins adjust item quantities, unit prices, and remove unavailable or unwanted recommendations.
   - The controller recalculates `raw_materials_sum`, applies a multiplier (default 3x), and updates `final_quoted_price`/`total_quoted`.
   - Proposal files are uploaded and versioned in `presentations`.
   - The admin can send quotation updates using `send_quotation` action, which sets `status = quotation_sent`, refreshes `price_valid_until`, and creates a client notification.

3. **Inventory Allocation & Promotion:**
   - AI-suggested items are linked to master `InventoryItem` records when names match normalized existing item names.
   - Manual linking is supported by `linkAiItem()`, allowing admins to map AI suggestions to existing inventory items.
   - Promotion to master inventory is supported by `promoteAiItem()`, which creates a new `InventoryItem` and attaches it to the booking item pivot.
   - The inventory system tracks reserved stock and net availability, with shortage alerts generated in the admin notifications dashboard.
   - Confirmed booking statuses reserve stock by decrementing `current_stock` and logging `InventoryTransaction` entries.

4. **Post-Event Return Tracking:**
   - Completed bookings that have physical booking items are automatically seeded with `AssetReturn` and `ReturnItem` records.
   - Admins manage returns via `admin/return-tracking` and individual return detail pages.
   - Returned quantities, conditions, and damage charges are recorded.
   - Inventory quantities are adjusted by the returned amount, and corresponding `InventoryTransaction` records are created to maintain traceability.
   - Return status is updated to `Partially Returned` or `Completed` based on completion of the return items.

## 4. Pending Tasks & Roadmap

- Resolve `final_quoted_price` persistence in `BookingController@update` / `Admin\BookingController@update` so manual overrides are always preserved after item adjustments and redirects.
- Add end-to-end automated tests for booking quote updates and inventory promotion fallbacks, including:
  - admin override + save flow,
  - AI item promotion to inventory,
  - shortage resolution,
  - return tracking status reconciliation.
