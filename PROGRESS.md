---
# 📊 System Progress, Logs & Implementation Roadmap

> ⚠️ **AUTOMATED DOCUMENTATION - AUTO-UPDATES WITH SYSTEM CHANGES**
> This document is automatically maintained whenever changes are made to the Raflora Enterprises system. Every implementation, bug fix, feature addition, and phase progression is reflected here in real-time. For current phase status and roadmap, reference this document as the single source of truth.

---

## 1. 🔍 CLIENT PORTAL AUDIT

### 📍 Client Routes & Views
- `/` -> `home.blade.php` (Public Landing)
- `/gallery` -> `gallery.blade.php` (Public Gallery)
- `/about` -> `about.blade.php` (Public About)
- Auth Routes: `/login`, `/register`, etc. handled by `AuthController`
- `/client/dashboard` -> `client.dashboard` (Client overview)
- `/client/bookings` -> `client.bookings` (Active Bookings)
- `/client/bookings/create` -> `client.booking-create` (Custom Booking Request Form)
- `/client/bookings/analysis/{booking}` -> `client.booking-analysis` (Quotation & AI Breakdown View)
- `/client/booking-history` -> `client.booking-history` (Past Bookings)
- `/client/account-settings` -> `client.account-settings` (Profile Management)

### ⚙️ Execution Flow (Step-by-Step)
1. **Booking Creation**: Client accesses `/client/bookings/create` and fills out the custom request form (`event_type`, date, venue, special requests). Crucially, they upload an `inspiration_image`.
2. **Gemini AI Parsing**: Upon form POST (`BookingController@store`), the server saves the image and immediately triggers `GeminiVisionService->analyzeImageFromPath()`. The AI returns a JSON array of suggested flowers and estimated Dangwa wholesale costs.
3. **Database Population**: The controller creates a new `Booking` record. It caches the AI JSON payload in `ai_analysis_results`. It dynamically injects the suggested flowers into the `inventory_items` table and links them via the `booking_items` pivot table with their quantities and prices.
4. **Total Pricing Engine (3x Markup)**: The controller sums the raw material costs (`raw_materials_sum`), applies a strict `3.0` multiplier, and calculates the `final_quoted_price` directly in the database.
5. **Payment Submission**: Client reviews the breakdown at `/client/bookings/analysis/{booking}`. They can submit GCash or Bank Transfer reference numbers via `/client/bookings/{booking}/payment-reference`, setting the booking status to `payment_pending`. Admin verification sets it to `downpayment_received`.

---

## 2. 🎛️ ALL 4 ADMIN INTERFACES AUDIT

### A. Event Booking Coordination
- **Controller**: `Admin\BookingController.php`
- **Views**: `admin.bookings.blade.php`, `admin.booking-show.blade.php`
- **Status**: **Fully Wired ✅**
- **Details**: Admins can list bookings, filter by status, and view deep details. Inside `booking-show`, admins can natively edit the BOM quantities, modify AI unit costs, remove items, adjust the 3x multiplier, send official quotations, decline bookings, and verify payments. Everything writes directly to the pivot tables and booking records.

### B. Gemini AI Image Analysis & Review Workspace
- **Controller**: Managed by closure in `routes/web.php`
- **Views**: `admin.ai-analysis.blade.php`
- **Status**: **UI Placeholder ⚠️**
- **Details**: While the AI itself successfully parses and prices data in the background (visible inside the specific Booking Coordination view), this dedicated "Analytics/Review" sub-page is currently displaying hardcoded HTML (e.g., static image paths and mock JSON data). It is not pulling from the `ai_analysis_results` table.

### C. Inventory Tracking & Shortage Alerts
- **Controller**: Managed by closure in `routes/web.php`
- **Views**: `admin.inventory.blade.php`
- **Status**: **UI Placeholder ⚠️**
- **Details**: The database contains `inventory_items` and `inventory_transactions`. The Gemini AI actively injects new items here. However, the dedicated inventory dashboard page contains static placeholders and is not displaying real-time stock counts or triggering shortage alerts based on upcoming event dates.

### D. Post-Event Asset Return & Damage Recovery
- **Controller**: Managed by closure in `routes/web.php`
- **Views**: `admin.return-tracking.blade.php`
- **Status**: **UI Placeholder ⚠️**
- **Details**: The database schema possesses `returns` and `return_items` tables to track vases and props post-event. However, the admin view for this is entirely static HTML with no active controller logic to log damaged items, missing hardware, or penalize deposits.

---

## 3. 🚧 SYSTEM GAPS & ROADMAP

### Identified Unhandled States & Missing Logic:
1. **Pre-Set Packages**: Currently, the system only accepts custom AI requests. There is no route or database structure for selecting predefined packages (e.g., "Standard Wedding Package A").
2. **Consultation Scheduling**: No interactive calendar, `meetings` model, or timezone logic exists for clients to book physical/virtual consultations.
3. **Presentation / Proposal Signing**: The "Quotation" is currently just a web view. There is no way for admins to upload external PDF design decks (`presentations`) and force the client to digitally "sign" or approve them before payment.
4. **Tentative Flagging**: Complex, long-term events do not have a `tentative` status with an automated expiry timer (price validity).

### 🚀 Immediate Next Steps (Implementation Plan)

#### Client-Side Focus:
- [x] **Step 1:** Modify `/client/bookings/create` and `/guest/booking` to include a toggle between "Custom AI Request" and "Select Pre-Set Package". (Fully implemented with dynamic routing `/booking/start`, active packages display, pre-selection via `?package_id=X`, and preset price handling).
- [ ] **Step 2:** Build a Consultation Scheduling module: Create `Meeting` model, controller, and interactive calendar view for `/client/schedule`.
- [ ] **Step 3:** Implement Presentation Approval loops allowing clients to view and accept uploaded PDFs.

#### Admin-Side Focus:
- [ ] **Step 4:** Wire the `admin.inventory` view to an `InventoryController`. Fetch real `inventory_items` and calculate shortages based on pending `booking_items` quantities.
- [ ] **Step 5:** Wire the `admin.return-tracking` view to handle post-event prop recovery, updating stock counts and logging damages.
- [ ] **Step 6:** Wire the `admin.ai-analysis` analytics dashboard to output real aggregated statistics from `ai_analysis_results` (e.g., average AI processing success rate, common flower requests).
