# Permission Scope — Analysis & Implementation Design

## 1. الهدف

إضافة **Scope** إلى نظام الـ General Permissions بحيث لا تكون الصلاحية مجرد:

- هل المستخدم يملك الصلاحية؟ `granted`
- هل يحتاج موافقة؟ `with_approve`

بل يصبح لدينا أيضاً:

- ما هو نطاق البيانات التي يستطيع المستخدم رؤيتها؟ `scope`

المبدأ المقترح:

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

## 2. الشكل المقترح لتعريف الـ Permission في Seeder

السؤال الأساسي هو هل يمكن تعريف الـ permission بهذا الشكل؟

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

### الإجابة

**نعم، يمكن تقنياً، لكن لا أنصح بهذا الشكل كتصميم نهائي.**

السبب أن النظام الحالي يتعامل مع `permission_mode` على أنه **array of mode names**:

```php
['with_approve']
```

والـ seeder الحالي يفحصه هكذا:

```php
in_array('with_approve', $modes ?? [])
```

إذا أصبح الشكل:

```php
[
    'scope' => ['onlyme', 'all', 'team']
]
```

فلن يعمل هذا الفحص بشكل طبيعي مع التصميم الحالي، وسيصبح `permission_mode` أحياناً list وأحياناً associative array.

---

# 3. التصميم الموصى به

نحافظ على `permission_mode` كـ **list of supported modes**:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => ['scope'],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

ولو permission يدعم الاثنين:

```php
[
    'permission' => 'some_claim_action',
    'permission_mode' => ['with_approve', 'scope'],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

ثم تكون قيم الـ scope نفسها في Permission الخاص بالمستخدم:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "team"
  }
}
```

وهذا يفصل بين:

```text
GeneralPermission.permission_mode
        ↓
What capabilities does this permission support?

User Permission JSON
        ↓
What value was assigned to this user?
```

---

# 4. لماذا لا نضع allowed values داخل permission_mode؟

لو كتبنا:

```php
'permission_mode' => [
    'scope' => ['onlyme', 'all', 'team']
]
```

فالـ database field سيصبح يحمل **metadata + configuration values** في نفس المستوى.

بينما المطلوب فعلياً هو:

```text
permission_mode = supported capabilities
scope           = selected value
```

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

وهذا يسمح بأن يكون نفس الـ permission قابلاً لإعطاء scopes مختلفة لمستخدمين مختلفين.

---

# 5. القيم المسموحة للـ Scope

القيم الأساسية المقترحة:

```text
all
onlyme
team
```

## all

المستخدم يستطيع الوصول إلى جميع records التي يسمح له الـ permission بالوصول إليها.

مثال:

```text
show_claim + scope=all
→ جميع الـ claims
```

## onlyme

المستخدم يستطيع الوصول فقط إلى الـ records المرتبطة به مباشرة.

مثال محتمل:

```text
show_claim + scope=onlyme
→ claims assigned/owned by current user
```

**مهم:** تعريف "own" يجب أن يعتمد على relation الرسمي الموجود في RCP، وليس على افتراض أن أي user_id يمثل صاحب الـ claim.

## team

المستخدم يستطيع الوصول إلى records الخاصة به وبأعضاء الـ team المرتبطين به.

لكن قبل implementation يجب تثبيت معنى team في الـ domain الحالي.

لا يجب افتراض أن team = supervisor hierarchy إلا بعد مراجعة العلاقات الموجودة فعلياً في:

- User
- Claim
- assignee/collector
- supervisor/team relationships

---

# 6. الشكل النهائي المتوقع للـ Permission JSON

مثال `show_claim`:

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

ولو permission يدعم approval أيضاً:

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

# 7. أهم قاعدة في التصميم

يجب ألا نخلط بين:

```text
Permission capability
Permission assignment
Data filtering
Approval workflow
```

التصميم يكون:

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

# 8. كيف تدخل الميزة إلى النظام بدون breaking changes؟

التطبيق يجب أن يكون تدريجياً.

## المرحلة 1 — Metadata

نضيف `scope` إلى `permission_mode` فقط للـ permissions التي تحتاج data visibility.

مثال:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => ['scope'],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

ولا نغير باقي permissions.

---

## المرحلة 2 — Scope Constants / Enum

يجب عدم نشر strings مثل:

```text
all
onlyme
team
```

في كل المشروع.

الأفضل إنشاء Enum أو Constants مركزية:

```php
enum PermissionScope: string
{
    case ALL = 'all';
    case ONLY_ME = 'onlyme';
    case TEAM = 'team';
}
```

وبذلك يصبح التحقق موحداً.

---

## المرحلة 3 — Permission Resolver

يجب إنشاء helper/service مركزي مسؤول عن قراءة permission configuration.

مثال conceptual:

```php
$permission = permission_value(
    'claims',
    'show_claim',
    auth()->user()
);

$scope = $permission['scope'] ?? null;
```

ويفضل أن يكون هناك resolver واضح مثل:

```text
PermissionResolver
    ├── granted()
    ├── scope()
    └── requiresApproval()
```

بدلاً من قراءة JSON مباشرة في Controllers.

---

# 9. تطبيق Scope على Query

الـ scope لا يجب أن يكون مسؤولية الـ Controller.

بدلاً من:

```php
if ($scope === 'onlyme') {
    ...
} elseif ($scope === 'team') {
    ...
}
```

داخل كل Controller، الأفضل أن يكون هناك Query/Service layer مسؤول عن تطبيق الـ visibility.

Conceptual example:

```php
$query = Claim::query();

$query = $claimVisibility->apply(
    $query,
    auth()->user(),
    'show_claim'
);
```

والـ service يقرر:

```text
all
    → no additional ownership restriction

onlyme
    → filter by current user's ownership/assignment

team
    → filter by users in current user's team
```

---

# 10. لا تعتمد على Policy وحدها

الـ Policy يمكن أن تتحقق من:

```text
Can user access this specific claim?
```

لكن الـ list/index يحتاج Query Scope حتى لا يتم تحميل records غير مسموح بها أصلاً.

القاعدة:

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

# 11. حماية الـ Show endpoint

إذا كان المستخدم لديه:

```text
show_claim = granted
scope = onlyme
```

فلا يكفي أن نتحقق من:

```php
granted === 1
```

ثم نعمل:

```php
Claim::findOrFail($id)
```

لأن ذلك يسمح للمستخدم بالوصول إلى claim خارج scope.

يجب أن يصبح الوصول منطقياً مثل:

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

أو الأفضل أن يتم ذلك من خلال query constrained من البداية.

---

# 12. التعامل مع المستخدمين الحاليين

هذه نقطة مهمة جداً لتجنب breaking existing behavior.

الـ permissions القديمة لا تحتوي على `scope`.

لذلك يجب تحديد default behavior.

الخيار الأكثر أماناً للـ rollout:

```text
permission لا يدعم scope
    → current behavior remains unchanged
```

أما permission يدعم scope مثل `show_claim`، فيجب تحديد default صريح عند migration/seeding.

لا يجب أن نضع:

```scope = null
```

ثم نفترض معناه في أكثر من مكان.

يفضل أن يكون default واضحاً ومكتوباً في الـ design.

---

# 13. Admin behavior

حالياً الـ Admin يتم إعطاؤه:

```json
{
  "granted": 1
}
```

والـ seeder يضيف:

```json
"with_approve": 0
```

للـ permissions التي تدعم approval.

عند إضافة scope، يجب تحديد scope الأدمن بشكل صريح.

مثلاً:

```json
{
  "show_claim": {
    "granted": 1,
    "scope": "all"
  }
}
```

إذا كان الـ business rule يقول إن Super Admin يرى كل شيء.

ولا يجب ترك scope غير موجود ثم الاعتماد على fallback غير واضح.

---

# 14. Validation

عند إنشاء/تعديل User Permission يجب منع:

```text
scope = anything_else
```

مثل:

```text
scope = manager
scope = branch
scope = random
```

والـ allowed values تكون:

```text
all
onlyme
team
```

ويجب أيضاً التحقق أن الـ permission نفسه يدعم scope قبل قبول قيمة scope.

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

التعديل يجب ألا يغير:

- permissions الحالية
- approval workflow
- Partner API authorization behavior
- existing role checks
- business status rules

إلا في الـ permissions التي يتم explicitly enabling `scope`.

وبالتالي يمكن rollout تدريجي:

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

يجب اختبار كل scope على مستوى الـ Query والـ endpoint.

## show_claim

### all

User يستطيع رؤية:

```text
claim A
claim B
claim C
...
```

### onlyme

User يستطيع رؤية:

```text
claims assigned to user
```

ولا يستطيع رؤية:

```text
claims assigned to another user
```

### team

User يستطيع رؤية:

```text
own claims
+
team members' claims
```

ولا يستطيع رؤية:

```text
claims outside the team
```

---

# 17. Security Rules

يجب اعتبار scope جزءاً من authorization boundary وليس مجرد UI filter.

لا يكفي إخفاء records من frontend.

يجب أن يكون enforcement في backend.

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
  "permission_mode": ["scope"],
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

لا يتم تعديل كل الـ policies والcontrollers مرة واحدة.

الترتيب المقترح:

1. Define `PermissionScope` enum.
2. Keep `permission_mode` as a flat list: `['scope']`.
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

**Do not implement `team` based on assumption.**

---

# 21. Final Recommendation

The recommended syntax is:

```php
[
    'permission' => 'show_claim',
    'permission_mode' => ['scope'],
    'type' => PermissionEnum::PUBLIC->value,
    'path' => 'claims',
],
```

وليس:

```php
'permission_mode' => [
    'scope' => ['onlyme', 'all', 'team']
]
```

لأن الأولى تحافظ على semantics الحالية للـ `permission_mode`، بينما الثانية تخلط بين **mode** و **mode options**.

القيم:

```text
onlyme
all
team
```

يجب أن تكون جزءاً من تعريف/validation خاص بالـ Scope، بينما `permission_mode` يحدد فقط أن الـ permission يدعم Scope.

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
