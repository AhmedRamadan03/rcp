# Permission Scope — Architecture & Implementation Analysis

> Status: design / analysis (no code changed). Reviewed against `main` of `rcp`.
> This document **replaces** the earlier revisions of the same file. The earlier "additive `custom` scope"
> idea was dropped in favor of the model requested later: **a scope per permission** chosen from
> `onlyme | team | all | users | roles` (single value or combination, §3.6).

---

## 1. Goal

Give a permission a **scope** that decides *whose data* the user can see, independently of *whether* the
user has the permission.

```text
granted       = Authorization        (may the user use this permission at all?)
scope         = Data visibility      (whose data does it apply to?)
with_approve  = Approval workflow    (does the action need a supervisor's approval?)
```

### 1.1 Scope values

| Scope | Data returned | Extra input |
|-------|---------------|-------------|
| `onlyme` | **My** data only | – |
| `team` | **My** data + data of **people who report to me** (`users.supervisor_id = me`) | – |
| `users` | **My** data + data of the **specific users** selected | `scope_ids` = user ids |
| `roles` | **My** data + data of **everyone who holds** the selected roles (resolved at query time) | `scope_ids` = role ids |
| `all` | Everything | – |

"**My data**" is always included, for every scope except `all` (which already includes it).

### 1.2 Combining scopes

A grant may combine several scope types, e.g. **role 1 + users 12 and 15 (who are not in role 1)**. Details in §3.6. A single value (`"scope": "roles"`) remains valid and means the same as before.

### 1.3 Out of scope (explicit non-goals)

- Recursive hierarchies (team of my team). `team` stays one level, exactly as today.
- Changing approval flow, delegation rules, Partner API auth, or per-action policies.

---

## 2. What exists today (verified in code)

### 2.1 Permission storage

```text
GeneralPermission (catalog)  : permission, path, type, permission_mode (array cast)
        │  PermissionService::create()
        ▼
Permission (per user row)    : JSON column per path (claims, claim_status, claims_files, settings,
                               third_parties, users, permissions, transactions, reports, role,
                               accounting, dashboard, payments)
        value per key        : int  OR  {"granted":0|1, "<mode>":0|1}
```

- `permission_mode` today is a **list of flag names**, e.g. `['with_approve']`.
- `GeneralPermissionSeeder::run()` does `GeneralPermission::truncate()` and re-creates the catalog on **every deploy**; user `Permission` rows are untouched.
- Admin seeder grants `granted = 1` (+ `with_approve = 0`) for everything.

### 2.2 Claim visibility today

Single choke point: **`Claim::scopeVisibleTo(Builder $query, User $user)`**.

```text
if claims.show_all_claims == 1            → return $query (everything)
assigneeIds = [me] + my subordinates       (users.supervisor_id)
visible if ANY of:
  A) claim has an ACTIVE assigning where `to` ∈ assigneeIds
  B) claim has an ACTIVE assigning to a user who DELEGATED to someone in assigneeIds
     (delegations: is_active, start_date <= now, end_date null or >= now)
  C) claim.user_id = me  AND  claim has no active assignee      (created by me, unassigned)
```

So, **today every user who is not `show_all_claims` already behaves as `team`**. `onlyme`, `users`, `roles` do not exist.

`show_claim` is only an **endpoint gate** (`ClaimPolicy`, ~line 54); it does not filter data.

### 2.3 Who calls `visibleTo` (all inherit any change)

| Caller | Purpose |
|--------|---------|
| `ClaimService::list()` (+ `listForCollector/SeniorCollector/ExternalOffice/Law`) | Claim lists |
| `can_user_access_claim()` → `ClaimPolicy` | show / update / delete / claim files |
| `AuthorizeFileAccess` middleware | File download authorization |
| `RiskController` | Risk list / claim risk |
| `ClaimInfoController` (~line 1358) | "can the assigner see this claim" check on assignment |

### 2.4 Places that do **not** use `visibleTo` (important for impact)

| Place | How it restricts today | Effect of this feature (Phase 1) |
|-------|------------------------|----------------------------------|
| `DashboardController` (`checkUserIds`, payments, targets, claims) | Hard-coded **by role id** (SUPER_ADMIN / SUPERVISOR see all, SENIOR_COLLECTOR sees team, others only self) | None |
| `PaymentTransactionPolicy` (`show_all_transactions`), `DashboardPolicy` (`show_all_executive`) | Boolean all-or-nothing | None |
| `accountClaimList` / `listExportForAccount` | Account-facing, no user filter | None |
| Export jobs (`ExportStatusReportClaims`, report jobs) | Receive `claimIds`/filters built by their callers | **Verify per export** that ids come from a `visibleTo` query (see §10, risk R4) |

---

## 3. Proposed design

### 3.1 Data model

**Catalog (`GeneralPermission.permission_mode`)** — becomes a *config map* for permissions that support scope, while legacy lists keep working:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => [
        'scope' => ['onlyme', 'team', 'users', 'roles', 'all'],   // allowed values = validation source of truth
    ],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
// legacy stays as is:
['permission' => 'send_discharge_letter', 'permission_mode' => ['with_approve'], ...],
```

**User permission (`permissions.claims` JSON)**:

```json
{ "show_claim": { "granted": 1, "scope": "onlyme" } }
{ "show_claim": { "granted": 1, "scope": "team"   } }
{ "show_claim": { "granted": 1, "scope": "all"    } }
{ "show_claim": { "granted": 1, "scope": "users", "scope_ids": [12, 15, 31] } }
{ "show_claim": { "granted": 1, "scope": "roles", "scope_ids": [3, 5] } }
```

`scope_ids` is **one generic key**; its meaning is decided by `scope` (`users` → user ids, `roles` → role ids). It is ignored for the other scopes.

### 3.2 Semantics and defaults (the rules that prevent regressions)

1. **No `scope` key** (every existing user) → treated as **`team`** = exactly today's behavior. **No data migration of user rows.**
2. **`show_all_claims = 1` keeps winning** and is checked first. It is the legacy spelling of `all`. (Cleanup of the duplicate in §12.)
3. **Present but invalid value / unknown scope** → **fail closed to `onlyme`** (never widen).
4. `users` / `roles` with **empty `scope_ids`** → behaves as `onlyme` (never `all`).
5. **"My data"** = rules A, B, C of §2.2 evaluated for `me`, so delegations *to me* and claims *created by me with no active assignee* still count for every scope.
6. For `users` / `roles` / `team`, the id list is fed into the **same** A and B branches; rule C (created-by) stays **me only** (as for `team` today).
7. `team` does **not** include subordinates' subordinates. `users`/`roles` do **not** include the selected users' subordinates.
8. `roles` is resolved **at query time** (`users.role_id IN (...)`, soft-deleted users excluded), so new/changed/removed users take effect on the next request without re-saving permissions.

### 3.3 Core idea — why the change is small

`scopeVisibleTo` already reduces visibility to **one list of user ids** (`$assigneeIds`) used in
`assignings.to IN (...) AND is_active = 1` and in the delegation branch. Every scope is therefore just a
*different way to build that list*:

```text
onlyme → [me]
team   → [me] + subordinates              (today)
users  → [me] + scope_ids
roles  → [me] + ids of users whose role_id ∈ scope_ids
all    → null  (no filter)
```

The three EXISTS branches, delegation window logic, indexes and pagination stay **untouched**.

### 3.4 Architecture

```text
 GeneralPermission (catalog)            allowed scopes per permission
          │
          ▼
 PermissionModes::normalize()  ◄── understands legacy list + new map (one place)
          │
          ├──► PermissionService::create()   validates/stores scope + scope_ids
          ├──► GeneralPermissionSeeder       admin defaults
          └──► PermissionController::index   catalog sent to UI

 User Permission JSON  ──►  VisibilityScope::assigneeIds($user, $perm)   (pure resolver)
                                    │   returns  null | int[]
                                    ▼
                          Claim::scopeVisibleTo()   ← single choke point
                                    │
     ┌────────────┬─────────────┬───┴─────────┬──────────────┐
  list/export   show/update    file access   risk           assign check
  (ClaimService) (ClaimPolicy)  (middleware)  (RiskCtrl)     (ClaimInfoCtrl)
```

Request flow for a list:

```text
GET /claims
  → Policy: show_claim.granted == 1 ?                      (authorization)
  → ClaimService::list → Claim::visibleTo($user)
        show_all_claims == 1 ? → no filter
        else VisibilityScope::assigneeIds() → ids | null   (visibility)
        build A/B/C clause with ids
  → filters, pagination → response
```

---

### 3.6 Combining scopes (e.g. role 1 **plus** extra users outside that role)

**Yes, this works, and it costs almost nothing** because every scope already resolves to "a list of user ids". A combination is
simply the **union** of those lists (plus my own id).

Stored shape (the single-value shape from §3.1 stays valid and is normalized to this by `VisibilityScope::normalize()`):

```json
{
  "show_claim": {
    "granted": 1,
    "scope": ["roles", "users"],
    "scope_ids": { "roles": [1], "users": [12, 15] }
  }
}
```

Meaning: **my data + everyone holding role 1 + users 12 and 15** (even if 12/15 are not in role 1). Other examples:

| Grant | Result |
|-------|--------|
| `["team","roles"]`, roles `[1]` | me + my subordinates + role 1 holders |
| `["roles","users"]`, roles `[1]`, users `[12,15]` | me + role 1 holders + users 12, 15 |
| `["team","users"]`, users `[40]` | me + my subordinates + user 40 |
| `"roles"` (string, legacy shape) + `scope_ids:[1]` | me + role 1 holders (same as §3.1) |

Rules:

1. `all` is **exclusive**: if present it wins (and validation rejects `all` combined with others, to avoid confusing grants).
2. `onlyme` is implied (my data is always included); accepted but redundant, and validation rejects it combined with others.
3. Duplicates are removed (`array_unique`); a user matched by role *and* listed in `users` appears once.
4. `scope_ids` is keyed by scope when the scope is an array; a plain list is accepted only for the single-string form.
5. Each scope in the combination follows the same defaults/fail-closed rules as §3.2 (invalid scope → `onlyme`; empty ids → contributes nothing).
6. Cost: still **one** `assignings.to IN (...)` filter; extra queries = number of *role-based* scopes in the combination (usually 1).

Why decide this now: the stored shape is the only part that is expensive to change later (it sits in user rows). Supporting both
shapes from day one costs ~15 lines in `normalize()`; adding it later would require a data migration.

---

## 4. Implementation (exact code changes)

Total: **2 new small classes + 1 enum, 5 small edits, 1 seeder line, tests**. No new tables, no migration of user data, no new endpoints.

### 4.1 New: `PermissionScope` enum

```php
namespace App\Enums;

enum PermissionScope: string
{
    case ONLY_ME = 'onlyme';
    case TEAM    = 'team';
    case USERS   = 'users';
    case ROLES   = 'roles';
    case ALL     = 'all';

    public function needsIds(): bool
    {
        return in_array($this, [self::USERS, self::ROLES], true);
    }
}
```

### 4.2 New: `PermissionModes` normalizer (one place that understands both formats)

Needed because three current consumers assume `permission_mode` is a list (§5, R1).

```php
namespace App\Support;

final class PermissionModes
{
    /** @return array{flags: string[], scope: string[]} */
    public static function normalize(array|string|null $mode): array
    {
        if (is_string($mode)) { $mode = json_decode($mode, true); }
        $mode ??= [];

        if (array_is_list($mode)) {                 // legacy: ['with_approve']
            return ['flags' => $mode, 'scope' => []];
        }

        $scope = $mode['scope'] ?? [];              // map: ['scope' => [...], 'with_approve' => true]
        unset($mode['scope']);

        return ['flags' => array_keys(array_filter($mode)), 'scope' => $scope];
    }
}
```

### 4.3 New: `VisibilityScope` resolver

Supports one scope or a combination (union). `normalize()` is the only place that understands both storage shapes (§3.6).

```php
namespace App\Services\Permissions;

use App\Enums\PermissionScope;
use App\Models\User;

final class VisibilityScope
{
    /**
     * Normalize stored value to ['scopes' => PermissionScope[], 'ids' => [scope => int[]]].
     * Accepts: "roles" + scope_ids:[3]   |   ["roles","users"] + scope_ids:{"roles":[3],"users":[12]}
     */
    public static function normalize(mixed $perm): array
    {
        $raw    = is_array($perm) ? ($perm['scope'] ?? null) : null;
        $rawIds = is_array($perm) ? ($perm['scope_ids'] ?? []) : [];

        if ($raw === null || ! config('permissions.scope_enabled', true)) {
            return ['scopes' => [PermissionScope::TEAM], 'ids' => []];     // legacy / switched off
        }

        $names  = is_array($raw) ? $raw : [$raw];
        $scopes = [];
        $ids    = [];

        foreach ($names as $name) {
            $scope = PermissionScope::tryFrom((string) $name);
            if (! $scope) { return ['scopes' => [PermissionScope::ONLY_ME], 'ids' => []]; }   // invalid => fail closed

            $scopes[] = $scope;
            if ($scope->ids() !== 'none') {
                // legacy single-scope shape: scope_ids is a plain list
                $list = array_is_list((array) $rawIds) && ! is_array($raw) ? (array) $rawIds : ($rawIds[$scope->value] ?? []);
                $ids[$scope->value] = array_values(array_unique(array_filter(array_map('intval', $list))));
            }
        }

        return ['scopes' => $scopes, 'ids' => $ids];
    }

    /** @return int[]|null  null = unrestricted (scope all) */
    public static function assigneeIds(User $user, mixed $perm): ?array
    {
        ['scopes' => $scopes, 'ids' => $ids] = self::normalize($perm);

        if (in_array(PermissionScope::ALL, $scopes, true)) {
            return null;                                              // all wins over everything
        }

        $result = [$user->id];                                        // "my data" is always included

        foreach ($scopes as $scope) {
            $result = [...$result, ...match ($scope) {
                PermissionScope::ONLY_ME => [],
                PermissionScope::TEAM    => $user->subordinates()->pluck('id')->all(),
                PermissionScope::USERS   => $ids['users'] ?? [],
                PermissionScope::ROLES   => ($ids['roles'] ?? []) === [] ? []
                    : User::query()->whereIn('role_id', $ids['roles'])->pluck('id')->all(),   // soft-deleted excluded
                PermissionScope::EXTERNAL_USERS => [],                // see §14.1
                default                  => [],
            }];
        }

        return array_values(array_unique($result));
    }
}
```

### 4.4 Edit: `Claim::scopeVisibleTo` (the only visibility edit)

Read the **raw** JSON, because `safe_json_decode()` → `wrap_permissions()` flattens `{"granted":1,...}` to `1` and drops `scope` / `scope_ids`.

```php
$rawClaims  = $user->permission()->first()?->claims ?? [];      // json-cast array
$claimsData = safe_json_decode($rawClaims);                     // unchanged consumer
$allShow    = custom_policy($user, ($claimsData->show_all_claims ?? 0) == 1);

if ($allShow) { return $query; }                                // unchanged

// BEFORE:
//   $subordinateIds = $user->subordinates()->pluck('id')->toArray();
//   $assigneeIds    = !empty($subordinateIds) ? [...$subordinateIds, $user->id] : [$user->id];
// AFTER:
$assigneeIds = VisibilityScope::assigneeIds($user, $rawClaims['show_claim'] ?? null);
if ($assigneeIds === null) { return $query; }                   // scope = all

// ... rest of the method (A/B/C branches) stays exactly as it is
```

### 4.5 Edit: `PermissionService::create()`

> Written for the single-string shape for clarity. For combinations, pass `$value['scope']` / `$value['scope_ids']` through `VisibilityScope::normalize()` after validation and store the normalized result (string form when only one scope, array form otherwise).

Replace the `foreach ($allowed_modes as $mode)` blocks with the normalizer, keep flags as ints, and store scope **as a string + id array** (today everything is cast to `(int)`):

```php
$modes = PermissionModes::normalize($permission['permission_mode'] ?? null);

if (is_array($value)) {
    $entry = ['granted' => (int) ($value['granted'] ?? 0)];

    foreach ($modes['flags'] as $flag) {
        $entry[$flag] = (int) ($value[$flag] ?? 0);               // unchanged behavior
    }

    if ($modes['scope']) {                                        // only permissions that declare scope
        $scope = PermissionScope::tryFrom((string) ($value['scope'] ?? 'team'));
        $scope = ($scope && in_array($scope->value, $modes['scope'], true)) ? $scope : PermissionScope::TEAM;

        $entry['scope'] = $scope->value;
        if ($scope->needsIds()) {
            $entry['scope_ids'] = array_values(array_unique(array_map('intval', (array) ($value['scope_ids'] ?? []))));
        }
    }
    $permissions[$category_key][$key] = $entry;
} else { /* unchanged */ }

// "not in request" branch: same, using $modes['flags'] only (no scope => team)
```

### 4.6 Edit: `PermissionUpdateRequest::withValidator` (existing hook)

> Accept `scope` as a string **or** an array of strings. When an array, `scope_ids` must be an object keyed by scope. Reject `all` / `onlyme` combined with other scopes. Validate each key's ids against `idsTable()` (§14.1).

For any entry that carries `scope`:

```text
scope       : required string, in the catalog's allowed list for THAT permission
scope_ids   : required when scope in (users, roles); array, min 1, max 200 (users) / 50 (roles)
              each: integer, exists:users,id  (users scope)  |  exists:roles,id  (roles scope)
scope on a permission whose catalog has no scope mode  → validation error
```

Optional business rule: forbid the Super Admin role in `roles` scope (§13, D4).

### 4.7 Edit: `get_permission_value()` (helper)

It currently does `(int) $v` for every key; `"roles"` would become `0` and `[3,5]` would become `1`.

```php
foreach ($val as $key => $v) {
    $result[$key] = (is_array($v) || (is_string($v) && ! is_numeric($v))) ? $v : (int) $v;
}
```

### 4.8 Edit: `GeneralPermissionSeeder`

```php
// admin loop: was in_array('with_approve', $modes)
$modes = PermissionModes::normalize($perm->permission_mode);
if (in_array('with_approve', $modes['flags'], true)) { $value['with_approve'] = 0; }
```

and change the `show_claim` catalog entry (§3.1). Admin receives no `scope` key and keeps full visibility through `show_all_claims`.

### 4.9 Edit: `PermissionController::index`

It returns `permission_mode` raw to the frontend. Keep legacy lists as lists, return the map for scope-enabled permissions. The UI must accept `list | map` (an old UI that ignores `scope` simply never sends it → users stay on `team`).

### 4.10 Config switch (safety)

```php
// config/permissions.php
return ['scope_enabled' => env('PERMISSION_SCOPE_ENABLED', true)];
```

With `false`, every user resolves to `team` (legacy) regardless of stored scopes → instant, deploy-free rollback per client.

### 4.11 What is intentionally NOT changed

`ClaimPolicy`, `AuthorizeFileAccess`, `can_user_access_claim`, `ClaimService`, `RiskController`, `ClaimInfoController` (all inherit via `visibleTo`), `show_all_claims`, delegation logic, created-by rule, approval workflow, Partner API auth, `wrap_permissions()` and the ~40 legacy `->xxx == 1` readers (extra keys do not break them because they are flattened).

---

## 5. Impact analysis

### 5.1 Behavior impact by user type

| User | Before | After (Phase 1) |
|------|--------|-----------------|
| Existing user, no `scope` | team | **team (identical)** |
| `show_all_claims = 1` (admin/managers) | all | **all (identical)** |
| Admin seed user | all | all |
| New: `onlyme` | – | Own claims only. **A supervisor set to `onlyme` stops seeing subordinates' claims** (intended) |
| New: `users` / `roles` | – | Own + selected people's claims |

### 5.2 Technical impact by component

| Component | Impact | Notes |
|-----------|--------|-------|
| `Claim::scopeVisibleTo` | **Changed** (≈ 8 lines) | single choke point |
| `PermissionService::create` | **Changed** | normalizer + scope storage |
| `PermissionUpdateRequest` | **Changed** | validation only |
| `get_permission_value` | **Changed** (1 line) | stop casting strings/arrays |
| `GeneralPermissionSeeder` | **Changed** (2 lines) | catalog + admin loop |
| `PermissionController::index` | Changed (format only) | UI must accept map |
| Claim list/show/update/delete/files/risk/assign | Inherit | no edit; behavior follows scope |
| Dashboard / transactions / executive policies | None | still role-based / boolean |
| Approval flow (`with_approve`) | None | orthogonal |
| Partner API auth | None | not based on user permission JSON |
| Legacy permission readers | None | `wrap_permissions` flattens objects |
| Deploy pipeline | None | seeder truncates catalog only; user rows untouched; per-client (ART/GIG/GGI/MIC) rollout via env switch |

### 5.3 Frontend impact

1. Permissions screen: for permissions whose `permission_mode` is a map with `scope`, render a scope selector; for `users`/`roles` render a multi-select fed by existing users/roles endpoints.
2. Payload: `permissions.claims.show_claim = { granted: 1, scope: "roles", scope_ids: [3,5] }`.
3. Must tolerate extra keys in the user-permission response and `permission_mode` as list **or** map. **Old UI keeps working** (no scope sent → `team`).

---

## 6. Performance analysis

### 6.1 Extra queries per `visibleTo` call

| Scope | Extra work vs. today |
|-------|----------------------|
| no scope / `team` | **0** (same subordinates query as today) |
| `onlyme` | **−1** (skips the subordinates query) |
| `users` | **0** (ids come from the already-loaded permission JSON) |
| `roles` | **+1** (`select id from users where role_id in (...)`) |
| `all` | **−1 or more** (returns before building the clause) |

### 6.2 SQL stays fast

- Filtering keeps using `assignings (to, is_active)` (migration `2026_10_04_140005`) and `assignings (claim_id)` (`2026_10_04_101414`). A longer `IN` on the leading column is fine for tens/hundreds of ids.
- No per-row work, no N+1, no new JOIN/GROUP BY; pagination and counts behave as before.
- `scope_ids` for `users` is capped (200) by validation, so the `IN` list is bounded.

### 6.3 Optional optimizations (only if measured)

1. **Per-request memoization** of `assigneeIds()` using a *scoped* container binding (**not** a `static` array: queue workers and Octane live across requests/jobs and would serve stale team/role membership).
2. `permission()->first()` is a fresh query on each call; reuse the already loaded `$user->permission` relation/`firstPermission` where available.
3. **Large role guard**: if a role resolves to thousands of users, switch that branch to a sub-select (`whereIn('to', fn ($q) => $q->select('id')->from('users')->whereIn('role_id', $roleIds)->whereNull('deleted_at'))`) — same semantics, one SQL statement.
4. Index on `users(role_id)` (Postgres does not index FKs automatically). Only if the plan shows a seq scan; use the repo's `CREATE INDEX CONCURRENTLY IF NOT EXISTS` pattern.
5. **No cross-request cache by default** — it would delay role/user changes. Add only for very large roles, with a short TTL.

---

## 7. Security model

- Scope is an **authorization boundary** enforced in `scopeVisibleTo`, never in controllers or the frontend.
- Single-record endpoints are protected by the same rule through `can_user_access_claim()` → consistent `403`.
- **Fail closed** on invalid/empty configuration (§3.2 rules 3–4).
- Ids are validated for existence and capped; unknown scopes are rejected at write time.
- Extended visibility also extends **actions** the user already holds permission for (update, delete, assign, files), exactly as `team` does today for subordinates. If view-only extension is required it needs a separate flag (not part of the minimal design).
- Granting wider scope than your own is an existing capability (`update_permissions` holders can already grant `show_all_claims`); not changed here.

---

## 8. Edge cases

| Case | Behavior |
|------|----------|
| `users`/`roles` with empty `scope_ids` | contributes nothing (alone → `onlyme`) |
| `["roles","users"]`, user listed and also in the role | visible once (de-duplicated) |
| `["all", ...others]` | rejected at write; if stored, `all` wins |
| `scope_ids` contains my own id | de-duplicated |
| Selected user soft-deleted | their claims remain visible only if still actively assigned to that id; role-resolved lists exclude deleted users automatically |
| User changes role | `roles` scopes reflect it on next request |
| Role deleted | `exists` validation prevents new use; an orphan id matches no users |
| Selected user delegated to a third person | covered by branch B (same as team) |
| Selected user's subordinates | **not** included (no recursion) |
| `show_all_claims = 1` and `scope = onlyme` | `all` (legacy flag wins; clean up per §12) |
| Claim created by a selected user with no active assignee | **not** visible (rule C is "me only", same as `team`) |
| Delegation *to me* with `onlyme` | visible (part of "my data") |

---

## 9. Testing strategy

Extend `tests/Unit/ClaimServiceListVisibilityTest.php` (already builds users/assignings/delegations on in-memory DB), plus feature tests.

| Case | Expectation |
|------|-------------|
| No `scope` key | identical to current results (**regression guard**) |
| `onlyme` | own (A/B/C) only; **not** subordinates' |
| `team` | own + subordinates' (today) |
| `users=[B]` | own + B's; not C's |
| `["roles","users"]` roles=[1], users=[12,15] | own + role 1 holders + 12 + 15; not others |
| `["team","roles"]` | own + subordinates + role holders |
| Single-string shape vs. equivalent array shape | identical results |
| `roles=[R]` | own + all active users with role R; role change/soft-delete reflected |
| `all` via scope | no filter |
| `show_all_claims=1` + any scope | all |
| Empty `scope_ids` / invalid scope stored | `onlyme` (fail closed) |
| Inactive assigning (`is_active=0`) of a scoped user | not visible |
| Delegation to me / from a scoped user | visible |
| `can_user_access_claim()`, `AuthorizeFileAccess` | allowed in scope, `403` outside |
| `PermissionService::create` | stores string scope + int ids; unknown scope → `team`; scope on non-scope permission ignored/rejected |
| Legacy `permission_mode: ['with_approve']` through `create()` and seeder | output identical to before |
| `get_permission_value()` with scope | returns string + array, not ints |
| Query count | `team`: same as before; `users`: no extra; `roles`: +1 |
| `PERMISSION_SCOPE_ENABLED=false` | everyone resolves to `team` |

---

## 10. Risks & mitigations

| ID | Risk | Mitigation |
|----|------|------------|
| R1 | `permission_mode` becomes a map and 3 list-assuming consumers break | Ship `PermissionModes` first (Phase 1), switch all consumers before changing the catalog |
| R2 | Values cast to `int` silently turn `"roles"` / `[3,5]` into `0` / `1` | Fix `PermissionService` and `get_permission_value` in the same release; tests in §9 |
| R3 | `wrap_permissions()` hides `scope` from normal readers | Read the **raw** JSON in `scopeVisibleTo` |
| R4 | A user restricted to `onlyme` still sees data through paths that don't use `visibleTo` (exports, some reports, dashboards) | Audit each export/report: the claim ids must come from a `visibleTo` query; list findings before enabling `onlyme/users/roles` for real users |
| R5 | Supervisors moved to `onlyme` lose access to subordinates (surprising) | UI help text; this is the definition of `onlyme` |
| R6 | Long-lived workers + static memoization → stale visibility | Use scoped binding, not statics |
| R7 | Seeder truncates catalog on each deploy while code and seeder versions differ across the 3 UAT deployments | Seeder and code ship in the same artifact; normalizer understands both formats |
| R8 | Large `IN` lists | Caps + sub-select fallback (§6.3) |

---

## 11. Rollout plan (each step is independently shippable)

1. **Foundation, no behavior change:** add `PermissionModes`, switch `PermissionService`, seeder admin loop, `PermissionController` to it; fix `get_permission_value`. Catalog still legacy lists.
2. **Resolver dormant:** add `PermissionScope`, `VisibilityScope`, edit `scopeVisibleTo`, add config switch. Nobody has `scope` ⇒ everyone is `team` ⇒ identical SQL. Add tests (§9). Run the existing visibility test suite.
3. **Catalog + validation:** change the `show_claim` seeder entry to the map form; add request validation.
4. **Audit exports/reports** (R4) and fix any path that bypasses `visibleTo`.
5. **UI:** scope selector + user/role pickers.
6. **Enable per user** from the permissions screen; monitor timings; apply §6.3 only if needed.
7. **Cleanup (optional):** migrate `show_all_claims = 1` → `scope = all`, hide the old checkbox (§12).

**Rollback:** set `PERMISSION_SCOPE_ENABLED=false` (everyone → `team`). Stored `scope` keys are inert; no data to revert.

---

## 12. The legacy `show_all_claims` duplicate

`show_all_claims` and `scope = all` mean the same thing. Phase 1 keeps both (legacy flag has priority) to avoid touching existing users. Later, a one-off migration can set `scope = all` where `show_all_claims = 1`, hide the old checkbox, and finally remove the flag; until then the UI should show only the scope selector for claims visibility so admins have one control.

---

## 13. Decisions needed before coding

| # | Decision | Recommendation |
|---|----------|----------------|
| D1 | Which permission carries the scope? | `show_claim` (keeps `show_all_claims` boolean untouched) |
| D2 | Should `users`/`roles`/`team` include *my own* data? | **Yes** (as requested) |
| D3 | Do actions (update/delete/assign/files) extend with the visibility? | Yes, same as `team` today; add a view-only flag later only if the business needs it |
| D4 | Roles that must not be selectable (e.g. Super Admin)? | Block in validation |
| D5 | Should `team` stay one level? | Yes |
| D6 | Apply scope to other permissions (transactions, reports, dashboard, users list) in this release? | **No** — claims only; see §14 |

---

## 14. Extending beyond claims (later, no engine change)

The resolver returns a **list of user ids** (or `null` = all). Each data domain only needs to map that list to its own column:

| Domain | Mapping |
|--------|---------|
| Claims | `assignings.to IN ids` (done in Phase 1) |
| Payment transactions / dashboard payments | `payment.claim.assignees.to IN ids` |
| Targets | `targets.user_id IN ids` |
| Users list | `users.id IN ids` |

Enabling another permission = add `'permission_mode' => ['scope' => [...]]` in the seeder + one `->whereIn(...)` using `VisibilityScope::assigneeIds()`. The hard-coded role logic in `DashboardController::checkUserIds` is the natural first candidate, but it is intentionally **not** touched now.

Combinations (e.g. `roles` + `users`) are already supported from Phase 1 (§3.6).

---

## 14.1 Adding a new scope later (e.g. `external_users`) — how easy is it?

**Easy: one enum case + one resolver branch (+ a validation rule).** Nothing in `scopeVisibleTo`, the policies, the
queries or the stored JSON shape changes, because every scope only has to answer one question:

> "Which user ids does this scope add to *my* data?"

### Does a new scope need `ids`?

**It depends on the scope; each scope declares it.** Three kinds exist:

| Kind | Needs `scope_ids`? | `scope_ids` meaning | Example |
|------|--------------------|---------------------|---------|
| none | No | – | `onlyme`, `team`, `all`, **`external_users`** (all users with `user_type = EXTERNAL_USER`) |
| ids | Yes | ids of some entity | `users` (user ids), `roles` (role ids), `clients` (client ids), `departments` |
| ids (optional) | Optional | narrows the set | `external_users` with `scope_ids = [41, 42]` → only those external users |

For `external_users` the codebase already has what is needed: `users.user_type` with `UserType::INTERNAL_USER / EXTERNAL_USER`
(used in user create/edit validation and in imports). So the resolver is one query:

```php
PermissionScope::EXTERNAL_USERS => [
    $user->id,
    ...User::query()
        ->where('user_type', UserType::EXTERNAL_USER->value)
        ->when($ids, fn ($q) => $q->whereIn('id', $ids))      // optional narrowing
        ->pluck('id')->all(),
],
```

### Steps to add any new scope (checklist)

| # | Where | Change | Size |
|---|-------|--------|------|
| 1 | `PermissionScope` enum | add `case EXTERNAL_USERS = 'external_users';` and its id rule (`none` / `required` / `optional`, and which table the ids belong to) | 2 lines |
| 2 | `VisibilityScope::assigneeIds()` | add one `match` arm returning the user ids | 5–8 lines |
| 3 | Catalog (seeder) | add the value to the permission's allowed list: `'scope' => [..., 'external_users']` | 1 line |
| 4 | `PermissionUpdateRequest` | nothing if the rule lives in the enum (§ below); otherwise one rule | 0–3 lines |
| 5 | Frontend | add the option to the selector (+ picker if it takes ids) | UI only |
| 6 | Tests | one case in the visibility test | 1 test |

**Not touched:** `Claim::scopeVisibleTo`, policies, file-access middleware, `PermissionService`, JSON storage, migrations, seeded user rows.

### Make it even cheaper: put the rules on the enum (recommended in Phase 1)

Instead of scattering `if ($scope === 'roles')` across the validator, service and UI payload, let the enum describe itself, so the
validator, the service and the catalog API all read the same metadata:

```php
enum PermissionScope: string
{
    case ONLY_ME        = 'onlyme';
    case TEAM           = 'team';
    case USERS          = 'users';
    case ROLES          = 'roles';
    case EXTERNAL_USERS = 'external_users';
    case ALL            = 'all';

    /** none | required | optional */
    public function ids(): string
    {
        return match ($this) {
            self::USERS, self::ROLES => 'required',
            self::EXTERNAL_USERS     => 'optional',
            default                  => 'none',
        };
    }

    /** table used to validate ids (null when no ids) */
    public function idsTable(): ?string
    {
        return match ($this) {
            self::USERS, self::EXTERNAL_USERS => 'users',
            self::ROLES                       => 'roles',
            default                           => null,
        };
    }
}
```

`PermissionService` stores `scope_ids` whenever `ids() !== 'none'`; `PermissionUpdateRequest` validates `scope_ids`
against `idsTable()`; the resolver stays the only place with business logic. A new scope = enum case + resolver arm.

### Cases that need a bit more than the checklist

| New scope | Extra work |
|-----------|-----------|
| Based on a **user attribute** (`user_type`, `client_id`, `role_id`, `supervisor_id`) | Resolver arm only |
| Based on a **claim attribute** (e.g. claims of a client / city, regardless of assignee) | Cannot be expressed as "user ids"; the resolver must return a *query constraint* instead of ids. Evolve the return type from `int[]|null` to a small value object (`UserIds`, `Unrestricted`, `ClaimConstraint`) and let `scopeVisibleTo` apply it. One-time refactor of ~15 lines; do it only when such a scope is actually requested |
| **Multiple scopes at once** (`roles` + `external_users`) | Nothing: already supported by §3.6 (just list both) |
| **Recursive team** (my team's team) | Resolver arm with a recursive CTE; add a depth cap and index on `users(supervisor_id)` |

### Performance of new scopes

A scope that resolves to a user-id list costs **at most one indexed query** (like `roles`) and reuses the same
`assignings (to, is_active)` filter. For big sets (e.g. all external users in a large deployment) use the sub-select
form from §6.3 so no id list is materialized in PHP.

### Compatibility of new scopes

Adding a case is additive: existing users keep their stored value, unknown values still **fail closed** to `onlyme`, and with
`PERMISSION_SCOPE_ENABLED=false` everything falls back to `team`. Releasing a new scope never changes what existing users see.

---

## 15. Summary

- Store a `scope` on the permission: one value or a **combination** (`onlyme | team | users | roles | all`), with `scope_ids` for `users`/`roles` (e.g. role 1 **+** users 12, 15).
- Missing scope = `team` = **exactly today's behavior**; `show_all_claims` still means `all`.
- Implement as **"build the assignee id list differently"** inside `Claim::scopeVisibleTo`; list, show, update, files, risk and assignment inherit it.
- Do the **normalizer + int-cast fixes first**, because three consumers assume a list and two cast every value to `int`.
- Cost: **no extra queries for existing users**, at most **+1 indexed query** for `roles`, no new tables, no data migration, and an env switch for instant rollback.
- The one real exposure to audit before enabling restricted scopes: **exports/reports/dashboard paths that do not use `visibleTo`** (R4).
