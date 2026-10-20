# Raflora Development Status

## Project Objective
Raflora is a Flower Arrangement Event Booking and Inventory Management System with Gemini AI-Assisted Image Analysis.

## Core Workflow
Client Booking
-> Event Details
-> Inspiration Image Upload
-> Gemini AI Analysis
-> Material Suggestions
-> Admin/Staff Validation
-> Inventory Check
-> Shortage/Material Planning
-> Quotation
-> Client Confirmation
-> Payment/Downpayment
-> Booking Confirmation
-> Event Preparation
-> Event Execution
-> Material Return
-> Damage/Condition Assessment
-> Inventory Reconciliation
-> Completion/Records

*Note: This workflow is the target architecture. Implementation truth comes from the actual repository.*

## Development Method
The development follows a strictly verified architecture progression. Documentation reflects what is currently CONFIRMED (directly verified from implementation), OBSERVED, DOCUMENTED, INFERRED, PROPOSED, or NOT VERIFIED.

---

## Completed

### S-01C
**Email Verification & OTP Foundation**
- **Status:** IMPLEMENTED / COMPLETED
- Includes `email_verifications` table, `EmailVerification` model, reusable `OtpService`, `EnsureEmailIsVerified` middleware, and OTP email templates. Integrated with client login/verification workflow. The architecture is designed to be reusable for Admin bootstrap and Staff activation flows.

### S-01D
**Single Admin Bootstrap & Account Recovery**
- **Status:** IMPLEMENTED / COMPLETED (subject to final repository verification)
- Supports exactly ONE Admin account. Includes `is_bootstrap` state, `AdminRecoveryCode` model, `EnsureAdminSetupCompleted` middleware, password reset, emergency recovery, email-change flows, and session revocation. Bootstrap Admin must complete setup before normal Admin access.

---

## Audited / Design Complete

### R1-B
**Evidence Completion & Correction Audit**
- **Status:** AUDIT COMPLETED / ACCEPTED WITH CORRECTIONS
- Verified the quotation, pricing, communication, inventory, and return architecture. Identified a critical pricing presentation mismatch (see Known Implementation Issues).

### S-01E
**New-Device / Trusted Device Security**
- **Status:** AUDITED / DESIGN STAGE
- Verified architectural gap: Admin/Staff currently lack OTP on unrecognized devices. Future implementation must preserve Email + Password -> device recognition -> OTP if unrecognized -> optional one-month trusted-device state. *NOT authorized for unrestricted implementation.*

---

## Known Implementation Issues

### Quotation Pricing Display
- **Status:** CONFIRMED UX DEFECT
- **Detail:** `QuotationPricingService` correctly applies the multiplier when calculating the final quotation total. However, the client-facing `booking-analysis` view currently iterates through the quotation items snapshot and displays the original unmultiplied material unit cost. 
- **Rule:** Authoritative quotation values should be calculated in the service/domain layer and then displayed by the UI. Do not move business pricing calculations into Blade templates. Original material costs must not leak to the client.

### P-01G Payment / Downpayment Integrity
- **Status:** NOT READY / REQUIRES FOLLOW-UP
- **Detail:** Previous partial implementation contained critical errors. The settings table structure is incompatible with the Setting model. `QuotationIssuanceService` incorrectly reads `downpayment_percentage` from HTTP requests instead of system-wide Admin settings. Existing payment statuses (`approved`/`admin_approved`) have inconsistent semantics.

### Current Test State
- **Status:** OBSERVED
- **Current audit-reported test state:** 370 passed / 17 failed (1376 assertions). Failure origin requires verification (failures appear related to a recent `claim_token_hash` NOT NULL constraint on `temporary_guest_bookings`).

---

## Current Phase
- R1 complete.
- R1-B complete.
- Ready to proceed to R2 Target Business Workflow.

## Next Phase
**R2 - Target Business Workflow**
R2 will define and verify the complete lifecycle mapping (Booking lifecycle, Client/Admin/Staff decision points, AI integration, inventory planning, quotation versioning, communications, payment transitions, execution, returns, and reconciliation) before database redesign or broad implementation.

## Deferred / Not Yet Authorized
- **S-01E New-Device Security:** Do not implement trusted device flows yet.
- **P-01G Fixes:** Do not repair payment/downpayment integrity at this stage.
- **Quotation Pricing UI Fix:** Do not fix the pricing exposure defect in Blade templates during this documentation phase.
- **R2 Implementation:** Do not implement R2 features or architectures.

