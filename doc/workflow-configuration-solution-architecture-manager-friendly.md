# RCP Workflow Configuration — Solution Architecture (Clear Review Version)

**Document purpose:** Explain the proposed solution in clear business and technical language so Product, Operations, and Engineering can review it together.  
**Status:** Proposal for review — not yet an approved implementation specification  
**Repository:** `AhmedRamadan03/rcp`  
**Branch:** `docs/workflow-configuration-analysis`

> This is a clearer, manager-oriented companion to `workflow-configuration-solution-architecture.md`. It is a new file; the original architecture document is unchanged. Existing-code observations are distinguished from proposed changes. Final implementation must be checked against the current database migrations, assignment services, and approved business rules.

## 1. Executive Summary

RCP needs a configurable way to identify claims that meet business rules and assign them to the right employee—without requiring a code change every time an operational rule changes.

The recommendation is to add a **Workflow Configuration layer** on top of the existing RCP claim and assignment system.

An authorized administrator would define:
- When a workflow should be evaluated (for example, every hour).
- Which conditions a claim must satisfy (for example, the claim is at least 10 days old).
- Whether the claim should be assigned to a specific employee or to a role.
- Whether the workflow is enabled.

The system would evaluate those rules in background jobs. When the target is a role, it would select an eligible employee with the lowest current active claim workload, while respecting the agreed capacity limits.

**The key safeguard:** the new workflow feature should use RCP's existing assignment mechanism. It must not create a competing assignment system or silently redistribute claims that are already assigned.

### Recommendation at a glance

| Question | Recommendation |
|---|---|
| Build a new workflow layer? | Yes, add it without replacing the current assignment flow. |
| How are rules evaluated? | Scheduled background processing, in bounded batches. |
| How are multiple conditions in one group combined? | All conditions must match (AND). |
| How is a role recipient selected? | Lowest current active assigned-claim count among eligible employees, subject to capacity. |
| What happens to existing assignments? | Leave them unchanged by default. |
| How should the feature launch? | Dry-run first, then a controlled pilot, then gradual rollout. |
| Is every detail already decided? | No. The decisions in Section 8 require Product/Operations/Engineering agreement. |

## 2. The Problem We Are Solving

Today, operational rules such as “find claims that have been waiting for 10 days” or “find claims linked to overdue payments” should be configurable and repeatable.

The solution needs to answer four questions reliably:

1. **Which claims qualify?** Evaluate the configured conditions against current claim and payment data.
2. **Who should receive each claim?** Use the selected employee directly, or choose an eligible member of the selected role.
3. **Can that employee receive another claim?** Check the agreed individual and role capacity limits before assignment.
4. **How do we prove what happened?** Record each workflow run and each claim-level decision so that retries, failures, and support investigations are traceable.

The system must also handle changing claim data, employees becoming unavailable, repeated jobs, and two workers trying to assign claims at the same time.

## 3. What Exists Today and What We Propose to Add

The repository review identified existing RCP concepts that the feature should reuse:

| Existing RCP concept | How the proposal uses it |
|---|---|
| Claims and claim statuses | Source data for claim eligibility and conditions. |
| Assignment records (`assignings`) | Existing record of assignment changes; continue using this mechanism. |
| Users and roles | Resolve the current members of a target role and check employee eligibility. |
| Payment links and related payment data | Evaluate approved payment-due-date and payment-status conditions. |
| Claim status history | Support rules that depend on a claim entering a status, when required. |

The model review does not by itself confirm every database constraint or business rule. Before implementation, Engineering must verify the existing migrations, assignment services, capacity rules, authorization checks, and scheduler/queue configuration.

### New components proposed

- **Workflow configuration:** stores each workflow, its conditions, target, interval, and enabled state.
- **Workflow evaluator:** checks claims against the configured rules using an approved list of fields and operators.
- **Background scheduler and jobs:** run evaluations without blocking API requests.
- **Role load balancer:** chooses the eligible role member with the lowest current workload.
- **Execution and audit records:** record which workflow ran, which claims matched, which assignments succeeded, and why a claim was skipped or failed.

These are proposed components. Their final table and class names should follow RCP conventions.

## 4. How It Would Work — Step by Step

1. An authorized user creates a workflow and defines its conditions, execution interval, and assignment target.
2. The application validates the configuration. Unsupported fields, invalid operators, missing targets, and incomplete condition groups are rejected.
3. When the workflow is due, the scheduler queues a background job.
4. The evaluator searches for eligible claims in manageable batches rather than loading the entire portfolio into memory.
5. For each candidate claim, the system rechecks its latest status, payment state, current assignment, and recipient eligibility.
6. If the target is a specific employee, the system checks whether that employee is eligible and has capacity.
7. If the target is a role, the load balancer selects the eligible role member with the lowest current active assigned-claim count, respecting capacity.
8. The system records the assignment through RCP's existing assignment mechanism and writes an audit result.
9. The run finishes with counts for matched, assigned, skipped, and failed claims. Safe failures can be retried without creating duplicate assignments.

### Example workflow

A business user configures this rule:

- **Condition:** claim age is at least 10 days.
- **Assignment target:** the Collections role.
- **Execution interval:** every hour.
- **Status:** enabled.

Every hour, RCP evaluates eligible claims against the rule. A claim that matches is assigned to an eligible Collections employee using the workload-balancing policy. A claim that no longer qualifies, is already assigned when the workflow only permits unassigned claims, or cannot be assigned because of capacity is skipped with a recorded reason.

**Important distinction:** “run every hour” is the workflow's evaluation schedule. “Claim is at least 10 days old” is a condition. These are different settings and must not be confused.

## 5. How Workload Balancing Should Behave

For role-based assignment, the recommended V1 rule is:

> Assign the next claim to the eligible employee who currently has the fewest active assigned claims, provided that the assignment does not exceed the agreed capacity limits.

Example, assuming both employees are eligible and have enough capacity:

| Employee | Current active claims | After one new assignment |
|---|---:|---:|
| Employee A | 1 | 2 |
| Employee B | 5 | 5 |

The next claim would still go to Employee A because their workload would then be 2 versus 5. The system repeats the selection for each claim, recalculating workload after successful assignments. It does not send every claim to the same person just because that person started with the smallest count.

If the starting counts are 1 and 5 and 20 claims are assigned, simple least-count balancing would result in approximately 13 claims per employee, assuming no capacity or eligibility changes. This is a useful test case, not a guarantee when capacity limits or other rules apply.

### Eligibility and capacity safeguards

- Resolve role members dynamically; do not rely on an old list of role members.
- Exclude inactive, deleted, or otherwise ineligible employees according to existing RCP rules.
- Count current active assignments, not all historical assignment rows.
- Check individual and role capacity before writing the assignment.
- If no employee is eligible or capacity is exhausted, do not force the assignment to an unrelated employee. Record the reason and leave the claim unchanged.
- Confirm the exact meaning of “active workload” and the source of capacity limits before implementation.

### Why concurrency matters

Two background workers could otherwise read the same workload counts and both select the same employee. The implementation must use database transactions and suitable locking/rechecks so that concurrent jobs cannot bypass capacity or create duplicate active assignments. The exact locking strategy depends on the real schema and database behavior and must be tested.

## 6. Proposed Data and Audit Records

The feature needs to store configuration separately from execution history.

| Proposed record | Purpose |
|---|---|
| `workflows` | Workflow name, type, interval, enabled state, and configuration version. |
| `workflow_condition_groups` | Each group of conditions and its assignment target. |
| `workflow_conditions` | Individual field/operator/value conditions within a group. |
| `workflow_executions` | Summary of each run: start/end, status, and result counts. |
| `workflow_claim_executions` | Per-claim decision, selected employee, outcome, and reason. |

These are proposed logical table names, not existing tables confirmed in the current system. Final schema details, foreign keys, indexes, and uniqueness constraints must be designed after reviewing the migrations and expected data volume.

### Configuration rules

- All conditions inside a group use **AND**: every condition must be true.
- The application must allow only approved fields and operators. Administrators must not be able to enter arbitrary SQL or executable code.
- The workflow stores a version so an audit record can identify which configuration produced a decision.
- Retries must be idempotent: repeating a job must not create a duplicate assignment.
- If multiple groups match the same claim, the business must decide whether the first matching group wins or multiple actions are allowed. Recommended default for V1: explicit priority and first-match-wins, pending approval.

## 7. Existing Data and Safe Rollout

The new feature should not treat every historical claim as a new assignment.

### Rollout plan

**Phase 1 — Verify the current system**
- Confirm how RCP currently records assignments.
- Agree on workload counting and employee/role capacity.
- Baseline current assignments, claim statuses, role membership, and performance.

**Phase 2 — Add the new configuration and audit tables**
- Use additive migrations.
- Do not rewrite or delete existing assignment records.
- Keep all new workflows disabled by default.

**Phase 3 — Dry-run**
- Evaluate claims without changing assignments.
- Report which claims would match, who would be selected, and why other claims would be skipped.
- Compare results with Operations' expectations and existing reports.

**Phase 4 — Limited pilot**
- Enable one approved workflow for a limited cohort.
- By default, assign only unassigned eligible claims. Reassignment of existing claims requires an explicit business decision.
- Monitor distribution, capacity, failures, and job duration.

**Phase 5 — Gradual rollout**
- Expand only after the pilot meets agreed acceptance criteria.
- Keep a documented disable/rollback procedure.

**Historical backfill or portfolio rebalancing is separate work.** If historical audit data is required, add honest audit metadata; do not fabricate assignment events. If Operations wants existing claims redistributed, require a separate preview, approval, exception-handling, and rollback plan.

## 8. Decisions Required Before Implementation

The architecture can guide detailed design, but the following business decisions must be confirmed first.

1. **Execution interval:** Does the configured duration mean how often a workflow runs? The proposal assumes yes; claim age remains a separate condition.
2. **Workload definition:** Which assignment records and claim statuses count as an employee's active workload?
3. **Capacity:** Where are employee and role limits defined today? What happens if an individual limit and a role limit conflict?
4. **Multiple matching groups:** Should the first/highest-priority group win, or can more than one action run for the same claim?
5. **Existing assignments:** Should V1 assign only unassigned claims? Recommended default: yes.
6. **Internal vs. External:** What operational behavior, data eligibility, and permissions distinguish the two workflow types?
7. **Condition fields:** Which claim and payment fields/operators are approved for the first release?
8. **Equal workloads:** What tie-breaker should be used when eligible employees have the same count?
9. **Failures:** When should a failed/skipped claim be reconsidered—on the next run, after a delay, or only after manual correction?
10. **Scale:** What are the expected claim volume, workflow frequency, and acceptable execution duration?

## 9. Main Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Incorrect or ambiguous business rules | Approve condition meanings, workload counting, capacity, and group priority before implementation. |
| Existing claims are unintentionally reassigned | Leave workflows disabled initially; dry-run first; default to unassigned claims only. |
| Two workers exceed capacity or select the same employee | Transactional rechecks, suitable locks, idempotency, and concurrency tests. |
| A retry creates duplicate assignments | Durable execution records and idempotent assignment orchestration. |
| A user configures an unsafe condition | Server-side allowlist of fields/operators and strict validation. |
| Evaluation becomes slow on a large portfolio | Chunked processing, measured indexes/query plans, and bounded queue jobs. |
| Audit history cannot explain an outcome | Store workflow version, claim-level result, selected user, and reason code. |
| The new path behaves differently from current RCP assignment | Reuse the existing assignment domain and run regression tests against current assignment APIs. |

## 10. Acceptance Criteria

The first release should not be considered ready until:

- Authorized users can create, edit, enable, and disable workflows.
- Invalid configuration is rejected before execution.
- Every condition in a group must match.
- Role assignment follows the agreed least-workload rule and enforces capacity.
- A claim is rechecked immediately before assignment.
- Concurrent/retried jobs do not exceed capacity or create duplicate assignments.
- Skipped and failed claims have clear reason codes.
- Creating or editing a workflow does not silently reassign existing claims.
- Existing claim, assignment, permission, and audit behavior still passes regression tests.
- Dry-run results are accepted by Operations before live assignment is enabled.

## 11. Delivery Plan

| Stage | Deliverable | Approval gate |
|---|---|---|
| 1. Discovery | Verify current assignment implementation, workload definition, capacity, and business decisions | Product + Operations + Engineering |
| 2. Foundation | Configuration schema, validation, authorization, CRUD, and versioning | Code review and tests |
| 3. Evaluation | Scheduler, condition registry, execution records, and dry-run reporting | Operations accepts sample results |
| 4. Assignment | Existing assignment integration, balancing, capacity enforcement, and concurrency protection | Capacity and concurrency tests pass |
| 5. Pilot | Limited workflow activation and monitoring | Operational sign-off |
| 6. Rollout | Gradual activation, runbook, and rollback procedure | Stable pilot metrics |

## 12. Final Recommendation

Proceed with detailed implementation planning for an additive workflow layer that reuses RCP's existing assignment system. Confirm the unresolved business rules, verify the current migrations and assignment services, and prove the results in dry-run mode before enabling live assignments.

**Approval requested:** approve the proposed direction and the discovery phase—not unrestricted production activation or historical reassignment. Historical portfolio rebalancing should remain outside V1 unless separately approved.
