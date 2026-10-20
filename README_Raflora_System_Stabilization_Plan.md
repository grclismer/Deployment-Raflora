# Raflora System     Plan

This README defines the implementation order for stabilizing Raflora's existing system. Follow the phases in sequence, inspect the current implementation before changing code, and keep changes limited to the current phase and its direct dependencies.

## Development Rules

- Inspect the current code, database, routes, middleware, views, and tests before modifying anything.
- Fix confirmed defects before adding features or redesigning working modules.
- Enforce authorization on the server; hiding a button or page is not sufficient.
- Preserve data integrity and business-state correctness.
- Treat AI-generated material suggestions as decision support; authorized staff must validate important results.
- Test the intended user journey, not only whether a page loads.
- Report unrelated discoveries under **Other Findings** instead of expanding the current phase's scope.
- Do not report a feature or test as working unless it has been verified.

## Phase 2 — Stabilize Auth and Ownership

**Scope:** Authentication, authorization, account relationships, record ownership, guest access, and access restrictions.

### Work to verify and repair
- Login, logout, session regeneration, and session invalidation.
- Role middleware and server-side permissions for Administrator, Staff, and Client.
- Registration-to-client linking and the relationship between user accounts and client records.
- Booking ownership checks across client and administrative routes.
- Guest booking access tokens and token validation.
- Guest booking analysis and payment access restrictions.
- Protection against unauthorized access through numeric IDs or modified URLs.
- Authentication and authorization regression tests.

**Why this phase comes first:** Every role depends on this foundation. Building more screens before access control is correct risks exposing private bookings or allowing unauthorized business actions.

**Completion criteria**
- Authentication and session behavior work as intended.
- Roles and permissions are enforced server-side.
- Users can access only records and actions they are authorized to use.
- Guest links are protected and validated consistently.
- Account, client, and booking relationships are correct.
- Relevant security and regression tests pass.

## Phase 3 — Stabilize the Administrator's Core Workflow

**Scope:** Booking review, AI-result validation, material approval, quotations and revisions, payment verification, inventory decisions, and booking-status transitions.

### Work to verify and repair
- Administrator review of submitted booking details.
- Validation of AI-generated material suggestions before they become approved business decisions.
- Quotation relationships, client/booking associations, calculations, revisions, and price validity.
- Payment verification and correct association of each payment with its booking and quotation.
- Correct handling of downpayments versus full payments.
- Legal booking-status transitions and consistent status terminology.
- Inventory checks and shortage/material-planning decisions needed by the booking workflow.
- Transaction safety and error handling for business-critical updates.

**Why this phase follows Auth:** The Administrator is the main business decision-maker. Client and guest workflows depend on administrative decisions to determine what happens next.

**Completion criteria**
- The Administrator can process a booking through valid review, approval, quotation, and financial steps.
- Relationships and calculations are correct.
- A quotation does not automatically confirm a booking.
- A payment submission does not count as verified payment.
- Status changes reflect valid business events.
- Inventory decisions and financial records remain consistent.

## Phase 4 — Connect the Client Journey

**Target journey:** **Booking → Quotation → Acceptance → Payment → Confirmation**

### Work to verify and repair
- Creating a booking with required event details.
- Uploading an inspiration image where required by the existing scope.
- Viewing booking progress and relevant AI/material-review results.
- Receiving and viewing the correct quotation.
- Responding to quotations or proposals through supported client actions.
- Submitting payment information or proof through the authorized flow.
- Viewing accurate payment, booking, and quotation statuses.
- Correct redirects, validation messages, loading states, and error handling.
- Client ownership checks on every relevant read and write action.

**Why this phase follows the Administrator workflow:** The client journey must reflect real decisions and records produced by the administrative workflow. Connecting screens without connecting business states creates misleading progress indicators and dead ends.

**Completion criteria**
- The client can complete each authorized action in the intended sequence.
- Every action stores the correct data.
- The client sees the actual persisted result and current business status.
- The client cannot view or modify another client's records.
- The next expected workflow step is available when prerequisites are met.

## Phase 5 — Complete the Guest Journey

**Target journey:** **Guest Booking → Protected Access → Account Conversion Where Applicable**

### Work to verify and repair
- Guest booking creation without premature account registration.
- Secure access to guest booking details and AI analysis.
- Token validation for guest booking links and related actions.
- Authorization for guest payment submission and other booking changes.
- Safe handling of expired, missing, invalid, or mismatched tokens.
- Conversion or linking of a guest booking to an authenticated client account, where supported by requirements.
- Preservation of booking ownership and history during conversion.
- Tests for guest access, token misuse, and conversion edge cases.

**Why this phase follows the Client journey:** The guest flow shares booking, quotation, and payment behavior with the client flow but has different identity and access requirements. Connect it after the underlying workflow and ownership rules are stable.

**Completion criteria**
- Guests can use intended guest functions without creating an account prematurely.
- Guest records are accessible only through valid authorization.
- Numeric IDs or guessed URLs cannot bypass token checks.
- Payment and booking actions are properly authorized.
- Account conversion, when applicable, preserves correct ownership and relationships.

## Phase 6 — Complete Staff Operations

**Target journey:** **Preparation → Material Movements → Event Execution → Returns**

### Work to verify and repair
- Staff assignments and limits of staff permissions.
- Event preparation details and checklists supported by the existing system.
- Authorized stock updates and recording of physical material release.
- Clear distinction between reserved, released, consumed, returned, damaged, and missing materials where these states exist in the implementation.
- Event execution records and return recording.
- Validation that staff actions correspond to an assigned event or authorized operation.
- Tests confirming staff cannot perform Administrator-only actions.

**Why this phase follows booking and inventory stabilization:** Staff operations depend on accurate booking decisions and inventory records. Implementing operational actions before those foundations are reliable can produce incorrect stock levels or unauthorized state changes.

**Completion criteria**
- Staff can perform authorized preparation and event operations.
- Material movements correspond to real operational events.
- Inventory records are updated consistently.
- Staff permissions do not grant unauthorized administrative privileges.
- Return records are available for reconciliation.

## Phase 7 — Finish Reconciliation and End-to-End QA

**Scope:** Damage assessment, missing-item handling, inventory reconciliation, scheduled alert verification, reporting, audit-log checks, and end-to-end regression testing.

### Work to verify and repair
- Recording returned materials and their condition.
- Assessing damaged, incomplete, or missing items.
- Ensuring damaged or missing assets are not counted as usable stock.
- Reconciling expected material movements against actual returns and usage.
- Verifying scheduled inventory alerts are configured and actually execute as required by approved documentation.
- Checking reports against underlying records.
- Checking audit logs for completeness, correct attribution, and duplicate entries.
- Running regression tests across authentication, Administrator, Client, Guest, Staff, quotations, payments, inventory, and returns.
- Testing clean-install or migration behavior where feasible.
- Testing a complete booking journey using realistic data.

**Why this phase is last:** Reconciliation depends on accurate booking, financial, inventory, and return records. End-to-end testing can then confirm that the entire process works across module boundaries.

**Completion criteria**
- Damage and missing-item outcomes are recorded correctly.
- Inventory reconciliation reflects actual material conditions and movements.
- Scheduled alerts and audit logging are verified rather than assumed.
- Reports match the underlying data.
- Critical regression tests pass.
- A booking can be traced from the initial request through event execution and final return records without broken workflow steps.

## Recommended Execution Method for Every Phase

1. **Understand** — confirm the intended workflow and requirements.
2. **Inspect** — examine relevant routes, controllers, services, models, migrations, views, middleware, and tests.
3. **Identify** — classify findings as `WORKING`, `PARTIAL`, `BROKEN`, `MISSING`, `DISCONNECTED`, `UNSAFE`, or `UNCLEAR`.
4. **Prioritize** — address blockers, data/business-logic errors, security issues, and core-workflow failures first.
5. **Plan** — define the minimum necessary changes and affected files.
6. **Implement** — modify only the current scope and direct dependencies.
7. **Test** — run targeted tests and relevant regression tests.
8. **Verify** — confirm stored data, permissions, status transitions, error handling, and the next workflow step.
9. **Report** — document changed files, tests run and results, remaining issues, and unrelated findings.

## Phase Exit Report

At the end of each phase, document:
- **Phase status:** Not Started / In Progress / Blocked / Complete
- **Confirmed findings:** Evidence-backed problems and behaviors.
- **Changes made:** Files and behavior changed, with reasons.
- **Tests run:** Commands or test cases and their actual results.
- **Acceptance criteria:** Pass/fail for each completion condition.
- **Remaining blockers:** Issues preventing the next phase.
- **Other findings:** Unrelated issues reported but not changed.
- **Next step:** The smallest verified task needed to proceed.

## Important Boundary

This plan describes the order and intended completion conditions; it does not prove that any feature currently exists or works. Before implementation, compare each phase with the latest approved capstone requirements and the current codebase. Resolve discrepancies using evidence, and do not silently assume missing behavior.
