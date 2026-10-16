---
description: Improve existing Raflora interfaces for clarity, consistency, responsiveness, accessibility, and usability. Preserve business logic, permissions, routes, data behavior, and approved scope.
---

# RAFLORA UI/UX IMPROVEMENT WORKFLOW

## ROLE
Act as a senior UI/UX engineer working within the Raflora system planning and implementation project. Follow the project's always-on rules in raflorafast.md and all applicable approved requirements.

## TASK
Improve the existing Raflora interface for clarity, consistency, responsiveness, accessibility, and smooth workflow completion without introducing unnecessary functionality or changing established business behavior.

## CONTEXT
Raflora is an event-management and material-planning system. Its intended workflow includes client booking, event details, inspiration-image upload, Gemini AI analysis, material suggestions, authorized staff validation, inventory checking, shortage planning, quotation, client confirmation, payment/downpayment, booking confirmation, event preparation and execution, material return, condition assessment, inventory reconciliation, and completion records.

This is the intended target workflow, not proof that every step is implemented. Inspect the current code and approved documentation before relying on any particular screen, route, role, or capability.

## SCOPE
Work only on the UI/UX issue explicitly requested or approved, including directly necessary UI dependencies.

Potentially relevant improvements include:
- Clear page hierarchy, labels, instructions, and action placement.
- Consistent spacing, typography, components, colors, and status presentation using existing project conventions.
- Responsive layouts for relevant screen sizes.
- Accessible forms, labels, focus states, keyboard interaction, and readable contrast.
- Appropriate form grouping and compact layouts.
- Clear validation messages and error, empty, loading, and success states.
- Usable tables, filters, pagination, and modal/detail views when already required by the workflow.
- Clear distinctions between primary and secondary actions.
- Useful confirmation or feedback for consequential actions.
- Consistent terminology between the UI, database states, and approved requirements.

These are options to evaluate, not a requirement to implement every item.

## OUT OF SCOPE
- Do not add new modules, dashboards, analytics, roles, reports, notifications, integrations, or other features merely to make the UI appear more complete.
- Do not redesign the entire application unless explicitly approved.
- Do not change business rules, pricing calculations, quotation logic, payment handling, inventory movement, booking status transitions, or ownership rules as a UI task.
- Do not weaken authentication, authorization, validation, or file-upload security.
- Do not replace dependencies, introduce a new design system, or refactor unrelated code without clear necessity.
- Do not treat visual appearance as evidence that a workflow works.

## TARGET FILES/MODULES
First identify the actual files involved. Depending on the project, these may include Blade templates, layouts, components, CSS, JavaScript, frontend assets, form requests, controllers, routes, and relevant tests. Modify only files directly required for the approved UI task or its direct dependencies. Do not assume filenames or framework structure before inspection.

## REQUIREMENTS
1. Preserve existing behavior and approved requirements.
2. Keep the UI consistent with actual permissions and business states.
3. Show only actions the current user is authorized to perform. Do not rely on hiding a button as the security boundary; server-side authorization must remain enforced.
4. Ensure form fields match server-side validation and persisted data. Do not silently discard existing fields or data.
5. Make errors understandable and recoverable without exposing stack traces, secrets, or sensitive information.
6. Ensure loading, disabled, success, and error states do not misrepresent whether a server-side operation succeeded.
7. For consequential actions, use appropriate confirmation and clear results without adding unnecessary steps.
8. Preserve route names, request formats, data contracts, and integration behavior unless a directly necessary, separately justified change is approved.
9. Use real project data and actual status definitions. Do not invent status labels or present unavailable information as fact.
10. For AI-assisted material suggestions, distinguish suggestions from staff-validated decisions whenever the existing requirements and implementation support that distinction. Do not imply that AI output is final approval.
11. Keep changes focused, maintainable, and compatible with the existing stack.
12. If an important visual improvement requires a business-logic or database change outside the approved scope, report the dependency instead of silently implementing it.

## IMPLEMENTATION PROCESS
### 1. Understand
Restate the exact UI task and define the included and excluded areas. Identify the affected user role and workflow step.

### 2. Inspect
Read raflorafast.md and relevant approved requirements. Inspect the existing page, shared layout/components, CSS/JavaScript, route/controller behavior, authorization, validation, and related tests. Determine which data and statuses the interface actually receives.

### 3. Identify
Classify findings as WORKING, PARTIAL, BROKEN, MISSING, DISCONNECTED, UNSAFE, or UNCLEAR. Separate visual/usability defects from underlying backend or business-logic defects. Record evidence for relevant findings.

### 4. Plan
List the minimum required files and changes. Explain any direct dependency. Do not proceed into unrelated cleanup or feature expansion.

### 5. Implement
Make the smallest coherent UI change. Reuse established project patterns and components. Preserve existing actions, field names, routes, server validation, authorization, and business rules. Do not claim success based on a visual change alone.

### 6. Test
Run relevant available checks, which may include:
- Existing automated tests for affected pages and workflows.
- Route and permission checks for affected actions.
- Form validation and invalid-input handling.
- Responsive and accessibility checks where feasible.
- Browser/UI checks where tooling is available.
- Verification that successful and failed requests display the correct state.
- Verification that data submission, persistence, and the next workflow step still work.

Report only tests actually run and their actual results. If browser testing or another check is unavailable, say so.

### 7. Verify
Inspect the final diff for scope creep, removed controls, changed request fields, broken links, unsafe output, unauthorized actions, inconsistent status displays, and regressions to adjacent workflow steps.

## TEST PLAN
Use the narrowest relevant tests first, then test the affected workflow integration. Confirm:
- The page renders with actual supported data.
- Controls lead to the intended existing actions.
- Valid and invalid submissions behave correctly.
- Server-side permissions remain enforced.
- Success and failure feedback reflects the real operation result.
- Persisted data and business states remain correct.
- The next workflow step is still reachable.
- Unrelated functionality remains untouched.

## ACCEPTANCE CRITERIA
The task is complete only when:
- The approved UI problem is addressed.
- The interface remains consistent with approved requirements and actual system behavior.
- Existing business logic, permissions, routes, data contracts, and workflow transitions are preserved.
- Relevant checks are executed and their results reported honestly.
- The final diff contains only in-scope changes or justified direct dependencies.
- Remaining limitations and unverified behavior are explicitly identified.

## FINAL REPORT
Provide:
1. TASK and TASK BOUNDARY.
2. OBSERVED UI/UX problems and supporting evidence.
3. Files changed and the purpose of each change.
4. Behavior intentionally preserved.
5. Tests/checks actually run and their results.
6. Verification of relevant data, authorization, and downstream workflow behavior.
7. Remaining risks or unverified items.
8. OTHER FINDINGS: unrelated issue, severity, affected area, impact, and recommended follow-up. Do not implement unrelated findings.

## FINAL RULE
Fix the existing interface problem first. Preserve working behavior. Improve usability without expanding scope. Evidence before claims; testing before completion.