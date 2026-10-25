# ORBIS — Specification Contradictions, Discrepancies & Resolutions

**Artifact:** `artifacts/audit/SPEC_CONTRADICTIONS.md`  
**Assessment Date:** 2026-10-25  
**Auditor:** Senior Software Architect, Product Analyst, and Application Security Engineer  
**Status:** **RECONCILED BASELINE — ACTIONABLE RESOLUTIONS ESTABLISHED**

---

## 1. Executive Summary

A comprehensive multi-document audit across the 18 reference sources identified **12 critical architectural, functional, security, and schema contradictions**. 

These contradictions previously created ambiguity between the client prototype mockup, the draft SQL schema, and the written product specifications. This document catalogues each contradiction, cites the conflicting evidence, assesses the technical and business impact, and establishes the binding reconciled resolution for the production baseline.

---

## 2. Contradiction Inventory & Detailed Resolution Matrix

### Contradiction 1: Primary Key Representation — ULID vs UUID
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): `id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY`.
  - [`database/DATA_DICTIONARY.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/DATA_DICTIONARY.md): `CHAR(26) ULIDs`.
  - [`docs/API_CONTRACT.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/API_CONTRACT.md): Line 10 specifies "UUID/ULID recommended; choice must be consistent".
  - [`docs/DECISIONS.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/DECISIONS.md): `OD-002` lists ID strategy as Open ("ULID or UUID consistently").
- **Technical & Security Impact:** Mixing UUIDv4 and ULID across services causes routing mismatch, regex routing errors, unaligned foreign key column types, and B-tree index fragmentation in MySQL InnoDB.
- **Reconciliation / Decision:** **Adopt ULID (`CHAR(26)`) consistently across all database entities, Eloquent models, and API endpoints.**
  - MySQL representation: `CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`.
  - Laravel 13 integration: Use `Illuminate\Database\Eloquent\Concerns\HasUlids` on all models.
  - API validation: Route constraints use regex `[0-7][0-9A-HJKMNP-TV-Z]{25}`.
- **Decision Owner:** Tech Lead / Architect.

---

### Contradiction 2: Project Progress Calculation — Task-Derived vs Manual Mode
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): Line 93 defines `progress_mode ENUM('manual','task_derived') NOT NULL DEFAULT 'task_derived'`.
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): Line 86 states "Progress calculation must have one documented rule (manual percentage or derived from tasks); MVP must choose one per project and avoid silently mixing formulas."
  - [`docs/DECISIONS.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/DECISIONS.md): `OD-003` is marked open.
- **Technical & Business Impact:** Allowing manual progress alongside task-derived progress leads to data desynchronization where a project claims 100% completion while open or overdue tasks remain active.
- **Reconciliation / Decision:** **Freeze project progress calculation to `task_derived` exclusively for MVP.**
  - Formula:
    $$\text{progress\_percent} = \begin{cases} 0 & \text{if } N_{\text{eligible}} = 0 \\ \operatorname{round}\left(\frac{N_{\text{done}}}{N_{\text{eligible}}} \times 100\right) & \text{if } N_{\text{eligible}} > 0 \end{cases}$$
  - Where $N_{\text{eligible}} = \operatorname{count}(\text{tasks WHERE status } \in \{\text{'backlog', 'todo', 'in\_progress', 'blocked', 'done'}\})$.
  - Tasks in `cancelled` status and soft-archived tasks are excluded from both numerator and denominator.
  - **Hardening Review A Resolution:** Tasks in `backlog` **must be included in the denominator**. (Excluding `backlog` falsely produces 100% complete status when planned work remains unstarted; see [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md) and [DECISION_REVIEW_FINDINGS.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REVIEW_FINDINGS.md)).
  - `manual` mode is disabled and excluded from API mutation requests in MVP.
- **Decision Owner:** Product Owner / Tech Lead.

---

### Contradiction 3: Financial & POS Data in Prototype vs Operational Scope in PRD
- **Conflicting Sources:**
  - [`reference/prototype/index.html`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/reference/prototype/index.html): Displays "Rp 1.250.000.000 Revenue", "3,420 Orders", Gross Margin, Tax Reconciliation, Sales Conversion rates.
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): Section 4 explicitly lists as Non-Goal: "Full ERP, accounting ledger, payroll, inventory valuation or POS transaction processing." Section 12 notes: "Financial/POS KPIs are excluded until a trustworthy source integration is defined."
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): Contains zero tables or columns for revenue, ledger, orders, products, or sales transactions.
- **Technical & Security Impact:** If users expect financial figures without an ERP/POS ingestion pipeline, the system would rely on fabricated mock metrics, creating audit and compliance liabilities.
- **Reconciliation / Decision:** **Strictly limit MVP metrics to operational intelligence.**
  - Prototype financial figures are officially classified as simulated mockup artifacts.
  - The MVP KPI catalogue consists exclusively of the 5 operational metrics defined in `OD-004`:
    1. `task_completion_rate` (percentage)
    2. `task_overdue_rate` (percentage)
    3. `overdue_task_count` (integer)
    4. `open_task_count` (integer)
    5. `branch_operational_status` (operational index: good / warning / critical)
  - Revenue, profit, orders, and margin cards will be replaced in production with Operational Task Completion, SLA Health, and Incident Response metrics.
- **Decision Owner:** Product Owner.

---

### Contradiction 4: Authentication Architecture — Cookie Session vs Token Bearer
- **Conflicting Sources:**
  - [`docs/DECISIONS.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/DECISIONS.md): `OD-001` left open between Laravel session/cookie for same-site SPA vs bearer tokens.
  - [`docs/API_CONTRACT.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/API_CONTRACT.md): Line 5 mentions "authenticated session/cookie or selected token strategy; decision required".
  - [`docs/SECURITY.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/SECURITY.md): Line 29 advises "Avoid storing access tokens or sensitive user data in localStorage."
- **Technical & Security Impact:** Storing bearer JWT/tokens in browser `localStorage` exposes multi-tenant session tokens to Cross-Site Scripting (XSS) extraction.
- **Reconciliation / Decision:** **Mandate first-party Laravel session/cookie authentication via Laravel 13 Sanctum SPA mode.**
  - Cookies configured with `HttpOnly`, `Secure`, and `SameSite=Lax`.
  - CSRF protection enforced via `X-XSRF-TOKEN` header on all state-mutating requests (`POST`, `PATCH`, `DELETE`).
  - Session rotation executed on login (`$request->session()->regenerate()`).
  - Logout explicitly invalidates session and regenerates CSRF token.
  - Company switching updates `current_company_id` in the authenticated session only after server-side membership validation.
- **Decision Owner:** Security Engineer / Tech Lead.

---

### Contradiction 5: Department Scoping & MySQL Nullable Unique Index Behavior
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): Line 78 defines `UNIQUE KEY uq_department_code (company_id, branch_id, code)`.
  - Review Note (Line 287): "Nullable branch_id in unique keys allows multiple NULLs; enforce company-wide department uniqueness in application logic or use a generated normalized scope key."
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): Section 6 allows departments to be company-wide (`branch_id IS NULL`) or branch-specific.
- **Technical & Database Impact:** In standard SQL and MySQL InnoDB, `NULL != NULL`. As a result, multiple departments can be created with identical `(company_id, NULL, 'IT')`, violating business uniqueness.
- **Reconciliation / Decision:** **Implement dual-layer uniqueness enforcement:**
  1. **Application Layer:** Enforce strict uniqueness in Laravel Form Requests:
     ```php
     Rule::unique('departments')->where(fn ($query) => 
         $query->where('company_id', $companyId)
               ->where('branch_id', $branchId)
     );
     ```
  2. **Database Migration Layer:** In Laravel 13 migrations, implement a virtual generated column:
     ```php
     $table->string('scope_key')->virtualAs("CONCAT(company_id, ':', COALESCE(branch_id, '__GLOBAL__'), ':', code)");
     $table->unique('scope_key', 'uq_department_scope_key');
     ```
- **Decision Owner:** Database Architect.

---

### Contradiction 6: Task Workflow Status vs SLA Health Display
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): `status ENUM('backlog','todo','in_progress','blocked','done','cancelled')`.
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): FR-007 defines `backlog → todo → in_progress → blocked → done` (with `cancelled` permitted by role).
  - [`reference/prototype/index.html`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/reference/prototype/index.html): Lines 1681–1698 cycle task status through `Overdue` → `In Progress` → `On Track`.
- **Technical & Business Impact:** Conflating workflow progress (`in_progress`, `done`) with schedule health (`overdue`, `on_track`) breaks state machine validation, corrupts `task_status_history`, and makes SLA calculation impossible.
- **Reconciliation / Decision:** **Strictly separate workflow status from schedule health:**
  - **Database Workflow Status (Stored in `tasks.status`):**
    `backlog`, `todo`, `in_progress`, `blocked`, `done`, `cancelled`.
  - **Schedule Health (Computed in API / UI View Model):**
    - `overdue`: `status != 'done' AND status != 'cancelled' AND due_at < NOW()`.
    - `due_soon`: `status != 'done' AND status != 'cancelled' AND due_at BETWEEN NOW() AND NOW() + INTERVAL 24 HOUR`.
    - `on_track`: `status != 'done' AND status != 'cancelled' AND due_at >= NOW() + INTERVAL 24 HOUR` (or `due_at IS NULL`).
    - `completed`: `status = 'done'`.
  - Prototype click-to-cycle behavior is replaced with an authorized modal status transition dropdown posting to `POST /api/v1/tasks/{id}/transitions`.
- **Decision Owner:** Product Analyst / Architect.

---

### Contradiction 7: Missing Role-Permission Matrix & Access Boundaries
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): Lists 7 membership roles (`owner`, `company_admin`, `operations_manager`, `branch_manager`, `department_lead`, `staff`, `auditor`).
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): Mentions role personas in narrative form without an explicit permission grid.
  - [`docs/TEST_PLAN.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/TEST_PLAN.md): Gate 1 requires "Authorization matrix is covered by tests", but matrix was unwritten.
- **Technical & Security Impact:** Developers make ad-hoc authorization assumptions, leading to privilege escalation or unintended data access.
- **Reconciliation / Decision:** **Formalize the binding role-permission matrix in `docs/PRD.md` and `docs/SECURITY.md`:**

| Role | Manage Company & Members | Manage Branches & Depts | Manage Projects & Tasks | Move Task Status | Reopen Done Task | View Audit Events | Export Reports |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Owner** | Yes (Full) | Yes | Yes | Yes | Yes | Yes | Yes |
| **Company Admin** | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| **Operations Manager** | No | View / Edit | Yes | Yes | Yes | Yes (Read) | Yes |
| **Branch Manager** | No | Scoped Branch | Scoped Branch | Yes | Scoped Branch | No | Scoped Branch |
| **Department Lead** | No | Scoped Dept | Scoped Dept | Yes | Scoped Dept | No | Scoped Dept |
| **Staff Member** | No | Read-only | Assigned Only | Assigned Only | No | No | No |
| **Auditor** | No | Read-only | Read-only | No | No | Yes (Read) | Yes (Read-only) |

- **Decision Owner:** Application Security Engineer.

---

### Contradiction 8: User Invitation Lifecycle & Schema Foreign Key Mismatch
- **Conflicting Sources:**
  - [`database/schema.mysql.sql`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/database/schema.mysql.sql): `company_user.user_id` is defined as `CHAR(26) NOT NULL` referencing `users(id)`. Status enum includes `'invited'`.
  - [`docs/API_CONTRACT.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/API_CONTRACT.md): Defines `POST /api/v1/members/invitations`.
  - Real-world Flow: When inviting a new team member by email, the user does not exist in `users` yet. Attempting to insert an `'invited'` record into `company_user` fails foreign key integrity.
- **Technical & Security Impact:** Admin cannot invite team members without pre-creating accounts or bypassing foreign keys.
- **Reconciliation / Decision:** **Introduce a dedicated `company_invitations` table in Laravel 13 migrations:**
  ```sql
  CREATE TABLE company_invitations (
    id CHAR(26) PRIMARY KEY,
    company_id CHAR(26) NOT NULL,
    email VARCHAR(254) NOT NULL,
    role ENUM(...) NOT NULL,
    branch_id CHAR(26) NULL,
    department_id CHAR(26) NULL,
    token_hash CHAR(64) NOT NULL,
    invited_by_membership_id CHAR(26) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    accepted_at DATETIME(6) NULL,
    created_at DATETIME(6) NULL,
    UNIQUE KEY uq_invite_token (token_hash),
    UNIQUE KEY uq_company_email_pending (company_id, email, accepted_at)
  );
  ```
  - When the recipient accepts via email link, their `users` record is verified/created, and an `active` row is inserted into `company_user`.
- **Decision Owner:** Backend Architect / Security Engineer.

---

### Contradiction 9: Audit Trail Immutability Claims
- **Conflicting Sources:**
  - [`docs/SECURITY.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/SECURITY.md): Line 42 notes "An ordinary database table is not automatically immutable against a privileged database administrator."
  - Baseline discussions occasionally implied audit records are unalterable.
- **Technical & Security Impact:** False claims of regulatory immutability in SOC2 / ISO audits create compliance liabilities.
- **Reconciliation / Decision:** **Acknowledge standard database capabilities transparently in documentation:**
  - The `audit_events` table is **application-level tamper-evident**, protected via:
    1. **Dual-Identity DB Architecture (Hardening Review B):**
       - Web/HTTP user (`orbis_app`): Granted strictly `SELECT, INSERT` on `audit_events` (`UPDATE` and `DELETE` denied).
       - Background maintenance user (`orbis_maintenance`): Granted `SELECT, DELETE` on `audit_events`, invoked exclusively via isolated CLI scheduler (`audit:prune-retention`) after cold-exporting data (see [DECISION_REVIEW_FINDINGS.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REVIEW_FINDINGS.md)).
    2. Eloquent model configured without updating capabilities.
    3. Log shipping of audit events to external write-once storage (e.g. AWS CloudWatch / S3 Object Lock) when regulatory compliance is required.
- **Decision Owner:** Application Security Engineer & DBA.

---

### Contradiction 10: SLO, RPO, RTO & Workload Assumptions
- **Conflicting Sources:**
  - [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): Section 8 listed performance targets as provisional non-functional proposals.
  - [`docs/DECISIONS.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/DECISIONS.md): `OD-009` and `OD-013` were completely open.
- **Technical Impact:** Without agreed targets, staging acceptance criteria cannot be evaluated objectively.
- **Reconciliation / Decision:** **Adopt the provisional targets defined in `OD-009` and `OD-013` as the binding test baseline:**
  - **Availability:** 99.5% monthly uptime (excluding planned maintenance windows).
  - **API Latency:** p95 ≤ 400 ms for core read endpoints (`/dashboard/summary`, `/tasks`, `/branches`).
  - **RPO (Recovery Point Objective):** 24 hours (daily automated encrypted backup).
  - **RTO (Recovery Time Objective):** 8 hours (validated restore runbook).
  - **Load Test Profile:** 20 companies, 10 branches/company, 100 users/company, 50 concurrent virtual users, 100,000 tasks/company over a 30-minute sustained test.
- **Decision Owner:** Operations Owner / Tech Lead.

---

### Contradiction 11: Prototype Viewport Zoom & Mobile Touch Bleed
- **Conflicting Sources:**
  - [`reference/prototype/index.html`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/reference/prototype/index.html): `<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>` and `. joy-active` CSS typo.
  - [`docs/UX_WORLD_SPEC.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/UX_WORLD_SPEC.md): Section 4 mandates "Do not disable browser zoom" and "Virtual joystick must support pointer/touch cancellation without bleeding into open panels."
- **Technical & Accessibility Impact:** Prototype violates WCAG 1.4.4 Resize Text; mobile users inadvertently steer character while scrolling task tables.
- **Reconciliation / Decision:** **Production React frontend must adhere to `UX_WORLD_SPEC.md`:**
  - Remove `maximum-scale=1,user-scalable=no` from HTML meta tag.
  - Fix `.joy-active` CSS selector.
  - Overlay cards and drawers must attach touch barrier (`e.stopPropagation()`) or conditionally unbind canvas raycasting while modals are active.
- **Decision Owner:** Frontend Lead.

---

### Contradiction 12: WebGL Dependency & Graceful Degradation
- **Conflicting Sources:**
  - [`reference/prototype/index.html`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/reference/prototype/index.html): Hardcoded Three.js canvas initialization. Blank page if WebGL unsupported.
  - [`docs/ARCHITECTURE.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/ARCHITECTURE.md) & [`docs/PRD.md`](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/docs/PRD.md): "Quick Dashboard is a first-class workflow. Core features must remain usable without WebGL."
- **Technical Impact:** Users on older hardware, mobile battery-saver modes, or restricted enterprise browsers are locked out of operational tasks.
- **Reconciliation / Decision:** **Implement `WebGLFallback` in React architecture:**
  - Wrap Three.js canvas in feature-detection boundary.
  - If `WebGLRenderingContext` is unavailable or `webglcontextlost` fires, render the 2D Quick Dashboard and 2D Campus Grid with a user notification toast.
- **Decision Owner:** Frontend Architect.
