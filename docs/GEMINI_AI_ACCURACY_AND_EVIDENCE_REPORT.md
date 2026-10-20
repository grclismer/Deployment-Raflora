# Gemini AI Accuracy and Evidence Validation — Phase Report

Scope: `README_Gemini_AI_Accuracy_and_Evidence_Validation.md`.

Evidence labels:
- **CONFIRMED**: verified by running code or tests.
- **OBSERVED**: seen in the code but not executed.
- **NOT VERIFIED**: not checked.

## Status

**In Progress.**
- **Done:** the software safeguards (workstreams 3 and 5, the business and safety rules, and parts of workstreams 1 and 2) are implemented and tested.
- **Not started:** the accuracy validation against human-verified references, and the ISO/IEC 25010 questionnaire. Both need the shop owner, staff and the approved respondents.

## 1. Implementation inspected

| Area | Files |
|---|---|
| Gemini calls, prompt, parsing, normalisation | `app/Services/GeminiVisionService.php`: `validateImage`, `analyzeImageFromPath`, `normalizeAiAnalysis`, `buildPricingSummary`, `validateAnalysisPayload`, `buildAnalysisDiagnostic` |
| Client upload and booking | `app/Http/Controllers/BookingController.php`: `analyzeTempImage`, `store`, `persistAiSuggestedMaterials` |
| Guest upload and request | `app/Http/Controllers/GuestBookingController.php`: `analyzeTempImage`, `store` |
| Guest request claim | `app/Http/Controllers/Client/ClaimGuestBookingController.php`: `claim` |
| Quotation boundary | `app/Services/QuotationIssuanceService.php`, `app/Services/QuotationPricingService.php` |
| Inventory demand | `app/Http/Controllers/Admin/InventoryController.php`: `index` |
| Interface | `resources/views/guest/booking-create.blade.php` (AI review step), `guest/booking-analysis`, `client/booking-create`, `admin/booking-show` |

**Workflow as implemented (OBSERVED):**
1. The image is uploaded to `analyze-temp-image`.
2. Gemini validates the image (call 1).
3. Gemini analyses the image (call 2). If the same image was analysed before, a cached result or a completed booking's template is used instead.
4. The result is normalised, validated, and stored in the session under an analysis token.
5. On submit, a client booking stores the rows as unconfirmed `booking_items` with `is_ai_suggested` set. A guest request stores the analysis, and the rows become booking items when the request is claimed.
6. Admin prices and confirms the items. A quotation is issued only from confirmed items.

## 2. Baseline (before changes)

`tests/Feature/GeminiEvidenceValidationTest.php` was written first and run against the unchanged code: **9 of 10 tests failed (CONFIRMED)**. The tenth test, on payload validation, already passed.

| # | Finding | Class | Evidence |
|---|---|---|---|
| B1 | A missing AI price was replaced from a keyword table in the code (for example, any "rose" became ₱180) and shown as if it were Gemini's estimate. | UNSAFE | `Failed asserting that 180.0 is null` |
| B2 | A missing or invalid quantity became 1, and fractional quantities were rounded up to 1. | UNSAFE | `Failed asserting that 1.0 is null` |
| B3 | Client bookings stored Gemini's price as `quoted_unit_price`, the price basis used for issuing quotations, and set the booking total to AI total × 3. Rows without a price or quantity were silently dropped. | UNSAFE | `90.0 is identical to 40.0` (AI ₱90 stored instead of Raflora's ₱40 record) |
| B4 | Claimed guest requests also dropped rows without a price or quantity, and kept the price invented in B1. | UNSAFE | `'90.00' is null` |
| B5 | A quotation could be issued with a confirmed AI item at ₱0 when Gemini gave no price. The guard only fired when an AI price existed. | UNSAFE | quotation was issued |
| B6 | The inventory page counted unconfirmed AI suggestions as reserved stock, which affects the shortage, low-stock and to-procure figures. | UNSAFE | an unconfirmed 25-unit AI row was included in "reserved" |
| B7 | Client analysis never sent the event date to Gemini. The analysis cache key ignored date, time and venue, so a result made for one date could be reused for another. | DISCONNECTED | `An expected request was not recorded` (no "Event Date") |
| B8 | The model, generation settings and analysis time were kept only in the session diagnostic, not with the stored analysis. | MISSING | no `analysis_meta` |
| B9 | When Gemini returned no value, the guest AI review step showed "95% Overall Confidence", "Widely available in the Philippines" and a stock reason for a suggested alternative. | UNSAFE | OBSERVED in view code |

**Already working (CONFIRMED by existing tests unless marked):**
- The prompt separates detections from recommendations, requires `visual_location: null` for items it can't ground in the image, and calibrates `confidence` and `needs_review` (OBSERVED).
- `normalizeVisualLocation` rejects invalid coordinates and clamps regions to the image (`BookingAnalysisLightboxTest`).
- Quota, connection and malformed-response failures are classified and are not shown as successful results (`AiAnalysisFailureHandlingTest`, `GeminiRateLimitAndReliabilityTest`).
- The guest pages label AI output as "Decision Support" and "AI-Assisted Initial Estimate" (OBSERVED).

## 3. Changes made

| File | Change | Why |
|---|---|---|
| `GeminiVisionService::normalizeAiAnalysis` | A missing or invalid quantity or price becomes `null`, with `quantity_status` / `price_status` = `unavailable`. Valid AI values are marked `ai_estimate`. Fractional quantities are kept. The keyword price table `estimateItemCostPhp` was removed. | B1, B2 |
| `GeminiVisionService::buildPricingSummary` | Totals are calculated by the application from AI rows that have both a quantity and a price. The rows left out are listed (`unpriced_items`, `unpriced_item_count`, `is_complete`), and the basis is `price_basis = ai_estimate_unverified`. | WS3: totals by application logic; no invented prices |
| `GeminiVisionService::validateAnalysisPayload` | Accepts an absent quantity or price, but still rejects one that is present and not a positive number. | Unpriced rows reach staff instead of failing the analysis or being guessed |
| `GeminiVisionService` | `analyzed_at` is added to each Gemini result. New `analysisContextHash()` and `buildAnalysisMeta()`. | B7, B8 |
| `BookingController::analyzeTempImage` (client) | Accepts `event_date` and `end_time` and passes them to Gemini. The cache key includes every input sent to Gemini. The full pricing summary is kept, and `analysis_meta` (source, model, generation config, `analyzed_at`, event date, `event_date_provided`, context) is attached. | B7, B8 |
| `GuestBookingController::analyzeTempImage` | Same cache key and `analysis_meta` changes. | B7, B8 |
| `GuestBookingController::store` | The request's pricing summary is recalculated from the combined AI rows, with no default quantity of 1. The direct-upload path now normalises the analysis and records `analysis_meta`. | B2, B8 |
| `BookingController::store` and `persistAiSuggestedMaterials` (client) | The quoted price is Raflora's inventory `unit_cost`, or 0 when there is none. Gemini's price is stored only as `ai_recommended_price`. Every AI row is kept unconfirmed; an unknown quantity is stored as 0 for staff to set. Totals are calculated by `QuotationPricingService` from verified prices. The direct analysis path passes the event date and normalises the result. | B3 |
| `ClaimGuestBookingController::claim` | Keeps AI rows without a price or quantity. An unknown quantity is stored as 0, and a missing AI price as `null`. | B4 |
| `QuotationIssuanceService::issue` | Blocks any confirmed AI-suggested item whose `quoted_unit_price` is ≤ 0, whether or not Gemini gave a price. | B5 |
| `Admin\InventoryController::index` | Reserved demand excludes unconfirmed AI suggestions, in both the table and the KPI cards. Confirmed items and manually added items still count, as `main`'s procurement tests expect. | B6: AI output must not reserve inventory |
| `tests/Feature/AdminNotificationsTest.php` | The base-commit assertion "P-01E: unconfirmed new item includes estimated cost" now expects ₱0 verified cost, with the AI price kept in `ai_recommended_price`. | Behaviour change from B3, matching how claimed guest requests were already priced |
| `resources/views/client/booking-create.blade.php` | Sends `event_date` with the analysis request when it has been entered. | B7 |
| `resources/views/guest/booking-create.blade.php` | A missing quantity or price shows "To be confirmed" or "To be priced by Raflora" instead of 1 or ₱0. The overall-confidence badge appears only when a value exists. The invented seasonal claim and alternative reason were removed. The estimate is labelled partial and lists how many rows are excluded. | B2, B9 |
| `resources/views/guest/booking-analysis.blade.php`, `admin/booking-show.blade.php` | A missing quantity is no longer shown as 1. | B2 |

## 4. Accuracy results — NOT VERIFIED

**No accuracy metrics were measured.** The README requires:
- reference annotations recorded by the owner or qualified staff before Gemini's output is seen;
- a representative image set;
- runs against the live Gemini API.

No images were sent to Gemini during this phase: there is no approved test set, and sending images to an external service needs your approval. Section 9 gives the procedure and the record sheet. No accuracy target was set, because the README says one needs an agreed basis.

## 5. Quality evaluation (ISO/IEC 25010 questionnaire)

**Not conducted.** It remains the formal software-quality instrument. Confirm the adviser-approved respondent count before running it; the capstone passages differ on this.

## 6. Tests run

**New: `GeminiEvidenceValidationTest` (10 tests)**
- Baseline on unchanged code: 9 failed, 1 passed.
- After the changes: 10 passed (68 assertions).

**Full suite**
- Command: `php vendor/bin/phpunit` on branch `feature/readme-workflow-stabilization`, with in-memory SQLite and a temporary `APP_KEY`.
- Result: **1462 tests, 1 failure**.
- The failure is `OtpDeliveryFeedbackAndRecoveryTest::test_smtp_configuration_resolves_via_config_repository`. It needs a local `.env` with SMTP settings, and it fails the same way on unmodified `main`.

**Updated during this phase**
- `AdminNotificationsTest`: the P-01E assertion, now superseded (section 3).
- The first version of the inventory filter also excluded unconfirmed manual items, which broke two of `main`'s `AdminInventoryProcurementReceiptTest` tests. It was narrowed to AI suggestions only, and both tests pass.

## 7. Acceptance criteria

| Criterion | Result | Evidence |
|---|---|---|
| Existing Gemini behaviour and baseline documented | PASS | Section 2 |
| Test images and human-verified references documented | NOT MET | Needs the owner or staff (section 9) |
| Identification and annotation evaluated against references | NOT MET | Needs references |
| Unsupported identifications and uncertain quantities handled appropriately | PARTIAL | Missing quantities and prices are never invented (CONFIRMED). Identification uncertainty relies on the prompt's `needs_review` and `confidence`, which is not measured against references. |
| Alternatives evaluated against event date and constraints | PARTIAL | The event date is now sent and recorded (CONFIRMED). Usefulness of the alternatives is not evaluated. Client analyses made before the date is entered are flagged `event_date_provided: false`. |
| Price and budget calculations use verified inputs and application logic | PASS for quotations and booking totals | Booking totals and issued quotations use Raflora price records and Admin prices only (CONFIRMED). The pre-booking estimate is still Gemini's per-item estimate, totalled by the application and labelled unverified or partial. |
| Visual replacement previews | Out of scope | Not implemented; not approved |
| Factual claims have traceable evidence or are marked unverified | PARTIAL | Prices and quantities carry `ai_estimate` or `unavailable`, and invented UI claims were removed. No external source integration exists; seasonal notes stay "requires Raflora validation". |
| Failed or incomplete analysis cannot bypass staff validation | PASS | AI rows are stored unconfirmed. Issuance requires confirmed rows with an Admin price (CONFIRMED). |
| Booking ownership and authorization enforced | PASS (unchanged) | Existing ownership tests pass. See section 10 for the public analysis endpoint. |
| Relevant targeted and regression tests pass | See section 6 | |
| ISO/IEC 25010 questionnaire remains the formal instrument | PASS (unchanged) | Section 5 |
| Results, limitations and remaining defects documented | PASS | This report |

## 8. Limitations

- The pre-booking estimate shown to guests and clients is still built from Gemini's per-item prices, which come from the prompt's "Dangwa market" instruction. It is labelled an unverified AI estimate, but it is not a Raflora price.
- Seasonality and alternatives are Gemini text with no cited source. They are shown as decision support that needs Raflora validation.
- The client form analyses the image in Step 1, before the event date is entered in Step 2. Most client analyses therefore have no date. This is recorded honestly; the client can retry the analysis after entering the date.
- Inventory matching for AI rows uses name and substring comparison. A wrong match would use the wrong inventory price until staff correct it.

## 9. Accuracy validation procedure (to run with the shop owner)

1. **Choose the images.** Pick 10–20 representative inspiration images across event types: weddings, debuts, corporate events, bouquets, and dense and simple scenes.
2. **Record the references first.** Before running Gemini, the owner or qualified staff record a reference list for each image: items, approximate quantities where defensible, and locations. Record any disagreement between reviewers.
3. **Run each image once** through the normal upload with the event context filled in. Save the `analysis_meta`: model, `analyzed_at` and context.
4. **Score each dimension separately**, using the record sheet below:
   - identification precision and recall;
   - annotation hit rate;
   - quantity error, only where a reference quantity is defensible;
   - uncertainty handling (`needs_review` on unclear items);
   - failure handling.
5. **Agree acceptance thresholds** with the adviser and reviewers before comparing results.

Record sheet (one row per reference or AI item):

| Image ID | Event type / date / venue | Reference item | Reference qty | AI item | Match (TP/FP/FN) | AI qty | Location correct? | AI `needs_review` | AI price status | Reviewer | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| | | | | | | | | | | | |

## 10. Other findings (reported, not changed)

**Not checked in this phase:**
- `GuestBookingController::store` accepts client-supplied `analysis_data` JSON when the session payload is missing. A request's "AI analysis" is therefore not guaranteed to come from Gemini. Staff review still applies, and claim prices come from inventory.

**Observed in the code:**
- `POST /bookings/analyze-temp-image` and `/bookings/validate-image` are throttled but have no `auth` middleware, so anyone can spend Gemini quota through the client endpoint. The guest endpoint is public by design.
- AI rows matched to inventory still create inventory shortage alerts at submission, before staff confirmation, based on AI quantities. They are informational only; nothing is reserved.
- `findCompletedBookingTemplate` reuses a completed booking's items for an identical image hash. This is now visible as `analysis_meta.source = completed_booking_template`.
- `GuestBookingController::persistAiSuggestedMaterials` is unused.

## 11. Next step

Assemble the image set and the owner's reference lists (section 9), then run the first baseline accuracy pass with the current model.
