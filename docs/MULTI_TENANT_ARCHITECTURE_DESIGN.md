# RCP — Multi-Tenant Architecture Design

> **Status:** Architecture Proposal / Design Document  
> **Scope:** RCP Backend  
> **Options:** Shared Database + `tenant_id` / Database-per-Tenant / Hybrid  
> **Recommended starting point:** Shared Database with a storage abstraction that allows Dedicated Database later.

---

## 1. Executive Summary

RCP can evolve from a single-organization application into a **Multi-Tenant Platform** where multiple companies use the same application while their data, users, files, payments, reports, jobs, and permissions remain isolated.

There are two primary storage strategies:

1. **Shared Database**
   - All tenants use the same PostgreSQL database.
   - Tenant-owned records contain `tenant_id`.
   - Application-level tenant scoping provides isolation.

2. **Database-per-Tenant**
   - Each tenant has its own PostgreSQL database.
   - A central Tenant Registry determines where each tenant is stored.
   - Application code switches to the tenant's connection at runtime.

The recommended long-term architecture is **Hybrid Multi-Tenancy**:

- Start most tenants in a Shared Database.
- Keep the application independent from the physical storage strategy.
- Move enterprise/compliance-sensitive tenants to Dedicated Databases when required.

This gives RCP lower initial complexity while preserving an enterprise-grade scaling path.

---

# 2. Current RCP Context

RCP is not a simple CRUD application. The current domain contains interconnected business flows:

- Users / Roles / Permissions
- Clients
- Claims
- Assignments
- Claim Status History
- Claim Risk History
- Claim Files
- Payments
- Payment Links
- Payment Transactions
- Payment provider integrations
- Messages / Calls / Notifications
- Imports
- Exports
- Background Jobs
- File synchronization
- Reporting
- Audit-related data

The main business relationship is approximately:

```
Client
  |
  +---- Claim
          |
          +---- Assignment
          +---- Status History
          +---- Risk History
          +---- Files
          |
          +---- Payment
                  |
                  +---- Payment Link
                  |
                  +---- Payment Transaction
                              |
                              +---- Payment Provider
```

Multi-tenancy must therefore be introduced across the **entire data and execution flow**, not only the `claims` table.

---

# 3. What Is a Tenant?

A Tenant represents an independent organization/company using RCP.

Example:

```
                         RCP Platform
                              |
              +---------------+---------------+
              |               |               |
          Tenant A         Tenant B        Tenant C
          Company A        Company B       Company C
              |               |               |
           Claims           Claims          Claims
           Payments         Payments        Payments
           Users            Users           Users
           Files            Files           Files
```

The fundamental rule is:

> A request, job, command, event, file, cache entry, report, or integration operation must have a deterministic Tenant Context whenever it operates on tenant-owned data.

---

# 4. Architecture A — Shared Database

## 4.1 Overview

All tenants use the same PostgreSQL database and schema.

Tenant isolation is implemented using a `tenant_id` column on tenant-owned tables.

```
                       RCP Application
                              |
                       Tenant Resolver
                              |
              +---------------+---------------+
              |               |               |
          Tenant A         Tenant B        Tenant C
              |               |               |
              +---------------+---------------+
                              |
                         PostgreSQL
                              |
              +---------------+---------------+
              |               |               |
            claims         payments        files
              |               |               |
          tenant_id       tenant_id       tenant_id
```

## 4.2 Example

```text
claims
-------------------------------------------------
id | tenant_id | client_id | status | amount
-------------------------------------------------
1  | 10        | 55        | open   | 5000
2  | 10        | 57        | paid   | 9000
3  | 20        | 12        | open   | 7000
```

A Tenant A query must never be allowed to return row 3.

---

# 5. Shared Database — RCP Schema

The following tables are expected to be tenant-scoped.

| Table / Domain | Tenant Scoped | Notes |
|---|---:|---|
| `claims` | Yes | Add `tenant_id` |
| `payments` | Yes | Add `tenant_id` |
| `payment_links` | Yes | Prefer explicit tenant ownership |
| `payment_transactions` | Yes | Important for payment isolation |
| `assignings` | Yes | Assignment belongs to tenant claim |
| `claim_status_histories` | Yes | Audit data must remain isolated |
| `claim_risk_histories` | Yes | Risk history is tenant data |
| `claim_files` | Yes | File ownership must be tenant-aware |
| `messages` | Yes | Tenant-scoped |
| `calls` | Yes | Tenant-scoped |
| Import records | Yes | Tenant-scoped |
| Export records | Yes | Tenant-scoped |
| `users` | Not necessarily | Prefer global users + tenant membership |
| Roles / Permissions | Depends | Can be global or tenant-owned |
| `tenants` | No | Platform-level registry |

---

# 6. Shared Database — Request Flow

```
HTTP Request
     |
     v
Authentication
     |
     v
Resolve Tenant
(host / subdomain / membership / trusted token)
     |
     v
TenantContext::set()
     |
     v
Authorization / RBAC
     |
     v
Controller
     |
     v
Application / Service Layer
     |
     v
Tenant-aware Data Access
     |
     v
PostgreSQL
     |
     v
WHERE tenant_id = current tenant
```

The Tenant must be resolved **before business data is accessed**.

## Important Security Rule

Never trust:

```http
POST /claims
{
    "tenant_id": 20
}
```

as proof that the caller belongs to Tenant 20.

Tenant identity should come from a trusted source such as:

- authenticated user membership
- authenticated API client
- trusted subdomain/domain mapping
- signed/trusted token claims
- platform-level tenant resolver

---

# 7. Shared Database — Tenant Context

A central abstraction should exist conceptually like:

```text
TenantContext
    |
    +-- currentTenant()
    +-- tenantId()
    +-- setTenant()
    +-- clear()
    +-- hasTenant()
```

Application services should consume the Tenant Context rather than repeatedly resolving tenant identity themselves.

This avoids having different controllers implement different tenant-resolution rules.

---

# 8. Shared Database — Data Isolation

The most important risk is accidental cross-tenant access.

Bad:

```php
Claim::find($id);
```

Potentially dangerous because `$id` may belong to another tenant.

Conceptually safer:

```php
Claim::where('tenant_id', $tenantId)
    ->findOrFail($id);
```

Or use a centralized tenant scope/data-access abstraction.

The exact implementation should be chosen after reviewing the existing model/repository conventions.

---

# 9. Shared Database — Composite Relationships

A major issue is cross-tenant relationships.

Example:

```text
Tenant A
  Claim #100
       |
       +---- client_id = 500

Tenant B
  Client #500
```

If `client_id` is globally unique, the database relationship may technically be valid while the business relationship is invalid.

Therefore relationship ownership should be enforced by:

- tenant-aware service validation
- tenant-aware model scopes
- authorization
- where appropriate, composite uniqueness/constraints
- automated cross-tenant tests

---

# 10. Shared Database — Index Strategy

Adding `tenant_id` changes query patterns.

Examples:

```sql
CREATE INDEX claims_tenant_status_idx
ON claims (tenant_id, status);

CREATE INDEX payments_tenant_claim_idx
ON payments (tenant_id, claim_id);

CREATE INDEX transactions_tenant_ref_idx
ON payment_transactions (tenant_id, transaction_ref);
```

Indexes must be based on actual access patterns.

Do not blindly index every `tenant_id`.

Validate with:

```sql
EXPLAIN (ANALYZE, BUFFERS)
...
```

Important for RCP because the current system already contains claims, payments, assignments, status/risk history, imports, and reporting queries where tenant filtering will become a common predicate.

---

# 11. Shared Database — Main Risks

| Risk | How it happens | Recommended mitigation |
|---|---|---|
| Data leakage | Query without tenant scope | Central tenant scope + authorization + tests |
| Cross-tenant relationship | Record references another tenant | Domain validation + constraints where appropriate |
| Cache collision | Generic cache keys | Tenant-prefixed keys |
| File collision | Generic file paths | Tenant-specific storage prefix |
| Queue leakage | Job has no tenant context | Store `tenant_id` in every tenant job |
| Report leakage | Aggregation without tenant filter | Tenant-aware reporting layer |
| Noisy Neighbor | One tenant consumes excessive resources | quotas, rate limits, queue isolation |
| Tenant-specific restore | One tenant needs restore | Logical backup/export or migrate tenant to Dedicated DB |
| Large database | Many tenants share one DB | Index review, partitioning when justified |

---

# 12. Noisy Neighbor Problem

With Shared DB, one large tenant can affect other tenants.

Example:

```
Tenant A
  2,000 claims
      |
      +---- normal load

Tenant B
  50,000,000 records
      |
      +---- heavy reports
      +---- imports
      +---- exports
      +---- queue workload
      |
      v
Shared PostgreSQL / Redis / Queue
```

Mitigations:

- per-tenant rate limits
- import/export quotas
- separate heavy queues
- worker concurrency controls
- query optimization
- tenant-level metrics
- database partitioning when justified
- moving very large tenants to Dedicated DB

---

# 13. Architecture B — Database-per-Tenant

## 13.1 Overview

Each tenant has an independent PostgreSQL database.

A central Tenant Registry stores metadata required to locate the database.

```
                         RCP Application
                                |
                         Tenant Resolver
                                |
                         Tenant Registry
                                |
             +------------------+------------------+
             |                  |                  |
         Tenant A DB        Tenant B DB        Tenant C DB
             |                  |                  |
         PostgreSQL          PostgreSQL          PostgreSQL
          isolated            isolated            isolated
```

---

# 14. Tenant Registry

The platform needs a central registry conceptually similar to:

```text
tenants
--------------------------------
id
name
slug
status
database_mode
database_identifier
settings
created_at
updated_at
```

Example:

```text
Tenant A
database_mode = shared
database_identifier = null

Tenant B
database_mode = dedicated
database_identifier = tenant_b_prod
```

This registry should not contain the tenant's entire business dataset.

It only tells the platform **who the tenant is and where/how its data is stored**.

---

# 15. Database-per-Tenant — Request Flow

```
HTTP Request
     |
Authentication
     |
Resolve Tenant
     |
     v
Tenant Registry
     |
     +-----------------------------+
     |                             |
 database_mode=shared       database_mode=dedicated
     |                             |
 Shared Connection            Tenant Connection
     |                             |
     +-------------+---------------+
                   |
             TenantContext
                   |
             Authorization
                   |
             Business Layer
```

The Domain/Application Layer should not care whether the underlying connection is shared or dedicated.

---

# 16. Database-per-Tenant — Main Risks

| Risk | Problem | Mitigation |
|---|---|---|
| Migration Explosion | Migration must reach many DBs | Central migration runner |
| Schema Drift | Tenant DBs on different versions | Migration version registry + deployment gate |
| Connection Management | Runtime DB switching | Dedicated connection manager |
| Long-running Workers | Old tenant connection can remain | Reset connection/context after every job |
| Infrastructure Cost | Many databases | Dedicated DB only for selected tenants |
| Monitoring | Many DBs to monitor | Central observability |
| Backup | Many independent backups | Backup orchestration |
| Cross-tenant Reporting | Data distributed | Reporting DB / data warehouse |
| Cross-DB Transactions | No simple ACID transaction | Avoid; use events/outbox |
| Provisioning | New tenant needs DB + schema | Automated provisioning workflow |

---

# 17. Schema Drift

One of the most dangerous Dedicated DB problems:

```
Tenant A → schema v43
Tenant B → schema v43
Tenant C → schema v42
Tenant D → schema v41
```

The application may expect v43.

Therefore deployment should include:

```
Migration Manager
       |
       +--> Tenant A → v43
       +--> Tenant B → v43
       +--> Tenant C → v43
       +--> Tenant D → v43
       |
       v
Verify all tenants
       |
       v
Deployment Gate
```

A production release should not be considered fully successful until required tenant schemas are validated.

---

# 18. Database-per-Tenant — Provisioning Flow

New Dedicated Tenant:

```
Create Tenant
      |
      v
Provision PostgreSQL Database
      |
      v
Run All Migrations
      |
      v
Seed Required Platform Data
      |
      v
Configure Tenant Integrations
      |
      v
Create Admin Membership
      |
      v
Health Check
      |
      v
Activate Tenant
```

Provisioning should be automated rather than manually performed.

---

# 19. Shared DB vs Database-per-Tenant

| Factor | Shared DB | Database-per-Tenant |
|---|---|---|
| Data isolation | Very good if correctly implemented | Excellent |
| Implementation complexity | **Low** | High |
| Infrastructure cost | **Low** | High |
| Adding tenants | **Easy** | More operational work |
| Tenant-specific backup | Harder | **Easy** |
| Tenant-specific restore | Harder | **Excellent** |
| Cross-tenant analytics | **Easy** | Harder |
| Noisy Neighbor | Higher | Lower |
| Enterprise isolation | Good | **Excellent** |
| Compliance isolation | Depends | **Strong** |
| Migration management | **Simple** | Complex |
| Operational overhead | **Low** | High |

---

# 20. Recommended Hybrid Architecture

The strongest long-term architecture for RCP is Hybrid.

```
                         RCP Platform
                              |
                       Tenant Registry
                              |
              +---------------+---------------+
              |                               |
       Shared DB Tenants               Dedicated DB Tenants
              |                               |
        Tenant A / B / C                    Enterprise X
              |                               |
       PostgreSQL Shared                PostgreSQL Dedicated
              |                               |
              +---------------+---------------+
                              |
                    Common Application Layer
                              |
       Claims | Payments | Risk | Workflow | Files | Reports
```

The application should expose a **Tenant Storage abstraction**.

Conceptually:

```
Tenant
  |
  +-- Storage Mode
        |
        +-- SharedDatabaseStorage
        |
        +-- DedicatedDatabaseStorage
```

The domain logic should not contain:

```if tenant is dedicated...
```

throughout the application.

That decision belongs in the infrastructure/data-access layer.

---

# 21. Why Hybrid Is the Best Fit for RCP

RCP is expected to have different customer sizes and requirements.

### SMB / Standard Tenants

Use:

```
Shared DB
```

Benefits:

- low cost
- simple operations
- easy reporting
- easy onboarding
- efficient resource utilization

### Enterprise Tenants

Use:

```
Dedicated DB
```

When they require:

- strict data isolation
- compliance
- independent backup/restore
- custom scaling
- heavy workloads
- tenant-specific database maintenance

---

# 22. Users / Membership / RBAC

A global user + tenant membership model is preferable.

```
User
 |
 +-- Membership --> Tenant A --> Admin
 |
 +-- Membership --> Tenant B --> Supervisor
 |
 +-- Membership --> Tenant C --> Collector
```

Conceptual table:

```text
tenant_user
----------------------------
tenant_id
user_id
role_id
status
created_at
updated_at

UNIQUE (tenant_id, user_id)
```

This allows the same person to work for multiple tenants with different roles.

Example:

```
Ahmed
 |
 +-- Tenant A → Admin
 |
 +-- Tenant B → Supervisor
```

---

# 23. Tenant-aware RBAC

Authorization becomes:

```
User
  |
  +--> Is member of Tenant?
          |
          +--> Role
                 |
                 +--> Permission
                        |
                        +--> Resource ownership
```

Checking only:

```user has permission
```

is insufficient.

The effective authorization becomes:

```
User
+ Tenant Membership
+ Role
+ Permission
+ Resource belongs to Tenant
```

---

# 24. Payment Architecture in Multi-Tenant RCP

Payments are one of the most sensitive domains in the RCP architecture.

Current conceptual flow:

```
Tenant
  |
Claim
  |
Payment
  |
Payment Link
  |
Payment Transaction
  |
Payment Provider
  |
Webhook / Status Enquiry
  |
Tenant-aware Settlement
  |
Payment / Claim Updates
  |
Outbox / Side Effects / Reconciliation
```

Rules:

1. Every Payment Transaction must have deterministic tenant ownership.
2. Provider references must not be trusted without tenant validation.
3. Webhook tenant resolution must use trusted mapping.
4. Reconciliation must be tenant-aware.
5. Idempotency keys should be scoped to tenant/business context.
6. Payment settlement must never update another tenant's Claim.
7. Payment reports must always be tenant-scoped.

---

# 25. Webhook Tenant Resolution

A dangerous design would be:

```
POST /payment/webhook
{
    "tenant_id": 20,
    "transaction_ref": "..."
}
```

and blindly trust `tenant_id`.

A stronger design is:

```
Provider Webhook
      |
Verify Provider / Signature / Source
      |
Resolve Transaction Reference
      |
Resolve Tenant Ownership
      |
Validate Tenant + Transaction
      |
Lock Transaction
      |
Settlement
```

The exact implementation depends on how the payment provider identifies merchants/accounts.

---

# 26. Queue Architecture

RCP already depends on background processing for domains such as:

- Risk calculation
- Imports
- Exports
- Notifications
- File synchronization
- Payment side effects

Every tenant-owned Job should carry Tenant identity.

```
Event / HTTP
    |
    v
Dispatch Job
    |
    +-- tenant_id
    +-- entity_id
    +-- idempotency key
    |
    v
Queue
    |
    v
Worker
    |
Resolve Tenant
    |
Set Tenant Scope / Connection
    |
Execute Job
    |
Clear Tenant Context
```

For Database-per-Tenant, clearing/resetting the connection is mandatory for long-running workers.

---

# 27. Queue Isolation and Noisy Neighbors

A large tenant may generate:

- thousands of imports
- large exports
- bulk operations
- risk recalculations

If everything uses the same queue:

```
Tenant A
Tenant B
Tenant C
     |
     v
One Queue
     |
     v
Tenant C heavy workload blocks others
```

Possible solution:

```
default
imports
exports
notifications
risk
payments
```

and optionally tenant-level throttling.

For Enterprise tenants, dedicated queues/workers can be introduced when required.

---

# 28. Cache Architecture

Cache keys must include tenant context.

Bad:

```text
users:123
claims:456
dashboard:stats
```

Good:

```text
tenant:10:users:123
tenant:10:claims:456
tenant:10:dashboard:stats
```

Even with Dedicated DB, Redis may remain shared at platform level, so tenant-aware keys remain recommended.

---

# 29. Files and Exports

RCP has files, imports, exports, generated artifacts, and cloud synchronization.

Storage should be logically partitioned:

```
private storage
 |
 +-- tenants/
      |
      +-- 10/
      |    +-- claims/
      |    +-- exports/
      |    +-- imports/
      |
      +-- 20/
           +-- claims/
           +-- exports/
           +-- imports/
```

Rules:

- Use private storage for sensitive files.
- Tenant must be part of the storage path.
- Download authorization must validate tenant ownership.
- Do not use filename secrecy as authorization.
- Temporary/signed URLs should be used where appropriate.
- Export jobs must carry tenant context.

---

# 30. Reports and Analytics

Shared DB makes tenant-scoped reporting easy:

```sql
SELECT SUM(amount)
FROM payments
WHERE tenant_id = :tenant_id;
```

But platform-wide analytics should explicitly opt into cross-tenant scope.

Recommended distinction:

```
Tenant Report
    |
    +-- Tenant-scoped query

Platform Report
    |
    +-- Explicit cross-tenant authorization
```

Never allow a generic report service to silently switch between the two modes.

---

# 31. Scheduler and Commands

Not every scheduled task is the same.

### Global tasks

Run once for the entire platform:

- tenant registry maintenance
- global migration checks
- platform monitoring

### Per-Tenant tasks

Run for every active tenant:

- daily reconciliation
- tenant-specific reports
- risk maintenance
- tenant notifications

Flow:

```
Scheduler
   |
   +-- Global Task
   |
   +-- Load Active Tenants
          |
          +--> Tenant A Job
          +--> Tenant B Job
          +--> Tenant C Job
```

Per-tenant tasks should generally be dispatched to queues instead of doing all tenant work in one scheduler process.

---

# 32. Observability

Every log and metric should be able to answer:

> Which Tenant did this operation belong to?

Example:

```text
tenant_id=10
claim_id=123
job=CalculateClaimRiskJob
duration=850ms
status=success
```

Metrics:

```text
claims_created{tenant="10"}
payment_failures{tenant="10"}
queue_latency{tenant="10"}
export_duration{tenant="10"}
```

This becomes essential for:

- debugging
- SLA monitoring
- noisy neighbor detection
- tenant-level billing/usage
- incident investigation

---

# 33. Migration Strategy — Current RCP to Shared Multi-Tenant

## Phase 0 — Inventory

Review all:

- tables
- models
- relations
- controllers
- services
- policies
- jobs
- events
- listeners
- commands
- schedulers
- cache keys
- file paths
- imports
- exports
- reports
- integrations

## Phase 1 — Tenant Foundation

Introduce:

```
tenants
tenant_user
tenant settings
TenantContext
TenantResolver
```

## Phase 2 — Add tenant_id

Add nullable `tenant_id` to business tables.

Initially nullable to allow controlled migration.

## Phase 3 — Backfill Existing Data

```
Existing RCP Data
      |
Determine owner
      |
Map record → Tenant
      |
Backfill tenant_id
      |
Find NULL / invalid ownership
      |
Validate
      |
Make tenant_id NOT NULL
```

## Phase 4 — Tenant Isolation

Introduce:

- tenant scopes
- tenant-aware repositories/services
- authorization
- cross-tenant validation
- database constraints where useful

## Phase 5 — Async Context

Update:

- Jobs
- Events
- Notifications
- Imports
- Exports
- Risk calculation
- File synchronization
- Scheduled tasks

## Phase 6 — Files / Cache / Reports

Make all of them tenant-aware.

## Phase 7 — Security Hardening

Test:

- IDOR
- cross-tenant access
- payment isolation
- file isolation
- export authorization
- webhook isolation

## Phase 8 — Production

Run Shared DB in production after isolation tests pass.

---

# 34. Migration to Dedicated Database Later

A Tenant can later be moved from Shared DB to Dedicated DB.

Recommended flow:

```
Tenant B currently in Shared DB
             |
             v
Provision Dedicated DB
             |
             v
Run migrations
             |
             v
Copy Tenant B data
             |
             v
Validate counts / FKs / checksums
             |
             v
Freeze Tenant B writes
             |
             v
Final delta synchronization
             |
             v
Switch database_mode
             |
             v
Smoke tests
             |
             v
Enable traffic
             |
             v
Monitor
             |
             v
Rollback window
```

This should be automated before it becomes an operational requirement.

---

# 35. Dedicated DB Migration Requirements

A migration tool should track:

```tenant_id
source_database
target_database
migration_status
started_at
completed_at
validation_status
error_message
```

Possible states:

```
pending
provisioning
copying
validating
cutover
completed
failed
rollback_required
```

Never change the Tenant's database mode before validation succeeds.

---

# 36. Cross-Tenant Security Test Matrix

| Test | Expected Result |
|---|---|
| Tenant A requests Tenant B Claim | 404/403 |
| Tenant A requests Tenant B Payment | 404/403 |
| Tenant A downloads Tenant B Export | Denied |
| Tenant A accesses Tenant B File | Denied |
| Tenant A submits Tenant B Claim ID | Denied |
| Tenant A triggers Tenant B operation | Denied |
| Tenant A reads global report | Only if explicitly authorized |
| Job A executes against B | Impossible |
| Cache A reads B key | Impossible |
| Webhook resolves wrong Tenant | Rejected |
| User has role in A but not B | B access denied |

---

# 37. PostgreSQL Row-Level Security — Optional Defense in Depth

For Shared DB, PostgreSQL Row-Level Security (RLS) can provide another isolation layer.

Conceptually:

```
Application
    |
Tenant Context
    |
PostgreSQL Session Context
    |
RLS Policy
    |
tenant_id = current tenant
```

This should be considered **defense in depth**, not a substitute for application authorization.

Before adopting RLS, evaluate:

- connection pooling
- transaction/session context
- background workers
- migrations
- administrative queries
- reporting
- performance

---

# 38. Third-Party Integrations

Integrations should also become tenant-aware.

Examples:

```
Tenant A
  |
  +-- Payment Provider Account A
  +-- SMS Credentials A
  +-- Cloud Storage A

Tenant B
  |
  +-- Payment Provider Account B
  +-- SMS Credentials B
  +-- Cloud Storage B
```

Credentials should never accidentally be reused across tenants.

A Tenant Integration abstraction should determine:

```
Tenant
  |
Integration
  |
Provider Configuration
  |
Credentials
  |
External API
```

---

# 39. Common Problems in Both Architectures

| Problem | Solution |
|---|---|
| Missing Tenant Context | Central TenantContext |
| Multi-tenant user | Membership model |
| Jobs without HTTP context | Include tenant_id in Job |
| Cache contamination | Tenant-prefixed keys |
| File leakage | Private storage + authorization |
| Report leakage | Tenant-aware reporting |
| Webhook ambiguity | Trusted tenant mapping |
| Third-party credential leakage | Tenant-scoped integration credentials |
| Missing isolation tests | Automated cross-tenant test suite |
| Poor observability | tenant_id in logs/metrics/traces |

---

# 40. Recommended Target Architecture

```
                         CLIENT
                           |
                           v
                    API / Web / Mobile
                           |
                           v
                  Authentication Layer
                           |
                           v
                    Tenant Resolver
                           |
                           v
                    Tenant Context
                           |
                           v
                 Authorization / RBAC
                           |
                           v
                    Application Layer
                           |
             +-------------+-------------+
             |             |             |
          Claims       Payments        Risk
             |             |             |
             +-------------+-------------+
                           |
                    Domain Events
                           |
             +-------------+-------------+
             |             |             |
          Queue         Outbox       Audit
             |             |             |
             v             v             v
         Workers      Side Effects   Compliance
             |
             v
     Tenant-aware Data Access
             |
       +-----+------------------+
       |                        |
   Shared DB              Dedicated DB
       |                        |
   tenant_id                 tenant DB
       |                        |
       +-----------+------------+
                   |
              PostgreSQL
```

---

# 41. Final Recommendation for RCP

## Recommended

### Start with:

```
Shared PostgreSQL
+
tenant_id
+
TenantContext
+
TenantResolver
+
Tenant-aware authorization
+
Tenant-aware Jobs
+
Tenant-aware Cache
+
Tenant-aware Files
```

### Then evolve to:

```
Hybrid Multi-Tenant
       |
       +-- Standard tenants → Shared DB
       |
       +-- Enterprise tenants → Dedicated DB
```

The critical architectural rule is:

> **The Domain Layer must not depend on where the Tenant is physically stored.**

That decision belongs to the data/infrastructure layer.

---

# 42. Why This Is Stronger Than Implementing One Strategy Directly

If RCP implements Shared DB everywhere with hard-coded assumptions:

```
Model → tenant_id
Controller → tenant_id
Job → tenant_id
Service → tenant_id
Report → tenant_id
```

then moving one customer to Dedicated DB later becomes difficult.

Instead:

```
Business Logic
      |
Tenant Abstraction
      |
+-----+----------------+
|                      |
Shared Storage     Dedicated Storage
```

The application can evolve without rewriting the business domain.

---

# 43. Implementation Roadmap

```
STEP 1
Tenant Foundation
        |
        v
STEP 2
Tenant Resolver + TenantContext
        |
        v
STEP 3
Shared DB tenant_id
        |
        v
STEP 4
Backfill Existing RCP Data
        |
        v
STEP 5
Tenant-aware Models / Services
        |
        v
STEP 6
RBAC + Authorization
        |
        v
STEP 7
Jobs / Events / Cache / Files / Reports
        |
        v
STEP 8
Payment + Webhook Tenant Isolation
        |
        v
STEP 9
Cross-tenant Security Tests
        |
        v
STEP 10
Production Shared DB
        |
        v
STEP 11
Tenant Registry + Storage Abstraction
        |
        v
STEP 12
Dedicated DB Support
        |
        v
STEP 13
Hybrid Tenant Placement
```

---

# 44. Acceptance Criteria

The Multi-Tenant architecture should not be considered production-ready until:

- [ ] Every tenant-owned business record has deterministic tenant ownership.
- [ ] Cross-tenant reads are prevented.
- [ ] Cross-tenant writes are prevented.
- [ ] Users can have tenant-specific memberships and roles.
- [ ] Jobs carry tenant context.
- [ ] Long-running workers reset tenant state.
- [ ] Cache keys are tenant-aware.
- [ ] Files and exports are tenant-aware and access-controlled.
- [ ] Payment settlement validates tenant ownership.
- [ ] Webhooks cannot settle another tenant's transaction.
- [ ] Reports are explicitly tenant-scoped.
- [ ] Imports/exports are tenant-scoped.
- [ ] Scheduler tasks distinguish global vs per-tenant work.
- [ ] Logs and metrics include tenant context.
- [ ] Automated cross-tenant security tests exist.
- [ ] Existing data has been safely backfilled.
- [ ] Shared DB indexes are validated with real query plans.
- [ ] Dedicated DB migration/version management is defined before enabling it.
- [ ] Rollback strategy exists for tenant database migration.

---

# 45. Conclusion

RCP is a strong candidate for Multi-Tenancy because its domain is already structured around clear business aggregates such as Claims, Payments, Risk, Assignments, Files, Imports, and Exports.

The recommended evolution is:

```
Current RCP
    |
    v
Shared Multi-Tenant
    |
    v
Tenant Abstraction
    |
    v
Hybrid Multi-Tenant
    |
    +---- Standard → Shared DB
    |
    +---- Enterprise → Dedicated DB
```

This approach minimizes the initial operational cost while preserving a path toward stronger isolation, enterprise requirements, independent backups, independent scaling, and tenant-specific infrastructure.

The most important architectural investment is not the `tenant_id` column itself.

It is establishing **Tenant Context as a first-class platform concept** and ensuring that every synchronous and asynchronous path respects it.
