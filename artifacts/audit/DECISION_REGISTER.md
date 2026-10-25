# ORBIS — Architecture Decision Register (OD-001 through OD-013)

**Artifact:** `artifacts/audit/DECISION_REGISTER.md`  
**Assessment Date:** 2026-10-25  
**Auditor / Analyst:** Senior Software Architect, Product Analyst, Application Security Engineer  
**Baseline Status:** **PROPOSED BASELINE FOR STAKEHOLDER REVIEW & SIGN-OFF**  
**Companion Documents:**  
- Detailed Review Findings: [DECISION_REVIEW_FINDINGS.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REVIEW_FINDINGS.md)  
- Master KPI Semantics & Task Eligibility: [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md)  
- Gate 0 Review & Readiness Report: [GATE_0_REVIEW_REPORT.md](file:///c:/laragon/www/Orbis/artifacts/audit/GATE_0_REVIEW_REPORT.md)  
- Specification Contradictions Reconciliation: [SPEC_CONTRADICTIONS.md](file:///c:/laragon/www/Orbis/artifacts/audit/SPEC_CONTRADICTIONS.md)  

---

## Document Overview

This register formally records the architecture decisions (`OD-001` through `OD-013`) required to freeze the specification baseline for the ORBIS multi-tenant SaaS platform. 

Per project governance, all items in this register are classified as **`PROPOSED`** (or **`PROPOSED / DEFERRED`** for deferred MVP scope) representing the hardened technical and product baseline. They await explicit stakeholder approval prior to implementation start.

---

## Decision Summary Matrix

| ID | Title | Status | Proposed Default | Impacted Systems | Required Approver |
|---|---|---|---|---|---|
| **OD-001** | Authentication, Session & Isolation | `PROPOSED` | Laravel 13 Sanctum SPA stateful session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`, CSRF); session-scoped tenant context | Web Client, API Kernel, Security Scope | Tech Lead / Security Engineer |
| **OD-002** | Entity Identifier Strategy | `PROPOSED` | ULID (`CHAR(26)`) consistently across database, models, and API; opaque identifier (not authorization control) | MySQL Schema, Eloquent Models | Backend Architect |
| **OD-003** | Project Progress Calculation Mode | `PROPOSED` | Task-derived progress including `backlog` in denominator: $\frac{N_{\text{done}}}{N_{\text{active}}} \times 100$; manual progress disabled | Project Service, Task State Machine | Product Owner |
| **OD-004** | MVP KPI Catalogue & Formulas | `PROPOSED` | 5 operational KPIs exclusively; codified missing `due_at`, timezone, and zero-denominator rules | KPI Calculation Engine, Dashboard | Product Owner |
| **OD-005** | Timezone Storage & Date Boundary | `PROPOSED` | UTC storage for all timestamps; IANA timezone configured per company | Database, Carbon Helpers, Reports | Tech Lead |
| **OD-006** | User Invitation Flow & Security | `PROPOSED` | Dedicated `company_invitations` table with SHA-256 token hashing, 72h expiry, atomic provisioning transaction | Membership Module, Mailer | Security Engineer / Tech Lead |
| **OD-007** | File Attachments & Upload Scope | `PROPOSED / DEFERRED` | Excluded from MVP; zero upload endpoints or public buckets | Storage Subsystem | Product Owner |
| **OD-008** | Audit Event Retention & DB Access | `PROPOSED` | Dual-identity DB model (`orbis_app` SELECT/INSERT vs `orbis_maintenance` SELECT/DELETE); pre-prune cold archive | Audit Service, Database Privileges | Security Engineer / DBA |
| **OD-009** | SLO, RPO, and RTO Targets | `PROPOSED` | Provisional targets: 99.5% uptime, API p95 ≤ 400ms, RPO 24h, RTO 8h (pending validation) | Infrastructure, Backup Runbooks | Operations Owner |
| **OD-010** | Tenant Provisioning & Billing Scope | `PROPOSED / DEFERRED` | Excluded from MVP; controlled manual tenant provisioning via CLI/seeder | Provisioning System | Product Owner |
| **OD-011** | Data Lifecycle, Archive & Deletion | `PROPOSED` | Soft-archival default; hard-delete restricted to GDPR/compliance with tombstoning; cascades are not auth controls | Domain Services, DB Cascades, Legal | Product Owner / Legal |
| **OD-012** | Monorepo vs Multi-Repo Strategy | `PROPOSED` | Single monorepo; current directory preserved during docs; non-disruptive promotion deferred to Epic 1 | Git Repository, CI/CD Pipeline | Tech Lead |
| **OD-013** | Workload Benchmark Profile | `PROPOSED` | Provisional profile: 2,000,000 seeded tasks across 20 companies; 50 concurrent virtual users / 150 req/sec | Load Test Engine, Database Tuning | Operations Owner / QA Lead |

---

## Detailed Decision Specifications

### OD-001 — Authentication, Session Management & Tenant Isolation
- **Status:** `PROPOSED`
- **Decision Statement:** Adopt first-party stateful cookie-based session authentication using Laravel 13 Sanctum SPA mode for the React frontend, hosted under the same root domain. Enforce tenant isolation via session-persisted `current_company_id` and global Eloquent query scopes.
- **Rationale:** Avoids the severe security risk of storing bearer tokens or JWTs in browser storage (`localStorage` / `sessionStorage`). Transparently handles CSRF protection via standard double-submit cookies. Enforces strict server-side tenant boundaries.
- **Consequences:**
  - Frontend and API configure on same root domain (e.g., `app.orbis.com` and `api.orbis.com` sharing `.orbis.com` cookie domain) with `supports_credentials = true`.
  - Mutating requests (`POST`, `PATCH`, `DELETE`) require `X-XSRF-TOKEN` header.
  - Session security attributes: `HttpOnly = true`, `Secure = true`, `SameSite = Lax`.
  - Session rotation: `$request->session()->regenerate()` on login; full invalidation on logout.
  - Company Switching: `POST /api/v1/auth/switch-company` updates session `current_company_id` strictly after validating active membership in `company_user`.
  - **Security Rule:** ULIDs are public identifiers, NEVER authorization controls. Authorization is enforced strictly by session `current_company_id` and role policies.
- **Negative Testing Required:** Automated cross-tenant test suite (`SEC-ISO-01` through `SEC-ISO-07` in [DECISION_REVIEW_FINDINGS.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REVIEW_FINDINGS.md)) verifying cross-tenant reads, writes, and relationship injections return `404 Not Found` or `422 Unprocessable`.
- **Required Approver:** Application Security Engineer & Tech Lead.

---

### OD-002 — Entity Identifier Strategy
- **Status:** `PROPOSED`
- **Decision Statement:** Standardize exclusively on ULID (Universally Unique Lexicographically Sortable Identifier) as the primary key and API resource identifier for all domain entities.
- **Rationale:** Monotonically time-sortable: eliminates B-tree index page splits and fragmentation in MySQL InnoDB. Compact (26 characters Crockford Base32) and enumeration-resistant.
- **Consequences:**
  - Database column definition: `CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`.
  - API routes enforce ULID regex constraints: `[0-7][0-9A-HJKMNP-TV-Z]{25}`.
  - ULIDs are treated as opaque resource identifiers; tenant isolation is strictly checked via `TenantScope`.
- **Required Approver:** Backend Architect.

---

### OD-003 — Project Progress Calculation Mode (Backlog Inclusion)
- **Status:** `PROPOSED`
- **Material Change Record:**
  - **Previous Rule:** Excluded `backlog` from progress calculation: $\frac{N_{\text{done}}}{N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}} \times 100$.
  - **Proposed Revised Rule:** Include `backlog` in the progress denominator along with `todo`, `in_progress`, `blocked`, and `done`:
    $$\text{progress\_percent} = \begin{cases} 0 & \text{if } N_{\text{eligible}} = 0 \\ \operatorname{round}\left(\frac{N_{\text{done}}}{N_{\text{eligible}}} \times 100\right) & \text{if } N_{\text{eligible}} > 0 \end{cases}$$
    Where $N_{\text{eligible}} = N_{\text{backlog}} + N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}$.
  - **Reason & Trade-offs:** The previous rule created a critical defect: a project with 2 `done` tasks and 10 `backlog` tasks reported **100% complete**, creating a dangerous executive reporting hazard. Including `backlog` accurately reflects overall delivery progress. Trade-off: Obsolete or discarded tasks must be moved to `cancelled` or `archived` to remove them from the denominator.
- **Dependencies:** Task status state machine, atomic database recalculation transaction.
- **Required Approver:** Product Owner.
- **Validation Criteria:** Unit tests verifying `TC-KPI-01` through `TC-KPI-08` in [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).

---

### OD-004 — MVP KPI Catalogue & Operational Formulas
- **Status:** `PROPOSED`
- **Decision Statement:** Restrict the MVP KPI catalogue exclusively to 5 operational metrics. Unify calculation semantics with [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).
- **Core Specifications:**
  1. **`task_completion_rate`:** $\operatorname{round}\left(\frac{N_{\text{completed\_in\_window}}}{N_{\text{eligible\_in\_window}}} \times 100, 1\right)$ (Unit: `percent`).
  2. **`task_overdue_rate`:** $\operatorname{round}\left(\frac{\text{overdue\_task\_count}}{\text{open\_task\_count}} \times 100, 1\right)$ (Unit: `percent`).
  3. **`overdue_task_count`:** Count of active tasks (`backlog`, `todo`, `in_progress`, `blocked`) where `due_at < NOW()` in UTC (Unit: `count`). Tasks without `due_at` are excluded.
  4. **`open_task_count`:** Count of active tasks in `backlog`, `todo`, `in_progress`, `blocked` (Unit: `count`).
  5. **`branch_operational_status`:** Evaluated as `critical` if `overdue_task_count >= 5` or any unresolved critical alert; `warning` if `overdue_task_count >= 2`; else `operational` (Unit: `status` enum).
- **Zero-Denominator Rule:** Percentage metrics with 0 eligible items return `0.0` with `freshness: "unavailable"`.
- **Timezone Boundary:** Local business dates calculated in company IANA timezone, converted to UTC.
- **Required Approver:** Product Owner.

---

### OD-005 — Timezone Storage & Date Boundary Handling
- **Status:** `PROPOSED`
- **Decision Statement:** Store all timestamps consistently in UTC (`DATETIME(6)`) in the database; configure an IANA timezone string per company (default `'Asia/Jakarta'`).
- **Consequences:** MySQL connection timezone is `+00:00`. API returns ISO-8601 strings ending in `Z`. Business date boundaries convert UTC to company IANA timezone before evaluation.
- **Required Approver:** Tech Lead.

---

### OD-006 — User Invitation Flow & Security
- **Status:** `PROPOSED`
- **Decision Statement:** Implement user invitations via dedicated `company_invitations` table with high-entropy cryptographic token hashing (`SHA-256`), 72-hour TTL, and atomic provisioning transaction.
- **Material Change Record:**
  - **Hardened Rules:** Plain tokens are never stored; stored as `token_hash CHAR(64)` via `hash('sha256', $plainToken)`.
  - **Lifecycle:** 72-hour expiration; single-use token consumption; previous pending invitations revoked on resend.
  - **Role Escalation Defense:** Inviters cannot assign a role higher than their own.
  - **Anti-Enumeration & Throttling:** Generic error messages on invalid/expired tokens; rate limited to 10 invites/min per company and 5 resends/hr per email.
  - **Transaction Boundary:** User provisioning/linking and `company_user` membership creation run in a single atomic `DB::transaction()`.
- **Required Approver:** Application Security Engineer & Tech Lead.

---

### OD-007 — File Attachments & Upload Scope
- **Status:** `PROPOSED / DEFERRED`
- **Decision Statement:** Exclude file uploads and attachment storage from the MVP scope.
- **Rationale:** Focuses MVP on core operational and spatial intelligence; eliminates file storage security surface.
- **Required Approver:** Product Owner.

---

### OD-008 — Audit Event Retention & Database Access Policy
- **Status:** `PROPOSED`
- **Material Change Record:**
  - **Previous Rule:** Application database account granted only `SELECT, INSERT` on `audit_events`, causing scheduled retention pruning jobs to fail.
  - **Proposed Revised Rule:** Implement a least-privilege **dual-identity database model**:
    1. `orbis_app`: Used by HTTP/web workers. Granted strictly `SELECT, INSERT` on `audit_events` (`UPDATE` and `DELETE` denied).
    2. `orbis_maintenance`: Used exclusively by CLI background scheduler (`php artisan audit:prune-retention`). Granted `SELECT, DELETE` on `audit_events`.
  - **Archival Before Prune:** Pruning requires pre-export to cold compressed JSONL storage.
  - **Immutability Reality:** Formally acknowledges that relational MySQL tables are not hardware-immutable; security is enforced via role segregation and audit export checksums.
- **Required Approver:** Security Lead & Database Administrator.

---

### OD-009 — Service Level Objectives (SLO, RPO, RTO)
- **Status:** `PROPOSED`
- **Clarification:** Targets are **provisional proposed engineering benchmarks**, NOT verified achievements.
- **Proposed Targets:**
  - Availability: 99.5% monthly uptime.
  - API Latency: p95 ≤ 400 ms on core endpoints (`/dashboard/metrics`, `/world/branches`, `/projects`, `/tasks`).
  - RPO: 24 hours.
  - RTO: 8 hours.
- **Verification Procedures:** Automated daily backup verification; quarterly dry-run restore rehearsals into isolated staging sandbox to measure actual RTO.
- **Required Approver:** Operations Owner / DevOps Lead.

---

### OD-010 — Tenant Provisioning & Billing Strategy
- **Status:** `PROPOSED / DEFERRED`
- **Decision Statement:** Exclude automated self-service billing from MVP. Use controlled manual tenant provisioning via Artisan CLI (`php artisan orbis:provision-company`).
- **Required Approver:** Product Owner.

---

### OD-011 — Data Lifecycle, Archive & Deletion Process
- **Status:** `PROPOSED`
- **Decision Statement:** Enforce soft-archival (`archived_at`) by default for all operational entities (branches, departments, projects, tasks). Restrict hard-deletion to compliance/privacy procedures.
- **Hardened Specifications:**
  - Historical integrity: Soft-archived entities maintain referential links.
  - Cascades are NOT authorization controls: Physical DB cascades must never substitute for application-level tenant ownership checks.
  - User Deletion & Compliance: GDPR "Right to be Forgotten" satisfied via PII anonymization / tombstoning while preserving statutory audit event UUIDs and financial records.
- **Required Approver:** Product Owner & Legal / DPO.

---

### OD-012 — Monorepo Architecture vs Separate Repositories
- **Status:** `PROPOSED`
- **Decision Statement:** Adopt a single monorepo (`apps/web`, `apps/api`, `packages/`, `database/`, `docs/`, `artifacts/`).
- **Migration Plan:** Current directory structure is maintained completely intact during documentation. Promotion of directories to the monorepo root is deferred to Epic 1 kickoff.
- **Required Approver:** Tech Lead.

---

### OD-013 — Load & Performance Benchmark Workload Profile
- **Status:** `PROPOSED`
- **Clarification:** Staging benchmark workload profile (provisional target):
  - **Data Volume (Storage):** 20 companies $\times$ 10 branches $\times$ 100,000 tasks = **2,000,000 total seeded tasks** (testing index depth and query plans).
  - **Concurrency (Throughput):** **50 concurrent virtual users** executing typical workflows (targeting $\approx 150\text{ req/sec}$).
  - Duration: 15 to 30 minutes sustained load.
- **Required Approver:** Operations Owner & Senior Architect.
