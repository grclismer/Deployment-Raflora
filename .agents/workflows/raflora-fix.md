---
description: Implement one approved Raflora issue at a time with minimal, verified changes and no unrelated refactoring or feature expansion.
---

# RAFLORA — CONTROLLED IMPLEMENTATION

## TASK

Fix one explicitly approved issue in the Raflora system. Preserve existing requirements, business rules, architecture, security, and working functionality.

## 1. AUTHORITY AND SCOPE

Follow the repository's always-on Raflora development rules, approved capstone requirements, adviser decisions, current implementation, and the user's latest explicit instructions.

The implementation must address the approved issue, not redesign the entire module.

Before making changes, state:

**CURRENT TASK:** One sentence describing the approved issue.

**TASK BOUNDARY:**
- Included: The specific issue and its necessary direct dependencies.
- Excluded: Unrelated features, modules, refactoring, schema changes, and architectural changes.

If the issue has not been approved, do not modify files. Inspect the problem and request approval before implementation.

If requirements conflict or the intended behavior is unclear, identify the conflict and ask for clarification instead of silently guessing.

## 2. INSPECT BEFORE MODIFYING

Inspect the existing implementation before editing anything.

Depending on the issue, inspect:
- Relevant Blade views, layouts, JavaScript, CSS, and shared components.
- Routes, middleware, controllers, form requests, validation, and services.
- Models, relationships, migrations, constraints, and relevant database records.
- Authentication, authorization, ownership, and role restrictions.
- Existing tests, related regressions, and the next workflow step.
- Git status and existing diffs to identify unrelated or uncommitted changes.

Search for existing functions, components, routes, and reusable patterns before creating replacements.

Establish:
1. The expected behavior from approved requirements.
2. The actual behavior from the current implementation.
3. The root cause of the issue.
4. The minimum files and dependencies that must change.
5. The regression risks and appropriate tests.

Do not assume that a feature is missing merely because it is not visible in one file.

## 3. IMPLEMENT THE MINIMUM NECESSARY FIX

Make the smallest complete change that resolves the approved issue.

Requirements:
- Preserve working functionality and established project conventions.
- Reuse existing components, services, validation, and business logic where appropriate.
- Keep guest and authenticated-client behavior consistent when they share the same workflow requirements.
- Preserve administrator and staff permissions and the intended operational handoffs.
- Keep frontend state consistent with authoritative backend and database state.
- Validate inputs on the server; do not rely exclusively on browser-side validation.
- Handle errors, invalid states, and failed requests without silently losing user data.
- Preserve existing API contracts, route names, response formats, and data relationships unless a change is explicitly required and approved.
- Add or update tests that directly cover the fix.

For UI issues, improve the existing interface without changing the underlying business process unnecessarily.

For backend issues, correct the underlying logic rather than hiding the problem with a frontend workaround.

For database issues, inspect migrations, relationships, constraints, and existing data implications before proposing or making schema changes.

## 4. RAFLORA BUSINESS RULES

Never bypass these rules for convenience:

- A quotation does not automatically confirm a booking.
- Payment records must belong to the correct booking.
- Booking statuses must reflect actual business events.
- Inventory changes must follow valid business events and existing inventory rules.
- Returned materials require appropriate condition assessment and reconciliation.
- AI-generated material suggestions and quantities are decision support, not final authority.
- Authorized staff must retain the required validation and approval responsibilities.
- Guest, client, staff, and administrator permissions must remain enforced.

The intended core workflow is:

Client Booking → Event Details → Inspiration Image Upload → Gemini AI Analysis → Material Suggestions → Admin/Staff Validation → Inventory Check → Shortage/Material Planning → Quotation → Client Confirmation → Payment/Downpayment → Booking Confirmation → Event Preparation → Event Execution → Material Return → Damage/Condition Assessment → Inventory Reconciliation → Completion/Records.

Treat this as the target workflow, not proof that every stage is implemented. Verify the relevant current implementation and requirements before changing behavior.

## 5. STRICT CHANGE CONTROL

Do not:
- Add features that were not requested or approved.
- Refactor unrelated modules.
- Replace the existing architecture or dependencies without evidence and approval.
- Create duplicate controllers, services, routes, components, or database records unnecessarily.
- Change pricing calculations, inventory rules, payment handling, booking statuses, or authorization merely to make a test pass.
- Remove validation, weaken security, expose credentials, or bypass approval steps.
- Overwrite unrelated user changes or revert unrelated modifications.
- Claim that a feature or test works without verification.

If an additional change is essential, explain why it is a direct dependency and keep it minimal.

If an unrelated defect is discovered, document it under OTHER FINDINGS. Do not automatically fix it.

## 6. TESTING AND VERIFICATION

After implementing the fix:

1. Review the exact diff.
2. Run the most relevant focused tests.
3. Run related integration or feature tests.
4. Test the affected end-to-end journey when practical.
5. Run the broader test suite when feasible and appropriate.
6. Check relevant authorization, validation, and failure cases.
7. Verify persistence and database effects when applicable.
8. Verify the next workflow step and any affected actor handoffs.
9. Check for regressions in guest, client, administrator, and staff workflows where relevant.
10. Report commands and actual results, including failures and skipped checks.

For frontend changes, also verify:
- Desktop and mobile responsiveness.
- Loading, empty, error, success, and disabled states where applicable.
- Form validation, modal behavior, navigation, and data preservation.
- Browser console errors and network failures when browser testing is available.

Do not claim browser-level or end-to-end verification if only automated feature tests were executed.

Do not alter tests merely to conceal a defect. Update expected behavior only when justified by approved requirements.

## 7. ACCEPTANCE CRITERIA

The task is complete only when:
- The approved issue's root cause is addressed.
- The intended action works.
- Relevant data and business state remain correct.
- Authentication and authorization remain enforced.
- Errors and invalid inputs are handled appropriately.
- The next affected workflow step remains functional.
- Relevant regression tests pass, or remaining failures are explicitly documented.
- The diff contains only necessary changes.
- No unrelated features or refactors have been introduced.

If any criterion cannot be verified, report it as NOT VERIFIED rather than claiming completion.

## 8. FINAL REPORT

Return the following report:

### TASK
Restate the approved issue and its boundary.

### ROOT CAUSE
Explain the confirmed cause and supporting evidence.

### CHANGES MADE
List each changed file and the purpose of its change.

### BUSINESS AND SECURITY IMPACT
Explain how the fix preserves relevant business rules, permissions, data integrity, and workflow continuity.

### TEST RESULTS
List actual commands, passed and failed tests, assertion counts when available, and any checks not performed.

### ACCEPTANCE CRITERIA
Mark each criterion PASS, FAIL, or NOT VERIFIED with concise evidence.

### OTHER FINDINGS
List unrelated findings, severity, affected area, impact, and recommended follow-up. Do not implement them.

### FINAL STATUS
Choose one:
- COMPLETE — all required acceptance criteria verified.
- PARTIAL — implementation made, but one or more criteria remain unresolved.
- BLOCKED — implementation cannot safely proceed.
- NOT IMPLEMENTED — inspection completed, but approval or clarification is still required.

Be precise. Distinguish confirmed facts, observations, inferences, and unverified assumptions.