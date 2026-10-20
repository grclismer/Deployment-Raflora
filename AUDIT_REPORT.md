# READ-ONLY Audit Report: AI Image Analysis & Booking System

## 1. Controller & Service Flow
- **`app/Services/GeminiVisionService.php`**
  - **Function**: `analyzeImageFromPath(string $filePath, ?string $specialRequests = null, ?string $eventType = null, ?string $eventTime = null, ?string $venue = null)`
  - **Role**: Validates images and queries the Gemini AI to extract `suggested_materials` with raw wholesale costs.
- **`app/Http/Controllers/GuestBookingController.php` & `app/Http/Controllers/BookingController.php`**
  - **Image Handling**: Uploaded inspiration images are stored permanently in `bookings/inspiration-images/` (public disk) or temporarily in `bookings/temp-analysis/` during AJAX preview flows.
  - **Context Passed**: `event_type`, `event_time`, `venue` (and `venue_address` fallback), and `special_requests`.
  - **Data Parsing & Storage**: The JSON analysis is parsed and saved into the `ai_analysis_results` table. The `suggested_materials` are also looped through to create `BookingItem` entries. The `Booking` model gets the `ai_analysis_data` json payload, `raw_materials_sum`, a hardcoded `multiplier` of `3.0`, and the `final_quoted_price` (Total * 3).

## 2. Database & Schemas
- **`bookings` Table**: Stores the overall quotation (`raw_materials_sum`, `multiplier`, `final_quoted_price`, `total_quoted`, `ai_analysis_data`).
- **`ai_analysis_results` Table**: Stores the literal JSON response (`raw_gemini_response` and `suggested_materials`) per booking.
- **`booking_items` Table**: Items are linked to the booking here.
- **Custom / Unmatched Items**: Represented by a boolean `is_ai_suggested = true` and `inventory_item_id = null`. 

## 3. Pricing & Display Logic (Frontend & Admin)
- **Guest / Client Portal Views (`resources/views/client/booking-analysis.blade.php` (Lines 88-96) & `resources/views/guest/booking-analysis.blade.php` (Lines 77-87))**:
  - The itemized unit costs and subtotals are **currently exposed to the user** (e.g., `{{ $material['quantity'] }} @ {{ $material['cost'] }} / each`).
  - **Discrepancy**: The frontend displays the RAW WHOLESALE prices per item (because the AI prompt explicitly requests raw prices without the 3x markup), but the Grand Total is computed with the 3x markup. This results in a mathematical mismatch on the frontend where the item subtotals do not add up to the Grand Total, which may confuse clients.
- **Admin Portal Views (`resources/views/admin/booking-show.blade.php` (Lines 223-239))**:
  - Items are fully editable (Quantity, Unit Cost).
  - Unmatched items have a clear visual indicator using the amber badge: `<span ...>Unmatched / AI Suggestion</span>`.
  - Admins are provided inline forms to "Link to Existing Inventory" or "Promote to Catalog" for unmatched items.

## 4. Missing Form Inputs & Constraints
- The controllers and `GeminiVisionService` take `venue`, `event_type`, `event_time`, and `special_requests`.
- **Missing Inputs**: `table_count`, `guest_count`, or `venue_scale`. Without these parameters, the AI struggles to accurately estimate the quantity of centerpieces or scale of backdrops required.

## 5. Recommendations for Refactoring
1. **Frontend Pricing Visibility**: Either hide the individual item unit prices and subtotals entirely on the guest/client facing views (displaying them as simply "Included" with quantities), or mathematically apply the `multiplier` (3.0) to the item unit costs before rendering them in the view.
2. **Schema-Safe Prompt Updates**:
   - Update `GeminiVisionService::analyzeImageFromPath()` to accept a new `$scaleContext` (combining table/guest count).
   - Inject these into the prompt's `Context:` block without altering the `suggested_materials` JSON output schema, ensuring existing database migrations and controllers remain unbroken.
3. **Add Missing Inputs**: Add `table_count` or `estimated_pax` to the booking forms to improve the AI's quantity estimation.
