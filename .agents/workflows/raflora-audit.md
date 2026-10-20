---
description: Inspect a Raflora module before development. Verify requirements, code, database, workflow, security, UI, and tests. Report evidence-based findings without modifying files.
---

# RAFLORA — INSPECT BEFORE DEVELOPMENT

## TASK
Perform an evidence-based audit of the specific Raflora module or workflow requested by the user before any implementation begins.

## CONTEXT
Raflora is an event-floral booking and management system. Its intended core workflow is:

Client Booking → Event Details → Inspiration Image Upload → Gemini AI Analysis → Material Suggestions → Admin/Staff Validation → Inventory Check → Shortage and Material Planning → Quotation → Client Confirmation → Payment/Downpayment → Booking Confirmation → Event Preparation → Event Execution → Material Return → Condition Assessment → Inventory Reconciliation → Completion and Records.

This is the intended target workflow, not proof that every stage is implemented.

## SCOPE
Inspect the requested module and its direct dependencies only.

1. **Requirements:** Review applicable approved capstone documents, adviser decisions, project instructions, and existing requirements.
2. **Implementation:** Inspect relevant routes, controllers, services, models, relationships, requests, validation, views, JavaScript, APIs, and configuration.
3. **Database:** Inspect relevant migrations, schema, constraints, relationships, status fields, and data ownership.
4. **Workflow:** Trace the user journey across relevant guest, client, administrator, and staff interfaces.
5. **Security:** Check authentication, authorization, role restrictions, ownership, input validation, file handling, and exposure of sensitive data.
6. **UI/UX:** Check navigation, forms, responsive behavior, loading and error states, accessibility, and dead ends.
7. **Tests:** Inspect existing automated tests and identify missing coverage for important business rules and user journeys.

## OUT OF SCOPE
- Do not modify application files, migrations, database records, environment settings, or dependencies.
- Do not implement fixes or new features during the audit.
- Do not audit unrelated modules unless a direct dependency must be traced.
- Do not assume missing functionality is required without checking the approved requirements.
- Do not propose architecture rewrites or optional features without evidence and justification.

## IMPLEMENTATION PROCESS
Follow this sequence:

UNDERSTAND → INSPECT → TRACE → VERIFY → CLASSIFY → PRIORITIZE → REPORT

1. Identify the exact task boundary and relevant requirements.
2. Locate the existing implementation before drawing conclusions.
3. Trace the workflow from the entry point through the backend, database, and next workflow stage.
4. Verify actual behavior using existing tests, read-only inspection, or safe non-destructive checks where appropriate.
5. Compare intended requirements with observed implementation.
6. Record evidence, affected files, relevant functions, and test coverage.
7. Identify the smallest logical scope for a future fix, without implementing it.

## FINDING CLASSIFICATION
Classify each finding as one of the following:

- WORKING — behavior is supported by implementation and verification evidence.
- PARTIAL — some required behavior exists, but the workflow is incomplete.
- BROKEN — existing behavior demonstrably fails.
- MISSING — a required capability has no identified implementation.
- DISCONNECTED — components exist but do not connect correctly.
- UNSAFE — a verified security, authorization, or data-integrity weakness exists.
- UNCLEAR — evidence is insufficient or requirements conflict.

Do not classify a capability as MISSING merely because it was not found in one file. Search its likely implementation paths first.

## PRIORITIZATION
Rank verified findings using this order:

1. BLOCKER — prevents a critical workflow or risks serious data loss.
2. DATA / BUSINESS LOGIC — incorrect calculations, relationships, statuses, or inventory movements.
3. SECURITY — authorization, ownership, validation, or sensitive-data exposure.
4. CORE WORKFLOW — incomplete transitions, disconnected modules, or dead ends.
5. UX — usability, responsive layout, confusing navigation, or misleading states.
6. OPTIONAL — nonessential improvements and future enhancements.

Explain the impact and distinguish confirmed defects from risks and suggestions.

## REQUIRED AUDIT REPORT
Produce the following sections:

### 1. Task and Boundary
- Exact task audited.
- Included and excluded areas.
- Applicable requirements.

### 2. Implementation Map
List the relevant files, routes, controllers, services, models, views, migrations, and tests. Explain their roles and connections.

### 3. Workflow Trace
Describe the actual flow from the user's entry point through the relevant database operations and next workflow stage. Identify disconnected transitions and dead ends.

### 4. Findings
For each finding, report:
- ID and title.
- Classification and severity.
- Requirement or business rule involved.
- Observed behavior and supporting evidence.
- Affected files and functions.
- User and business impact.
- Recommended minimal correction.

### 5. Requirements-to-Implementation Matrix
Map each relevant requirement to its workflow stage, implementation location, and verification status.

Use CONFIRMED, OBSERVED, DOCUMENTED, INFERRED, PROPOSED, or NOT VERIFIED where appropriate.

### 6. Test and Verification Gaps
List existing tests inspected, tests actually executed if any, their results, and missing regression or end-to-end coverage. Never claim a test passed unless it was executed and its result was observed.

### 7. Prioritized Remediation Plan
Recommend the smallest safe sequence of changes. Identify dependencies and acceptance criteria for each proposed step. Do not implement the plan.

### 8. Other Findings
Report unrelated discoveries separately, including severity, affected area, impact, and recommended follow-up. Do not expand the task scope to fix them.

### 9. Final Decision
Conclude with:
- Whether the module is ready for implementation.
- The highest-priority verified defect or unresolved requirement.
- What must be clarified before development.
- The recommended next task.

## CONSTRAINTS
- Follow the Raflora project instructions and approved requirements.
- Inspect before modifying; this workflow does not authorize modifications.
- Preserve existing business rules and data integrity.
- Gemini AI provides decision support; authorized staff retain validation authority.
- A quotation must not automatically confirm a booking.
- Inventory changes must correspond to valid business events.
- Payments must belong to the correct booking.
- Returned materials require appropriate condition assessment.
- Prefer fixing and connecting existing functionality over adding features.
- Do not invent routes, files, relationships, requirements, test results, or system behavior.
- If evidence is insufficient, report the uncertainty rather than guessing.

## ACCEPTANCE CRITERIA
The audit is complete only when:
1. The requested scope and applicable requirements are identified.
2. Relevant implementation paths and direct dependencies have been inspected.
3. Critical business rules and workflow transitions have been traced.
4. Findings are evidence-based, classified, and prioritized.
5. Verification limits and test gaps are explicit.
6. A minimal, testable remediation plan is provided.
7. No application or database changes have been made.

## FINAL REPORT
End with a concise summary of implementation readiness, blockers, key findings, verification limitations, and the single recommended next action.