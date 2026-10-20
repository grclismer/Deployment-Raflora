---
description: Test Raflora changes against approved requirements. Verify business logic, persisted data, authorization, error handling, workflow transitions, downstream behavior, and regression risks. 
---

# RAFLORA REGRESSION AND ACCEPTANCE TESTING WORKFLOW

## ROLE
Act as a senior QA engineer, business-logic tester, security reviewer, and system verification specialist for Raflora. Follow the project’s always-on instructions in `raflorafast.md` and all applicable approved requirements.

## TASK
Verify that an approved Raflora change works correctly, preserves existing functionality, respects security and business rules, and allows the affected workflow to continue successfully.

## CONTEXT
Raflora's intended core workflow is:

Client Booking → Event Details → Inspiration Image Upload → Gemini AI Analysis → Material Suggestions → Admin/Staff Validation → Inventory Check → Shortage/Material Planning → Quotation → Client Confirmation → Payment/Downpayment → Booking Confirmation → Event Preparation → Event Execution → Material Return → Damage/Condition Assessment → Inventory Reconciliation → Completion/Records.

This is the target workflow, not proof that every stage exists. Inspect the current implementation and approved documentation before deciding which tests are applicable.

## SCOPE
Test the specific approved change and its direct dependencies. Expand to adjacent workflow steps when needed to verify integration or detect regressions.

Include relevant checks for:
- Expected behavior and acceptance criteria.
- Input validation and error handling.
- Database persistence and relationships.
- Business rules, calculations, and state transitions.
- Authentication, authorization, and record ownership.
- Route, controller, service, API, and UI integration.
- Inventory consistency and booking/payment relationships.
- File upload validation and Gemini AI integration when affected.
- Regression risks to related existing functionality.
- The next workflow step after the tested operation.

## OUT OF SCOPE
- Do not implement unrelated features or fixes while testing.
- Do not modify production data or perform destructive operations without explicit authorization and a safe test plan.
- Do not weaken authorization, validation, or business rules to make a test pass.
- Do not rewrite modules, replace dependencies, or restructure the architecture merely to simplify testing.
- Do not fabricate test results, pretend to execute unavailable tools, or mark untested behavior as verified.
- Do not treat a successful page render or HTTP response alone as proof of end-to-end correctness.

## TARGET FILES/MODULES
Inspect the actual implementation before selecting test targets. Relevant files may include automated tests, routes, controllers, services, models, migrations, form requests, authorization policies, frontend code, and configuration.

Modify test files only when required by the approved task. Change application code only when the user explicitly authorizes a fix or implementation. If a defect is discovered during a test-only task, document it and request or follow the separately approved repair scope.

## REQUIREMENTS

### 1. Evidence and traceability
- Identify the requirement or acceptance criterion being tested.
- Trace it to the affected workflow, module, and implementation.
- Record the test procedure, expected result, actual result, and evidence.
- Distinguish CONFIRMED, OBSERVED, DOCUMENTED, INFERRED, and NOT VERIFIED findings where appropriate.

### 2. Functional testing
Verify that each affected action performs its intended operation, returns the correct feedback, and handles invalid or failed requests appropriately.

### 3. Database integrity
Where applicable, verify that:
- Records are created, updated, and associated with the correct entities.
- Required fields and relationships remain valid.
- Duplicate submissions do not create unintended duplicate records.
- Failed operations do not leave inconsistent partial state.
- Calculations and persisted values agree with the approved business rules.

Use isolated test data or a safe test environment. Do not damage real client, booking, payment, or inventory records.

### 4. Business-state correctness
Verify that state transitions reflect real business events and follow approved rules.

In particular:
- Creating a quotation must not automatically confirm a booking.
- Payments must belong to the correct booking.
- Inventory must change only through valid business events.
- Returned materials must receive the required condition assessment before applicable inventory reconciliation.
- AI-generated suggestions must not bypass required authorized staff validation.

These are business safeguards to verify against the actual implementation and approved requirements.

### 5. Authorization and security
For affected operations, test relevant permitted and denied scenarios:
- Unauthenticated access.
- Authorized access.
- Unauthorized roles or users.
- Attempts to access or modify another user's records.
- Invalid or manipulated request parameters.
- Server-side validation and authorization enforcement.

Do not expose credentials, API keys, personal information, or sensitive application data in reports.

### 6. Error and recovery testing
Where applicable, test missing required fields, invalid values, duplicate submissions, unavailable dependencies, failed uploads, API failures, and database operation failures.

Verify that the system handles failures safely, communicates the problem clearly, and avoids falsely reporting success or leaving inconsistent state.

### 7. Regression testing
Check the changed function and its direct dependencies first. Then test relevant adjacent functions, routes, shared components, and workflow transitions that may have been affected.

Do not claim that the entire application is regression-free based on a limited test suite.

## IMPLEMENTATION PROCESS

### STEP 1 — Understand
Restate the task, define TASK and TASK BOUNDARY, and identify the approved acceptance criteria.

### STEP 2 — Inspect
Read `raflorafast.md`, relevant requirements, the change diff, affected implementation, database relationships, authorization, and existing tests.

Determine the actual framework, available test commands, environment, and safe test-data strategy before running tests.

### STEP 3 — Identify
Classify relevant findings as WORKING, PARTIAL, BROKEN, MISSING, DISCONNECTED, UNSAFE, or UNCLEAR.

Identify high-severity issues first:
BLOCKER → DATA/LOGIC → SECURITY → CORE WORKFLOW → UX → OPTIONAL.

### STEP 4 — Plan
Prepare a focused test plan covering:
- Positive scenarios.
- Negative and validation scenarios.
- Authorization scenarios.
- Data persistence and business-state checks.
- Relevant integration and downstream workflow checks.
- Regression tests.

Prioritize tests according to risk and the approved task.

### STEP 5 — Execute
Run available automated tests and relevant manual or integration checks in the appropriate environment.

Use safe, isolated test data. Do not run destructive commands without authorization and an understood recovery plan.

If a command fails, distinguish a test failure from environment setup, dependency, configuration, or tooling failure.

### STEP 6 — Diagnose
For every failure, record the actual result, reproducible steps where possible, severity, affected workflow, and supporting evidence.

Do not change application code automatically during a testing-only task. If a repair is authorized, make the minimum necessary change and rerun affected tests.

### STEP 7 — Verify
Inspect the final diff and test results. Confirm whether data, permissions, business rules, error handling, and downstream behavior were actually verified.

Rerun relevant tests after any authorized fix. Do not rely solely on an earlier passing result if the implementation subsequently changed.

## TEST PLAN

Select applicable scenarios rather than blindly testing every item.

1. **Functional:** The intended action succeeds with valid inputs.
2. **Validation:** Invalid or incomplete inputs are rejected appropriately.
3. **Persistence:** Correct values and relationships are stored.
4. **Business logic:** Calculations and state transitions match approved rules.
5. **Authorization:** Only permitted users can perform the operation.
6. **Failure handling:** Failed operations do not falsely report success or leave unintended state.
7. **Integration:** The affected operation works across the relevant UI, routes, controllers, services, and database.
8. **Workflow continuity:** The next required step remains accessible and receives correct data.
9. **Regression:** Related existing functions continue to behave as expected.

## ACCEPTANCE CRITERIA
A tested change may be reported as VERIFIED only when sufficient evidence supports the applicable criteria.

Completion requires:
- The approved acceptance criteria are evaluated.
- Relevant tests are actually executed where the environment permits.
- Actual test results are recorded accurately.
- Persisted data and business rules are checked where applicable.
- Authorization and security are checked for affected operations.
- Relevant integration and downstream behavior are verified.
- Failures, blocked tests, and untested areas are disclosed.
- The final result does not overstate coverage or certainty.

A page loading successfully, a button responding, or an HTTP 200 response alone is insufficient evidence of complete functionality.

## FINAL REPORT
Provide:

1. **TASK** — the exact change or function tested.
2. **TASK BOUNDARY** — what was included and excluded.
3. **TEST ENVIRONMENT** — relevant environment and limitations.
4. **TEST CASES** — procedure, expected result, actual result, and outcome.
5. **COMMANDS EXECUTED** — actual commands and their observed results.
6. **DATA VERIFICATION** — records, relationships, calculations, and state transitions checked.
7. **SECURITY VERIFICATION** — applicable authorization and validation results.
8. **WORKFLOW VERIFICATION** — affected stages and downstream behavior tested.
9. **DEFECTS** — severity, evidence, impact, and recommended action.
10. **COVERAGE GAPS** — tests not run, blocked checks, and unresolved uncertainty.
11. **OTHER FINDINGS** — unrelated issues, severity, affected area, impact, and recommended follow-up. Do not implement them automatically.
12. **FINAL STATUS** — PASS, PASS WITH LIMITATIONS, FAIL, or BLOCKED, with a concise evidence-based explanation.

## FINAL RULE
Test real behavior, not appearances. Verify the data, business rules, permissions, and next workflow step. Report evidence honestly. Never claim an unexecuted test passed.