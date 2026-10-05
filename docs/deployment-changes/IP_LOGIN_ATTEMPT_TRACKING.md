# IP-Based Login Attempt Tracking & Lockout

## Problem

The verification-code login flow previously rate-limited failed attempts based on `claim_id`. This only protected against brute-forcing **a single claim**, but an attacker could rotate the `link` / `verification_code` (i.e., target different claims) from the same IP with no cumulative limit, opening the door to a system-wide **enumeration attack**.

## Solution

Claim-based rate limiting was replaced with **IP-based** rate limiting, with an escalating lockout duration each time the same IP gets blocked again.

---

## How It Works

### 1. Attempt Logging

Every login attempt (successful or failed) is recorded in the `login_attempts` table along with its `ip_address`, for audit/history purposes.

### 2. Lock State

A new `ip_login_locks` table holds the lockout state for each IP independently:

| Column            | Purpose                                                                  |
| ----------------- | ------------------------------------------------------------------------ |
| `ip_address`      | The IP address of the request                                            |
| `failed_attempts` | Current count of failed attempts (before reaching the lockout threshold) |
| `block_count`     | Cumulative number of times this IP has been locked out                   |
| `locked_until`    | Timestamp when the current lockout expires (if any)                      |

### 3. Escalation

| Lockout occurrence  | Lockout duration |
| ------------------- | ---------------- |
| 1st time            | 5 minutes        |
| 2nd time            | 10 minutes       |
| 3rd time and beyond | 60 minutes       |

- Once an IP reaches the allowed number of failed attempts (default: 5), it gets locked out according to the table above.
- On a successful login, the IP's lock record (`failed_attempts`, `block_count`, `locked_until`) is deleted entirely from `ip_login_locks`, so the IP starts fresh from the 1st escalation tier the next time it fails.
- **Trade-off (superseded decision):** an earlier version of this feature preserved `block_count` after a successful login, specifically to prevent an attacker from resetting the escalation ladder with a single lucky/successful guess after being locked out. That protection was intentionally dropped in favor of a cleaner UX for legitimate users who mistype their code a few times before getting it right. If abuse via this path becomes a real problem, consider only resetting `block_count` when the success happens outside of an active lockout window.

### 4. Configuration

All the tunable numbers (attempt threshold, lockout durations) live in `config/login_attempts.php`:

```php
<?php

return [
    'login_max_attempts_per_ip' => env('LOGIN_MAX_ATTEMPTS_PER_IP', 5),

    'lock_durations_minutes' => [
        1 => 5,
        2 => 10,
    ],

    'login_lock_duration_default_minutes' => env('LOGIN_LOCK_DURATION_DEFAULT_MINUTES', 60),
];
```

- `login_max_attempts_per_ip` and `login_lock_duration_default_minutes` can be adjusted via `.env` without touching any code.
- `lock_durations_minutes` (the 1st and 2nd lockout durations) is kept as a plain array in the config file since it's an array and rarely changes. If a new escalation stage is needed (e.g., a differently-timed 3rd lockout), add it here directly.

#### `.env` Variables

```dotenv
LOGIN_MAX_ATTEMPTS_PER_IP=5
LOGIN_LOCK_DURATION_DEFAULT_MINUTES=60
```

| Variable                              | Purpose                                                      | Default |
| ------------------------------------- | ------------------------------------------------------------ | ------- |
| `LOGIN_MAX_ATTEMPTS_PER_IP`           | Number of failed attempts allowed before lockout             | 5       |
| `LOGIN_LOCK_DURATION_DEFAULT_MINUTES` | Lockout duration (in minutes) from the 3rd occurrence onward | 60      |

---

## Files Changed

- `app/Services/LoginAttemptService.php` — Core logic for logging attempts and computing lockouts
- `app/Models/IpLoginLock.php` — New model for per-IP lock state
- `app/Http/Controllers/Api/V1/DebtorController.php` — Checks lockout status before processing the verification code
- `config/login_attempts.php` — Attempt threshold and lockout duration settings
- `database/migrations/..._add_ip_address_column_to_login_attempts_table.php`
- `database/migrations/..._create_ip_login_locks_table.php`

---

## Important Notes

1. **You must run migrations** after pulling the update:

    ```bash
    php artisan migrate
    ```

2. **If `config:cache` is enabled** (typically in production), run:

    ```bash
    php artisan config:clear
    php artisan config:cache
    ```

    so the new configuration values are picked up correctly.

3. Lockout now depends solely on IP, not on the claim — meaning if someone tries wrong codes across different claims from the same device, they'll get locked out after the configured attempt count, regardless of which claims were targeted.

4. If a 4th escalation stage is needed (e.g., 6 hours after the 4th lockout), it can be added in `config/login_attempts.php` without any code changes.
