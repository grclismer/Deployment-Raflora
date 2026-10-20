# Raflora — Gemini AI Analysis Accuracy and Evidence Validation

## Purpose

This phase focuses on evaluating and improving the accuracy and practical usefulness of Raflora's existing Gemini AI-Assisted Image Analysis module. It is separate from general system stabilization.

The objective is not merely to make Gemini return a response. The module should produce image analysis and material suggestions that can be reviewed, checked against evidence, and used responsibly as decision support by Raflora's authorized personnel.

This README defines the proposed scope and evaluation approach. It does **not** claim that the listed capabilities already exist or work in the current implementation.

## Alignment with the Capstone Objective

The capstone states:

> To evaluate the functionality of the Gemini AI module and the system's overall usability using a standardized evaluation instrument.

The capstone document describes a questionnaire adapted from the ISO/IEC 25010 Software Quality Model. Its six quality characteristics are:

1. Functionality
2. Usability
3. Reliability
4. Efficiency
5. Maintainability
6. Portability

The questionnaire uses a five-point Likert scale, as described in the capstone methodology.

This phase should preserve that formal evaluation approach while adding a separate, documented method for checking the accuracy of Gemini's actual outputs. The supplementary accuracy checks do not replace the standardized questionnaire.

## Two Complementary Evaluation Methods

### A. Standardized Software-Quality Evaluation

Use the capstone's approved questionnaire to evaluate the system's quality and usability with the intended respondents. Follow the approved questionnaire, respondent plan, scoring method, and interpretation criteria.

**Evidence to retain:**
- Completed evaluation forms and collected responses, handled appropriately.
- Scores and weighted-mean calculations.
- Interpretation using the approved scale.
- A record of the evaluation procedure and any limitations.

**Important:** The available capstone passages differ on the number of respondents. Confirm the latest adviser-approved methodology before conducting the formal evaluation. Do not choose a respondent count by assumption.

### B. Gemini-Specific Accuracy Validation

Compare Gemini's outputs against reference results established or verified by the shop owner and/or qualified floral-design staff. Use representative inspiration images and record the expected results before judging the AI output.

**Evidence to retain:**
- Test image and relevant event context.
- Human-verified reference annotations or material list.
- Gemini output and the date/model/configuration used, where available.
- Discrepancies, corrections, and reviewer decisions.
- The scoring method and limitations.

The questionnaire evaluates perceived software quality; the accuracy-validation procedure evaluates whether the AI's outputs match verified expectations. They answer different questions and should be reported separately.

## Scope of This Phase

### Workstream 1 — Image Understanding and Annotation

Inspect and evaluate whether Gemini can:
- Identify visible event-design elements and materials.
- Describe colors, shapes, arrangement, and relevant visual characteristics.
- Mark the correct regions or locations on the inspiration image, if annotation is supported or is approved for implementation.
- Estimate quantities only where the image supports a reasonable estimate.
- Distinguish visible evidence from inference.
- Flag unclear, obscured, or low-confidence identifications rather than inventing details.

An image may not reveal exact flower species, material composition, dimensions, or quantities. Such details must not be presented as confirmed facts without sufficient evidence.

### Workstream 2 — Event-Date-Aware Alternatives

Evaluate whether suggested alternatives account for relevant booking context, including:
- Event date and type.
- Event location and indoor/outdoor conditions where relevant.
- Seasonal suitability and material perishability.
- Supplier lead time and delivery constraints.
- Raflora's usable inventory and existing reservations.
- Time available to source or prepare a substitute.

Distinguish general seasonal suitability from supplier-confirmed availability and Raflora-confirmed stock. Do not claim that an item is available merely because Gemini recommends it.

### Workstream 3 — Budget and Cost Accuracy

Evaluate whether alternatives can be compared against the client's budget using reliable price inputs.

Preferred price evidence includes Raflora's current price records and dated supplier quotations. External prices must be identified with their source and date checked.

The application, not free-form AI arithmetic, should calculate totals and differences using verified quantities and prices.

Where applicable, keep these cost categories distinct:
- Material costs.
- Labor and setup.
- Transport and delivery.
- Taxes and other charges included under Raflora's actual pricing rules.
- Total quotation amount.

Do not invent missing prices. Mark unknown or unverified costs clearly.

### Workstream 4 — Alternative Design Visualization

Evaluate whether the system can present a useful preview of how selected substitutions may change the original design.

If image editing or generation is implemented or approved, the preview should:
- Use the client's inspiration image as the reference.
- Identify which materials or design elements are being replaced.
- Preserve the rest of the design as closely as practical.
- Show relevant changes in color, texture, size, arrangement, and overall appearance.
- Be labeled as a simulated concept, not a guarantee of the final physical installation.

A visually convincing preview does not prove that materials are available, structurally compatible, or within budget. Those require separate checks.

### Workstream 5 — Sources, Evidence, and Credibility

For factual recommendations, provide traceable evidence where available. The evidence should support the specific claim being made.

| Claim | Preferred evidence |
|---|---|
| Raflora has sufficient stock | Current inventory records checked against reservations and usable condition |
| A material has a stated price | Current Raflora price record or dated supplier quotation |
| A supplier can provide an item | Supplier confirmation for the relevant date and quantity |
| A material is seasonally suitable | Credible horticultural reference or relevant supplier guidance |
| A material suits a venue condition | Reliable technical guidance or qualified review |
| An object is visible in the image | Original image and corresponding annotation |
| A replacement may look a certain way | Clearly labeled visual preview and its stated limitations |

For external references, retain the source title, publisher or supplier, direct URL where available, date accessed, and the claim supported. Assess authority, relevance, freshness, traceability, and whether corroboration is necessary.

A citation alone does not establish that a recommendation is correct. If credible evidence is unavailable, state **Unverified — staff confirmation required** rather than fabricating a source.

## Required Business and Safety Rules

- Gemini is decision support, not the final authority.
- AI-generated suggestions must be reviewable by authorized staff.
- Failed or incomplete analysis must not be shown as a successful, complete result.
- AI output must not automatically approve materials, reserve inventory, verify payment, or confirm a booking.
- Results and uploaded images must remain associated with the correct booking and protected by authorization.
- Unsupported identifications, quantities, prices, availability claims, and citations must not be presented as verified facts.
- Cost calculations and business-state transitions must follow application business rules.
- The system must communicate uncertainty and provide a safe path for staff review.

## Accuracy Test Plan

Build a representative test set of inspiration images. Before evaluating Gemini, have the shop owner or qualified reviewers establish reference annotations and expected material suggestions. Record uncertainty or disagreement among reviewers rather than treating subjective judgments as unquestionable truth.

Evaluate each applicable dimension separately:

| Dimension | Example evaluation method |
|---|---|
| Object/material identification | Compare predicted labels with verified reference labels |
| Image annotation | Assess whether marked regions correspond to the intended objects |
| Quantity estimation | Compare estimates with verified quantities where a reference quantity is defensible |
| Event-date suitability | Have qualified reviewers assess date and context constraints |
| Availability claims | Check against inventory or supplier evidence |
| Budget calculations | Recalculate using verified quantities, prices, and business rules |
| Alternative usefulness | Have reviewers assess practicality and trade-offs |
| Visual replacement preview | Check whether requested substitutions occurred and unrelated elements were preserved |
| Source credibility | Verify that each source exists and actually supports its associated claim |
| Uncertainty handling | Check whether the system flags insufficient evidence instead of inventing details |
| Failure handling | Test invalid inputs, malformed responses, timeouts, and API failures |

Establish a baseline before changing prompts or implementation. Agree on acceptance thresholds with the project requirements and reviewers before declaring success. Do not invent a target accuracy percentage without an agreed basis.

## Implementation Method

Follow this process for the phase:

1. **Understand** — confirm approved requirements and the intended Gemini workflow.
2. **Inspect** — examine the existing upload flow, Gemini service, prompts, response parsing, persistence, interface, authorization, and tests.
3. **Identify** — classify findings as `WORKING`, `PARTIAL`, `BROKEN`, `MISSING`, `DISCONNECTED`, `UNSAFE`, or `UNCLEAR`.
4. **Establish a baseline** — record existing behavior and evaluate representative test cases before changing the system.
5. **Prioritize** — fix incorrect or unsafe outputs and core workflow defects before optional improvements.
6. **Plan** — identify the minimum required code, data, UI, and test changes.
7. **Implement** — modify only this phase and its direct dependencies; avoid unrelated refactors and new features.
8. **Test** — run accuracy checks, failure-path tests, authorization tests, and relevant regression tests.
9. **Verify** — confirm saved results, source traceability, uncertainty handling, staff review, and workflow integration.
10. **Report** — document changes, evidence, test results, remaining limitations, and next steps.

## Out of Scope Unless Separately Approved

- Replacing the existing AI architecture without evidence that it is necessary.
- Building a general-purpose AI research engine.
- Automatic supplier purchasing or commitments.
- Automatic approval of AI-generated material lists.
- Automatic booking confirmation or payment verification by AI.
- Unrelated dashboards, analytics, integrations, roles, or system redesign.
- Claiming scientific or universal AI accuracy from a small, unrepresentative test set.

## Acceptance Criteria

This phase should not be marked complete until the applicable criteria below have been tested and evidenced:

- [ ] Existing Gemini behavior and baseline performance are documented.
- [ ] Test images and human-verified reference results are documented.
- [ ] Image identification and annotation are evaluated against references.
- [ ] Unsupported identifications and uncertain quantities are handled appropriately.
- [ ] Alternatives are evaluated against event date and relevant constraints.
- [ ] Price and budget calculations use verified inputs and application logic.
- [ ] Visual replacement previews, if in scope, are checked against requested changes.
- [ ] Factual claims have traceable evidence or are explicitly marked unverified.
- [ ] Failed or incomplete analysis cannot bypass required staff validation.
- [ ] Booking ownership and authorization are enforced.
- [ ] Relevant targeted and regression tests pass.
- [ ] The ISO/IEC 25010-adapted questionnaire remains the formal software-quality evaluation instrument, subject to the approved methodology.
- [ ] Evaluation results, limitations, and remaining defects are documented.

## Phase Exit Report

At the end of the phase, record:

- **Status:** Not Started / In Progress / Blocked / Complete
- **Implementation inspected:** Relevant files and workflow components.
- **Baseline:** Test set, reference method, and observed results.
- **Changes made:** Files and behavior changed, with justification.
- **Accuracy results:** Metrics, discrepancies, and reviewer findings.
- **Quality evaluation:** Questionnaire method and results, when formally conducted.
- **Tests run:** Actual commands or test cases and outcomes.
- **Acceptance criteria:** Pass/fail with evidence.
- **Limitations:** Known uncertainty, unsupported cases, and data constraints.
- **Other findings:** Unrelated issues reported but not changed.
- **Next step:** Smallest verified task needed to proceed.

## Final Boundary

This README describes a proposed development and evaluation plan. It does not prove that any listed capability already exists, that sources are currently integrated, or that Gemini meets an accuracy threshold. Inspect the current code and compare it with the latest adviser-approved capstone requirements before implementation. Resolve discrepancies using evidence and do not silently assume missing behavior.
