# Workflow Configuration — Executive Summary

> **Audience:** Business Manager / Product Owner / Operations Management  
> **Purpose:** Decision brief based on the technical analysis of the Workflow Configuration feature.  
> **Repository baseline reviewed:** `main` at `0c1ed66`  
> **Status:** Analysis and recommendations only. No application code has been implemented or tested.

---

## 1. What is the feature?

Workflow Configuration is intended to let authorized users define rules that automatically assign claims to a specific employee or to an eligible role.

A workflow contains conditions, a target, an execution interval, and an enabled/disabled state. When the workflow runs, matching claims are evaluated and assigned according to the approved business rules. The goal is to reduce manual assignment work, make distribution more consistent, and make assignment decisions traceable.

## 2. Executive assessment

**The feature is feasible, but the requirements are not ready for implementation as-is.** The BRD, prototype, and current application do not fully agree. Some capabilities described as already available in the BRD were not found in the reviewed `main` branch, so the real implementation scope may be larger than expected.

The recommended approach is to confirm the business decisions first, then deliver the feature in controlled phases. Do not treat the prototype as the final specification until its differences from the BRD are resolved.

## 3. Most important findings

### A. The prototype conflicts with the BRD on assignment distribution

The BRD specifies assigning claims to the eligible employee with the **lowest current number of assigned claims**, recalculating after each assignment.

The prototype contains multiple, conflicting distribution approaches. Its simulation uses capacity utilization and can produce a different result from the BRD.

**Recommendation:** Confirm the BRD rule as the single source of truth, and update the prototype to match it before implementation.

### B. Several foundational capabilities were not found in the reviewed code

The reviewed `main` branch did not show a complete implementation of the following capabilities as described in the BRD:

- Employee and role assignment capacity limits.
- Explicit employee eligibility rules.
- A unified audit trail for workflow configuration and execution.
- Concurrency protection for competing assignment operations.

These may exist in another branch or system, but that must be verified. Otherwise, they need to be included in the actual scope and estimate.

### C. Capacity enforcement affects existing operations

The proposed capacity rule may affect more than automated workflows. It can also change manual assignments and imports. Enforcing it immediately could reject assignments that currently succeed; some import paths may log exceptions without clearly surfacing the failure.

**Recommendation:** Roll out capacity enforcement gradually: **off → warning/measurement → enforcement**, with explicit exceptions for approved legal transfers and other operations that must bypass the guard. Confirm the policy with Operations before enabling enforcement.

### D. First activation could affect existing claims

Without safeguards, activating a workflow could process a large existing backlog or repeatedly move claims between assignees.

**Recommendation:** Require a dry-run preview before the first live execution, establish a clear starting point for eligible claims, cap the number processed per run, and define a rule that prevents unnecessary reassignment when the current assignee already satisfies the workflow target.

### E. The BRD leaves business rules unresolved

The analysis identified decisions that could materially change the design and effort, including:

- Whether workflows process already-assigned claims.
- Whether the execution duration means a recurring schedule or the age of a claim.
- How Internal and External workflows determine eligible recipients.
- How employee-level and role-level capacities interact.
- What happens when multiple workflows match the same claim.
- Whether capacity applies to manual assignments and imports, and which exceptions are allowed.
- Which prototype-only options are genuinely in scope.

## 4. Recommended delivery plan

| Phase | Outcome | Key condition |
|---|---|---|
| **1. Business decisions** | Approve the assignment rule, capacity policy, eligibility, priorities, and execution behavior. | Required before building the execution engine. |
| **2. Configuration foundation** | Add workflow data structures, permissions, validation, and configuration APIs. | Build only against approved rules. |
| **3. Execution engine** | Add condition evaluation, eligible-recipient selection, safe assignment, scheduling, execution logs, and dry-run. | Business rules and concurrency strategy confirmed. |
| **4. Controlled rollout** | Measure capacity violations, review affected paths, then enable enforcement gradually. | Operations sign-off and regression/concurrency testing. |

This is a relative delivery sequence, not a time estimate. Effort should be estimated after the unresolved decisions and deployment assumptions are confirmed.

## 5. Decisions requested from management

Before committing to the execution phase, please confirm:

1. **Distribution rule:** Use the BRD rule—lowest current active claim count, recalculated after each assignment—as the authoritative rule.
2. **Capacity policy:** Apply both role-level and employee-level limits, and define whether/how they apply to manual assignments and imports.
3. **Eligible recipients:** Define what Internal/External means operationally and which user attributes determine eligibility.
4. **Reassignment behavior:** Define when already-assigned claims can be moved and how unnecessary repeated reassignment is prevented.
5. **Workflow priority:** Decide what happens when more than one workflow matches the same claim.
6. **Scope alignment:** Confirm which prototype-only features are included; keep features not defined in the BRD out of scope until formally approved.
7. **Initial rollout:** Approve dry-run, a controlled first run, and gradual capacity enforcement.

## 6. Main risks and controls

| Risk | Recommended control |
|---|---|
| Prototype behavior is implemented instead of the approved business rule. | Align prototype and BRD before development. |
| Existing claims are reassigned unexpectedly. | Dry-run, activation baseline, and per-run processing limit. |
| Capacity enforcement disrupts imports or operational transfers. | Gradual rollout, monitoring, and explicit exceptions. |
| Concurrent operations exceed capacity or create inconsistent assignments. | Database-backed concurrency control and dedicated tests. |
| Missing decisions increase scope or cause rework. | Complete the business-decision phase before the execution engine. |

## 7. Current status and limitations

This document summarizes a technical analysis of the BRD, prototype, and selected code on `main` at `0c1ed66`. It is **not evidence that the feature has been implemented or validated in production**. The application was not run and its tests were not executed as part of that analysis. Production data, real deployment/queue settings, frontend behavior, and other branches were not examined.

**Recommended next step:** Hold a short decision session with Business/Operations and the Backend/Architecture team, record the answers to the decisions above, and update the BRD/prototype before implementation estimates are finalized.
