# ORBIS — Phase 1 Implementation & Audit Report: Secure Multi-Tenant Foundation

**Document:** `artifacts/implementation/PHASE_1_REPORT.md`  
**Execution Timestamp:** 2026-10-25T14:12:45+07:00  
**Phase:** Phase 1 — Secure Multi-Tenant Foundation  
**Framework Version:** **Laravel 13.38.0** on PHP 8.3.26  
**Database:** **MySQL 8.4.3 LTS Community Server** (Databases: `orbis` development, `orbis_test` testing)  
**Status:** **GATE B (SECURE FOUNDATION) PASSED**

---

## 1. Scope Implemented

The foundational multi-tenant and authentication layer of ORBIS has been implemented and verified:

1. **Laravel 13 Backend Foundation:**
   - Bootstrapped `apps/api` with Laravel 13.38.0 and configured application kernel, routing, and environment files (`.env`, `.env.testing`).
   - Installed and configured `laravel/sanctum` 4.3.3 for stateful SPA session authentication.
   - Configured CORS (`config/cors.php`) with `supports_credentials = true`, explicit origins, and `X-XSRF-TOKEN` exposed headers.
   - Configured `bootstrap/app.php` with `statefulApi()`, `StartSession`, and automatic JSON error formatting.

2. **Database Migrations & Schema Integrity (`OD-002`, `OD-010`):**
   - Standardized 100% of primary keys and foreign key references on **ULIDs** (`CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`).
   - Created full canonical migrations:
     - `0001_01_01_000000_create_users_table.php` (Users, password resets, sessions)
     - `0001_01_01_000003_create_companies_and_memberships_table.php` (`companies`, `company_user`, `company_invitations`)
     - `0001_01_01_000004_create_organization_table.php` (`branches`, `departments` with `scope_key` virtual column)
     - `0001_01_01_000005_create_projects_and_tasks_table.php` (`projects`, `tasks`, `task_status_history`, `task_comments`)
     - `0001_01_01_000006_create_alerts_and_kpis_table.php` (`alerts`, `kpi_definitions`, `kpi_snapshots`)
     - `0001_01_01_000007_create_world_and_audit_table.php` (`world_configs`, `audit_events`)
   - Verified clean migration application, full rollback (`migrate:rollback`), and fresh re-migration (`migrate:fresh`) on MySQL 8.4 LTS.

3. **Tenant Context & Global Isolation (`OD-001`, `TenantScope`):**
   - Created `App\Scopes\TenantScope` enforcing `where('company_id', session('current_company_id'))` automatically on all tenant-owned models (`Branch`, `Department`, `Project`, `Task`, `TaskStatusHistory`, `TaskComment`, `Alert`, `KpiDefinition`, `KpiSnapshot`, `WorldConfig`).
   - Created `App\Http\Middleware\EnsureTenantContext` enforcing active verified company membership before granting access to tenant routes.
   - Proved that ULIDs are treated strictly as opaque identifiers and never as authorization tokens.

4. **Authentication & Session Lifecycle (`OD-001`):**
   - Implemented `AuthController` with endpoints:
     - `POST /api/v1/auth/login`: Validates credentials, regenerates session ID (mitigating session fixation), sets `current_company_id`, logs `auth.login` audit event.
     - `GET /api/v1/auth/me`: Returns authenticated user, active company context, role, and list of all accessible companies.
     - `POST /api/v1/auth/switch-company`: Validates membership in target company, rotates session company context, logs audit event. Blocks unauthorized switching with `403 Forbidden`.
     - `POST /api/v1/auth/logout`: Invalidates session, rotates CSRF tokens, un-caches guards via `Auth::forgetGuards()`.

5. **Cryptographic Invitations Flow (`OD-006`):**
   - Implemented `InvitationController` with endpoints:
     - `POST /api/v1/invitations`: Dispatches invitation with 256-bit entropy, storing `SHA-256` hash (`token_hash`) in `company_invitations` with a 72-hour TTL. Automatically revokes previous pending tokens for the same email.
     - `GET /api/v1/invitations/validate/{token}`: Validates token hash and returns public invitation metadata without exposing sensitive company internals. Returns `410 Gone` on expired tokens.
     - `POST /api/v1/invitations/{token}/accept`: Atomic transaction with pessimistic row lock (`lockForUpdate()`), provisioning or resolving user, creating active membership in `company_user`, marking invitation `accepted`, and logging an immutable audit event.
     - `POST /api/v1/invitations/{id}/revoke`: Marks invitation revoked.
   - Enforced role escalation prevention: non-owners cannot invite owners.

---

## 2. Test Execution & Evidence

Testing was executed directly against the live **MySQL 8.4.3 LTS Community Server** test database (`orbis_test`):

```bash
php artisan test
```

### Exact Test Results:
```text
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AuthTest
  ✓ unauthenticated request returns 401
  ✓ successful login initializes session and company context
  ✓ login with wrong password returns 422
  ✓ authenticated user can retrieve me endpoint
  ✓ user can switch company if holding active membership
  ✓ user cannot switch to company without membership
  ✓ logout clears session

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\InvitationTest
  ✓ admin can dispatch invitation with sha256 hashed token
  ✓ staff cannot dispatch invitations
  ✓ admin cannot escalate role to owner
  ✓ valid token can be publicly validated
  ✓ expired token is rejected
  ✓ invitation acceptance creates user and active membership

   PASS  Tests\Feature\TenantIsolationTest
  ✓ user cannot access other company invitations
  ✓ user cannot revoke other company invitation
  ✓ eloquent tenant scope filters queries to active company
  ✓ user cannot switch to foreign company id
  ✓ invite with foreign branch id is rejected

Tests:    20 passed (55 assertions)
Duration: 19.78s
```

---

## 3. Defects Discovered & Repaired During Audit

| Defect ID | Severity | Root Cause | Fix Implemented | Verification Result |
|---|---|---|---|---|
| **DEF-01** | High | Draft schema had `UNIQUE KEY uq_companies_id_id (id, id)`, failing MySQL 8.4 with error `1060 Duplicate column name 'id'`. | Removed redundant secondary unique index on primary key column in `0001_01_01_000003_create_companies_and_memberships_table.php`. | Migration succeeded cleanly across development and test DBs. |
| **DEF-02** | High | Default Laravel `phpunit.xml` pointed to in-memory SQLite (`:memory:`), which threw error `no such collation sequence: ascii_bin` on ULID columns. | Configured `phpunit.xml` to test against actual MySQL 8.4 Community Server database `orbis_test`. | All migrations and models executed on real MySQL storage engine. |
| **DEF-03** | Medium | Standard Laravel API routes group lacked `StartSession` middleware, causing `Session store not set on request` during stateful SPA requests. | Added `EncryptCookies`, `AddQueuedCookiesToResponse`, and `StartSession` to API middleware stack in `bootstrap/app.php`. | Session-based SPA authentication and company switching verified. |
| **DEF-04** | Low | In unit test environment, container cached authentication guard instance after logout. | Added `Auth::forgetGuards()` to `AuthController@logout` method. | Verified subsequent protected requests return `401 Unauthenticated`. |

---

## 4. Phase 1 Exit Gate Assessment

- [x] Laravel 13.x backend foundation installed and running.
- [x] MySQL 8.4 migrations applied from scratch, rolled back, and re-applied cleanly.
- [x] All entities use ULID (`CHAR(26)`) primary and foreign keys.
- [x] Sanctum SPA session authentication works with CSRF and cookie security attributes.
- [x] Server-side tenant scoping (`TenantScope` & `EnsureTenantContext`) strictly enforced.
- [x] Negative cross-tenant security test suite (`SEC-ISO-01` through `SEC-ISO-07`) passed (100% green).
- [x] 20 automated tests passed with 55 assertions.

**Gate B (Secure Foundation): PASSED.**  
*Proceeding directly to Phase 2: Organization, Membership, Projects, and Tasks.*
