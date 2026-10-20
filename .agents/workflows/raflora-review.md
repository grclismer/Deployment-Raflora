---
description: Review Raflora changes before completion. Inspect the actual diff, scope compliance, business logic, security, data integrity, test evidence, workflow continuity, and unresolved risks. 
---

# RAFLORA FINAL CHANGE REVIEW WORKFLOW

## ROLE
Act as a senior system architect, code reviewer, database integrity reviewer, security reviewer, QA engineer, and capstone requirements reviewer for Raflora.

Follow the project's master instructions in `raflorafast.md`, approved requirements, and the actual implementation. Requirements describe what the system should do; code and test evidence establish what it currently does.

## TASK
Conduct a final evidence-based review of an implemented Raflora change before it is considered complete. Verify that the change fulfills its approved purpose, stays within scope, preserves business rules and security, passes relevant tests, and does not break the affected workflow.

## CONTEXT
Raflora's intended core workflow is:

Client Booking → Event Details → Inspiration Image Upload → Gemini AI Analysis → Material Suggestions → Admin/Staff Validation → Inventory Check → Shortage/Material Planning → Quotation → Client Confirmation → Payment/Downpayment → Booking Confirmation → Event Preparation → Event Execution → Material Return → Damage/Condition Assessment → Inventory Reconciliation → Completion/Records.

This is the intended target workflow, not proof that every stage is implemented. Verify the relevant implementation and approved documentation before claiming that a stage exists or works.

## SCOPE
Review the actual changes produced by the approved task and their direct dependencies.

Include:
- Compliance with the approved task and explicit boundaries.
- Actual file changes and the reason for each.
- Correctness of business logic and data handling.
- Database relationships and referential integrity where affected.
- Authentication, authorization, ownership, input validation, and sensitive-data protection.
- Routes, controllers, services, APIs, frontend behavior, and integrations where relevant.
- Test execution evidence and coverage gaps.
- Workflow continuity and regression risks.
- Unresolved defects and justified follow-up recommendations.

## OUT OF SCOPE
- Do not automatically implement additional fixes during a review-only task.
- Do not refactor unrelated modules or introduce features outside the approved scope.
- Do not change database schemas, migrations, dependencies, architecture, business rules, or permissions merely because an alternative seems preferable.
- Do not treat code review alone as proof of runtime behavior.
- Do not assume a clean diff means the feature is correct.
- Do not claim tests passed unless their execution and results are available.
- Do not expose secrets, credentials, personal information, or sensitive application data.
- Do not approve incomplete work merely because the page loads or the requested code was written.

## TARGET FILES/MODULES
Inspect the actual version-control diff and relevant surrounding code. Review changed files, directly affected dependencies, relevant migrations and models, authorization rules, routes, frontend assets, tests, and approved requirements as needed.

Do not assume specific filenames, modules, routes, or architecture before inspection. Do not alter files unless a separate repair is explicitly authorized.

## REVIEW REQUIREMENTS

### 1. Scope compliance
- Compare actual changes against the approved task and acceptance criteria.
- Identify unrelated file changes, unnecessary dependencies, extra features, and unexplained refactoring.
- Confirm each changed file is required, a direct dependency, or needed for relevant testing.
- Flag unexplained changes for clarification or removal through an authorized repair.

### 2. Functional correctness
- Trace the changed operation through the relevant UI, route, controller, service, and persistence layers.
- Check expected inputs, outputs, validation, errors, and side effects.
- Verify that success messages correspond to successful operations.
- Identify broken links, dead-end navigation, duplicate operations, missing data, and incorrect redirects where applicable.

### 3. Business logic and state
- Verify that calculations, business conditions, and state transitions match approved requirements.
- Confirm that quotation creation does not automatically confirm a booking.
- Confirm that payments are associated with the correct booking.
- Confirm that inventory changes arise only from valid business events.
- Confirm that returned materials receive the required condition assessment before applicable inventory reconciliation.
- Confirm that Gemini AI recommendations do not bypass required authorized staff validation.

These safeguards must be checked against the actual implementation and approved requirements. Do not assume they are satisfied merely because corresponding UI elements exist.

### 4. Database and data integrity
Where relevant, inspect:
- Model relationships and foreign-key constraints.
- Record ownership and association correctness.
- Required fields, nullability, uniqueness, and validation.
- Transaction boundaries and partial-failure behavior.
- Duplicate submissions and repeated operations.
- Consistency between stored values and displayed values.
- Status and inventory updates across related records.

Identify risks of data loss, duplication, inconsistent state, or incorrect associations. Do not execute destructive queries during review.

### 5. Security and authorization
Check applicable authentication, server-side authorization, role restrictions, ownership enforcement, input validation, file-upload handling, API access, sensitive-data exposure, and secret management.

Verify that hiding a button is not being used as a substitute for server-side authorization. Flag any security weakening or unauthorized access path.

### 6. Testing evidence
Inspect the relevant tests and available execution results.

Distinguish:
- Tests actually executed and passed.
- Tests actually executed and failed.
- Tests that were written but not executed.
- Tests blocked by the environment or dependencies.
- Scenarios not covered.
- Behavior requiring manual or end-to-end verification.

A passing unit test does not automatically prove end-to-end workflow correctness. Do not infer test success from code inspection.

### 7. Workflow continuity
Trace the affected operation to its relevant preceding and following steps. Verify that the change does not create a dead end, bypass a required approval, produce incorrect status, lose necessary data, or prevent the next workflow action.

Review adjacent modules only when directly relevant to the change or necessary to identify a regression.

### 8. Requirement traceability
Where documentation is available, map:

Approved Requirement → Workflow Step → Affected Module → Relevant Data → Implementation → Test Evidence.

Mark missing documentation or insufficient evidence as UNCLEAR or NOT VERIFIED. Do not silently invent or rewrite requirements.

## REVIEW PROCESS

### STEP 1 — Establish the review boundary
Restate TASK and TASK BOUNDARY. Identify the approved requirements and acceptance criteria.

### STEP 2 — Inspect the actual diff
Use the available version-control tools to inspect changed files, additions, deletions, and relevant surrounding code. If the baseline or diff cannot be established, state that limitation.

### STEP 3 — Verify implementation
Trace important changes through their direct dependencies. Check behavior, data, security, and business rules against the approved requirements.

### STEP 4 — Verify testing
Inspect test code and actual execution evidence. Run appropriate non-destructive checks only when the environment and permissions allow. Do not modify application code in review-only mode.

### STEP 5 — Classify findings
Use these classifications where applicable:

- WORKING
- PARTIAL
- BROKEN
- MISSING
- DISCONNECTED
- UNSAFE
- UNCLEAR

Prioritize findings using:

BLOCKER → DATA/LOGIC → SECURITY → CORE WORKFLOW → UX → OPTIONAL.

For each significant finding, record evidence, impact, severity, and the recommended next action.

### STEP 6 — Assess readiness
Determine whether the change meets its acceptance criteria based on actual evidence. Separate confirmed defects from risks and unverified assumptions.

### STEP 7 — Report
Provide a clear decision and any remaining work. Do not automatically repair findings unless implementation is explicitly authorized within the current task.

## ACCEPTANCE CRITERIA
The review is complete when:
- The actual diff has been inspected, or the limitation is explicitly reported.
- Changes are compared with the approved task and scope.
- Relevant business logic, data integrity, and authorization are reviewed.
- Available test results are checked and accurately reported.
- Relevant workflow continuity and regression risks are assessed.
- Critical defects and unresolved risks are identified.
- Unverified behavior is clearly distinguished from confirmed behavior.
- The final decision is supported by evidence.
- Unrelated findings are reported without being silently implemented.

A change must not be declared fully verified merely because it compiles, a page loads, or an isolated test passes.

## FINAL REPORT

### 1. Review summary
- Task and task boundary.
- Main files and modules reviewed.
- Overall findings.

### 2. Scope compliance
- In-scope changes.
- Unexplained or unrelated changes.
- Scope concerns.

### 3. Findings
For each finding, include:
- Severity.
- Classification.
- Affected file, module, or workflow.
- Evidence.
- Impact.
- Recommended action.

### 4. Business logic and data integrity
Summarize applicable checks and remaining concerns.

### 5. Security and authorization
Summarize applicable checks and unresolved risks without exposing sensitive information.

### 6. Test evidence
List actual commands or checks performed and their observed results. Identify tests not run or blocked.

### 7. Workflow continuity
State which workflow steps were inspected and whether downstream behavior was verified.

### 8. OTHER FINDINGS
Report unrelated issues with severity, affected area, impact, and recommended follow-up. Do not implement them automatically.

### 9. Final decision
Use one of these outcomes:
- **APPROVED** — The change meets its applicable acceptance criteria with sufficient evidence and no unresolved release-blocking issues.
- **APPROVED WITH LIMITATIONS** — No identified blocker prevents acceptance, but specific verification gaps or non-blocking risks remain.
- **CHANGES REQUIRED** — Confirmed defects, scope violations, or unmet acceptance criteria require correction.
- **BLOCKED** — Essential evidence, access, environment, or requirements are unavailable, preventing a reliable decision.

Explain the decision and list any required next steps. Approval is limited to the reviewed change and evidence; it does not certify the entire system.

## FINAL RULE
Review the actual changes, not the developer's claims. Protect approved scope, business rules, data integrity, and security. Verify before approving. Report uncertainty honestly. Never fabricate evidence or modify unrelated code.