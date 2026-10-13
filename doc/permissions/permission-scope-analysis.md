# Permission Scope — Analysis & Implementation Design

## 1. Purpose

Add **Scope** to the General Permissions system so a permission is not limited to:

- Does the user have the permission? `granted`
- Does the action require approval? `with_approve`

It also defines:

- What data visibility range the user has access to? `scope`

Core principle:

```text
granted       = Authorization
scope         = Data Visibility
with_approve  = Approval Workflow
```

Example:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

---


> **Status of this document (reviewed against `main`):** Sections 1–22 describe the general
> scope design. They were reviewed against the real code and **Section 23 onward** corrects
> a few assumptions and adds the **custom scope (roles / specific users + own data)** extension,
> designed for the fewest code changes and the best performance. If a statement in
> Sections 1–22 conflicts with Sections 23+, Sections 23+ win.

---

## 2. Proposed Permission Definition in the Seeder

The key question is whether the permission can be defined this way.

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team']
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

### Answer

**Yes. This is the intended design, with the important note that the current code must update how `permission_mode` is read.**

In this design, `permission_mode` is not just a list of mode names; it becomes a **configuration map**:

```php
[
    'scope' => ['onlyme', 'all', 'team'],
]
```

This means:

- `scope` = the mode supported by the permission.
- `['onlyme', 'all', 'team']` = the allowed values for that mode.
- When the permission is assigned to a user, exactly **one Scope value** is selected.

Example:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team'],
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

Then the User Permission becomes:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

This means GeneralPermission defines the **available options**, while User Permission stores the **actual selected value**.

---

# 3. Recommended Design

The target `permission_mode` structure is a configuration map.

A permission that supports Scope:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team'],
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

If a permission supports both Scope and Approval:

```php
[
    'permission' => 'some_claim_action',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team'],
        'with_approve' => true,
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

The selected scope value is then stored in the User Permission:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

This separates:

```text
GeneralPermission.permission_mode
        ↓
What capabilities does this permission support?

User Permission JSON
        ↓
What value was assigned to this user?
```

---

# 4. Why Put Allowed Values Inside permission_mode?

because the purpose here is for the GeneralPermission definition to be the **source of truth for the allowed options**.

```php
'permission_mode' => [
    'scope' => ['onlyme', 'all', 'team'],
]
```

This means:

```text
scope
    ├── onlyme
    ├── all
    └── team
```

User Permission stores only one selected value:

```json
"scope": "team"
```

Therefore:

```text
GeneralPermission
    → What values are allowed?

User Permission
    → What value was selected for this user?
```

This also makes the permission definition the source for validation instead of duplicating the scope list across multiple places.

Example:

```text
General Permission:
show_claim
mode:
    scope

User A:
show_claim:
    granted = 1
    scope = onlyme

User B:
show_claim:
    granted = 1
    scope = team

User C:
show_claim:
    granted = 1
    scope = all
```

This allows the same permission to be assigned with different scopes to different users.

---

# 5. Allowed Scope Values

The proposed core values are:

```text
all
onlyme
team
```

## all

The user can access all records allowed by the permission.

Example:

```text
show_claim + scope=all
→ all claims
```

## onlyme

The user can access only records directly associated with them.

Example:

```text
show_claim + scope=onlyme
→ claims assigned/owned by current user
```

**Important:** The definition of "own" must be based on the official relationship in RCP, not on the assumption that any user_id represents the claim owner.

## team

The user can access their own records and records belonging to members of their team.

Before implementation, the exact meaning of `team` must be established from the current domain.

Do not assume that team means supervisor hierarchy until the actual relationships are reviewed:

- User
- Claim
- assignee/collector
- supervisor/team relationships

---

# 6. Expected User Permission JSON

Example: `show_claim`

### All

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "all"
  }
}
```

### Only Me

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "onlyme"
  }
}
```

### Team

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

If the permission also supports approval:

```json
{
  "some_action": {
    "granted": 1,
    "scope": "team",
    "with_approve": 1
  }
}
```

---

# 7. Core Design Rule

Do not mix:

```text
Permission capability
Permission assignment
Data filtering
Approval workflow
```

The model is:

```text
GeneralPermission
    └── permission_mode
            ├── scope
            └── with_approve

User Permission
    └── permission
            ├── granted
            ├── scope
            └── with_approve
```

---

# 8. How to Introduce the Feature Without Breaking Changes

The rollout should be incremental.

## Phase 1 — Metadata

Add `scope` to `permission_mode` only for permissions that require data visibility.

Example:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => ['scope'],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

Do not change the remaining permissions.

---

## Phase 2 — Scope Constants / Enum

Do not spread strings such as:

```text
all
onlyme
team
```

throughout the project.

Prefer a centralized Enum or Constants:

```php
enum PermissionScope: string
{
    case ALL = 'all';
    case ONLY_ME = 'onlyme';
    case TEAM = 'team';
}
```

This keeps validation consistent.

---

## Phase 3 — Permission Resolver

Create a centralized helper/service responsible for resolving permission configuration.

Conceptual example:

```php
$permission = permission_value(
    'claims',
    'show_claim',
    auth()->user()
);

$scope = $permission['scope'] ?? null;
```

Prefer a clear resolver such as:

```text
PermissionResolver
    ├── granted()
    ├── scope()
    └── requiresApproval()
```

instead of reading permission JSON directly in Controllers.

---

# 9. Applying Scope to Queries

Scope should not be implemented directly in Controllers.

Instead of:

```php
if ($scope === 'onlyme') {
    ...
} elseif ($scope === 'team') {
    ...
}
```

Instead, use a centralized Query/Service layer responsible for applying visibility.

Conceptual example:

```php
$query = Claim::query();

$query = $claimVisibility->apply(
    $query,
    auth()->user(),
    'show_claim'
);
```

The service decides:

```text
all
    → no additional ownership restriction

onlyme
    → filter by current user's ownership/assignment

team
    → filter by users in current user's team
```

---

# 10. Do Not Rely on Policies Alone

A Policy can verify:

```text
Can user access this specific claim?
```

However, list/index endpoints need query-level filtering so unauthorized records are never loaded in the first place.

Rule:

```text
Index/List
    → Query-level filtering

Show single record
    → Query filtering + Policy/authorization

Action
    → Permission + Policy + business rules

Approval
    → existing approval workflow
```

---

# 11. Protecting the Show Endpoint

If the user has:

```text
show_claim = granted
scope = onlyme
```

It is not enough to check:

```php
granted === 1
```

and then doing:

```php
Claim::findOrFail($id)
```

Otherwise, the user could access a claim outside their scope.

The access flow should be:

```text
Find claim
    ↓
Check permission
    ↓
Resolve scope
    ↓
Check claim belongs to allowed scope
    ↓
Return claim
```

Preferably, enforce this through a constrained query from the beginning.

---

# 12. Handling Existing Users

This point is critical to avoid breaking existing behavior.

Existing permissions do not contain `scope`.

Therefore, default behavior must be explicitly defined.

The safest rollout rule is:

```text
permission does not support scope
    → current behavior remains unchanged
```

For a permission that supports scope, such as `show_claim`, an explicit default should be defined during migration/seeding.

Do not use:

```scope = null
```

and assign different meanings to it across the system.

The default should be explicit and documented.

---

# 13. Admin Behavior

Currently, Admin is granted:

```json
{
  "granted": 1
}
```

The seeder adds:

```json
"with_approve": 0
```

for permissions that support approval.

When Scope is introduced, the Admin scope should be explicit.

For example:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "all"
  }
}
```

if the business rule is that Super Admin can see everything.

Do not leave scope undefined and rely on an implicit fallback.

---

# 14. Validation

When creating or updating a User Permission, reject:

```text
scope = anything_else
```

Such as:

```text
scope = manager
scope = branch
scope = random
```

The allowed values are:

```text
all
onlyme
team
```

Also verify that the permission itself supports Scope before accepting a scope value.

For example:

```text
show_claim
    supports scope
    ✓ scope=team

create_claim
    does not support scope
    ✗ scope=team
```

---

# 15. Backward Compatibility

The change must not alter:

- existing permissions
- approval workflow
- Partner API authorization behavior
- existing role checks
- business status rules

except for permissions where `scope` is explicitly enabled.

This enables a gradual rollout:

```text
Phase 1
    Add scope metadata

Phase 2
    Add resolver

Phase 3
    Add visibility filtering

Phase 4
    Migrate selected permissions

Phase 5
    Add tests

Phase 6
    Enable UI configuration
```

---

# 16. Testing Strategy

Every scope must be tested at both query and endpoint level.

## show_claim

### all

The user can see:

```text
claim A
claim B
claim C
...
```

### onlyme

The user can see:

```text
claims assigned to user
```

The user cannot see:

```text
claims assigned to another user
```

### team

The user can see:

```text
own claims
+
team members' claims
```

The user cannot see:

```text
claims outside the team
```

---

# 17. Security Rules

Scope must be treated as part of the authorization boundary, not merely a UI filter.

Hiding records in the frontend is not sufficient.

Enforcement must happen in the backend.

That is:

```text
Frontend filter = UX
Backend scope enforcement = Security
```

---

# 18. Recommended Final Data Model

## GeneralPermission

```json
{
  "permission": "show_claim",
  "permission_mode": {
    "scope": ["onlyme", "all", "team"]
  },
  "type": "public",
  "path": "claims"
}
```

## User Permission

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

## Permission with approval + scope

```json
{
  "some_action": {
    "granted": 1,
    "scope": "team",
    "with_approve": 1
  }
}
```

---

# 19. Recommended Implementation Order in RCP

Do not modify all Policies and Controllers at once.

Recommended order:

1. Define `PermissionScope` enum.
2. Update `permission_mode` to the configuration-map structure.
3. Add `scope` support only to selected permissions.
4. Extend permission assignment/resolution to support `scope`.
5. Define the exact meaning of `onlyme`.
6. Define the exact team relation for `team`.
7. Build centralized Claim visibility/query logic.
8. Apply it to claims index/list.
9. Apply the same rule to single-claim show.
10. Add Policy consistency checks.
11. Add feature tests for all scopes.
12. Only then expose scope selection in the permissions UI.

---

# 20. Critical Decision Before Coding

Before implementation, the project must answer one domain question:

### What exactly is "team"?

Possible interpretations:

- claims assigned to users who report to the current supervisor;
- claims assigned to members of an explicit team relation;
- claims belonging to the same operational group;
- another existing RCP relationship.

This must be taken from the actual User/Claim relationships in the code.

**Do not implement `team` based on an assumption.**

---

# 21. Final Recommendation

The final recommended design is:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team'],
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

For a permission supporting Scope + Approval:

```php
[
    'permission' => 'some_claim_action',
    'permission_mode' => [
        'scope' => ['onlyme', 'all', 'team'],
        'with_approve' => true,
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

The User Permission stores the selected value:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

### Why is this the right structure?

GeneralPermission becomes responsible for defining:

- The permission itself.
- The modes it supports.
- The allowed values for each mode.

User Permission becomes responsible for:

- Whether the permission is granted
- Which Scope was selected
- Whether the action requires approval

### Important implementation note

The current codebase contains permissions using the legacy `permission_mode` format, such as:

```php
['with_approve']
```

Therefore, the schema must not be changed without updating the existing consumers.

First, add a centralized **Normalization/Resolver** that understands both the legacy and new structures, then update the seeder and permission resolution incrementally.

This preserves backward compatibility and prevents different parts of the system from interpreting `permission_mode` differently.

---

# 22. Target Architecture


```text
                    GeneralPermission
                           |
                           v
                 permission_mode
                  /              \
              scope          with_approve
                |                  |
                v                  v
        PermissionScope       Approval Flow
        all/onlyme/team
                |
                v
        User Permission JSON
                |
                v
        Permission Resolver
                |
                v
       Claim Visibility Layer
                |
        +-------+-------+
        |       |       |
       all   onlyme   team
        |       |       |
        +-------+-------+
                |
                v
        Controller / Policy
```

This architecture keeps authorization, data visibility, and approval as separate concerns and allows the feature to be introduced incrementally without changing unrelated permissions.

---

# 23. Review Findings (document vs. actual code on `main`)

The branch `review/general-permission-policy-flow` contains only this document, so the review
compares the proposed design with the real code.

| # | Finding | Evidence | Consequence |
|---|---------|----------|-------------|
| 1 | The data-visibility switch is **`show_all_claims`**, not `show_claim`. `show_claim` only gates *endpoint access* (`ClaimPolicy` ~line 54). | `Claim::scopeVisibleTo` reads `claims->show_all_claims`. | Examples in Sections 1–22 that put `scope` on `show_claim` should be read as "the claims visibility rule". |
| 2 | **"What is `team`?" (Section 20) is already answered by the code**: the user + direct subordinates (`users.supervisor_id`, one level) + claims of users who delegated to them (active `delegations` window) + claims they created that have no active assignee. | `scopeVisibleTo`, `User::subordinates()`, `User::delegationsFrom()`. | Current default behavior == `team`. No new definition needed. |
| 3 | **`onlyme` does not exist today.** A non-`show_all_claims` user always sees subordinates too. | Same method. | `onlyme` would be a *behavior change* for supervisors; keep it as an optional later phase. |
| 4 | **Single choke point.** All claim visibility goes through `Claim::scopeVisibleTo` (list in `ClaimService`, `RiskController`, `ClaimInfoController` assign check, `AuthorizeFileAccess`, `can_user_access_claim()` used by `ClaimPolicy` for show/update/delete/files). | `grep visibleTo` / `can_user_access_claim`. | The feature can be implemented in **one method**; every consumer inherits it. |
| 5 | **Three places assume `permission_mode` is a list of flag names** and will break (type error / wrong behavior) if it becomes a map: `PermissionService::create()` (`foreach ($allowed_modes as $mode) $entry[$mode]`), `GeneralPermissionSeeder` admin loop (`in_array('with_approve', $modes)`), and the `GET permissions` response consumed by the frontend (`PermissionController`, returns `permission_mode` raw). | Code above. | Section 21's "normalizer first" rule is mandatory, not optional. |
| 6 | **Values are forced to integers.** `PermissionService::create()` does `(int) $value[$mode]` and `get_permission_value()` does `(int) $v` for every key. `"team"` or `[3,5]` would silently become `0` / `1`. | Code above. | A non-numeric `scope` and the id lists need explicit handling in both. |
| 7 | **`wrap_permissions()` flattens `{"granted":1,...}` to `1`** for legacy readers (`safe_json_decode(...)->show_claim == 1`). | `app/helpers.php`. | Good: extra keys (`scope`, `scope_roles`, `scope_users`) do **not** break the ~40 legacy readers. But it also means `scopeVisibleTo` must read the **raw** JSON, not the wrapped object, to see the extra keys. |
| 8 | `GeneralPermissionSeeder::run()` does `GeneralPermission::truncate()` and re-creates on every deploy. | Seeder. | Catalog changes ship safely with the deploy; **user `Permission` rows are not touched**, so old users have no `scope` key → must default to current behavior (Section 12). |
| 9 | Admin seeder gives `granted = 1` to everything (and `with_approve = 0`). | Seeder. | Admin keeps working because `show_all_claims` already returns all claims; no `scope` is needed for Admin if `all` stays mapped to `show_all_claims` (see 25.3). |
| 10 | Section 8 Phase 3 proposes a new `permission_value()` helper. The codebase already has `get_permission_value()` (path, key, user). | `app/helpers.php`. | Extend the existing helper instead of adding a parallel one. |

---

# 24. The Feature: Custom Visibility Scope (Roles / Users + My Own Data)

## 24.1 Requirement

For the claims-visibility permission, an admin can grant a user:

- all claims (`all`), or
- the current default (`team`), or
- **`custom`**: *own data* **plus** the data of a chosen set of **roles** and/or a chosen set of **specific users**.

Example: collector X sees his own claims + every claim assigned to anyone with role *Collector Supervisor* + claims assigned to users 12 and 15.

## 24.2 Core idea (why this is cheap and fast)

Today `scopeVisibleTo` already reduces visibility to **one list of user ids** (`$assigneeIds` = user + subordinates) and uses it in `assignings.to IN (...) AND is_active = 1` (and in the delegation clause).

So a custom scope is **not a new query shape**. It only means *"add more ids to `$assigneeIds`"*:

```text
assigneeIds = [ me ] + [ my subordinates ]            -- current behavior (team)
            + [ users listed in scope_users ]          -- NEW
            + [ users whose role_id in scope_roles ]   -- NEW
```

Everything after that line (the three EXISTS branches, delegation, created-by rule, indexes) stays **unchanged**.

## 24.3 Stored shape (User Permission JSON, `claims` column)

```json
{
  "show_all_claims": { "granted": 0 },
  "show_claim": {
    "granted": 1,
    "scope": "custom",
    "scope_roles": [3, 5],
    "scope_users": [12, 15]
  }
}
```

Rules:

- No `scope` key (every existing user) → `team` (current behavior). **Zero migration of user rows.**
- `show_all_claims.granted = 1` still wins and returns all claims (checked first, as today).
- `scope_roles` / `scope_users` are read **only when** `scope = custom`; ignored otherwise.
- `custom` is **additive** to the current baseline (own + subordinates + delegation + created-by). It never removes anything the user sees today, so it cannot cause a regression for existing flows.

> Where to hang it: the key can live on `show_claim` (as the doc proposes) or on `show_all_claims`. Keep it on **`show_claim`** so that `show_all_claims` keeps its boolean meaning. `show_claim` is currently flat (`{"granted":1}`), so adding keys to it does not affect legacy readers (finding 7).

## 24.4 Catalog (seeder) definition

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['team', 'custom'],   // 'all' stays driven by show_all_claims; 'onlyme' optional later
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

`permission_mode` is now a map (as proposed in Sections 2 and 21). Legacy entries stay as lists (`['with_approve']`) and are handled by the normalizer in 25.1.

---

# 25. Minimal Code Changes (exactly what is touched)

Total: **1 enum, 1 normalizer, 4 small edits, 1 seeder line, tests.** No migration for user data, no new tables, no new endpoints.

## 25.1 `PermissionModes` normalizer (new, ~25 lines)

One place that understands both formats so no consumer interprets `permission_mode` differently:

```php
final class PermissionModes
{
    /** @return array{flags: string[], scope: string[]} */
    public static function normalize(array|string|null $mode): array
    {
        if (is_string($mode)) { $mode = json_decode($mode, true); }
        $mode = $mode ?? [];

        // legacy list: ['with_approve']
        if (array_is_list($mode)) {
            return ['flags' => $mode, 'scope' => []];
        }

        // map: ['scope' => [...], 'with_approve' => true]
        $scope = $mode['scope'] ?? [];
        unset($mode['scope']);
        return ['flags' => array_keys(array_filter($mode)), 'scope' => $scope];
    }
}
```

## 25.2 `PermissionScope` enum (new)

```php
enum PermissionScope: string
{
    case TEAM   = 'team';    // current default
    case CUSTOM = 'custom';  // team + roles/users
    // case ONLY_ME = 'onlyme';  // optional, later (changes behavior for supervisors)
}
```

`all` is intentionally **not** a stored value: it stays `show_all_claims`, so there is one source of truth for "see everything".

## 25.3 `Claim::scopeVisibleTo` — the only visibility edit

Read the raw JSON (the wrapped object loses extra keys — finding 7) and extend `$assigneeIds`:

```php
// BEFORE: $claimsData = safe_json_decode($user->permission()->first()?->claims);
$rawClaims   = $user->permission()->first()?->claims ?? [];        // already json-cast array
$claimsData  = safe_json_decode($rawClaims);                        // same call as before
$allShow     = custom_policy($user, ($claimsData->show_all_claims ?? 0) == 1);
if ($allShow) { return $query; }                                    // unchanged

$subordinateIds = $user->subordinates()->pluck('id')->toArray();    // unchanged
$assigneeIds    = [...$subordinateIds, $user->id];                  // unchanged

// NEW (only when explicitly configured)
$assigneeIds = array_values(array_unique([
    ...$assigneeIds,
    ...self::customScopeUserIds($user, $rawClaims['show_claim'] ?? []),
]));
// ...everything below stays exactly as it is
```

```php
protected static function customScopeUserIds(User $user, array $perm): array
{
    if (($perm['scope'] ?? null) !== PermissionScope::CUSTOM->value) {
        return [];                                   // default path: no extra query at all
    }

    $userIds = array_map('intval', $perm['scope_users'] ?? []);
    $roleIds = array_map('intval', $perm['scope_roles'] ?? []);

    $fromRoles = $roleIds
        ? User::query()->whereIn('role_id', $roleIds)->pluck('id')->all()   // 1 query, soft-deleted excluded
        : [];

    return [...$userIds, ...$fromRoles];
}
```

Behavior notes:

- **Default users pay nothing**: the `scope !== custom` early return means no extra query and identical SQL to today.
- Custom users pay **at most one** indexed query (`users.role_id IN (...)`), and only if roles are configured.
- Because the same `$assigneeIds` feeds the delegation branch, "claims of someone who delegated to a person in my scope" works consistently with `team`.

## 25.4 `PermissionService::create()` — keep the string/array values

Replace the `(int)` copy loop with the normalizer, and add the custom keys only for permissions whose catalog declares `scope`:

```php
$modes = PermissionModes::normalize($permission['permission_mode'] ?? null);

if (is_array($value)) {
    $entry = ['granted' => (int) ($value['granted'] ?? 0)];

    foreach ($modes['flags'] as $flag) {                // with_approve etc. — same as before
        $entry[$flag] = (int) ($value[$flag] ?? 0);
    }

    if ($modes['scope']) {                              // permission supports scope
        $scope = $value['scope'] ?? 'team';
        $entry['scope'] = in_array($scope, $modes['scope'], true) ? $scope : 'team';

        if ($entry['scope'] === 'custom') {
            $entry['scope_roles'] = self::ids($value['scope_roles'] ?? []);   // int[], unique, capped
            $entry['scope_users'] = self::ids($value['scope_users'] ?? []);
        }
    }
    $permissions[$category_key][$key] = $entry;
}
```

The "not present in request" branch (`granted = 0`) uses the same `flags` list only. Unknown `scope` falls back to `team` (the safe, current behavior) instead of raising on old UI payloads.

## 25.5 `PermissionUpdateRequest` — validate ids

Add validation in `withValidator` (existing hook), only for entries that carry `scope`:

```php
'scope'        → in:team,custom
'scope_roles'  → array, max:50, each integer exists:roles,id
'scope_users'  → array, max:200, each integer exists:users,id
```

Reject `scope_roles` that contain Super Admin if the business does not want a user to see all admin-owned claims (decision in Section 28).

## 25.6 `get_permission_value()` — do not cast non-scalars

```php
foreach ($val as $key => $v) {
    $result[$key] = is_array($v) || (is_string($v) && !is_numeric($v)) ? $v : (int) $v;
}
```

Without this, `scope = "custom"` becomes `0` and the id arrays become `1` for any code that calls `get_permission_value('claims','show_claim',$user)`.

## 25.7 `GeneralPermissionSeeder` admin loop

```php
$modes = PermissionModes::normalize($perm->permission_mode);
if (in_array('with_approve', $modes['flags'], true)) { $value['with_approve'] = 0; }
```

Admin gets no `scope` key (stays `team` by default; Admin sees everything anyway through `show_all_claims`).

## 25.8 `PermissionController::index` (response to frontend)

Return the **normalized** structure only for permissions that use the map form, and keep legacy lists untouched, so the existing UI is not broken:

```json
{ "permission": "show_claim", "path": "claims", "permission_mode": { "scope": ["team", "custom"] } }
```

The UI must treat `permission_mode` as `list | map`. A UI that does not know `scope` simply never sends it → the user stays on `team`.

## 25.9 What is intentionally NOT changed

- `ClaimPolicy`, `AuthorizeFileAccess`, `can_user_access_claim`, `ClaimService`, `RiskController`, `ClaimInfoController`: they all call `visibleTo` and inherit the feature.
- `show_all_claims`, delegation logic, created-by rule, approval workflow, Partner API auth.
- `wrap_permissions()` and the ~40 legacy `->xxx == 1` readers.
- User `permissions` rows (no data migration).

---

# 26. Performance Analysis

## 26.1 Queries per `visibleTo` call

| User type | Extra queries vs. today | SQL shape change |
|-----------|-------------------------|------------------|
| `show_all_claims = 1` (admin, managers) | 0 | none (returns before building the clause) |
| Default / `team` (all existing users) | 0 | none — identical SQL |
| `custom`, users only | 0 | longer `IN (...)` list |
| `custom`, with roles | +1 (`select id from users where role_id in (...)`) | longer `IN (...)` list |

## 26.2 Why the SQL stays fast

- Filtering uses `assignings (to, is_active)` — the composite index added in migration `2026_10_04_140005` — plus `assignings (claim_id)` from `2026_10_04_101414`. A larger `IN` list on the leading column keeps using the index (bitmap/index scan); sizes in the tens or low hundreds are not a concern.
- No per-row work, no N+1: the extension is resolved **once per query**, not per claim.
- No `JOIN`/`GROUP BY` is added; the result set is still a filtered `claims` query, so pagination/count behave as before.

## 26.3 Optional optimizations (add only if measured)

1. **Request-level memoization** of `customScopeUserIds()` (static array keyed by user id). `visibleTo` can run several times in one request (list + file access + policy), so this avoids repeating the role query.
2. **Short cache** (60–300 s) of the resolved id list, keyed by `user_id + permission.updated_at`. Trade-off: a user who changes role may stay visible for the TTL. Prefer memoization alone first; use the cache only for very large roles.
3. **Index** on `users(role_id)` (Postgres does not index a foreign key automatically; the `users` table is small, so only if the plan shows a seq scan on a large table). If added, follow the existing `CREATE INDEX CONCURRENTLY IF NOT EXISTS` pattern.
4. **Large role guard**: if a role resolves to thousands of users, switch that branch to a subquery (`whereIn('to', fn($q) => $q->select('id')->from('users')->whereIn('role_id', $roleIds))`) instead of materializing ids. This keeps the same semantics with one SQL statement.

## 26.4 Freshness

Because roles are resolved at query time (no cache by default), changing a user's `role_id`, deactivating or deleting a user is reflected on the next request. This is the main reason to **avoid** caching by default.

---

# 27. Compatibility & Safety Matrix

| Existing behavior | Impact |
|-------------------|--------|
| Users without `scope` | Unchanged (`team`) |
| `show_all_claims = 1` | Unchanged (checked first) |
| Delegation window logic | Unchanged; also applies to ids added by `custom` |
| Created-by-with-no-active-assignee rule | Unchanged |
| `ClaimPolicy` show/update/delete/files | Inherit the extended visibility (same as subordinates today) |
| Legacy `permission_mode: ['with_approve']` | Works through the normalizer |
| Legacy permission readers (`->xxx == 1`) | Unaffected (`wrap_permissions` flattens) |
| Old frontend that never sends `scope` | User stays `team`; payload accepted |
| Seeder truncate on every deploy | Safe; user rows untouched |
| Per-client deployments (ART/GIG/GGI/MIC) | Same code path; feature is opt-in per user |

Security notes:

- Extended visibility also extends **actions** the user already holds permission for (update, delete, files, assign), because they use `can_user_access_claim()`. This is identical to how subordinates work today. If view-only extension is required, add a flag (`scope_readonly`) and check it in `ClaimPolicy` — this is *not* part of the minimal design.
- Never trust ids from the client: validate existence and cap list sizes (25.5); always enforce in `scopeVisibleTo`, never in controllers or the frontend (Section 17).
- Only holders of `update_permissions` can set these fields (existing route authorization).

---

# 28. Decisions Needed Before Coding

1. **Include subordinates in `custom`?** Recommended: **yes** (additive, zero regression). If the business wants "own + selected only", add `include_team: false` later (equivalent to the optional `onlyme` phase).
2. **Should roles resolve to users dynamically?** Recommended: **yes** (query time). New users added to the role become visible automatically; the alternative (storing user ids) would need re-saving permissions.
3. **View-only vs. full actions** on the extended claims (see security notes). Recommended default: same as `team`.
4. **Forbidden roles** (e.g. Super Admin) in `scope_roles`.
5. Whether the same scope should later apply to **exports/reports/dashboard**; they currently use their own rules (`show_all_executive`, `show_all_transactions`). Out of scope for this change.

---

# 29. Tests to Add

Extend `tests/Unit/ClaimServiceListVisibilityTest.php` (it already builds users/assignings/delegations in an in-memory DB):

| Case | Expectation |
|------|-------------|
| No `scope` key | Same result as today (regression guard) |
| `scope = custom`, `scope_users = [B]` | sees own + subordinates + B's claims, not C's |
| `scope = custom`, `scope_roles = [R]` | sees own + every active user with role R |
| User with role R soft-deleted / role changed | no longer visible on next call |
| Inactive assignment (`is_active = 0`) of a scoped user | not visible |
| Delegation from a scoped user | visible (same as team) |
| `show_all_claims = 1` + custom keys | all claims (custom ignored) |
| `scope` unknown value through `PermissionService::create` | stored as `team` |
| Legacy `permission_mode: ['with_approve']` through `create()` and the seeder | identical output to before |
| `get_permission_value('claims','show_claim',$u)` with custom scope | returns `scope` string and id arrays, not ints |
| `can_user_access_claim()` / `AuthorizeFileAccess` for a claim of a scoped user | allowed; for outside claim → 403 |
| Query count assertion | default user: no extra queries; custom with roles: +1 |

---

# 30. Rollout Plan (smallest safe steps)

1. Add `PermissionModes` + `PermissionScope`; switch `PermissionService`, the seeder loop and `PermissionController` to the normalizer (**no behavior change**, legacy lists only). Ship and verify.
2. Fix `get_permission_value()` casting (**no behavior change**).
3. Add `customScopeUserIds()` and the `$assigneeIds` merge in `scopeVisibleTo` (dormant: nobody has `scope = custom`). Add tests.
4. Change the `show_claim` seeder entry to the map form and add request validation (`scope`, `scope_roles`, `scope_users`).
5. Update the permissions UI to show the scope selector and the role/user pickers (frontend can lag behind: old UI keeps working).
6. Enable per user from the permissions screen, measure query timings, then consider 26.3 optimizations only if needed.

---

# 31. Final Recommendation (summary)

- Keep `show_all_claims` as the **"all"** switch and keep today's behavior as **`team`**.
- Add **one** new scope value, **`custom`**, with `scope_roles` and `scope_users`, stored beside `granted` in the existing `show_claim` JSON.
- Implement it as **"add ids to `$assigneeIds`"** inside `Claim::scopeVisibleTo`, the single choke point; every list, show, update, file and assign path inherits it with no further edits.
- Make `permission_mode` map-aware through **one normalizer** first, because three current consumers assume a list and two cast every value to `int`.
- Performance cost: **0 extra queries** for every existing user and **at most 1 indexed query** for custom users with roles; no new tables, no data migration.
