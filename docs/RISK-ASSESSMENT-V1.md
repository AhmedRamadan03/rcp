# Risk Assessment — V1
## Production Readiness / Manager & Product Documentation

> **Branch:** `fix/risk-assessment-production-readiness`  
> **Risk Version:** `1.0`  
> **Scope:** Risk Assessment only.  
> **Current V1 does not implement a Decision Engine, automatic approval/rejection, assignment routing, or any other business action based on the risk result.

---

## 1. Feature Purpose

Risk Assessment calculates a numerical risk score for every claim using a fixed set of explainable factors.

The objective of V1 is to provide the business/user with:

- A single risk score from **0 to 100**.
- A risk level: **Low / Medium / High**.
- A human-readable risk label and color.
- A detailed breakdown showing how every factor contributed to the final score.
- The calculation timestamp.
- A historical record of previous risk calculations.
- The reason/trigger that caused a calculation.
- The user responsible for a manual calculation when applicable.
- A version number (`1.0`) so future scoring rules can be distinguished from V1.

The feature is therefore an **assessment and visibility layer**. It does not make a final business decision.

---

# 2. Current V1 Risk Model

The final risk score is calculated from **7 factors**.

| Factor | Weight |
|---|---:|
| Claim Type | 15% |
| Claim Amount | 20% |
| Accident Age | 15% |
| Claim History | 15% |
| Debtor Type | 20% |
| City Risk | 10% |
| Client Risk | 5% |
| **Total** | **100%** |

Every factor first receives an internal score from **0–100** (depending on the factor's rule), then its weighted contribution is calculated.

### Contribution formula

For every factor:

```
contribution = factor_score × weight / 100
```

The final score is:

```
final_score = min(100, round(sum(all contributions), 2))
```

The score is stored with two decimal places.

---

# 3. Risk Levels

The V1 level thresholds are fixed:

| Final Score | Level | Meaning |
|---:|---|---|
| 70–100 | High | High risk |
| 30–69.99 | Medium | Medium risk |
| 0–29.99 | Low | Low risk |

Implementation:

```
score >= 70  => high
score >= 30  => medium
otherwise    => low
```

### Current presentation attributes

The Claim model derives:

- **High**
  - level: `high`
  - color: `red`
  - label: `مخاطر عالية - مراجعة إجبارية`

- **Medium**
  - level: `medium`
  - color: `orange`
  - label: `مخاطر متوسطة - مراجعة مستحبة`

- **Low**
  - level: `low`
  - color: `green`
  - label: `مخاطر منخفضة - معالجة عادية`

> These labels describe the risk assessment result. V1 does not technically enforce the suggested business action.

---

# 4. Factor 1 — Claim Type

**Weight: 15%**

The claim type is converted into a risk score as follows:

| Claim Type | Score |
|---|---:|
| Individual | 20 |
| Corporate | 40 |
| Leasing | 35 |
| Government | 25 |
| Municipality | 30 |
| Unknown/default | 10 |

Example:

If the claim is Corporate:

```
score = 40
weight = 15%
contribution = 40 × 15 / 100 = 6
```

---

# 5. Factor 2 — Claim Amount

**Weight: 20%**

The amount is mapped to a risk score using fixed thresholds:

| Claim Amount | Score |
|---:|---:|
| >= 100,000 | 100 |
| >= 50,000 | 80 |
| >= 20,000 | 60 |
| >= 10,000 | 40 |
| >= 5,000 | 25 |
| < 5,000 | 10 |

Example:

For a claim amount of 60,000:

```
score = 80
weight = 20%
contribution = 80 × 20 / 100 = 16
```

---

# 6. Factor 3 — Accident Age

**Weight: 15%**

The system calculates how old the accident is in months.

Rules:

| Accident Age | Score |
|---|---:|
| > 24 months | 90 |
| > 12 months | 70 |
| > 6 months | 40 |
| > 3 months | 20 |
| <= 3 months | 10 |

Special cases:

- No accident date -> **50**
- Future accident date -> **50**

Example:

If the accident happened 15 months ago:

```
score = 70
weight = 15%
contribution = 70 × 15 / 100 = 10.5
```

---

# 7. Factor 4 — Claim History

**Weight: 15%**

The system looks for previous claims belonging to the same debtor using:

```
debtor_nid
```

The current claim itself is excluded.

Two values are calculated:

- Total previous claims.
- Previous claims whose status is one of:
  - `rejected`
  - `cancelled`
  - `closed`

The score formula is:

```
history_score = (previous_claims × 10) + (bad_claims × 20)
```

The result is capped at 100:

```
min(100, calculated_score)
```

If there is no debtor NID:

```
score = 0
```

### Example

A debtor has:

- 2 previous claims
- 1 rejected claim

Then:

```
(2 × 10) + (1 × 20)
= 20 + 20
= 40
```

With a 15% weight:

```
40 × 15 / 100 = 6
```

### Important V1 behavior

The history is based on the existing claims available in the database at calculation time. It is not limited to a specific date window.

---

# 8. Factor 5 — Debtor Type

**Weight: 20%**

| Debtor Type | Score |
|---|---:|
| Third Party | 80 |
| Insured | 20 |
| Unknown/default | 50 |

Example:

Third Party:

```
80 × 20 / 100 = 16 contribution
```

---

# 9. Factor 6 — City Risk

**Weight: 10%**

City Risk is intentionally conservative in V1.

## 9.1 No City

If the claim has no `city_id`:

```
city_risk = 30
```

This is a neutral score.

Contribution:

```
30 × 10 / 100 = 3.00
```

## 9.2 City Has Fewer Than 1,000 Historical Claims

The system counts historical claims in the same city, excluding the current claim.

If:

```
historical_claims < 1000
```

then:

```
city_risk = 30
```

This prevents a very small sample from producing an aggressive city risk score.

## 9.3 City Has 1,000 or More Historical Claims

Once the city has at least 1,000 historical claims, the system calculates:

```
bad_claims = rejected + cancelled + closed
```

Then:

```
city_risk =
    (bad_claims / total_historical_claims) × 100
```

The result is capped at 100.

The statuses `rejected`, `cancelled`, and `closed` are treated equally as bad outcomes for this factor.

### Example

A city has:

- 1,000 historical claims
- 300 bad claims
  - 100 rejected
  - 100 cancelled
  - 100 closed

Then:

```
300 / 1000 × 100 = 30
```

Therefore:

```
city_risk = 30
weight = 10%
contribution = 3.00
```

### Current V1 City Risk rule

There is currently **no date/time window** for city history. The calculation uses the available historical claims in that city.

The **1,000 claim minimum** is the current V1 threshold and can be changed in a future version if the business decides a different statistical minimum is more appropriate.

---

# 10. Factor 7 — Client Risk

**Weight: 5%**

The system looks at previous claims belonging to the same client.

The current claim is excluded.

Bad claims for this factor are:

- `rejected`
- `cancelled`

The calculation is:

```
rejection_rate = rejected_claims / total_previous_claims
client_risk = rejection_rate × 100 × 2
```

The result is capped at 100.

If there is no `client_id`:

```
score = 30
```

If the client has no previous claims:

```
score = 30
```

Therefore, a client without enough history receives a neutral score rather than being treated as automatically risky or safe.

### Example

Client history:

- 100 previous claims
- 20 rejected/cancelled

Then:

```
20 / 100 × 100 × 2
= 40
```

Contribution:

```
40 × 5 / 100 = 2
```

---

# 11. Complete Calculation Example

Suppose a claim produces these factor scores:

| Factor | Score | Weight | Contribution |
|---|---:|---:|---:|
| Claim Type | 40 | 15% | 6.00 |
| Amount | 80 | 20% | 16.00 |
| Accident Age | 20 | 15% | 3.00 |
| Claim History | 40 | 15% | 6.00 |
| Debtor Type | 20 | 20% | 4.00 |
| City Risk | 30 | 10% | 3.00 |
| Client Risk | 40 | 5% | 2.00 |
| **Final** | | **100%** | **40.00** |

Final result:

```
risk_score = 40.00
risk_level = medium
```

The system can therefore explain not only the final score but exactly where the score came from.

---

# 12. What Is Stored on the Claim?

The current Claim stores:

- `risk_score`
- `risk_factors`
- `risk_version`
- `risk_calculated_at`

### risk_factors structure

Each factor contains:

```json
{
  "score": 30,
  "weight": 10,
  "contribution": 3.0,
  "label": "مخاطر المنطقة"
}
```

Therefore the frontend can display a complete factor-by-factor explanation without recalculating anything.

---

# 13. Risk Versioning

V1 uses:

```
risk_version = "1.0"
```

The version is stored on:

- Current Claim risk snapshot.
- Claim Risk History records.

The purpose is traceability.

If the scoring rules change later, a future version such as `2.0` can coexist conceptually with historical V1 calculations without pretending that old calculations were made using the new rules.

---

# 14. Risk History

Every calculation creates a record in:

```
claim_risk_histories
```

The history stores:

- claim ID
- risk score
- risk factors
- risk version
- trigger
- calculated_by
- created_at / updated_at

This means the system does not simply overwrite the current score. It also keeps a calculation trail.

### Example history sequence

A claim can have:

```
created
  -> score 25.00

status_change
  -> score 42.50

updated
  -> score 38.00

manual_recalc
  -> score 40.00
```

The current Claim contains the latest snapshot, while the history contains previous calculation snapshots.

---

# 15. When Is Risk Calculated Automatically?

The Claim model is registered with:

```
ClaimRiskObserver
```

The observer triggers the risk calculation asynchronously.

## 15.1 When a Claim Is Created

On Claim creation:

```
created
```

A `CalculateClaimRiskJob` is dispatched after the database transaction commits.

Trigger stored in history:

```
created
```

## 15.2 When a Claim Is Updated

Risk is recalculated only when one of these risk-related fields changes:

- `amount`
- `type`
- `debtor_type`
- `accident_date`
- `status`
- `city_id`
- `debtor_nid`
- `client_id`

If none of these fields changes, the observer does not dispatch a risk calculation.

### Status change

If `status` changes, the trigger is:

```
status_change
```

Otherwise:

```
updated
```

This prevents unnecessary risk calculations for unrelated Claim changes.

---

# 16. Background Job

The automatic calculation runs through:

```
CalculateClaimRiskJob
```

The job:

1. Receives the Claim.
2. Refreshes the Claim from the database.
3. Stops safely if the Claim no longer exists.
4. Resolves the calculating user.
5. Calls `RiskCalculationService::calculate()`.
6. Saves the current snapshot and history.

Current job settings:

```
tries = 3
timeout = 60 seconds
```

The use of a background job means Claim creation/update does not need to perform the full risk calculation synchronously inside the main request.

---

# 17. Manual Recalculation

The feature also supports explicit manual recalculation.

Endpoint:

```
POST /{claimId}/risk/recalculate
```

The operation:

1. Verifies that the authenticated user can view the Claim.
2. Recalculates the risk immediately through the Risk Service.
3. Uses the authenticated user as `calculated_by`.
4. Stores the trigger as:

```
manual_recalc
```

5. Returns the newly calculated risk result.

This is useful when an administrator/user wants to explicitly refresh the assessment.

---

# 18. Getting Risk Details for a Claim

Endpoint:

```
GET /{claimId}/risk
```

The endpoint returns:

- `risk_score`
- `risk_level`
- `risk_color`
- `risk_label`
- `risk_factors`
- `calculated_at`
- calculation history
- history ID
- historical score
- trigger
- calculation date
- calculating user's name

The endpoint also respects the existing Claim visibility rules through `visibleTo(auth()->user())`.

Therefore a user cannot use the Risk endpoint to bypass the application's Claim visibility restrictions.

---

# 19. Risk Summary

Endpoint:

```
GET /risk-summary
```

The summary is calculated over Claims visible to the authenticated user.

It returns:

- `total_claims`
- `high_risk`
- `medium_risk`
- `low_risk`
- `avg_score`
- `high_risk_percentage`

### Example

```json
{
  "total_claims": 1000,
  "high_risk": 100,
  "medium_risk": 500,
  "low_risk": 400,
  "avg_score": 41.75,
  "high_risk_percentage": 10
}
```

This gives management a high-level view of the current risk distribution without opening individual Claims.

---

# 20. What the User/Business Gets

For an individual Claim, the user gets:

### 1. Overall risk

Example:

```
Risk Score: 40.00
Risk Level: Medium
```

### 2. Explanation

The user can see which factors increased or reduced the risk.

Example:

```
Claim Type
Score: 40
Weight: 15%
Contribution: 6.00

Amount
Score: 80
Weight: 20%
Contribution: 16.00

City Risk
Score: 30
Weight: 10%
Contribution: 3.00
```

### 3. Calculation date

The user knows when the current assessment was generated.

### 4. History

The user can see how the Claim's risk assessment changed over time.

### 5. Trigger

The system identifies whether a calculation happened because of:

- Claim creation
- Status change
- Other risk-related update
- Manual recalculation

### 6. Management summary

Management can see:

- Number of Claims.
- Number of High Risk Claims.
- Number of Medium Risk Claims.
- Number of Low Risk Claims.
- Average risk score.
- Percentage of High Risk Claims.

---

# 21. What V1 Does NOT Do

The following are intentionally outside the current V1 scope:

- Automatic Claim approval.
- Automatic Claim rejection.
- Automatic assignment.
- Automatic routing to a collector/lawyer/team.
- Blocking Claim processing because of risk.
- Making legal/business decisions automatically.
- A Decision Engine.
- Assignment Engine integration.
- Automatic escalation workflow.
- Predictive machine learning.
- Statistical/ML model training.
- Dynamic weights configured from the UI.

The current feature answers:

> **"How risky does this Claim appear according to the current V1 rules, and why?"**

It does not answer:

> **"What business action must the system take?"**

That can be built as a separate Decision Engine in a future phase.

---

# 22. Data Flow

The implemented flow is:

```
Claim Created / Risk Field Updated
                |
                v
       ClaimRiskObserver
                |
                v
      CalculateClaimRiskJob
                |
                v
       RiskCalculationService
                |
        +-------+-------+
        |       |       |
        v       v       v
      Factor Factor   Factor ...
        |       |       |
        +-------+-------+
                |
                v
        Weighted Contributions
                |
                v
          Final Score 0-100
                |
        +-------+--------+
        |                |
        v                v
 Current Claim      Risk History
 snapshot           snapshot
        |                |
        +-------+--------+
                |
                v
             API
                |
        +-------+--------+
        |                |
        v                v
   Claim Risk        Risk Summary
     Details
```

---

# 23. Persistence Model

## Current Claim

The current calculation is stored directly on `claims`.

Relevant fields:

- `risk_score`
- `risk_factors`
- `risk_version`
- `risk_calculated_at`

## Historical Calculations

Every calculation creates a `claim_risk_histories` record.

This provides an audit trail and preserves previous calculations.

---

# 24. Performance Considerations Implemented

The Risk calculations query Claim history by:

- debtor NID
- client ID
- status

The production-readiness changes include indexes for:

```
(debtor_nid, status)
client_id
status
amount
```

These indexes support the historical and risk-related Claim queries.

The automatic calculation itself is moved to a queued Job rather than forcing the complete calculation into the Claim request lifecycle.

---

# 25. V1 Test Coverage

The branch contains automated tests for:

## City Risk

Verified scenarios:

1. No city -> neutral score 30.
2. City with fewer than 1,000 historical Claims -> neutral score 30.
3. City with 1,000 historical Claims -> rejected/cancelled/closed are treated as bad Claims.

The verified local result is:

```
PASS Tests\\Unit\\RiskCalculationServiceTest

3 passed
12 assertions
```

The City Risk contribution is also verified:

```
30 × 10% = 3.0
```

## Job

The Job test verifies that the calculation trigger is passed correctly to the Risk Service.

## Claim Risk Attributes

Tests verify that:

- 75 -> high/red
- 50 -> medium/orange
- 10 -> low/green

---

# 26. Example: Why a New Claim Can Have a Score Around 30

A new Claim is not automatically Low Risk.

Several neutral/default factors exist.

For example, City Risk can be:

```
30
```

because:

- no city exists, or
- the city has fewer than 1,000 historical Claims.

Its contribution is therefore:

```
3.00 points
```

The remaining factors are then added according to their individual scores and weights.

Therefore a final result such as:

```
30.80
```

is valid and means:

```
total weighted contributions = 30.80
risk level = medium
```

It does **not** mean that the City Risk itself is 30.80.

---

# 27. Current V1 Business Interpretation

The V1 design intentionally combines:

- Claim characteristics.
- Financial exposure.
- Accident age.
- Debtor historical behavior.
- Debtor category.
- Geographic historical outcomes.
- Client historical outcomes.

The weights express the current business priority:

- Amount and Debtor Type are the strongest individual factors at 20% each.
- Claim Type, Accident Age and Claim History are 15% each.
- City Risk is 10%.
- Client Risk is 5%.

The model is deterministic and explainable: the same Claim data and same historical database state produce the same calculation.

---

# 28. Important Current-State Notes

The following describe the implementation exactly as it exists in V1:

1. City Risk uses a **1,000 historical claim minimum**.
2. City Risk uses all available historical Claims; no date window is currently applied.
3. City Risk treats `rejected`, `cancelled`, and `closed` equally as bad outcomes.
4. Claim History treats `rejected`, `cancelled`, and `closed` as bad outcomes.
5. Client Risk treats `rejected` and `cancelled` as bad outcomes.
6. The current Claim is excluded from its own historical calculations.
7. Soft-deleted Claims are naturally excluded by the normal Eloquent queries.
8. Risk is recalculated asynchronously for automatic Claim create/update events.
9. Manual recalculation is available through the Risk API.
10. Every calculation creates a historical snapshot.
11. Risk version `1.0` is persisted for the current Claim and history.
12. V1 does not make approval/rejection/assignment decisions.

---

# 29. Current API Capability Summary

| Capability | Status |
|---|---|
| Calculate Claim Risk | Implemented |
| Automatic calculation on Claim creation | Implemented |
| Automatic recalculation on risk-related updates | Implemented |
| Background Job | Implemented |
| Manual recalculation | Implemented |
| Risk score | Implemented |
| Risk level | Implemented |
| Factor breakdown | Implemented |
| Factor weights | Implemented |
| Factor contributions | Implemented |
| Calculation timestamp | Implemented |
| Risk history | Implemented |
| Trigger tracking | Implemented |
| Calculated-by tracking | Implemented |
| Risk versioning | Implemented |
| Claim Risk API | Implemented |
| Risk Summary API | Implemented |
| Decision Engine | Not part of V1 |
| Assignment integration | Not part of V1 |
| Automatic approval/rejection | Not part of V1 |

---

# 30. V1 Release Position

## What can be demonstrated to management

The first version can be demonstrated as:

> **An explainable, auditable Risk Assessment engine that automatically evaluates Claims, stores the current risk snapshot, preserves calculation history, exposes the reasons behind the score, and provides management-level risk distribution statistics.**

The feature currently provides the foundation required for a future Decision Engine without coupling risk calculation to business actions.

## Recommended next phase

After V1 acceptance, the next phase can define separately:

1. Decision rules based on risk levels.
2. Required actions for High/Medium/Low risk.
3. Assignment/routing rules.
4. Escalation rules.
5. Threshold calibration using real historical production data.
6. Reassessment of the City Risk minimum sample size.
7. Additional statistical validation of the scoring model.

These are **future-phase decisions**, not part of the current V1 calculation engine.
