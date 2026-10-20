# Raflora Stabilization — Phase Exit Reports

Reports for `README_Raflora_System_Stabilization_Plan.md`. Test counts are from `vendor/bin/phpunit` runs in this repository (SQLite in-memory, `phpunit.xml`).

Known environment-only failure (all phases): `Auth\OtpDeliveryFeedbackAndRecoveryTest::test_smtp_configuration_resolves_via_config_repository` expects `smtp.gmail.com` from a local `.env`; the test environment has no `.env`, so mail resolves to `127.0.0.1`. Not caused by these changes (it failed before any change was made).

---

## Phase 2 — Stabilize Auth and Ownership

**Phase status:** Complete

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Login regenerates the session; logout invalidates the session and regenerates the CSRF token; Admin/Staff passwords are verified before the device/OTP chain and never use remember-me. | WORKING | `AuthController::login`, `handleAdminStaffLogin`, `logout`; existing auth tests pass |
| 2 | Client portal routes only required `auth` + `verified`, not the client role. Admin/Staff could open `/client/*`, which silently created a `clients` row for their email and let an Admin change their email through `/client/account-settings`, bypassing the Admin email-change OTP flow. | UNSAFE | `routes/web.php` client group |
| 3 | Client accounts are linked to `clients` rows by email only. Changing the account email did not move the client record (bookings became orphaned), did not reset email verification, and could attach the account to another, unlinked client record with that email. | UNSAFE / BROKEN | `account-settings.update` closure; `BookingController::resolveClient` |
| 4 | Secure file endpoints compared `booking.guest_access_token === query('guest_token')`. Client-created bookings have a `null` token and Laravel converts `?guest_token=` to `null`, so anonymous requests could read any client booking's inspiration images and message attachments (including payment proofs). Verified with a probe request returning HTTP 200. | UNSAFE | `SecureFileController::showInspirationImage`, `showMessageAttachment` |
| 5 | The operations panel could create additional `admin` users, contradicting the single-admin design (S-01D) and breaking emergency recovery, which resolves "the" admin with `User::where('role','admin')->first()`. | UNSAFE | `admin.account.accounts.store`; `AdminRecoveryController` |
| 6 | Temporary UI mock routes (`/mock/*`, marked "TEMPORARY ONLY") were public in every environment and rendered Admin/Staff screens. | UNSAFE | `routes/web.php`, `MockUIController` |
| 7 | `POST /bookings/validate-image` was public and unthrottled; each request calls the Gemini API (cost/DoS exposure). The endpoint is not used by any view. | UNSAFE | `routes/web.php`, `BookingController::validateImageAjax` |
| 8 | Client booking actions (analysis, status, accept, request changes, reply, cancellation, payment reference, proposal feedback, meetings) enforce booking ownership; guest accept/payment endpoints validate the token with `hash_equals` and stay blocked for unclaimed guests. | WORKING | controller checks; existing + new tests |

### Changes made
- `app/Http/Middleware/ClientMiddleware.php` (new), `bootstrap/app.php` (alias `client`), `routes/web.php`: client portal limited to client accounts; Admin/Staff are redirected to their own dashboards (JSON → 403). Admin keeps read access to `client/bookings/{booking}/updates` for supervision (controller enforces admin-or-owner).
- `routes/web.php` account settings: an email change moves the user's client record, refuses an email already linked to another client record, resets `email_verified_at`, sends a verification OTP, and is audit-logged.
- `app/Http/Controllers/SecureFileController.php`: guest-token access requires a non-empty token, a stored token, a constant-time match, and an unclaimed booking.
- `routes/web.php` + `resources/views/admin/settings.blade.php`: account creation limited to Staff.
- `routes/web.php`: mock routes registered only in the `local` environment; `validate-image` throttled (5/min, same as its sibling endpoint).
- `tests/Feature/PackageFallbackConfirmationTest.php`: posts the client booking form as a client account instead of an admin (the test's subject — package fallback confirmation — is unchanged).

### Tests run
- `tests/Feature/Phase2AuthAndOwnershipStabilizationTest.php` (new): 11 tests, 58 assertions — pass.
- Full suite: 1132 tests, 1 failure (the known environment-only SMTP test).

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| Authentication and session behavior work as intended | Pass |
| Roles and permissions are enforced server-side | Pass (client portal now role-checked) |
| Users can access only authorized records and actions | Pass (secure-file IDOR closed) |
| Guest links are protected and validated consistently | Pass |
| Account, client, and booking relationships are correct | Pass (email change keeps linkage) |
| Relevant security and regression tests pass | Pass |

### Remaining blockers
None.

### Other findings (not changed)
- `User::meetings()` and `CalendarEvent` reference a `user_id` column / `calendar_events` table that do not exist; both are unused.
- `ClaimGuestBookingController::claim` calls `lockForUpdate()` outside a transaction for temporary requests, so the row lock has no effect (the claimed-state check still prevents double claims in normal use).
- `tests/Feature/PackageFallbackConfirmationTest.php` writes `scratch/test_response.html` into the project on every run.

### Next step
Phase 3 — Administrator core workflow.

---

## Phase 3 — Stabilize the Administrator's Core Workflow

**Phase status:** Complete

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | `PUT admin/bookings/{booking}` applied any submitted `status` before handling actions. A probe moved a `pending` booking straight to `confirmed` (no verified payment), `quotation_sent` (no quotation issued), `event_in_progress` (no dispatch / fresh-flower checks), and `event_completed`. The UI only ever posts the current status, so these were request-level bypasses of every workflow guard. | UNSAFE / DATA-LOGIC | Probe test; `Admin\BookingController::update` |
| 2 | The `accept` (final approval) action checked the *submitted* status: `status=approved&action=accept` turned a `pending` booking into `admin_approved`. | UNSAFE | Probe test |
| 3 | `mark_event_in_progress`, `mark_event_completed`, and `log_final_payment` did not check the booking's starting stage (e.g. a final payment could be logged on a pending booking). | DATA-LOGIC | `update()` action branches |
| 4 | `verifyPayment` only checked `verified_at`; a payment the Admin had **rejected** could later be verified and confirm the booking. | DATA-LOGIC | `verifyPayment`, `rejectPayment` |
| 5 | `decline` accepted any status, including events in progress and completed/cancelled bookings. | DATA-LOGIC | `Admin\BookingController::decline` |
| 6 | Quotation issuance requires confirmed materials and Admin-reviewed prices; client acceptance moves to `approved` (not confirmed); payment submission moves to `payment_submitted` (not verified); verification requires the exact submitted amount and re-checks stock. Client quotation breakdowns show selling prices only (the R1-B raw-cost exposure is no longer present). | WORKING | `QuotationIssuanceService`, `BookingController::acceptQuotation` / `submitPaymentReference`, client view |

### Changes made
- `Admin\BookingController::update` + `illegalRawStatusTransition()`: a form-submitted status must be a valid business event (stop: decline/cancel; confirm only with a verified payment; post-event stages only after the event started; client-owned statuses never set by Admin; closed bookings stay closed). Status-governing actions ignore the submitted status and use the booking's real status; event start/complete and final-payment logging check their starting stage. Completion keeps its existing guard.
- `verifyPayment`: only `pending` submissions can be verified.
- `decline`: only before the event starts and never for closed bookings.
- Tests: `tests/TestCase.php` gained `recordVerifiedPayment()`. Tests that previously confirmed bookings without any payment now record a verified payment first (`Step16DBookingConflictAndAvailabilityVerificationTest`, `AdminConfirmedMaterialRequirementTest`, `AdminInventoryStockRulesTest`, `Step16GCoreWorkflowEndToEndVerificationTest` journey E); their subjects (availability, locking, release) are unchanged. `AdminNotificationsTest` label-normalization test now normalizes a label on a legal transition and asserts the illegal jump is rejected.

### Tests run
- `tests/Feature/Phase3AdminWorkflowStabilizationTest.php` (new): 6 tests, 52 assertions — pass.
- Full suite: 1138 tests, 1 failure (known environment-only SMTP test).

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| Admin can process a booking through valid review, approval, quotation, and financial steps | Pass |
| Relationships and calculations are correct | Pass (existing pricing/issuance tests) |
| A quotation does not automatically confirm a booking | Pass |
| A payment submission does not count as verified payment | Pass |
| Status changes reflect valid business events | Pass (guard + tests) |
| Inventory decisions and financial records remain consistent | Pass |

### Remaining blockers
None.

### Other findings (not changed)
- `Admin\BookingController::update` is ~800 lines with several intermediate `save()` calls and no surrounding transaction; a mid-request failure can leave partial item edits. A refactor into a service with one transaction is recommended but outside this phase's minimal scope.
- The update form's status list accepts `payment_pending`/`rejected`, which the workflow no longer produces.

### Next step
Phase 4 — Client journey.

---

## Phase 4 — Connect the Client Journey

**Phase status:** Complete

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Booking → Quotation → Acceptance → Payment → Confirmation works end to end through the real routes: package booking creation, Raflora Review, official quotation issuance, client acceptance (→ `approved`, not confirmed), Admin final approval, downpayment submission linked to the accepted quotation (amount = quotation × downpayment %), exact-amount verification, and confirmation (`downpayment_received`, `confirmed_at` set, balance correct). | WORKING | `Phase4ClientJourneyEndToEndTest` |
| 2 | Ownership is enforced on read and write: another client receives 403 on the booking page, acceptance, and the status endpoint. | WORKING | same test |
| 3 | The change-request loop works: an outdated quotation cannot be accepted while changes are pending; the revised quotation supersedes the previous version (one issued version at a time). | WORKING | same test |
| 4 | The client could not start a conversation with Raflora until a message already existed, although the backend accepts messages at any stage. | DISCONNECTED | Fixed during the README workflow work (message form always available for active bookings) |
| 5 | The bookings list used its own hard-coded hints (e.g. "Booking confirmed • Preparation underway" as soon as a downpayment was verified, before any preparation). | UX (misleading) | `client/bookings.blade.php` |
| 6 | Client and guest forms offer exactly the event types the server accepts (`wedding`, `birthday`, `corporate`, `other` + custom). | WORKING | views vs `BookingRequest` |

### Changes made
- `resources/views/client/bookings.blade.php`: each booking shows its README workflow stage and detail from `BookingWorkflowService` (same source as the booking page tracker).

### Tests run
- `tests/Feature/Phase4ClientJourneyEndToEndTest.php` (new): 2 tests, 58 assertions — pass.
- Full suite: 1140 tests, 1 failure (known environment-only SMTP test).

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| The client can complete each authorized action in sequence | Pass |
| Every action stores the correct data | Pass |
| The client sees the persisted result and current business status | Pass |
| The client cannot view or modify another client's records | Pass |
| The next expected step is available when prerequisites are met | Pass |

### Remaining blockers
None.

### Other findings (not changed)
- `BookingController::submitPaymentReference` reads `downpayment_percentage` from the accepted quotation (default 50%). The R1-B note about `QuotationIssuanceService` reading it from the HTTP request no longer applies: issuance reads the system setting.

### Next step
Phase 5 — Guest journey.

---

## Phase 5 — Complete the Guest Journey

**Phase status:** Complete

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Guests can submit a request without an account; the request is stored as a temporary record (24-hour claim window) with a hashed token, and no payment or quotation action is possible before claiming. | WORKING | `GuestBookingController::store`, existing guest tests |
| 2 | Claiming a temporary request locked the row **outside** a transaction and did not re-check the claim inside it, so a double-submit or parallel claim could convert one request into two bookings. Reproduced with a simulated race (test fails without the fix). | DATA-LOGIC | `ClaimGuestBookingController::claim` |
| 3 | Permanent (older) guest bookings could not be claimed — the claim page only looked up temporary requests — and an expired quotation hid the claim button. | DISCONNECTED | Fixed during the README workflow work (claim support + guest page) |
| 4 | Guest numeric-ID endpoints (`accept`, `payment-reference`) require the matching token and stay blocked for unclaimed guests; invalid tokens → 404, expired → 403, claimed → 403 on guest routes. | WORKING | new + existing tests |
| 5 | Secure files accepted an empty guest token for client bookings. | UNSAFE | Fixed in Phase 2 |
| 6 | Expired-request cleanup removes only expired **unclaimed** requests and their images; claimed requests (whose images the booking now uses) are kept. | WORKING | `CleanupExpiredGuestBookings`, new test |

### Changes made
- `app/Http/Controllers/Client/ClaimGuestBookingController.php`: atomic compare-and-set (`unclaimed → claimed`) as the first statement of the claim transaction; a losing claim creates nothing and gets "already claimed".

### Tests run
- `tests/Feature/Phase5GuestJourneyStabilizationTest.php` (new): 5 tests, 35 assertions — pass. The race test was verified to fail with the compare-and-set disabled.
- Full suite at phase close: see Phase 6.

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| Guests use guest functions without creating an account prematurely | Pass |
| Guest records are accessible only through valid authorization | Pass |
| Numeric IDs or guessed URLs cannot bypass token checks | Pass |
| Payment and booking actions are properly authorized | Pass |
| Account conversion preserves ownership and relationships | Pass (single booking, client-linked, details preserved) |

### Remaining blockers
None.

### Other findings (not changed)
- The claimed booking keeps the raw guest token in `bookings.guest_access_token`; guest-only routes and secure files reject it once the booking is claimed, so it no longer grants access.

### Next step
Phase 6 — Staff operations.

---

## Phase 6 — Complete Staff Operations

**Phase status:** Complete

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Staff see and act only on events assigned to them (404 otherwise); assignment only accepts Staff accounts; Staff are blocked (403) from Admin payment verification, return reconciliation, dispatch, and assignment routes. | WORKING | `Staff\EventController::authorizedBooking`, `StaffMiddleware`, new + existing tests |
| 2 | Staff physical counts create an Admin review alert and never change stock (Decision A). | WORKING | `updatePhysicalStock`, new test |
| 3 | **Returned stock was never restored when Staff recorded the return first.** Staff return submissions store `quantity_good` for Admin review without crediting inventory, but Admin reconciliation treated an existing `quantity_good` as stock already credited. Confirming the Staff count therefore produced a zero difference and the returned items stayed out of inventory (reproduced: 8 instead of 10). | DATA-LOGIC (BLOCKER for reconciliation) | `Admin\ReturnTrackingController::update` |
| 4 | Staff could submit return counts and condition observations after Raflora completed the return audit, reopening a reconciled audit as "Partially Returned". | DATA-LOGIC | `Staff\EventController::submitReturn` / `recordCondition` |
| 5 | Material states are distinct: reservation (`booking_lock`/`booking_release`), physical release (`dispatch`/`dispatch_correction`), credited return (`return`), downward correction (`damage`); damaged and lost units are not credited as usable stock. | WORKING | `InventoryDispatchService`, return reconciliation, new test |

### Changes made
- `Admin\ReturnTrackingController::update`: the good quantity already credited for a booking item is read from the inventory ledger (`return` + `damage` movements); pre-ledger single-field legacy rows keep their previous behavior; counts recorded by Staff are treated as not yet credited.
- `Staff\EventController`: return and condition submissions are refused once the return audit or booking is completed.
- `tests/Feature/DeterministicMultiItemLockOrderingTest.php`: the "previously returned" fixture now includes the ledger entry that a real prior credit always has (the rollback assertion is unchanged; the transaction count reflects that prior entry).

### Tests run
- `tests/Feature/Phase6StaffOperationsStabilizationTest.php` (new): 5 tests, 30 assertions — pass. The stock-credit test was verified to fail under the previous logic.
- Return-related suites (`MixedConditionMaterialReturnTest`, `ReturnAuditIntegrityTest`, `DispatchCorrectionReturnAuditIntegrityTest`, `StaffMaterialReturnTest`, `DeterministicMultiItemLockOrderingTest`): 47 tests — pass.
- Full suite: 1150 tests, 1 failure (known environment-only SMTP test).

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| Staff can perform authorized preparation and event operations | Pass |
| Material movements correspond to real operational events | Pass |
| Inventory records are updated consistently | Pass (return credit fixed) |
| Staff permissions do not grant Admin privileges | Pass |
| Return records are available for reconciliation | Pass |

### Remaining blockers
None.

### Other findings (not changed)
- `StaffMiddleware` admits Admin accounts to Staff routes (supervision by design); Admin actions on Staff pages are not restricted to an assignment.

### Next step
Phase 7 — Reconciliation and end-to-end QA.

---

## Phase 7 — Finish Reconciliation and End-to-End QA

**Phase status:** Complete (MySQL clean install not verified — see Remaining blockers)

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Damaged/lost units are recorded per item, are not credited as usable stock, and charge decisions (charge / no charge / pending) drive `pending_resolution` vs completion. | WORKING | Return suites; Phase 7 journey (9 good + 1 damaged → stock 29 of 30) |
| 2 | Scheduled alert commands are registered (`inventory:check-tiered-shortages`, `alerts:check-expired-quotations`, `alerts:check-price-reconfirmations`, `guest-bookings:cleanup`) and execute successfully against a freshly migrated database — **but the deployment never ran the scheduler**: the container only starts `php artisan serve`, so no alert or cleanup ever ran in production. | BROKEN (deployment) | `php artisan schedule:list`; `nixpacks.toml`; `scripts/container-startup.sh` |
| 3 | Every booking status change was audited twice (once by the Booking model, once by the controller), and some contextual entries reused the same action name. | DATA (duplicate audit) | Probe: one cancellation → two `status_changed` rows |
| 4 | Reports use verified payments only for revenue (`verified_at` + verified statuses); contract and pipeline values come from booking quotes; existing reporting-integrity tests pass. | WORKING | `ReportController`, `AdminReportingCalculationIntegrityTest` |
| 5 | Clean install: all 79 migrations run on a fresh SQLite database. | WORKING (SQLite) | `php artisan migrate` on an empty database |
| 6 | One booking can be traced from the guest request through all 14 README stages to completion and final return records using only real routes for Guest, Client, Admin, and Staff. | WORKING | `Phase7ReadmeEndToEndJourneyTest` |

### Changes made
- `scripts/container-startup.sh`: starts `php artisan schedule:work` in the background before the web server (disable with `RAFLORA_RUN_SCHEDULER=false` when a separate cron/scheduler runs `php artisan schedule:run` every minute); output goes to `storage/logs/scheduler.log`.
- `routes/console.php`: scheduled tasks use `withoutOverlapping()->onOneServer()` so multiple instances do not duplicate alerts (requires a lock-capable cache store such as the default `database` store).
- `app/Models/Booking.php`: the model's `status_changed` entry is the single authoritative status audit (records previous and new status in `old_values` / `new_values`). Controller entries that duplicated it were removed (`Admin\BookingController::update`) or renamed to describe the business event (`quotation_accepted`, `final_payment_status_applied`, `return_audit_status_applied`).

### Tests run
- `tests/Feature/Phase7ReadmeEndToEndJourneyTest.php` (new): 1 test, 63 assertions — pass (includes the ordered, de-duplicated status audit trail).
- Clean SQLite install + manual run of all four scheduled commands — exit code 0.
- Full suite: 1151 tests, 1 failure (known environment-only SMTP test).

### Acceptance criteria
| Criterion | Result |
|-----------|--------|
| Damage and missing-item outcomes are recorded correctly | Pass |
| Inventory reconciliation reflects actual conditions and movements | Pass (Phase 6 fix + journey) |
| Scheduled alerts and audit logging are verified rather than assumed | Pass (scheduler now started; audit de-duplicated) |
| Reports match the underlying data | Pass |
| Critical regression tests pass | Pass |
| A booking can be traced from request to final return records | Pass |

### Remaining blockers
- **MySQL clean install not verified.** A local MySQL server is listening, but no credentials were available and no database was created on it. Run `php artisan migrate` against a disposable MySQL database before release.
- `Auth\OtpDeliveryFeedbackAndRecoveryTest::test_smtp_configuration_resolves_via_config_repository` depends on a local `.env` with Gmail SMTP settings.

### Other findings (not changed)
- `Admin\BookingController::update` remains a large method with intermediate saves (see Phase 3).

### Next step
UI/UX modernization (mobile and desktop).

---

## UI/UX Modernization — Mobile and Desktop

**Status:** Complete

### Method
Real pages were rendered from the application (seeded with representative bookings, inventory, returns, notifications and quotations) and screenshotted at 390 px (phone) and 1440 px (desktop) before and after each change. 49 pages were checked across guest, client, admin, staff, auth and error screens.

### Pass 1 — Core journey screens
- Shared shell: admin and staff layouts (Inter type, sticky header, compact mobile bar), client navbar, notification bell.
- README workflow tracker component (`components/booking-workflow`) redesigned: stage pill, "Now" card, 14-segment progress strip, collapsible phases on phones.
- Admin bookings list, booking review, dashboard; staff dashboard and event page; client bookings and booking pages; guest tracking.
- Shared CSS: softer borders/shadows, compact sidebar items, `rf-table--stack` (tables become cards on phones).

### Pass 2 — Remaining screens
- **Tables that were cut off on phones** now render as labelled cards (`data-label` support added to `rf-table--stack`): inventory, archived inventory, return tracking, return audit, quotations, client records, team accounts, recent activity, client booking history.
- **Summary tiles** show two-up on phones instead of one tall card per metric (inventory, reports).
- **Public packages:** cards no longer overflow at phone width. **Gallery:** hero copy readable over the photo on phones.
- **Package create/edit:** inventory mapping rows no longer overlap the quantity input.
- **Auth and error pages:** the brand panel no longer pushes the form or error message below the fold on phones.

### Defects found and fixed during this phase
| Page | Defect | Fix |
|---|---|---|
| Admin → Change Email | Always returned HTTP 500 (`$admin` undefined in view); linked from Account Management | Controller passes the signed-in admin |
| Admin → Return Tracking | Client name always "N/A" (view read non-existent `first_name`/`last_name` on Client) | Uses `full_name`, falls back to guest name |
| Client → Account | Page was a menu of four `href="#"` links; the working profile form was commented out although `account-settings.update` exists | Rebuilt as a profile/password form posting to the existing route; dead links removed |
| Client → Notifications | Cards showed the event type instead of the notification title; modal inserted message text with `innerHTML` | Title + "Event · Booking #id" meta line; modal uses `textContent` |
| Client → Booking History | Unquoted bookings showed "Quote: ₱0.00 / Balance: ₱0.00" | Shows "Not yet quoted" |
| Admin → Reports | Revenue series never drew: Chart.js treated the `revenue` scale as radial (axis inferred from the id's first letter) | Scales declared `type: 'linear', axis: 'y'`; Chart.js pinned to 4.4.1; charts render after web fonts load |

### Tests run
- `tests/Feature/UiModernizationRegressionTest.php` — 8 tests covering the defects above (all pass).
- Full suite: 1159 tests, 1 failure — `Auth\OtpDeliveryFeedbackAndRecoveryTest::test_smtp_configuration_resolves_via_config_repository` (environment-only, pre-existing; needs local SMTP `.env`).

### Limitations
- Screenshots were taken in headless Chrome only; no physical iOS/Android device or Safari testing.
- Inventory "Reserved" counts in the screenshots come from the seeded fixture, not production data.

### Other findings (not changed)
- `client/booking-create.blade.php` and `packages/index.blade.php` build some markup with `innerHTML` from admin-managed package data (package names, included items). Admin-authored, so lower risk, but worth escaping in a later pass.
- Admin → Account Management shows every team account as "Active"; there is no account deactivation feature, so the label is static.

### Next step
Gemini AI accuracy and evidence validation (`README_Gemini_AI_Accuracy_and_Evidence_Validation.md`).

---

## Frontend Workflow Stabilization — Booking Step 2, shared guest/client structure

**Status:** Complete for the booking forms; Admin/Staff verified, not changed (see Remaining work)

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | A photo linked to some of the picked areas was always refused at submission: the analysis is hashed with the brief narrowed to the photo's areas (`EventDesignBrief::forPhoto`), the submission compared the full brief. Every booking with 2+ areas failed (guest and client). | BROKEN | Probe: analyze for `stage` with `entrance`+`stage` picked → store → `analysis_error` |
| 2 | Client "Other" event types were analyzed with `event_type=other` and the type folded into the notes, but submitted with `other_event_type` and plain notes → always refused. | BROKEN | Probe (client) |
| 3 | `analysis_error` was set in 9 places and rendered nowhere; the client form then restored the refused photos as "analyzed" → silent resubmission loop. | BROKEN | `grep analysis_error resources/views` |
| 4 | Every `throttle:N,1` route shared one counter per IP/user: 3 estimate refreshes + 1 venue search made the guest **submission** return 429 (client submission likewise). | BROKEN | Probe; `ThrottleRequests::resolveRequestSignature` |
| 5 | Guest photo requests sent no `Accept: application/json`: oversized/wrong-type photos and HTTP 429 were reported as "connection problem". | BROKEN | Guest analyze fetch |
| 6 | "Retry" on a photo restored from a saved draft (no file) left it on "Analyzing" forever and blocked Next. | BROKEN | `processUploadQueue` → `analyzeSingleImage` returned early |
| 7 | Preference edits after analysis: the warning lived inside the closed dialog, Next was not blocked, the server refused at submission. | PARTIAL | Design brief stale banner |
| 8 | Guest and client Step 2 were separate implementations (≈1,500 and ≈500 lines of JS) that had drifted; the client form had no per-photo analysis view and no review step. | PARTIAL | Views |
| 9 | Area notes (`decor_area_notes`) were already stored in `design_brief`, validated, sent to the AI prompt and shown to Admin/Staff. | WORKING | `EventDesignBrief::displayRows` |
| 10 | Admin/Staff handoffs (review → quotation → payment → execution → return → completion) work; AI rows reach Admin as unvalidated ("0/7 validated", "Unmatched / AI Suggestion"). | WORKING | `Phase7ReadmeEndToEndJourneyTest`; Admin page rendered for a booking made in the browser |

### Changes made
- **Server:** `InspirationPhotos::analysisIsStale()` compares each photo against the brief narrowed to its own areas (used by `GuestBookingController::store` and `BookingController::store`). Both analyze endpoints pass the resolved event type (e.g. "Product launch") to Gemini. Booking-flow throttles get their own counters (`throttle:N,1,<prefix>`): photo-analysis, ai-estimate, location, image-check, guest-booking, client-booking; limits unchanged.
- **`components/inspiration-photos`** (new, used by both forms): preferences summary + dialog with Save/Cancel (Cancel restores the previous values), upload with client-side type/size checks, gallery of 3 cards per page (only the current page is in the DOM; page clamps on removal), indeterminate progress with Cancel, per-photo truthful states (waiting, analyzing, analyzed, failed, cancelled, out of date, needs areas), design summary with server ranges, and dialogs for one photo's analysis (existing viewer, boxes, material pager), one area (note, photos, range, paginated materials) and the price breakdown. Dialogs: focus moves in and back, Tab stays inside, Escape closes, full screen on phones. A photo is out of date when the event details or its own areas' preferences changed; changing them back makes it current again.
- **Guest form:** Step 2 uses the component (4,261 → ~2,100 lines); refusal message shown on Step 2 with the photos marked; the draft is kept until the tracking page confirms the request (cleared there).
- **Client form:** 3 steps — Event Details → Booking Method & Design → Review & Submit (new); same component and wording as the guest form; "Other" event types sent as submitted; refusal message shown; package inclusions inserted as text.

### Tests run
- `tests/Feature/BookingStep2WorkflowFixesTest.php` (new): 11 tests — pass. Mutation check: reverting the per-photo hash or the throttle prefixes makes 4 of them fail.
- Updated for the new structure (assertions on moved IDs only): `Step2CompactionAndAreaPreferencesTest`, `Step2StaleContextAndTruthfulProgressTest`, `AiAnnotationAccuracyTest`.
- Full suite: 1590 tests, 8355 assertions, 0 failures (baseline before this work: 1579 tests, 0 failures).
- Browser (headless Chrome via DevTools Protocol, real app on SQLite, fake Gemini endpoint via `GEMINI_API_URL`): guest journey 49/49 checks, client journey 24/24, edge cases 10/10 (AI rejection, outage, rate limit, focus trap, reload mid-analysis, validation error keeps photos). No horizontal overflow at 390, 768 and 1366 px.

### Remaining work
- **Decision needed:** the "Combined AI Estimate" total across areas (added 2026-10-15) conflicts with the 2026-10-14 rule "no combined total for guests/clients". Behavior left unchanged.
- No physical phone/Safari testing; screenshots from headless Chrome only.
- Admin/Staff screens were verified, not redesigned (no verified defects in scope).

### Other findings (not changed)
- Auth/OTP routes still share one throttle counter per IP (login, register, password reset, admin recovery). Low risk, same fix pattern.
- An AJAX request that reaches the server between a failed submit and its redirect consumes Laravel's one-time flash (errors disappear). Seen only when submitting within milliseconds of an estimate refresh.
- An AI service outage (HTTP 500 from Gemini) is shown as "try a clearer image" (`GeminiVisionService::failureMessageForType`).
- Staff see the client's stated budget range in the design brief (existing, asserted by `EventDesignBriefTest`).

---

## System-wide frontend consistency (Guest, Client, Admin, Staff)

**Status:** Partially complete — shared patterns applied where defects or inconsistencies were verified; no page-by-page redesign of Admin/Staff (they already passed the UI modernization phase)

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Payment verify/reject, booking decline, inventory archive/restore and session/device revokes used the browser's `confirm()`, while the Admin layout already had the app's confirmation dialog. | INCONSISTENT | `grep "return confirm(" resources/views` (7 forms) |
| 2 | Ten list pages used Laravel's default pager; the shared `components.pagination` hard-coded "packages" and purple and was used only on Packages. Filters were already kept (`withQueryString()`). | INCONSISTENT | Controllers and views |
| 3 | The client inbox loaded every notification ever sent. | UNBOUNDED | `ClientNotificationController::index` `->get()` |
| 4 | "Back to Bookings" on the Admin booking page dropped the list's search, filters and page. | PARTIAL | `route('admin.bookings')` without query |
| 5 | Area notes could only be edited inside the full preferences dialog; the main screen showed "· note". | PARTIAL | `components/inspiration-photos` |
| 6 | Decoration areas come from the fixed `EventDesignBrief::DECOR_AREAS` (7, not Admin-configurable); the picker already wraps chips and has a searchable, scrollable add list. | WORKING | `components/design-brief-fields` |
| 7 | Staff queues are scoped to the staff member's own active events; the dashboard shows 12 and the tasks page paginates. Staff are refused Admin routes (403). | WORKING | `Staff\WorkspaceController`; browser check |
| 8 | Viewing the Admin booking page and Admin notifications links booking materials to inventory items by name (a write on GET). It never confirms or reserves stock. | UNCLEAR (existing, not changed) | `reconcileBookingInventoryLinks` |

### Changes made
- `components/confirm-modal`: declarative `data-confirm` / `data-confirm-message` / `data-confirm-label` / `data-confirm-tone` on any form (carries a named submit button's value, single submission, warning icon for danger). Used by `admin/booking-show`, `admin/inventory/index`, `admin/inventory/archived`, `admin/settings`.
- `components/pagination` generalized (counts, `aria-current`, phone layout "Page x of y", brand colors) and set as the default view in `AppServiceProvider`.
- `Admin\BookingController::index` remembers the list URL; `admin/booking-show` "Back to Bookings" uses it (only a URL that starts with the bookings list).
- `ClientNotificationController::index`: the 100 most recent, unread count from all notifications, note about older ones (still on each booking's Updates page).
- `components/inspiration-photos`: per-area note dialog (Save/Cancel, 500-character counter, warning when analyzed photos for that area would need re-analysis); chips show a note preview and Add/Edit note. Writes the existing `decor_area_notes[area]` field only on Save.

### Tests run
- `tests/Feature/SystemWideFrontendConsistencyTest.php` (new): 8 tests, 66 assertions — pass. Removing the inbox cap or the stored list URL makes 2 of them fail.
- Browser (headless Chrome, real app on SQLite): Admin 18/18 (sign-in with emailed code, filtered second page and pager, "Back to Bookings", decline/verify/archive dialogs incl. Escape and a real verification, no overflow at 390/1366 px), Staff 10/10 incl. 403 on Admin pages, Client notes and inbox 15/15 plus 2 count checks that matched the database once a real "Payment Verified" notification was counted.
- Full suite: see the final report of this pass.

### Remaining work
- Decision still needed on the "Combined AI Estimate" total (see previous section).
- Admin settings still uses `alert()`/`confirm()` in its own scripts (photo size, remove photo, import file); not form actions, left as is.
- Login and password-reset routes still share one rate-limit counter per IP.

---

## Quotation Price Integrity (Admin audit F1–F3)

**Status:** Complete for send, reissue and revised reconfirmation; payment defects from the audit (F4, F5, F16) not in scope

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | An Admin override never reached the quotation: `QuotationIssuanceService::issue` recalculated the total, then the controller wrote the posted price onto the booking only. Client saw/paid ₱18,000; Admin's balance expected ₱20,000. | UNSAFE | Audit probe; `test_admin_override_is_the_issued_quotation_booking_client_and_payment_price` fails on the old code |
| 2 | The price summed every line (`calculateTotals`), the snapshot only confirmed ones, and "Remove" only set `procurement_status` → removed and unconfirmed lines stayed in the snapshot and/or the price. | BROKEN | Probe: snapshot kept the removed line, ₱19,500 vs ₱18,000 |
| 3 | A refused send/reissue saved the booking in its `catch` after prices, items and fields were already changed; item writes and auto-confirmations before a refusal were kept. | UNSAFE | Probe: refused re-send after acceptance saved ₱25,000 on the booking |
| 4 | The editor's "Final Quoted Total" is always posted and follows its own running total, which can include unconfirmed AI rows carrying Gemini estimates. Treating every posted value as an override would put AI estimates into client prices. | Design constraint | `booking-show` script; `persistAiSuggestedMaterials` keeps `estimated_unit_cost_php` |

### Changes made
- `QuotationPricingService`: `quotableItems()` (confirmed, quantity > 0, not removed) and `quotationTotals()` (Σ qty × unit price × multiplier + existing labour). `calculateTotals()` keeps its working-figure role for booking creation/claim (shared arithmetic, same results). `REMOVED_FROM_QUOTATION = 'unmatched'` names the existing removal marker.
- `QuotationIssuanceService::issue()`: price and snapshot from the same quotable lines; optional `overridePrice`; the booking takes the issued figures (raw sum, multiplier, final price). Package bookings keep their stored price as before.
- `Admin\BookingController::update()`: send, reissue and revised reconfirmation run in one transaction; refusals throw (`refuseBookingUpdate`) so nothing is kept; removed the code that copied the posted price onto the booking; the client email is sent after commit.
- `admin/booking-show`: hidden `final_price_overridden` (1 once the Admin types a price or the page loads with a previous override; 0 while the box only mirrors the running total). A posted price without the flag (non-browser callers, existing tests) is still treated as the Admin's price.

### Tests run
- `tests/Feature/QuotationPriceIntegrityTest.php` (new): 9 tests, 100 assertions — pass. All 9 fail against the pre-change service and controller.
- `AdminNotificationsTest`: two fixtures given one confirmed line, because they only passed through F2/F3 (a quotation of only a removed line; rows kept after a refused send). Their assertions about the kept line and the new catalog entry are unchanged.
- Full suite (Gemini and Brevo pointed at a closed local port): 1620 tests, 8685 assertions, 0 failures.
- Headless Chrome on server-rendered copies of the editor (network blocked): flag 0 → follows quantity edits; typing sets 1 and keeps the typed price; a reloaded override loads as 1 — 9/9.

### Remaining work
- Reissue and revised reconfirmation still ignore on-screen quantity/price/remove edits (only "Approve & Send" applies them); a typed override is honoured. Decide whether reissue should apply edits.
- A removed line that was confirmed still counts for reservation and dispatch (`confirmedInventoryItems`).
- Ticking "Remove" on an unconfirmed AI suggestion that is not linked to inventory does not get past the "Confirm every material requirement" check.
- With an override, snapshot lines (unit prices) no longer add up to the total shown to the client; the quotation keeps raw sum, multiplier and labour so the difference is traceable.

### Other findings (not changed)
- Substituting a material clears its confirmation, so a substitution made while sending leaves that line off the quotation.
- When labour is set (no Admin input exists; demo/imported data only), the editor's load check treats the stored total as an override.
- An invalid `valid_until` still raises an error (now with nothing saved).

---

## Payment Closure and Final Payment Integrity (Admin audit F4, F5, F16)

**Status:** Complete for decline, cancellation approval, payment rejection and final payment

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Declining (or approving a cancellation) left the client's waiting payment open; rejecting it later set a declined booking back to "admin_approved" (payment unlocked). Rejecting during a cancellation request also wiped the request. | UNSAFE | Audit probe; `PaymentClosureIntegrityTest` fails on the old code |
| 2 | "Log Final Payment" had no status, amount or balance check: ₱0 on a pending booking was saved as a verified payment. | UNSAFE | Audit probe |
| 3 | Rejecting a payment told the client nothing, although the confirmation dialog says the client will be told. | BROKEN | Audit probe (0 notifications) |

### Decision (user, this phase)
A booking is not declined or cancelled while a client payment waits for checking: Admin verifies or rejects it first.

### Changes made
- `Admin\BookingController`: decline (with a row lock), cancellation approval and closing through the booking form are refused while a payment waits. Rejection moves the booking back only when it was waiting for that payment; during a cancellation request it updates the saved prior stage instead; a closed booking stays closed. The client gets a "Payment Not Verified" notification with the next step. Final payment: event started, amount above ₱0 and not above the balance, no client payment waiting (the legacy `log_final_payment` action gets the waiting-payment check too).

### Tests run
- `tests/Feature/PaymentClosureIntegrityTest.php` (new): 7 tests, 44 assertions — pass; all 7 fail against the previous controller.
- Full suite (Gemini and Brevo pointed at a closed local port): 1627 tests, 8729 assertions, 0 failures.

### Remaining work
- Neither "Payment Verified" nor "Payment Not Verified" is emailed to registered clients (in-app only); guests still get the existing status email.
- No reason field when rejecting a payment; Verify/Reject inside the Notifications pop-up still have no confirmation step.
- No refund handling when a booking with verified payments is declined or cancelled.

---

## Notification Actions vs. Review Decisions (Admin audit F12)

**Status:** Complete for Notifications, Dashboard alerts and the inline restock path

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | For Staff stock-count requests (`physical_count_variance`), `is_read` is the decision record: approve and reject both just set it. "Mark Read", "Dismiss All" (Notifications and Dashboard) and the Dashboard's per-alert dismiss therefore closed requests without a decision or audit entry. | UNSAFE | Audit probe; `AdminAlertDecisionIntegrityTest` |
| 2 | The Notifications pop-up classified stock-count requests as stock shortages (they carry an inventory item), so it showed a Restock form instead of Approve/Reject; the approve/reject buttons were unreachable. | BROKEN | Rendered page; `@elseif($isInventoryAlert)` came first |
| 3 | That Restock form posted the request's `alert_id`, so adding stock also closed the stock-count request. | UNSAFE | `InventoryController::resolveShortage` |
| 4 | Reject had no "already reviewed" check (approve had one), so a request could be decided twice. | PARTIAL | `rejectStockAdjustment` |
| 5 | Payment Verify/Reject in the pop-up had no confirmation step (the booking page has one). | UX | View |
| 6 | Other Staff alerts (material requests, issue reports) and system alerts have no Admin decision step; "read" means acknowledged. | WORKING | Alert creators |

### Changes made
- `AdminAlert::DECISION_TYPES` / `requiresDecision()`: one definition of alerts closed only by approve/reject.
- `routes/web.php`: mark-read refuses an open decision alert; Dismiss All skips them and says how many still need a decision.
- `Admin\BookingController::rejectStockAdjustment`: decided once, like approval.
- `Admin\InventoryController::resolveShortage`: a restock never closes a decision alert.
- Views: Notifications pop-up shows Approve/Reject for stock counts (both with confirmation); no "Mark Read" on open requests; Verify/Reject payment and Dismiss All ask for confirmation. Dashboard: "Review" link instead of dismiss for open requests; Dismiss All asks for confirmation.

### Tests run
- `tests/Feature/AdminAlertDecisionIntegrityTest.php` (new): 9 tests, 44 assertions — pass; 6 fail against the previous files (the other 3 guard behaviour that must not change: ordinary dismiss, approval, Staff refused).
- Related existing tests (notifications, dashboard, staff inventory/workspace, shortage resolution, frontend consistency): 108 tests — pass.
- Full suite (Gemini and Brevo pointed at a closed local port): 1636 tests, 8773 assertions, 0 failures.
- Confirmation dialogs verified as rendered attributes only; not clicked in a browser.

### Remaining work (not in this task)
- Email for payment verified/not verified, a rejection reason, and refund handling (needs a business decision).

---

## Return Lifecycle Integrity (Admin audit F11, F13)

**Status:** Complete — one return-record path; approval re-evaluates the booking

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | Four places created return records. `Admin\BookingController::ensureReturnRecordForBooking` (run when the event ends, on final payment and on cancellation) built items from booking lines plus name keywords and **created inventory items**; the Return Tracking page and the Staff event page built them from the dispatch ledger. An ended event therefore got rows for undispatched hardware and unlinked lines (and new catalog entries). | UNSAFE | `ReturnLifecycleIntegrityTest::test_event_completion_creates_one_return_from_dispatched_items_only` fails on the old code |
| 2 | Viewing created records: the Return Tracking list created one for every eligible booking, the manage page created one (or an empty one), the Staff event page created one. Merely opening the manage page of a zero-hardware booking made its audit a completion requirement. | UNSAFE | `test_display_pages_do_not_create_return_records` fails on the old code |
| 3 | Existence checks ran without a lock, so simultaneous requests could create two records (a second open record blocks completion). | PARTIAL (code) | No row lock before the check |
| 4 | Approving damage/loss set the charge decisions but never re-evaluated the return or the booking: the booking stayed "pending resolution", the client was not told, and nothing was in a transaction. Approval with nothing to decide was accepted and logged. | BROKEN | 4 approval tests fail on the old code |
| 5 | The return audit itself (split quantities, stock credit, charges, completion rules on save) works. | WORKING | Existing return tests unchanged in their assertions |

### Changes made
- `app/Services/ReturnRecordService.php` (new): dispatched quantities and `openFor()` — locks the booking, reuses the existing record, otherwise creates one from the dispatch ledger (empty only when Admin asks).
- `Admin\BookingController::ensureReturnRecordForBooking`, `Staff\EventController::ensureReturnRecord`: use the service (no keyword guessing, no catalog creation). `Staff\EventController::show` no longer creates.
- `Admin\ReturnTrackingController`: list and manage pages only read; manage shows `admin/return-tracking-start` (new view) when no record exists; new `start()` (POST `admin.return-tracking.start`, Admin only) opens it. `approve()`: one locked transaction; refused when nothing awaits a decision or when already approved; recomputes the return status and charge total; applies the same booking rules as saving the audit (`applyReturnOutcomeToBooking`, extracted unchanged from `update()`); one "Return review complete" client notification (with the charge and new balance), plus the existing "Booking completed" one when it completes.
- `routes/web.php`: the start route.

### Tests run
- `tests/Feature/ReturnLifecycleIntegrityTest.php` (new): 10 tests, 59 assertions — pass. Run first against the unchanged code: 8 of 10 detect the defects (2 error because the start route did not exist, 6 fail); the other 2 guard unchanged behaviour (split quantities, rejected adjudication). No working-tree file was swapped for this comparison.
- Existing tests whose setup relied on a page view creating the record now open it the new way (Admin start, or the service as at event end); their assertions are unchanged: `AdminReturnTrackingModernizedWorkflowTest`, `MixedConditionMaterialReturnTest`, `Phase2b8EventExecutionAndReturnAccountabilityTest`, `Phase6StaffOperationsStabilizationTest`, `ReturnAuditIntegrityTest`, `StaffMaterialReturnTest`, `ReadmeBookingWorkflowFlowTest`, `Step16GCoreWorkflowEndToEndVerificationTest`, `DeterministicMultiItemLockOrderingTest`, `AdminNotificationsTest` (one test). The list-page test now asserts that viewing creates nothing. `AdminReturnTrackingAccountabilityTest` approves a return with a real pending loss (it previously approved one with nothing to decide).
- Related return/staff/workflow/payment files: 463 tests — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1646 tests, 8833 assertions, 0 failures. `php -l` clean on all changed PHP files.
- Browser: NOT VERIFIED (no live session; pages checked through feature-test responses only).

### Remaining work / other findings (not changed)
- No unique constraint on returns per booking: the code reads the latest record and loops over all, and existing data was not inspected; the booking lock prevents new duplicates.
- Bookings already past the event without a record (older data) need Admin's "Start return audit"; the Staff page says recording becomes available until then.
- `Admin\BookingController::inferIsPerishableFromName` is now unused.
- "Rejected" adjudication still only records the decision; what it should mean for the client is undefined.
- `Staff\EventController::show` still writes the staff checklist on view (`ensureChecklist`).

---

## Inventory Consistency (Admin audit F7, F8, F9)

**Status:** Complete for the verified defects; two definitional questions left for a decision (see Remaining work)

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | "Reserved" means two different, separately tested things: the ledger (`InventoryItem::reserved_stock`: locks − releases − dispatches, reusable items only) used by reservation, dispatch, payment verification, the booking page, Staff and the shortage command; and booked demand (confirmed lines on upcoming active bookings, fresh flowers included) on the Inventory page, where it drives "To Procure". Both are asserted by existing tests (`ReservedStockIntegrityTest` / `GeminiEvidenceValidationTest`, `AdminInventoryProcurementReceiptTest`). | UNCLEAR (label) | Code and tests |
| 2 | The Notifications booking-shortage panel subtracted the booking's *requirement* from total holds to get "held by other bookings", so a booking that had not reserved yet saw other bookings' holds disappear (3 on hand, 2 held elsewhere, 2 needed → no shortage shown). | BROKEN | `test_booking_shortage_counts_stock_held_by_other_bookings` fails on the old code |
| 3 | The booked-demand query was copied twice in `InventoryController::index`; the per-booking ledger formula is repeated in several services. | PARTIAL | Code |
| 4 | Low-stock counts differ: Dashboard/Reports use `current_stock <= min_stock`; `InventoryItem::isLowStock()` uses ledger availability; the Inventory page uses booked demand. | UNCLEAR | Code |
| 5 | Any free-text row in the quotation editor became a catalog item (non-perishable, cost = quoted price) on every save, not only on Send. | UNSAFE | `test_free_text_quotation_line_does_not_create_a_catalog_item` fails on the old code |
| 6 | `promoteAiItem` with a line id that is not on the booking created a catalog item *and* a booking line from unvalidated quantity/price. All promote paths created duplicate names. | UNSAFE | 2 tests fail on the old code |
| 7 | Name auto-linking lower-cased *after* stripping non `a-z0-9` characters, so capitals were deleted ("White Roses" → "hite oses"): real matches failed and unrelated names collided ("Rose"/"hose"). With duplicate catalog names it linked one arbitrarily (then Send auto-confirmed it). | UNSAFE | `test_send_does_not_link_an_ai_line_to_an_ambiguous_name` fails on the old code |
| 8 | Viewing the Admin booking page, its edit page and Notifications ran the name auto-linking (database writes on GET). | UNSAFE | GET-write probe; `test_admin_booking_and_notification_pages_do_not_write` fails on the old code |
| 9 | Return processing no longer creates catalog items (fixed in the F11 phase). | WORKING | `ReturnLifecycleIntegrityTest` |
| 10 | Staff dashboard/event pages create the event's default checklist rows on view; Staff event page records message read receipts. Both are intended (documented by `StaffChecklistTest` and code comments), idempotent, and the checklist has a unique (booking, key) index. | WORKING (intentional) | GET-write probe over all Admin/Staff/Client GET pages |

### Changes made
- `app/Models/InventoryItem.php`: `reservedForBooking()` (the per-booking ledger rule, shared with `reserved_stock`), `scopeWithBookedDemand()` (the Inventory page's single definition), `scopeNamed()` (case/space-insensitive name lookup).
- `app/Http/Controllers/Admin/InventoryController.php`: both index queries use `withBookedDemand()` (same results).
- `app/Http/Controllers/Admin/BookingController.php`: shortage panel uses `reservedForBooking()`; Notifications and the booking/edit pages no longer link on view; Send links a line only to a name with exactly one catalog match, with the casing fix; free-text lines stay unlinked (`pending`, never the "removed" marker); `promoteAiItem` refuses a line id not on the booking and validates quantity/price; every promote path refuses an existing catalog name (`duplicateCatalogNameError()`).

### Tests run
- `tests/Feature/InventoryConsistencyIntegrityTest.php` (new): 9 tests, 40 assertions — pass. Run first against the unchanged code: 6 fail on the defects, 2 error on the new shared helpers, 1 passes (explicit linking must keep working). No working-tree file was swapped.
- `AdminNotificationsTest::test_admin_update_creates_inventory_catalog_entry_for_proposal_items` asserted the F8 behaviour itself; renamed `…_keeps_proposal_items_without_creating_catalog_entries` and now asserts the line is kept unlinked with no catalog item.
- Baseline before changes: 1646 tests, 8833 assertions, 0 failures. After: 1655 tests, 8872 assertions, 0 failures (Gemini and Brevo pointed at a closed local port). `php -l` clean on changed files.
- GET-write probe (scratch, not committed): before — Admin Notifications/booking pages linked materials; after — only the two intentional Staff writes remain.
- Browser: NOT VERIFIED (no live session).

### Remaining work / decisions
- **Decision:** should the Inventory page call booked demand "Reserved" (it is not held stock), and which low-stock rule should Dashboard, Reports and the Inventory page share? Left unchanged; no approved requirement found.
- The per-booking ledger formula is still repeated in `InventoryDispatchService`, `verifyPayment` and the booking page; only the shortage panel was moved to the shared helper.
- The shortage panel counts the full required quantity even when part was already dispatched.
- `promoteBookingItemsToInventory` (Notifications "add missing items") still creates items as non-perishable with the quoted price as cost; client-side AI matching (`BookingController::persistAiSuggestedMaterials`) has its own name matcher (out of scope).
- Staff checklist initialisation on view is kept; a simultaneous first view could hit the unique index and error once.

---

## Inventory Definitions (Admin audit F7, decision resolution)

**Status:** Complete for terminology, the on-hand low-stock rule, equivalent ledger copies and the shortage panel; listed differences kept on purpose

### Definitions (from code and existing tests)
| Term | Calculation | Meaning | Used by |
|------|-------------|---------|---------|
| Reserved Stock | Per booking: locks − releases − net dispatch, floored at 0, summed (`InventoryItem::reserved_stock`, `reservedForBooking()`) | Reusable stock actually held for confirmed bookings in their preparation period; fresh flowers are never held | Reservation, dispatch, payment verification, booking page, Staff, Notifications |
| Booked Demand | Confirmed (or non-AI) lines on upcoming bookings not cancelled/declined/completed (`scopeWithBookedDemand()`) | What upcoming bookings need, flowers included; basis of To Procure | Inventory page |
| Available Stock | On hand − Reserved Stock (`net_available`); on the Inventory page, on hand − Booked Demand | Stock free for new commitments | Reservation/dispatch checks; Inventory page status |
| Low Stock — on hand | `current_stock <= min_stock` (`scopeBelowMinimum()`) | Reorder point on physical stock | Dashboard, Reports, Notifications (which also flags over-held items) |
| Low Stock — planning | (on hand − Booked Demand) <= minimum | Will booked events leave stock below minimum | Inventory page status |

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | The Inventory page labelled Booked Demand "Reserved" ("Quantity committed to valid event bookings"), the same word the ledger uses for stock actually held. | PARTIAL (label) | `InventoryDefinitionsTest` label test fails on the old view |
| 2 | Dashboard and Reports already agreed (on-hand rule) but each repeated it; the Inventory page's planning rule is a different, legitimate purpose. `isLowStock()` (ledger) has no caller in the application. | PARTIAL | Code |
| 3 | The dispatch check and the unfulfilled-release in `InventoryDispatchService` repeated the ledger formula with the same meaning. Payment verification differs (throws on a negative ledger as an integrity guard), the booking page shows total held including dispatched, and reservation computes what is left to hold — not the same meaning, left as they are. | PARTIAL | Code |
| 4 | The Notifications shortage panel compared a line's full quantity with stock on hand, although dispatched units have already left on-hand stock: 4 needed, 3 dispatched, 1 held, 2 on hand → false shortage of 2. | BROKEN | Shortage test fails on the old code |

### Changes made
- `app/Models/InventoryItem.php`: `scopeBelowMinimum()`, `dispatchedForBooking()`; docblocks for `isLowStock()` and the definitions.
- `resources/views/admin/inventory/index.blade.php`: "Reserved" → "Booked Demand" in the column, drawer, help text, receive-stock dialog and To Procure wording; STATUS tooltip explains the planning rule. Calculations unchanged.
- `Admin\DashboardController`, `Admin\ReportController`: on-hand low stock through `belowMinimum()` (same results).
- `app/Services/InventoryDispatchService.php`: dispatch check and unfulfilled release use `reservedForBooking()` (the only writer stores releases as positive quantities, so results are identical).
- `Admin\BookingController::notifications()`: each line's requirement is reduced by units already dispatched to that booking (spread over its lines for the same item).

### Tests run
- `tests/Feature/InventoryDefinitionsTest.php` (new): 5 tests, 26 assertions — pass. First run against the unchanged code: 2 fail (label, shortage), 1 errors (shared low-stock scope did not exist), 2 pass (distinct figures; dispatch/release), which guard unchanged behaviour. No working-tree file was swapped.
- Wording assertions updated to the new label only: `AdminInventoryProcurementReceiptTest` (2), `AdminInventoryRedesignTest` (3), `AdminInventoryTerminologyAndSharedHeaderTest` (2).
- Inventory-related files: 35 tests, 212 assertions — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1660 tests, 8901 assertions, 0 failures. `php -l` clean on changed files.
- Browser: NOT VERIFIED (no live session).

### Remaining work / decisions
- Should the Dashboard and Reports also warn on planning low stock (after Booked Demand)? Kept on hand; no approved requirement found.
- `isLowStock()` (ledger) is unused by the application; kept because tests use it.
- The Notifications shortage table's "Reserved" column shows stock held for *other* bookings; label unchanged.
- Imported data (System Data import) inserts ledger rows directly; a negative release there would now be read as recorded instead of by absolute value in the unfulfilled-release step.

---

## Ledger Import Integrity (follow-up to F7)

**Status:** Complete — import now enforces the release sign; shortage-table label corrected

### Ledger sign convention (application writers)
| Movement | Stored sign | Writer |
|----------|-------------|--------|
| `booking_lock` (hold) | negative | `Admin\BookingController::reserveReusableMaterialsForBooking` (older versions also wrote positive and 0; readers use abs or `< 0`) |
| `booking_release` | **positive** | `InventoryDispatchService::releaseUnfulfilledReservations` — the only writer, positive in every version in history |
| `dispatch` / `dispatch_correction` | negative / positive | `InventoryDispatchService` |
| `procurement`, `return` / `damage`, `adjustment` | positive, positive/negative, signed | Inventory, return audit, stock adjustment |

Readers that depend on the release sign: `InventoryItem::reservedForBooking()`/`reserved_stock` subtract the stored value; the booking page, reserve-materials and payment verification count only releases `> 0`.

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | System Data import validated only references for ledger rows: any sign, any type and non-numeric quantities (cast to 0) were inserted. A negative release (−1 against a −4 hold) would read as a hold of 5 in `reserved_stock` and be ignored by the booking page — the readers disagree. Application exports cannot produce it (releases are always positive); only edited or foreign datasets. | UNSAFE (import boundary) | `SystemDataLedgerImportTest`: negative release and non-numeric quantity previews returned 200 on the old code |
| 2 | Valid imported holds and releases already behaved exactly like application-made ones. | WORKING | Same test passes on old and new code |
| 3 | The Notifications shortage table's "Reserved" column showed stock held for *other* bookings. The single-item stock alert's "Reserved" is the total ledger hold — accurate, unchanged. | PARTIAL (label) | `Admin\BookingController::notifications()` |
| 4 | Import has no duplicate check: bookings and ledger rows are inserted on every import, so importing the same dataset twice duplicates them (clients and users are matched by email). | UNCLEAR (code reading, not changed) | `SystemDataImporter::executeImport` |

### Changes made
- `app/Services/SystemData/SystemDataImporter.php` (`validateDataset`): a ledger row needs a numeric `quantity_change`; a `booking_release` must be positive. Validation runs in preview and again before the import transaction, so a refused dataset writes nothing. Other types are not sign-checked (holds were historically stored with both signs; older types such as `consumed` and `event_started` exist).
- `resources/views/admin/notifications.blade.php`: shortage-table column "Reserved" → "Held for Other Bookings" (with a tooltip). Calculation unchanged.

### Tests run
- `tests/Feature/SystemDataLedgerImportTest.php` (new): 4 tests — pass. First run against the unchanged code: 3 fail (negative release accepted, non-numeric quantity accepted, label), 1 passes (valid import parity). No working-tree file was swapped.
- Focused and related files (System Data, inventory definitions/consistency, reserved stock, dispatch, partial release, notifications, shortage resolution): 82 tests, 454 assertions — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1664 tests, 8917 assertions, 0 failures. `php -l` clean.
- Browser: NOT VERIFIED (no live session).

### Remaining work
- Re-importing a dataset duplicates bookings and ledger rows (finding 4); import idempotency was out of scope.
- Hold, dispatch and correction signs are not validated on import (historical data has positive holds); readers still disagree on a positive hold (`reserved_stock` uses abs, the booking page counts only negative holds).
- Ledger rows with an empty `transaction_type` are still accepted.

---

## Historical Ledger Consistency (follow-up to F7)

**Status:** Complete — hold sign and transaction type enforced at import; readers already agree on all data the application or import can produce

### Correction to the previous section
"Ledger Import Integrity" said holds were historically stored with both signs. Git history shows otherwise: every application version wrote `booking_lock` as `-$requiredQty`, and one version (`c962c25`, 2026-07-30) wrote `0` as a "downpayment verified" marker. The positive `$requiredQty` seen in `0d309c4` belongs to a `booking_release` row. The committed database dump and the demo dataset contain no hold rows.

### Hold readers
| Reader | Reads holds as | Meaning |
|--------|----------------|---------|
| `InventoryItem::reservedForBooking()` / `reserved_stock` (→ dispatch check, unfulfilled release, shortage panel) | Σ abs(hold) − releases − net dispatch | Net hold still held |
| `Staff\StaffWorkspaceService::dispatchRows` | Σ abs(hold) − releases; dispatched separately | Total hold recorded |
| Booking page `buildBookingReviewView` | abs(Σ negative holds) − positive releases; dispatch separately | Total hold recorded |
| `reserveReusableMaterialsForBooking` | same | Total hold, to work out what is left to hold |
| `verifyPayment` | abs(Σ negative holds) − positive releases − dispatch; throws if negative | Net hold, integrity guard |
| `BookingWorkflowService::netLocked`, `CheckTieredInventoryShortages`, mark-in-progress check | negative holds only | Total hold / any real hold |

On negative holds and zero markers both forms give identical results (a zero marker adds nothing; `0 < 0` is false, so it never counts as a real hold). They differ only for a positive hold, which no application path writes. The readers' distinct purposes (net hold, total hold, remaining to hold) were kept; no reader was changed.

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | System Data import accepted a positive `booking_lock`, which the shared rule would count as a hold while the booking page, payment verification and workflow would ignore it. | UNSAFE (import boundary) | `HistoricalLedgerConsistencyTest`: preview returned 200 on the old code |
| 2 | Import accepted empty, blank or missing `transaction_type`. | UNSAFE (import boundary) | Same test, 3 cases |
| 3 | Hold readers agree on canonical data including legacy zero markers; payment verification still rejects an impossible ledger (more released than held). | WORKING | Guard tests pass on old and new code |

### Changes made
- `app/Services/SystemData/SystemDataImporter.php` (`validateDataset`): `transaction_type` must be a non-blank string (no fixed list — older types `consumed` and `event_started` still import); `booking_lock` must be negative or 0. Release and numeric checks from the previous task kept. Runs in preview and again before the import transaction, so refused datasets write nothing.

### Tests run
- `tests/Feature/HistoricalLedgerConsistencyTest.php` (new): 5 tests — pass. First run against the unchanged code: 2 fail (positive hold, empty type), 3 pass (historical import, reader agreement, payment integrity guard). One fixture of mine was corrected before the baseline: a dispatched booking is treated as a post-event payment, which skips the integrity check by design.
- Related files (ledger import, System Data, inventory definitions/consistency, reserved stock, dispatch, partial release, preparation reservation, payment integrity, stock rules): 86 tests, 454 assertions — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1669 tests, 8938 assertions, 0 failures. `php -l` clean.
- Browser: NOT VERIFIED (no live session).

### Remaining work
- Rows already in a database that came from foreign data or direct edits (positive holds) are not rewritten; readers still differ on them. No such rows exist in the committed dump.
- Test fixtures (e.g. `AdminInventoryStockRulesTest`) create positive holds directly; they exercise only the abs-based reader.
- Import idempotency: re-importing a dataset duplicates bookings and ledger rows (unchanged).
- Dispatch and dispatch-correction signs are not validated on import.

## Admin Account UI — Add Account Form Isolation and Team Status

**Status:** Complete — findings 1–4 of the Admin Account UI audit fixed; password-session policy unchanged (awaiting decision)

### Confirmed findings
| # | Finding | Class | Evidence |
|---|---------|-------|----------|
| 1 | A failed Add Account refilled the Admin's own Profile "Full Name" with the applicant's name (shared field `name`, profile used `old('name')`). Saving the profile would rename the Admin. | UNSAFE (data) | `AdminAccountCreationFormTest`: profile input held "Applicant Name" on the old code |
| 2 | An Add Account password mismatch opened Security and read as the Admin's own password change failing (tab chosen from field names). | BROKEN | Same test file |
| 3 | The Add Account dialog closed on error and lost name/email; errors only in the page-level list. | PARTIAL | Same test file |
| 4 | Team Accounts labelled every account "Active", though new Staff have `email_verified_at` NULL until first sign-in and no status field exists. | PARTIAL (data shown) | Same test file |

### Changes made
- `app/Http/Controllers/Admin/AdminSettingsController.php` (`storeAccount`): validates with `validateWithBag('createAccount', …)`. Rules (Staff only, unique email, confirmed password) unchanged.
- `resources/views/admin/settings.blade.php`:
  - Tab selection: errors in the `createAccount` bag open Administration first (before the query parameter and field-name checks).
  - Profile "Full Name" ignores old input when the Add Account form failed.
  - Add Account dialog: reopens on load (`data-open-on-load`, focus on the first invalid field), in-dialog error summary (`role="alert"`), per-field messages with `aria-invalid`/`aria-describedby`, labels tied to inputs, name/email refilled from old input only after its own failure. Passwords never refilled (Laravel's `dontFlash` keeps them out of old input; inputs have no `value`).
  - Team Accounts: column "Verification" shows "Email verified" or "Awaiting first sign-in verification" from `email_verified_at`; no claim about active/disabled.
- `tests/Feature/Phase2AuthAndOwnershipStabilizationTest.php`: the "no second Admin" assertion now names the `createAccount` bag (same refusal, new bag).

### Tests run
- `tests/Feature/AdminAccountCreationFormTest.php` (new): 9 tests — pass. Against the unchanged code: 5 fail (profile contamination, dialog reopen/values, password mismatch tab, role error bag, status), 4 pass (passwords not refilled, successful creation, profile/password forms' own validation, Staff forbidden).
- `AdminAccountSettingsTest` 23 tests and `AdminBootstrapAndRecoveryTest` 31 tests — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1678 tests, 9003 assertions, 0 failures. `php -l` clean.
- Browser: NOT VERIFIED (no live session); dialog auto-open and focus are checked in rendered HTML only.

### Remaining work
- Password change from Settings does not sign out other sessions or trusted devices — policy decision pending.
- Unreachable "Reset My Password" dialog; Add Account subtitle still says "admin or staff"; no inline errors on the Security password form; sidebar label.

## Admin Password Change — Session and Trusted-Device Revocation

**Status:** Complete — approved policy applied to the Account Settings password change

### Existing mechanisms (reused)
| Mechanism | Where | Used by |
|-----------|-------|---------|
| Delete the user's rows from the `sessions` table (database session driver, `SESSION_DRIVER=database`) | `AdminPasswordResetController`, `AdminRecoveryController`, `AdminSettingsController::revokeOtherSessions` (keeps the current row) | Reset, recovery, "Sign out other sessions" |
| Set `revoked_at` on `trusted_devices` rows; `TrustedDeviceService::findUsableForUser` ignores revoked rows | `AdminRecoveryController`, `TrustedDevice::revoke()` | Recovery, single-device revoke |
| Laravel remember-me | Suppressed for Admin/Staff (`AuthController`); the trusted-device cookie replaces it | — |

Reset and recovery also end the current session because the user is not signed in there; in Settings the Admin is signed in, so the current session is kept (as "Sign out other sessions" does).

### Changes made
- `app/Http/Controllers/Admin/AdminSettingsController.php` (`updatePassword`, also serves `admin.account.password`): after validation, one `DB::transaction` updates the password, deletes the Admin's other `sessions` rows (current kept) and revokes all of the Admin's unrevoked trusted devices. On any failure the transaction rolls back (password unchanged) and an error is shown on Security. After commit: this browser's trust cookie is cleared, audit entries `password_updated`, `sessions_revoked`, `trusted_devices_revoked`, and the success message says what was signed out. Validation rules unchanged.

### Tests run
- `tests/Feature/AdminPasswordChangeRevocationTest.php` (new, database session driver, real session rows and trusted devices): 4 tests, 49 assertions — pass. Against the unchanged controller: 2 fail (successful change, backward-compatible route), 2 pass (rejected changes touch nothing, Staff forbidden).
- `AdminAccountSettingsTest` 23, `AdminAccountCreationFormTest` 9, `AdminBootstrapAndRecoveryTest` 31 — pass. Full suite (Gemini and Brevo pointed at a closed local port): 1682 tests, 9052 assertions, 0 failures. `php -l` clean.
- Browser: NOT VERIFIED (no live session).

### Remaining work
- Session revocation relies on the database session driver; with another driver the `sessions` delete removes nothing (same limitation as reset, recovery and "Sign out other sessions").
- A request already in progress in another browser when the password changes can write its session row back when it finishes (database session handler upsert); the next password change or "Sign out other sessions" removes it.
