---
description: Trace and repair Raflora business workflows across actors, screens, routes, services, database records, status transitions, authorization, and downstream steps.
---

# RAFLORA — END-TO-END WORKFLOW REPAIR

## TASK

Inspect, trace, and repair an explicitly approved Raflora business workflow so that its relevant steps, actors, data, and status transitions connect correctly from beginning to end.

## 1. CURRENT TASK AND BOUNDARY

Before implementation, state:

**CURRENT TASK:** One sentence describing the requested workflow and its intended outcome.

**TASK BOUNDARY**
- INCLUDED: The requested business journey and its necessary direct dependencies.
- EXCLUDED: Unrelated workflows, optional features, broad refactoring, and architectural redesign.

Inspect first. If the user has not authorized implementation, report the findings and recommended repair plan without modifying files.

If requirements conflict or essential business behavior is unclear, request clarification before making consequential changes.

## 2. AUTHORITY AND EVIDENCE

Follow the Raflora project instructions and this authority order:

1. Approved project scope and requirements.
2. Latest adviser decisions and capstone documentation.
3. Actual code, database schema, migrations, routes, and tests.
4. The intended business workflow.
5. The current task.

Distinguish required behavior from observed implementation. Do not assume a stage exists merely because it appears in the intended workflow.

Use evidence from the repository. Record relevant file paths, functions, routes, relationships, status fields, and tests. Distinguish CONFIRMED, OBSERVED, DOCUMENTED, INFERRED, PROPOSED, and NOT VERIFIED claims.

## 3. RAFLORA CORE WORKFLOW

Use the following as the intended target flow:

Client Booking → Event Details → Inspiration Image Upload → Gemini AI Analysis → Material Suggestions → Admin/Staff Validation → Inventory Check → Shortage/Material Planning → Quotation → Client Confirmation → Payment/Downpayment → Booking Confirmation → Event Preparation → Event Execution → Material Return → Damage/Condition Assessment → Inventory Reconciliation → Completion/Records.

Trace only the stages relevant to the requested task and their direct dependencies.

## 4. INSPECT THE COMPLETE EXECUTION PATH

For each relevant workflow transition, inspect:

### A. Actors and permissions
- Identify the guest, authenticated client, administrator, and staff responsibilities involved.
- Verify route middleware, authentication, authorization, role restrictions, and record ownership.
- Confirm that each actor can perform only the permitted actions.

### B. Interface and navigation
- Identify the source screen, action, destination screen, and expected result.
- Check forms, buttons, links, redirects, modals, navigation controls, and status feedback.
- Find dead ends, missing actions, incorrect redirects, duplicate entry, and confusing handoffs.
- Verify responsive behavior when relevant to the task.

### C. Backend execution
- Trace routes, controllers, requests, validation, services, events, and relevant model methods.
- Confirm that submitted values are validated and that the correct business operation is invoked.
- Check error handling, repeated requests, partial failures, and duplicate operations.

### D. Database and business state
- Inspect the affected models, relationships, migrations, constraints, and status fields.
- Verify that data belongs to the correct booking, client, or operational record.
- Confirm that state changes are persisted correctly and reflect real business events.
- Check transaction boundaries and consistency where multiple related writes are involved.

### E. Workflow handoff
- Identify what the next actor or module receives.
- Verify the required data, status, permissions, and available next action.
- Confirm that the previous step cannot be incorrectly skipped.
- Check whether failure recovery allows the user to resume without corrupting or duplicating records.

## 5. REQUIRED WORKFLOW MAP

Produce a table for the relevant journey with these columns:

| Step | Actor | UI / Entry Point | Backend Operation | Data / Status Change | Next Step | Result |

Use actual implementation evidence. Mark unknown or unverified entries explicitly rather than inventing routes, records, or transitions.

## 6. CLASSIFY FINDINGS

Use these classifications:

- WORKING — the transition is supported by implementation and verification evidence.
- PARTIAL — the step exists but does not fully meet its requirements.
- BROKEN — the observed transition or behavior fails.
- MISSING — a required step or transition has no identified implementation.
- DISCONNECTED — the source and destination functionality exist but do not connect correctly.
- UNSAFE — a verified security, authorization, or data-integrity risk exists.
- UNCLEAR — requirements or implementation evidence are insufficient.

Prioritize findings:

1. BLOCKER
2. DATA / BUSINESS LOGIC
3. SECURITY / AUTHORIZATION
4. CORE WORKFLOW
5. UI / UX
6. OPTIONAL IMPROVEMENTS

For each finding, provide its evidence, impact, affected actors, affected files, and recommended minimal correction.

## 7. BUSINESS RULES THAT MUST BE PRESERVED

- A quotation does not automatically confirm a booking.
- Payments must be associated with the correct booking.
- Booking, quotation, and payment statuses must represent their actual business events.
- Inventory changes must follow valid business events and established inventory rules.
- Material returns require appropriate condition assessment and inventory reconciliation.
- Gemini AI suggestions are decision support, not final business authority.
- Authorized staff must retain the required material validation and operational approval responsibilities.
- Existing authentication, authorization, ownership, and validation controls must remain enforced.

Never change these rules merely to make a workflow appear connected. Verify the approved requirements and existing implementation first.

## 8. IMPLEMENTATION RULES

When implementation is explicitly authorized:

1. Identify the root cause of the broken or disconnected transition.
2. List the necessary files and direct dependencies.
3. Propose the smallest complete correction.
4. Preserve working modules and existing contracts.
5. Implement only the approved scope.
6. Update or add targeted regression tests.
7. Verify the affected journey and its downstream handoff.
8. Inspect the final diff for unrelated changes.

Do not add new modules, roles, integrations, dashboards, payment methods, or database structures unless explicitly approved or demonstrably necessary for the requested correction.

If a necessary dependency expands the scope materially, explain why and obtain approval before proceeding.

## 9. TEST PLAN

Verify, where relevant:

- Successful completion of the intended business journey.
- Correct data persistence and relationships.
- Correct status transitions and next-step availability.
- Validation failures and unauthorized access.
- Duplicate submissions and repeated operations.
- Failed requests and recoverable states.
- Guest/client and administrator/staff handoffs.
- Regression coverage for directly affected functionality.
- End-to-end behavior beyond isolated page rendering.

Run focused tests first, followed by related integration or feature tests and broader tests when feasible.

Report actual commands and observed results. Do not claim browser or end-to-end verification if only unit or feature tests were executed.

## 10. ACCEPTANCE CRITERIA

A workflow repair is complete only when:

1. The intended requirement is identified.
2. The relevant execution path is traced.
3. The required action works for the authorized actor.
4. Correct data is persisted and related to the correct records.
5. Business rules and statuses remain accurate.
6. Authorization and validation remain enforced.
7. The next intended workflow step receives the required data and state.
8. Errors and recoverable failures are handled appropriately.
9. Relevant regression tests pass or remaining failures are documented.
10. The final changes remain within the approved scope.

If any criterion cannot be verified, mark it NOT VERIFIED.

## 11. FINAL REPORT

Return the following sections:

### A. CURRENT TASK AND BOUNDARY
State the task, included scope, and exclusions.

### B. REQUIREMENTS
Summarize the relevant approved requirements and expected business behavior.

### C. WORKFLOW MAP
Show the actual sequence of actors, screens, backend operations, data changes, and handoffs.

### D. FINDINGS
List classifications, severity, evidence, affected files, business impact, and recommended corrections.

### E. CHANGES MADE
If implementation was authorized, list each changed file and explain why it was necessary. If implementation was not authorized, state that no files were modified.

### F. TEST RESULTS
List executed commands, actual outcomes, failures, and unverified scenarios.

### G. ACCEPTANCE CRITERIA
Mark each applicable criterion PASS, FAIL, or NOT VERIFIED with evidence.

### H. OTHER FINDINGS
Record unrelated discoveries separately without implementing them.

### I. FINAL STATUS
Choose one:
- COMPLETE — the required workflow and its relevant handoffs are verified.
- PARTIAL — some steps work, but one or more requirements remain unresolved.
- BLOCKED — a critical issue or clarification prevents safe progress.
- AUDIT COMPLETE — findings and repair plan are ready, but implementation was not authorized.

Be evidence-based. Do not invent behavior or report an unverified workflow as complete.