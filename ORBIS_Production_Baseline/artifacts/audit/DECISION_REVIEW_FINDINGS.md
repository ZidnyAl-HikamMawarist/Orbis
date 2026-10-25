# ORBIS — Decision Register Review Findings & Specification Hardening

**Document:** `artifacts/audit/DECISION_REVIEW_FINDINGS.md`  
**Review Date:** 2026-10-25  
**Reviewers:** Senior Software Architect, Application Security Engineer, Product Analyst  
**Context:** Gate 0 Human Review & Pre-Implementation Hardening  
**Framework Baseline:** **Laravel 13.x (Mandatory)**  
**Status:** **PROPOSED BASELINE FOR STAKEHOLDER SIGN-OFF** (All decisions remain `PROPOSED` until explicit human authorization)

---

## 1. Executive Summary & Review Scope

Following the completion of the ORBIS specification reconciliation and the creation of decisions `OD-001` through `OD-013` in [DECISION_REGISTER.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md), a rigorous architectural, security, and product analysis review was conducted to address seven critical implementation risks:

1. **Review A: OD-003 & OD-004 — Progress & KPI Consistency:** Elimination of the "backlog exclusion dilemma" where projects with unstarted work falsely report 100% completion. Unification with [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).
2. **Review B: OD-008 — Audit Retention & Database Privilege Architecture:** Resolution of the operational conflict between application-tier `INSERT/SELECT` only permissions and background retention pruning. Formal rejection of absolute database immutability claims for mutable relational tables.
3. **Review C: OD-001 & Tenant Isolation:** Production specification for Laravel 13 Sanctum SPA stateful session/CSRF authentication. Verification of cookie policies, CORS, session rotation, company switching, and establishment of a comprehensive negative cross-tenant test suite (rejecting ULIDs as security boundaries).
4. **Review D: OD-006 — Invitations Security & Transaction Boundaries:** Cryptographic token hashing (`SHA-256`), 72-hour expiry, atomic user provisioning/membership linking, role escalation prevention, and anti-enumeration controls.
5. **Review E: OD-009 & OD-013 — Performance & Disaster Recovery Targets:** Formal clarification that SLOs, RTO, RPO, and benchmark profiles are **provisional proposed test targets**, not verified achievements. Clarification of 2,000,000 seeded tasks across 20 companies vs 50 concurrent virtual users.
6. **Review F: OD-011 — Data Lifecycle (Archival vs Hard-Deletion):** Entity-by-entity retention policy, historical reference preservation (tombstoning/snapshots), cascading restrictions (`RESTRICT` vs `CASCADE`), and legal compliance boundaries (GDPR vs statutory audit).
7. **Review G: OD-012 — Repository Monorepo Structure:** Pragmatic assessment of current workspace layout vs target monorepo, defining a zero-disruption migration strategy deferred to Epic 1 kickoff.

---

## 2. Review Findings & Technical Hardening

### Review A: OD-003 & OD-004 — Progress and KPI Consistency

#### 1. The Core Defect ("Backlog Exclusion Dilemma")
In the initial draft of `OD-003`, project progress was proposed as:
$$\text{progress\_percent} = \frac{N_{\text{done}}}{N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}} \times 100$$
*(Excluding tasks in `backlog`)*

**Impact Analysis:**
- If a project contains 2 completed tasks and 10 planned tasks residing in `backlog`, the formula evaluated to $\frac{2}{2} \times 100 = \mathbf{100\%}$.
- This presented a critical operational hazard: executives, branch managers, and 3D department views would observe a "100% Complete" green status on projects that had barely commenced.
- Furthermore, when a task was later moved from `backlog` to `todo`, project progress would artificially plummet (e.g. from 100% down to 66%), confusing end-users and distorting operational reporting.

#### 2. Reconciled Architectural Standard
- **Unified Rule:** All committed tasks attached to a project (`project_id IS NOT NULL`) represent scope. Therefore, **`backlog` must be included in the denominator** of project progress. Only `cancelled` and `archived` tasks are excluded from the denominator.
- **Formula:**
  $$\text{progress\_percent} = \begin{cases} 0 & \text{if } N_{\text{eligible}} = 0 \\ \operatorname{round}\left(\frac{N_{\text{done}}}{N_{\text{eligible}}} \times 100\right) & \text{if } N_{\text{eligible}} > 0 \end{cases}$$
  Where $N_{\text{eligible}} = N_{\text{backlog}} + N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}$.
- **Detailed Specifications:**
  The complete task eligibility matrix across all 6 workflow statuses, missing `due_at` rules, timezone conversions, zero-denominator fallbacks, and golden test cases are codified in [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).

#### 3. Material Change Record (OD-003)
- **Decision ID:** `OD-003`
- **Previous Rule:** Project progress excluded `backlog` and only evaluated `todo`, `in_progress`, `blocked`, and `done`.
- **Proposed Revised Rule:** Project progress includes `backlog` in the denominator along with `todo`, `in_progress`, `blocked`, and `done`. Excludes only `cancelled` and `archived` tasks.
- **Reason & Trade-offs:** Prevents deceptive 100% progress reporting on partially planned projects. Trade-off: Teams must consciously cancel or archive obsolete tasks to remove them from denominator.
- **Required Human Approver:** Product Owner / Head of Product.
- **Tests / Evidence Needed:** Unit tests validating `TC-KPI-01` through `TC-KPI-08` in [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).
- **Current Status:** `PROPOSED` (Pending human approval).

---

### Review B: OD-008 — Audit Retention and Database Privilege Architecture

#### 1. The Architectural Conflict
`OD-008` specified that the application database account should strictly possess `SELECT` and `INSERT` privileges on `audit_events` to enforce append-only immutability. Simultaneously, `OD-008` required a 90-day retention pruning job to purge expired audit records.
An application connection lacking `DELETE` permissions cannot execute pruning queries, causing the scheduled retention task to fail with MySQL error `1142 (42000): DELETE command denied to user`.

#### 2. Reality of Relational Database Immutability
**Engineering Caution:** Standard MySQL 8.x InnoDB tables cannot be rendered completely immutable against database administrators (`root`), privileged accounts, or storage tampering. True regulatory immutability requires external Write-Once-Read-Many (WORM) storage (e.g. AWS S3 Glacier Vault Lock or hardware-enforced append-only logs). Within the ORBIS application layer, immutability is defined as **least-privilege segregation of duties**.

#### 3. Reconciled Least-Privilege Architecture: Dual-Identity Model
To resolve the conflict while enforcing defense-in-depth, database access is divided between two distinct MySQL user identities:

```
                    ┌───────────────────────────────┐
                    │  ORBIS HTTP API App Worker    │
                    │   (Laravel 13 Web / Octane)   │
                    └──────────────┬────────────────┘
                                   │ Connects as: `orbis_app`
                                   │ Grants: SELECT, INSERT (NO UPDATE, NO DELETE on audit_events)
                                   ▼
                    ┌───────────────────────────────┐
                    │ MySQL 8.4 `audit_events`      │
                    │ (Append-Only for Web Traffic) │
                    └──────────────▲────────────────┘
                                   │ Connects as: `orbis_maintenance`
                                   │ Grants: SELECT, DELETE (Only for Pruning & Maintenance)
                                   │ Executed via: Dedicated CLI Artisan Command in isolated VPC/cron
                    ┌──────────────┴────────────────┐
                    │  ORBIS Scheduled CLI Runner   │
                    │   (`php artisan audit:prune`) │
                    └───────────────────────────────┘
```

1. **Application Identity (`orbis_app`):**
   - **Used by:** Laravel web requests, queue workers handling customer traffic.
   - **Permissions on `audit_events`:** `GRANT SELECT, INSERT ON orbis.audit_events TO 'orbis_app'@'%';`
   - **Enforcement:** `UPDATE` and `DELETE` privileges are strictly revoked. Even if an SQL injection vulnerability were present in a web endpoint, an attacker cannot mutate or purge audit trails.

2. **Maintenance / Retention Identity (`orbis_maintenance`):**
   - **Used by:** Dedicated background cron/scheduler executing `php artisan audit:prune-retention`.
   - **Permissions on `audit_events`:** `GRANT SELECT, DELETE ON orbis.audit_events TO 'orbis_maintenance'@'127.0.0.1';`
   - **Execution Constraints:**
     - The maintenance credential is stored in a separate environment variable (`DB_MAINTENANCE_PASSWORD`) accessible only to the CLI scheduler context, never exposed to web request workers.
     - Pruning must be preceded by an automated export to cold storage (e.g., compressed JSON-lines uploaded to secured object storage) before record removal.
     - Archival summaries (records pruned count, date range, cryptographic checksum of exported data) are inserted into `audit_prune_logs`.

#### 4. Failure Monitoring & Negative Testing
- **Pruning Failure Alerting:** The `audit:prune-retention` command emits Prometheus metrics and dispatches an alert (PagerDuty/Slack) if the job fails, times out, or detects un-exported records.
- **Automated Negative Test (`AuditImmutabilityTest`):**
  A continuous integration test connects using the standard `orbis_app` test connection and attempts:
  - `DB::table('audit_events')->where('id', $id)->update(['action' => 'tampered']);` $\rightarrow$ Asserts `QueryException` (Access denied).
  - `DB::table('audit_events')->where('id', $id)->delete();` $\rightarrow$ Asserts `QueryException` (Access denied).

#### 5. Material Change Record (OD-008)
- **Decision ID:** `OD-008`
- **Previous Rule:** Application database account granted only `SELECT, INSERT` on `audit_events`, creating an unresolvable failure in scheduled deletion.
- **Proposed Revised Rule:** Implement a dual-identity model: `orbis_app` has `SELECT, INSERT` (strictly append-only for web workers); `orbis_maintenance` possesses `SELECT, DELETE` and is restricted to isolated CLI scheduled tasks with pre-pruning cold archival. Acknowledge that absolute database immutability is external to MySQL.
- **Reason & Trade-offs:** Solves retention pruning while maintaining defense-in-depth against web-layer tampering. Trade-off: Requires configuring and rotating two sets of database credentials.
- **Required Human Approver:** Security Lead & Database Administrator / Operations Lead.
- **Tests / Evidence Needed:** CI tests verifying permission rejection for `orbis_app` and successful retention execution for `orbis_maintenance`.
- **Current Status:** `PROPOSED` (Pending human approval).

---

### Review C: OD-001 & Tenant Isolation

#### 1. Laravel 13 Sanctum SPA Session & CSRF Architecture
For the decoupled React + Vite SPA on the primary domain and Laravel 13 API on the backend, authentication utilizes Sanctum stateful session cookies:

1. **Pre-flight CSRF Handshake:**
   - Frontend initiates `GET /sanctum/csrf-cookie` with `credentials: 'include'`.
   - Laravel generates and sets an encrypted `XSRF-TOKEN` cookie (readable by JavaScript).
2. **Login & Session Rotation:**
   - User posts credentials to `POST /api/v1/auth/login`.
   - Upon authentication, Laravel invokes `$request->session()->regenerate()` to mitigate session fixation attacks.
   - Sets the stateful session cookie (`orbis_session`) with security attributes:
     - `HttpOnly = true` (inaccessible to malicious JavaScript / XSS).
     - `Secure = true` (enforced via HTTPS in staging/production).
     - `SameSite = Lax` (safeguards against cross-site request forgery while permitting top-level navigations).
     - `Domain = .orbis.local` (or production apex domain, permitting cookie sharing across `app.orbis.com` and `api.orbis.com`).
3. **CORS & Trusted Origins:**
   - `config/cors.php`: `supports_credentials = true`, `allowed_origins = ['https://app.orbis.com']`.
   - Wildcards (`*`) are strictly prohibited in `allowed_origins` when `supports_credentials` is active.
4. **Company Switching Flow:**
   - A user member of multiple companies switches context via `POST /api/v1/auth/switch-company` payload `{ "company_id": "01JAX..." }`.
   - **Verification:** Backend verifies active membership in `company_user` for `(user_id, company_id)`.
   - Updates session state: `session(['current_company_id' => $targetCompanyId])`.
   - Does NOT issue a new session token, but re-evaluates company-level permissions and returns updated company payload.
5. **Logout Flow:**
   - `POST /api/v1/auth/logout` invalidates session: `Auth::guard('web')->logout()`, `$request->session()->invalidate()`, `$request->session()->regenerateToken()`.

#### 2. ULIDs are NOT Authorization Controls
**Architectural Rule:** Universally Unique Lexicographically Sortable Identifiers (ULIDs) provide decentralization and temporal sorting. **A ULID is never a secret or an authorization token.** Every single incoming request must be scoped to the authenticated tenant context derived from the session (`session('current_company_id')`), completely independent of the route parameter.

#### 3. Negative Tenant Isolation Test Suite Specification
The test suite must include dedicated integration tests that explicitly attempt cross-tenant security breaches:

| Test ID | Method & Route | Attack Scenario | Expected Response | Enforcing Layer |
|---|---|---|:---:|---|
| **SEC-ISO-01** | `GET /api/v1/projects/{tenant_b_ulid}` | Tenant A attempts to read Tenant B project | `404 Not Found` (Anti-enumeration) | Eloquent Global Scope (`TenantScope`) |
| **SEC-ISO-02** | `PATCH /api/v1/tasks/{tenant_b_ulid}` | Tenant A attempts to modify Tenant B task | `404 Not Found` | Eloquent Global Scope |
| **SEC-ISO-03** | `DELETE /api/v1/branches/{tenant_b_ulid}` | Tenant A attempts to delete Tenant B branch | `404 Not Found` | Eloquent Global Scope |
| **SEC-ISO-04** | `POST /api/v1/tasks` | Tenant A attempts to inject foreign `project_id` or `branch_id` belonging to Tenant B | `422 Unprocessable` (Validation Rule `exists:projects,id,company_id,...`) | Custom Form Request Validator |
| **SEC-ISO-05** | `POST /api/v1/auth/switch-company` | User attempts to switch to `company_id` where no active `company_user` record exists | `403 Forbidden` | CompanySwitchPolicy |
| **SEC-ISO-06** | `GET /api/v1/dashboard/metrics` | Tenant A requests dashboard metrics; verifies no Tenant B task counts pollute aggregations | `200 OK` (Only Tenant A data) | Raw SQL / Eloquent tenant filtering |
| **SEC-ISO-07** | `POST /api/v1/invitations/{token}/accept` | User accepts invitation while authenticated under an unrelated company | `200 OK` (Joins invited company, switches context cleanly) | Atomic Acceptance Service |

---

### Review D: OD-006 — Invitations

#### 1. Cryptographic Token Handling & Storage
To protect pending invitations against database breach exposure:
- **Plain Token Generation:** 256-bit entropy using PHP `bin2hex(random_bytes(32))` (64 hexadecimal characters).
- **Storage:** Stored in `company_invitations.token_hash` as a **SHA-256 hash** (`hash('sha256', $plainToken)`). The plaintext token is never persisted.
- **Email Delivery:** The plaintext token is transmitted solely via invitation email link: `https://app.orbis.com/invitations/accept?token={plainToken}`.

#### 2. Lifecycle Rules & State Transitions
- **Single-Use Acceptance:** Upon successful acceptance, `company_invitations.status` transitions from `'pending'` to `'accepted'`, and `accepted_at` is set to `NOW()`. A token cannot be reused.
- **Revocation / Invalidation on Resend:**
  - If an admin resends an invitation to an existing email, any existing pending invitation for that `(company_id, email)` pair is immediately marked as `'revoked'` and a new token is generated.
- **Expiry Enforcement:** Invitations strictly expire **72 hours** from issuance (`expires_at = created_at + 72 hours`). Expired tokens yield `410 Gone` with code `INVITATION_EXPIRED`.
- **Role Assignment Authorization:**
  - An inviter cannot assign a role higher than their own (e.g., an `admin` cannot invite a `company_owner`).
  - Only `company_owner` and `admin` roles possess the `invitation:create` capability.
- **Anti-Enumeration Protections:**
  - When an unauthenticated actor queries `GET /api/v1/invitations/validate?token=...`, the response only returns the company name and invited email if the token is valid and unexpired.
  - Invalid, expired, or revoked tokens return identical generic `404 Not Found` or `410 Gone` messages to avoid timing or enumeration leaks.
- **Rate Limiting:**
  - `POST /api/v1/invitations` is throttled to **10 requests per minute per company**.
  - Resend endpoint is restricted to **5 requests per hour per target email**.

#### 3. Atomic Provisioning Transaction Boundary
The acceptance of an invitation must execute inside an ACID database transaction:

```php
DB::transaction(function () use ($plainToken, $password, $name) {
    $tokenHash = hash('sha256', $plainToken);
    
    // 1. Lock invitation record
    $invitation = CompanyInvitation::where('token_hash', $tokenHash)
        ->where('status', 'pending')
        ->where('expires_at', '>', now())
        ->lockForUpdate()
        ->firstOrFail();

    // 2. Resolve or provision user
    $user = User::where('email', $invitation->email)->first();
    if (! $user) {
        $user = User::create([
            'email' => $invitation->email,
            'name' => $name,
            'password_hash' => Hash::make($password),
            'status' => 'active',
        ]);
    }

    // 3. Prevent duplicate active membership
    $membership = CompanyUser::where('company_id', $invitation->company_id)
        ->where('user_id', $user->id)
        ->first();

    if ($membership) {
        throw new DuplicateMembershipException('User already belongs to this company.');
    }

    // 4. Attach company membership
    CompanyUser::create([
        'company_id' => $invitation->company_id,
        'user_id' => $user->id,
        'role' => $invitation->role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    // 5. Consume invitation
    $invitation->update([
        'status' => 'accepted',
        'accepted_at' => now(),
    ]);

    // 6. Audit event recording
    AuditEvent::log('invitation.accepted', [
        'company_id' => $invitation->company_id,
        'user_id' => $user->id,
        'invitation_id' => $invitation->id,
    ]);
});
```

---

### Review E: OD-009 & OD-013 — Performance and Recovery

#### 1. Status: Proposed Targets vs Verified Achievements
**Formal Clarification:** All SLO, latency, RTO, RPO, and capacity figures documented in `OD-009` and `OD-013` represent **proposed architectural targets for validation**, NOT verified production achievements. No production load tests have yet been executed on the Laravel 13 backend.

#### 2. Latency Measurement Conditions (p95 Target)
- **Target:** Server-side p95 response time $\le 400\text{ms}$ under standard load.
- **Measured Endpoints:**
  1. `GET /api/v1/dashboard/metrics` (Aggregated company KPIs)
  2. `GET /api/v1/world/branches` (3D island coordinates and status pills)
  3. `GET /api/v1/projects` (Paginated project list with progress)
  4. `GET /api/v1/tasks` (Filtered task grid)
- **Measurement Boundary:** Measured at the Laravel application gateway / reverse proxy (excluding client-side WebGL scene render time or wide-area network latency).
- **Environment:** Tested under baseline infrastructure (MySQL 8.4 with proper composite indexing and PHP 8.3/8.4 OPcache active).

#### 3. Clarification of Data Volume vs Concurrency
To eliminate ambiguity in `OD-013`:
- **Seeded Data Volume (Storage Footprint):**
  - **20 companies** $\times$ **10 branches** $\times$ **100 users** = **2,000 branches** and **2,000 users**.
  - **100,000 tasks per company** across 20 companies = **2,000,000 total task records** seeded into the database.
  - This benchmarks query planner efficiency, B-Tree index depth, and foreign key cascades on large multi-tenant tables.
- **Concurrent Active Virtual Users (Throughput Footprint):**
  - **50 concurrent virtual users** executing typical operational workflows (70% read / 20% update / 10% create).
  - Target throughput: $\approx 150\text{ requests per second}$ sustained over a 15-minute load window.

#### 4. Disaster Recovery Verification (RPO 24h / RTO 8h)
To validate RPO and RTO claims prior to production certification:
- **Backup Verification:** Automated nightly physical/logical backups (`mysqldump` or Percona XtraBackup) with SHA-256 integrity verification.
- **Restore Rehearsal:** Quarterly dry-run restore tests into an isolated sandbox to measure actual Recovery Time Objective (RTO) against the 8-hour target.
- **Failure Monitoring:** Heartbeat check alerting when backup generation is overdue (> 24 hours).

---

### Review F: OD-011 — Archive and Deletion

#### 1. Entity Lifecycle Policy (Archive vs Hard-Delete)
To prevent accidental data loss and maintain historical auditability, ORBIS enforces **soft-archival by default** across all primary business entities:

| Entity | Archival Mechanism (`archived_at`) | Hard-Delete Permitted? | Historical References Retained | Cascading Rule |
|---|---|:---:|---|---|
| **`companies`** | Soft-deactivate via `status = 'suspended'` | Only via Legal GDPR Request | All financial and audit logs | `RESTRICT` if active tasks/invoices exist |
| **`branches`** | `archived_at DATETIME` | No (Soft-archive only) | Historical tasks preserve `branch_id` | `RESTRICT` on children |
| **`departments`** | `archived_at DATETIME` | No (Soft-archive only) | Historical tasks preserve `department_id` | `RESTRICT` on children |
| **`projects`** | `archived_at DATETIME` | No (Soft-archive only) | Tasks retain `project_id` association | `RESTRICT` on tasks |
| **`tasks`** | `archived_at DATETIME` | Only via Explicit Admin Hard-Delete | Task activity logs retained | Comments/attachments `CASCADE` on hard delete |
| **`users`** | `status = 'deactivated'` | GDPR Anonymization / Tombstone | `audit_events.user_id` retained | `RESTRICT` on created records |
| **`audit_events`** | Pruned after 90 days to cold storage | No direct delete | Cold storage JSONL archives | None |

#### 2. Cascades are NOT Authorization Controls
Database foreign key cascade rules (`ON DELETE CASCADE`) are physical integrity mechanisms, **never security controls**. A hard-delete operation must explicitly verify company ownership across every affected relationship before issuing a query.

#### 3. Human Review Flags (Legal & Compliance)
- **GDPR / Right to be Forgotten:** If a user requests deletion, their PII (name, email) must be scrubbed or anonymized (`user_xxxx@deleted.local`), while their audit event UUIDs and timestamps are retained for statutory enterprise compliance.
- **Sign-off Required:** Requires formal review from Corporate Legal Counsel and Data Protection Officer (DPO).

---

### Review G: OD-012 — Repository Monorepo Structure

#### 1. Analysis of Current Workspace vs Monorepo Target
The current physical workspace is organized as:
```
c:\laragon\www\Orbis\
├── artifacts\audit\          <-- Audit artifacts, findings, matrix
├── ORBIS_Production_Baseline\ <-- Reconciled specs, docs, prototype, database schema
│   ├── docs\
│   ├── database\
│   ├── reference\prototype\
│   └── traceability\
```
The target architecture in `OD-012` calls for an integrated monorepo:
```
/
├── apps/
│   ├── api/                  <-- Laravel 13.x backend
│   └── web/                  <-- React + TypeScript + Vite frontend
├── packages/                 <-- Shared types, config
├── database/                 <-- Migrations, seeds
├── docs/                     <-- Specifications
└── artifacts/                <-- Audit records
```

#### 2. Recommended Migration Strategy: Zero Disruption
- **Pre-Implementation Rule:** **No files shall be moved, renamed, or restructured during this documentation phase.**
- **Migration Plan for Epic 1 Kickoff:**
  1. Once Gate 0 is approved by human stakeholders, initialize the monorepo root at `c:\laragon\www\Orbis`.
  2. Move `ORBIS_Production_Baseline/docs` $\rightarrow$ `docs/`.
  3. Move `ORBIS_Production_Baseline/database` $\rightarrow$ `database/`.
  4. Move `ORBIS_Production_Baseline/reference` $\rightarrow$ `reference/`.
  5. Scaffold `apps/api` using Laravel 13.x installer.
  6. Scaffold `apps/web` using Vite React-TS.
  7. Maintain `artifacts/` at root level.

---

## 3. Summary of Material Decision Changes

| Decision ID | Previous Specification | Proposed Revised Specification | Reason & Trade-offs | Human Approver | Required Evidence | Approval Status |
|---|---|---|---|---|---|:---:|
| **OD-003** | Project progress excluded `backlog` from calculation. | Project progress includes `backlog` in denominator: $\frac{N_{\text{done}}}{N_{\text{all\_active}}} \times 100$. | Prevents deceptive 100% progress on unstarted work. | Product Owner | `TC-KPI-01..08` Unit Tests | `PROPOSED` |
| **OD-004** | KPIs lacked explicit missing `due_at` and timezone rules. | Codified exact formulas, missing `due_at` handling, and IANA timezone conversion. | Ensures dashboard math matches individual project screens. | Product Owner | Golden KPI Matrix Tests | `PROPOSED` |
| **OD-006** | Plain invitation tokens stored; generic transactional flow. | Tokens hashed with SHA-256 (`token_hash`); 72h expiry; atomic user/membership creation. | Mitigates token leak risks on DB exposure; prevents partial states. | Security Lead | Invitation Acceptance Integration Test | `PROPOSED` |
| **OD-008** | Single DB user with `SELECT, INSERT` breaking retention pruning. | Dual identity: `orbis_app` (SELECT, INSERT) vs `orbis_maintenance` (SELECT, DELETE via CLI). | Enables automated pruning without weakening web worker isolation. | Security Lead & DBA | MySQL Permission Negative Tests | `PROPOSED` |
| **OD-009** | Targets (p95 $\le$ 400ms, RPO 24h, RTO 8h) stated without test disclaimers. | Formally classified as provisional proposed targets pending benchmark validation. | Avoids claiming unverified performance achievements. | Operations Lead | Baseline Benchmark Execution Report | `PROPOSED` |
| **OD-011** | Soft-delete vs hard-delete boundaries ambiguous. | Explicit per-entity retention matrix; soft-archive default; tombstoning for audit. | Protects audit integrity and complies with data protection norms. | Legal / DPO | Soft-Delete Scope Integration Tests | `PROPOSED` |
| **OD-013** | Seeded volume vs concurrent users conflated. | Separated: 2,000,000 seeded tasks (volume) vs 50 virtual users / 150 rps (concurrency). | Clarifies load testing scope and infrastructure sizing. | Senior Architect | K6 / Locust Load Test Plan | `PROPOSED` |

---

## 4. Conclusion & Next Steps

All identified risks from the initial specification freeze have been hardened with production-grade architectural and security standards. 
Before Gate 0 can close, explicit human review is required on:
1. Product Owner approval of the revised project progress formula (including `backlog`).
2. Security Lead approval of the dual-identity database permissions and invitation token hashing.
3. Legal sign-off on the data retention and user tombstoning model.

*Implementation (Laravel 13 scaffolding and Epic 1) remains strictly suspended until Gate 0 is formally signed off.*
