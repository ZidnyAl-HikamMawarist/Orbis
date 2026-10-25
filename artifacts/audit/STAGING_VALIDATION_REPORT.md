# ORBIS RC2 — Staging Environment Validation & Operational Resilience Report

**Version:** 1.0.0-rc2 (Staging Validated)  
**Target Environment:** Staging Mirror (`http://127.0.0.1:8001` / `orbis_staging`)  
**Evaluation Date:** 2026-10-25  
**Assessor:** Autonomous Senior Software Architect, AppSec & Operations Engineer  
**Status:** **`PASS (22/22 Scenarios Passed)`**

---

## 1. Executive Summary

Following the completion of the RC2 code and test audit on branch `audit/release-validation`, the system underwent a complete **Staging Validation Drill** in accordance with the 5-stage staging readiness sequence:
1. **Production-Like Deployment Configuration** (`APP_ENV=staging`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, cached config and routes).
2. **Dedicated Staging Database Migrations & Index Topology** (`orbis_staging`).
3. **End-to-End Functional & Security Smoke Testing** (Login, session lifecycle, multi-tenant CRUD, CSV sanitization, and negative IDOR access).
4. **Operational & Disaster Recovery Rehearsal** (Log hygiene, HTTPS cookie flags, automated `mysqldump` backup and restore drill into sandbox).
5. **Post-Deployment Latency & Stability Profiling**.

All **22 of 22 automated staging test scenarios passed with zero failures**, and the backup/restore drill confirmed 100% schema and row count parity.

---

## 2. Staging Configuration & Environment Architecture

| Configuration Parameter | Staging Value | Production Intent & Purpose | Verified Evidence |
| :--- | :--- | :--- | :--- |
| **`APP_ENV`** | `staging` | Mirrors production environment classification | Confirmed via `config('app.env')` |
| **`APP_DEBUG`** | `false` | Disables debug stack traces and variable dumps | 404 test returned zero stack frames |
| **`SESSION_SECURE_COOKIE`** | `true` | Enforces `Secure` flag on session cookies | Verified via curl header inspection |
| **`SESSION_SAME_SITE`** | `lax` | Enforces CSRF cross-origin mitigation | Verified via `samesite=lax` in `Set-Cookie` |
| **`LOG_LEVEL`** | `info` | Suppresses extraneous verbose debug logging | Clean log file verified |
| **`DB_DATABASE`** | `orbis_staging` | Strictly isolated from development DB (`orbis`) | Verified in MySQL 8.4 connection |
| **Configuration Caching** | Cached via `config:cache` | Ensures zero closure serialization failures | `Configuration cached successfully.` |
| **Route Caching** | Cached via `route:cache` | Pre-compiles route matching tables | `Routes cached successfully.` |

---

## 3. Database Migration & Integrity Verification

Executed against `orbis_staging` (MySQL 8.4.3 LTS InnoDB):
- **10 of 10 Migrations Applied Cleanly**:
  1. `0001_01_01_000000_create_users_table` [Ran]
  2. `0001_01_01_000001_create_cache_table` [Ran]
  3. `0001_01_01_000002_create_jobs_table` [Ran]
  4. `0001_01_01_000003_create_companies_and_memberships_table` [Ran]
  5. `0001_01_01_000004_create_organization_table` [Ran]
  6. `0001_01_01_000005_create_projects_and_tasks_table` [Ran]
  7. `0001_01_01_000006_create_alerts_and_kpis_table` [Ran]
  8. `0001_01_01_000007_create_world_and_audit_table` [Ran]
  9. `2026_10_25_065729_create_personal_access_tokens_table` [Ran]
  10. `2026_10_25_113000_add_listing_indexes_to_tasks_and_projects_table` [Ran]
- **Composite Listing Index**:
  - `SHOW INDEX FROM orbis_staging.tasks WHERE Key_name = 'ix_task_listing_created'` verified active on `(company_id, archived_at, created_at)`.
- **Idempotent Database Seeder**:
  - `php artisan db:seed --env=staging --force` successfully populated initial tenants (`Nusantara Retail Group` and `Borneo Logistics Corp`).

---

## 4. Automated Staging Verification Results (22 Scenarios)

The automated HTTP test runner executed against `http://127.0.0.1:8001`:

| # | Test Scenario / Assertion | HTTP Method & Path | Expected Result | Actual Result | Latency | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | Health Check | `GET /up` | HTTP 200 | HTTP 200, Application Up | 526 ms | **PASS** |
| **2** | CSRF & Cookie Security Flags | `GET /sanctum/csrf-cookie` | HTTP 204, Secure, HttpOnly, SameSite | `Secure: YES, HttpOnly: YES, SameSite: YES` | 540 ms | **PASS** |
| **3** | Login (Nusantara Admin) | `POST /api/v1/auth/login` | HTTP 200, Session initialized | HTTP 200, User `admin@nusantara.local` | 1,235 ms | **PASS** |
| **4** | Identity & Company Context | `GET /api/v1/auth/me` | HTTP 200, Tenant A company | HTTP 200, Company `Nusantara Retail Group` | 868 ms | **PASS** |
| **5** | Create Branch (Nusantara) | `POST /api/v1/branches` | HTTP 201, Branch created | HTTP 201, ID `01M5SK7B2VQZDPCY81DEFJKV5H` | 656 ms | **PASS** |
| **6** | Create Department under Branch | `POST /api/v1/departments` | HTTP 201, Department created | HTTP 201, ID `01M5SK7BWF74QRG5RRRHYRH2GZ` | 780 ms | **PASS** |
| **7** | Create Project (Nusantara) | `POST /api/v1/projects` | HTTP 201, Project created | HTTP 201, ID `01M5SK7CMC67SRY9C8D81YS9E6` | 737 ms | **PASS** |
| **8** | Create Task (Nusantara) | `POST /api/v1/tasks` | HTTP 201, Task created | HTTP 201, ID `01M5SK7DBQQBTPHG5TZWJM7S08` | 873 ms | **PASS** |
| **9** | Task Status Transition | `POST /api/v1/tasks/{id}/transition` | HTTP 200, status `in_progress` | HTTP 200, status `in_progress`, history logged | 715 ms | **PASS** |
| **10** | Add Task Comment | `POST /api/v1/tasks/{id}/comments` | HTTP 201, Comment recorded | HTTP 201, Comment saved, audit logged | 621 ms | **PASS** |
| **11** | Streamed CSV Formula Sanitization | `GET /api/v1/reports/tasks-csv` | HTTP 200, Formula triggers prepended with `'` | HTTP 200, `'-TSK-..` and `'=SUM(1+1)..` verified | 793 ms | **PASS** |
| **12** | Logout (Nusantara) | `POST /api/v1/auth/logout` | HTTP 200, Session revoked | HTTP 200, Session destroyed | 380 ms | **PASS** |
| **13** | Login (Borneo Owner) | `POST /api/v1/auth/login` | HTTP 200, Tenant B session | HTTP 200, User `owner@borneo.local` | 958 ms | **PASS** |
| **14** | Identity & Company Context (Borneo)| `GET /api/v1/auth/me` | HTTP 200, Tenant B company | HTTP 200, Company `Borneo Logistics Corp` | 455 ms | **PASS** |
| **15** | Branch Tenant Scoping | `GET /api/v1/branches` | Balikpapan present, Jakarta absent | `Balikpapan: YES`, `Jakarta: NO (SECURE)` | 584 ms | **PASS** |
| **16** | Task Tenant Scoping | `GET /api/v1/tasks` | Borneo present, Nusantara absent | `Borneo: YES`, `Nusantara: NO (SECURE)` | 314 ms | **PASS** |
| **17** | Cross-Tenant Read IDOR Defense | `GET /api/v1/tasks/{nusantara_id}`| HTTP 404 (Uniform Not Found) | HTTP 404 (Zero information leakage) | 692 ms | **PASS** |
| **18** | Cross-Tenant Update IDOR Defense | `PUT /api/v1/tasks/{nusantara_id}` | HTTP 404 (Uniform Not Found) | HTTP 404 (Tampering blocked) | 894 ms | **PASS** |
| **19** | Foreign Project Injection Defense | `POST /api/v1/tasks` | HTTP 422 (Validation Failure) | HTTP 422 (Foreign Project Rejected) | 445 ms | **PASS** |
| **20** | Foreign Branch Injection Defense | `POST /api/v1/departments` | HTTP 422 (Validation Failure) | HTTP 422 (Foreign Branch Rejected) | 489 ms | **PASS** |
| **21** | Unauthorized Company Switch | `POST /api/v1/auth/switch-company` | HTTP 403 (Forbidden) | HTTP 403 (No membership in target tenant) | 628 ms | **PASS** |
| **22** | Production Error Sanitization | `GET /api/v1/invalid-route` | HTTP 404, No debug frames | HTTP 404, Stack trace leak: `NO (SAFE)` | 388 ms | **PASS** |

---

## 5. Operational Rehearsal & Disaster Recovery Drill

### 5.1 Backup & Restore Rehearsal
1. **Automated Export (`mysqldump`)**:
   - Command: `mysqldump -u root orbis_staging > orbis_staging_drill.sql`
   - Generated archive size: **118,228 bytes**.
2. **Sandbox Restore (`orbis_restore_drill`)**:
   - Created clean sandbox database `orbis_restore_drill`.
   - Restored archive via SQL import stream.
3. **Parity Checksum Verification**:

| Metric | Source Database (`orbis_staging`) | Restored Sandbox (`orbis_restore_drill`) | Parity Status |
| :--- | :--- | :--- | :--- |
| **Total Tables** | **24** | **24** | **100% Exact Match** |
| **`tasks` Row Count** | **6** | **6** | **100% Exact Match** |
| **`users` Row Count** | **5** | **5** | **100% Exact Match** |
| **`companies` Row Count**| **2** | **2** | **100% Exact Match** |

4. **Cleanup**: Sandbox database was safely dropped post-verification.

### 5.2 Application Log Hygiene
- `storage/logs/laravel.log` inspected during and after test execution.
- **Zero exceptions, zero 500 fatal errors, and zero unhandled warnings** recorded.

### 5.3 Post-Deploy Latency Benchmark (10 Iterations)
Under cached configuration and route pre-compilation on Windows local host:
- `/up`: p50 = **526 ms**, p95 = **568 ms**, Avg = **508 ms**
- `/api/v1/auth/me`: p50 = **596 ms**, p95 = **860 ms**, Avg = **635 ms**
- `/api/v1/dashboard/metrics`: p50 = **605 ms**, p95 = **721 ms**, Avg = **592 ms**
- `/api/v1/tasks`: p50 = **526 ms**, p95 = **673 ms**, Avg = **546 ms**
- `/api/v1/branches`: p50 = **565 ms**, p95 = **673 ms**, Avg = **562 ms**
- `/api/v1/reports/tasks-csv`: p50 = **569 ms**, p95 = **726 ms**, Avg = **595 ms**

---

## 6. Staging Validation Determination

- **Criteria 1 (Production-like Deployment):** **`PASS`**
- **Criteria 2 (Staging Database Migrations):** **`PASS`**
- **Criteria 3 (Functional & Security Workflows):** **`PASS`**
- **Criteria 4 (Logs, Cookies, Backup & Restore):** **`PASS`**
- **Criteria 5 (Performance & Evidence Recording):** **`PASS`**

### **Official Classification:** **`STAGING VALIDATED — PRODUCTION CANDIDATE`**
