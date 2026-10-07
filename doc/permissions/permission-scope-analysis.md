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

مثال:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

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

مثال:

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

Permission يدعم Scope:

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

لأن Purpose هنا هو أن يكون تعريف الـ GeneralPermission هو **مصدر الحقيقة للخيارات المسموحة**.

```php
'permission_mode' => [
    'scope' => ['onlyme', 'all', 'team'],
]
```

تعني:

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

مثال:

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

مثال:

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

مثال:

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

مثال conceptual:

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

بدلاً من:

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

ثم نعمل:

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

هذه نقطة Importantة جداً لتجنب breaking existing behavior.

Existing permissions do not contain `scope`.

Therefore, default behavior must be explicitly defined.

The safest rollout rule is:

```text
permission لا يدعم scope
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

مثلاً:

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

مثل:

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

مثلاً:

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

- permissions الحالية
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

أي:

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

- الـ permission نفسه.
- الـ modes التي يدعمها.
- القيم المسموحة لكل mode.

User Permission becomes responsible for:

- Whether the permission is granted
- Which Scope was selected
- Whether the action requires approval

### ملاحظة تنفيذية Importantة

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
