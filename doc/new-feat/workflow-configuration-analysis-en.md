# Workflow Configuration and Claim Distribution — Feature Analysis

## Purpose

Explain how configurable workflows could distribute claims to users without breaking the existing assignment process, and answer three questions:

1. What does the current database/system contain, and which claim statuses should be eligible for distribution?
2. What database and application changes are needed, how should recipients be selected, and how should competing rules be prioritized?
3. How should existing data be preserved and handled during rollout?

> **Analysis boundary:** Parts of `Claim`, `Assigning`, `AssigningService`, `ClaimStatus`, and `Roles` were reviewed on `main`. This document is a proposal, not an implementation report. All assignment entry points, the active-claim definition, and final business rules must be confirmed before implementation. New table and column names below are proposed, not deployed migrations.

---

## 1. Current Database and System Analysis

### 1.1 What exists today?

- `claims` stores claims, and the `ClaimStatus` enum defines values used by the claim lifecycle.
- `assignings` stores assignment history. Current code references fields including `claim_id`, `from`, `to`, `supervisor_id`, `assign_date`, and `is_active`.
- `Claim::assignees()` exposes assignment records, while `Claim::assignee()` selects the latest active assignment where `is_active = 1`.
- The current `AssigningService` distributes a list of claims among selected recipients, deactivates previous assignments for selected claims, and inserts new assignment records. A bulk assignment path from Excel also exists.
- Users are related to roles through `users.role_id` and `role()`; users also have a `supervisor_id` relationship.
- The existing `Roles` enum includes roles such as `SUPERVISOR`, `COLLECTOR`, and `SENIOR_COLLECTOR`. Reuse the project's roles rather than creating a parallel role system.

**Conclusion:** The workflow should select eligible claims and recipients, then use the existing assignment path to persist the result. Do not create a second assignment table that competes with `assignings` as the source of truth.

### 1.2 Which claim statuses should be distributed?

The current `ClaimStatus` enum includes `registered`, `assigned`, `rejected`, `approved`, `follow_up`, `follow_up/delay`, `follow_up/partial`, `law_firm`, `default_in_payment`, `default_in_payment_by_system`, `closed`, `done`, and collection/action-related statuses.

Status alone is not enough. Eligibility must consider both the claim status **and** whether an active assignment exists in `assignings`.

| Current status | Proposed default for automatic distribution | Reason / condition |
|---|---|---|
| `registered` | **Initially eligible** | Include only when there is no active assignment and the claim passes tenant/client and workflow conditions. |
| `assigned` | **Excluded by default** | Do not reassign automatically unless an explicit reassignment rule is enabled. |
| `approved` | Requires business confirmation | The status name alone does not establish whether the claim should be distributed. |
| `follow_up`, `follow_up/delay`, `follow_up/partial` | Excluded by default | These appear to represent follow-up states; moving them needs an explicit reassignment rule. |
| `law_firm` | Excluded by default | This represents a legal path; include it only in a dedicated workflow if required. |
| `rejected`, `closed`, `done`, and collection/closure statuses | Excluded by default | Likely terminal or collection states; confirm exact business semantics before implementation. |
| `default_in_payment`, `default_in_payment_by_system` | Excluded from general distribution by default | Special follow-up states should only be included through explicit rules. |

**Recommended V1 scope:** claims with status `registered` and **no active assignment**. Make reassignment of already-assigned claims a separate, explicit option with clear conditions and an audit reason. Do not change `claims.status` as a side effect unless the existing assignment behavior and business rules explicitly require it.

The business owner must approve the final status list; enum names alone do not prove the complete lifecycle rules.

---

## 2. Changes Required for Workflow Configuration

### 2.1 Proposed database design

This is a logical starting design, not a description of the existing schema or a ready-to-run migration.

#### `workflows` — workflow definition

| Proposed field | Purpose |
|---|---|
| `id` | Primary key |
| `name`, `description` | Workflow name and description |
| `is_enabled` | Enable/disable execution |
| `priority` | Priority between workflows |
| `schedule` or run settings | Execution schedule/trigger settings |
| `created_by`, timestamps | Ownership and change tracking |

#### `workflow_groups` — condition and assignment groups

| Proposed field | Purpose |
|---|---|
| `id`, `workflow_id` | Group identity and parent workflow |
| `name` | Group name |
| `priority` | Priority when conditions overlap |
| `match_mode` | For example, `all` or `any` conditions |
| `assignment_mode` | For example, `role` or `specific_users` |
| `role_id` | Target role when assigning by role |
| `is_enabled` | Enable/disable the group |

#### `workflow_conditions` — claim selection rules

| Proposed field | Purpose |
|---|---|
| `id`, `workflow_group_id` | Condition identity and parent group |
| `field_key` | Field from an approved allowlist |
| `operator` | Approved comparison operator |
| `value` | Typed condition value |
| `sort_order` | Optional UI/evaluation ordering |

Never store or execute arbitrary SQL from user-configured conditions. Supported fields and operators must be defined by the application.

#### `workflow_group_users` — optional specific recipients

Maps a group to explicitly selected users when the assignment mode is user-based. An existing suitable relation can be reused if one is found during implementation.

#### `workflow_runs` — execution history

Initially records `id`, `workflow_id`, start/end times, run status, summary counts, and a run/idempotency key.

#### `workflow_execution_logs` — per-claim outcome

Initially records `workflow_run_id`, `claim_id`, matched group, candidate/selected user, result (`assigned`, `skipped`, `failed`, or `conflict`), and reason. Do not fabricate historical workflow runs for assignments made before launch.

#### `workflow_audits` — configuration audit

Tracks who changed workflow settings, what changed, and when.

**Core relationships:**
- One workflow → many groups.
- One group → many conditions.
- A group targets a role or selected users according to `assignment_mode`.
- One workflow → many runs.
- One run → many per-claim outcomes.
- Successful assignments continue to use `assignings` as the actual assignment record.

Do not add capacity tables until code inspection confirms there is no authoritative existing source for capacity limits or workload. Confirm column types, foreign keys, and indexes against the actual migrations before finalizing the schema.

### 2.2 How should user distribution work?

**Proposed flow:**

1. An administrator configures claim conditions and chooses whether recipients are selected by **Role** or from **specific Users**.
2. The administrator sets workflow priority and priority for each condition group.
3. On execution, the system selects eligible claims only and evaluates the configured groups.
4. If no group matches, record the claim as `skipped` with a reason.
5. If one group matches, select the recipient according to its assignment mode:
   - **Role-based:** find active, eligible users in the target role and correct scope, then apply the approved distribution policy and Capacity rules.
   - **Specific-user selection:** honor the administrator's explicit selection; do not block the assignment solely because workflow Capacity has been reached.
6. Immediately before persistence, revalidate claim/user eligibility and applicable Capacity; protect against duplicate and concurrent assignment.
7. Persist through the shared assignment path into `assignings` and record the execution outcome.

#### How should workload be balanced across users in a role?

This is a business decision. Options include:
- **Least-loaded:** select the user with the lowest active claim count or approved workload score.
- **Round-robin:** rotate between eligible users.
- **Capacity-aware:** exclude users at the configured limit, then select among the remaining eligible users.

The exact calculation cannot be settled until the business defines what an “active claim” means and whether workload is a raw count or weighted by claim value/type. Choose one clear V1 policy and record why each user was selected. Do not count every historical `assignings` row as current workload; define the workload based on the appropriate active records and approved rules.

### 2.3 Will this affect manual assignment?

There are two options. **Option B is recommended.**

- **Option A — fully separate:** all manual assignment remains outside workflow and Capacity logic; current behavior remains unchanged.
- **Option B — run distribution logic only when assigning by Role (recommended):**
  - When the administrator selects a Role and the system must choose recipients, apply eligibility, distribution, and Capacity rules.
  - When the administrator explicitly selects a user or users, honor that manual selection and do not block solely because workflow Capacity is exceeded.
  - In both modes, retain authorization, claim/user validation, concurrency safeguards, and assignment history.

Distinguish **recipient selection mode** from **operation source**. Pass trusted server-side context such as `assignment_mode = role | specific_users` and `source = workflow | manual | import | transfer`. Do not trust an unvalidated client-supplied source, and do not use a broad bypass that disables other validation.

This preserves manual flexibility while enabling calculated distribution when the system chooses recipients by role.

### 2.4 What if two condition groups match at the same time?

Priority must be controlled by the administrator, not inferred from query order or record insertion order.

**Proposal:**
1. Give each workflow a priority and each group a priority within its workflow.
2. If multiple groups match a claim, sort them by the agreed priority direction. Define clearly in the UI whether a smaller or larger number wins.
3. **Default: execute only one group per claim per run**, then log other matches as `not_selected_lower_priority`. This avoids competing assignments for the same claim.
4. If priorities tie and groups select different recipients, do not pick a winner arbitrarily. Either reject conflicting configuration at save time or record `conflict` and require the administrator to resolve the priority.
5. If multiple groups must intentionally run for the same claim in the future, implement that as an explicit separate capability—not default behavior.

Example: Group A has priority 1 and assigns to the collectors role; Group B has priority 2 and assigns to a specific user. If both match, A wins under the declared ordering and B is logged as not selected. If priorities tie, record a conflict rather than assigning the claim twice.

---

## 3. Handling Existing Data

1. **Audit before changes:** inspect claims with active assignments, duplicate/inconsistent assignment rows, deleted/inactive users, and all assignment-writing paths.
2. **Preserve history:** do not rewrite old `assignings` records just to introduce workflows. Do not rewrite `from`/`to` values or fabricate historical workflow runs.
3. **Use additive migrations:** add workflow tables, indexes, and permissions without destructive changes; keep automated execution disabled after the initial deployment.
4. **No automatic reassignment:** claims already assigned should not be redistributed at activation unless the business owner approves a specific reassignment scope.
5. **Dry-run first:** evaluate a bounded set without writing assignments; report included/excluded claims, proposed recipient, Capacity, winning priority, and reason.
6. **Define the starting scope:** decide whether the first run covers new claims only or a specifically bounded historical set. Never automatically process all old claims on activation.
7. **Roll out gradually:** enable one workflow over a small scope, review results and errors, and then expand.
8. **Delay risky constraints:** do not add a constraint that assumes one active assignment per claim until current data, business semantics, and every assignment writer have been checked.
9. **Safe rollback:** disabling workflows stops new automated assignments; it must not delete historical assignments or workflow audit logs.

---

## 4. Expected System Impact

| Area | Expected impact | Risk control |
|---|---|---|
| `claims` | Read status and fields used in conditions; no automatic status change unless explicitly approved | Allowlisted fields and approved status rules |
| `assignings` | Remains the assignment record; workflow creates new records through the shared path | Preserve history and revalidate before writes |
| Users and roles | Recipients selected by role or specific-user selection | Validate role, activity, scope, and permissions |
| Manual assignment | Could be blocked if Capacity is applied globally | Apply distribution/Capacity when selecting by Role, or explicitly exempt manual selection |
| Imports/transfers | Could change if forced through general Capacity rules | Define policy per source |
| Performance | Condition evaluation and workload calculation add database work | Aggregated queries, suitable indexes, avoid N+1 queries |
| Operations/support | Need to explain why a claim was assigned or skipped | Per-run and per-claim logs with reasons |
| Permissions | Workflow management and execution permissions are needed | Separate permissions and audit changes |

---

## 5. Decisions Required Before Implementation

- Approve eligible claim statuses and whether `approved` or follow-up states belong in any workflow.
- Define active assignment, workload calculation, and Capacity source.
- Choose the role-based distribution policy: least-loaded, round-robin, or another approved policy.
- Confirm that Role-based recipient selection triggers distribution/Capacity while specific-user manual selection is not blocked by Capacity.
- Define priority direction, tie handling, and the default of one winning group per claim per run.
- Decide how previously assigned claims, imports, transfers, and reassignment are handled.
- Define whether the first run covers only new claims or a bounded historical scope.

## Final Recommendation

Start with a limited V1: `registered` claims without an active assignment, role-based recipient selection, and one explicitly approved workload/Capacity policy. Do not block manual selection of specific users solely due to workflow Capacity. Give groups explicit priorities and prevent duplicate competing assignments. Preserve `assignings` as the assignment source of truth, and use dry-run plus gradual activation before production.
