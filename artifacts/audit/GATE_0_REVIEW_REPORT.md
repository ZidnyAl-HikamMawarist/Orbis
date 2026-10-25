# ORBIS — Gate 0 Decision Review & Readiness Report

**Document:** `artifacts/audit/GATE_0_REVIEW_REPORT.md`  
**Assessment Date:** 2026-10-25  
**Reviewers:** Senior Software Architect, Application Security Engineer, Product Analyst  
**Framework Baseline:** **Laravel 13.x (Mandatory)**  
**Gate Phase:** Gate 0 (Specification Freeze & Decision Baseline)  
**Overall Readiness Verdict:** **SPECIFICATION HARDENED — PENDING HUMAN SIGN-OFF TO CLOSE GATE 0**

---

## 1. Executive Summary

A comprehensive multi-disciplinary audit and hardening review has been conducted across the ORBIS production specification baseline. Thirteen foundational architectural decisions (`OD-001` through `OD-013`) have been defined, rigorously reconciled against prototype behavior, and hardened against known security, operational, and mathematical edge cases.

All identified implementation hazards—including the **backlog progress dilemma** (false 100% completions), the **audit database privilege contradiction** (pruning vs append-only), **Sanctum SPA session/CSRF flows**, and **invitation token security**—have been resolved with concrete architectural proposals.

In accordance with strict operating rules:
- **No application code, migrations, or scaffolding have been generated.**
- **All decisions remain in `PROPOSED` status** awaiting human stakeholder review.
- **Backend stack is locked to Laravel 13.x** on PHP 8.3/8.4 and MySQL 8.4 LTS.

---

## 2. Decision Categorization for Stakeholder Review

To facilitate efficient multi-stakeholder governance, the 13 decisions are categorized into actionable review streams:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       ORBIS GATE 0 DECISION STREAMS                     │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │
     ┌───────────────────────────────┼───────────────────────────────┐
     ▼                               ▼                               ▼
[ Stream 1: Technical Defaults ] [ Stream 2: Product Owner ] [ Stream 3: Sec/Ops/Legal ]
• OD-001 Multi-Tenancy Scope    • OD-003 Progress Formula    • OD-006 Invitation Security
• OD-002 Workflow State Machine • OD-004 KPI Semantics       • OD-008 Audit DB Privileges
• OD-005 Two-Tier RBAC                                       • OD-009 SLO/RPO/RTO Targets
• OD-007 Quick Dashboard                                     • OD-011 Lifecycle Archival
• OD-010 Canonical Schema                                    • OD-013 Test Load Profile
• OD-012 Monorepo Layout
```

### Stream 1: Decisions Accepted as Provisional Technical Defaults
These decisions represent industry-standard engineering foundations with low business ambiguity, recommended for acceptance as provisional technical defaults:

1. **`OD-001` (Multi-Tenancy & Tenant Isolation):** Shared database, separated tenant schema via foreign key `company_id` and global Eloquent query scopes (`TenantScope`). Session-based tenant context; ULIDs treated as opaque public identifiers, never authorization boundaries.
2. **`OD-002` (Task Workflow State Machine):** 6 explicit workflow states (`backlog`, `todo`, `in_progress`, `blocked`, `done`, `cancelled`). Strict state transitions enforced via Laravel domain state machine.
3. **`OD-005` (Two-Tier Role Hierarchy):** Company-level (`company_owner`, `admin`, `member`) and Department-level (`lead`, `contributor`, `viewer`). Clean separation between tenant administrative rights and departmental task delegation.
4. **`OD-007` (Quick Dashboard as First-Class Workflow):** Complete feature and data parity between Three.js 3D world view and responsive HTML/CSS Quick Dashboard modal. Automatic fallback when WebGL fails.
5. **`OD-010` (Laravel Migrations as Canonical Schema):** Version-controlled Laravel migrations serve as the authoritative schema; synchronized raw SQL reference maintained at `database/schema.mysql.sql`.
6. **`OD-012` (Monorepo Directory Layout):** Target layout (`apps/api`, `apps/web`, `packages/`, `database/`, `docs/`, `artifacts/`). Current structure retained unchanged during documentation; zero-disruption directory promotion deferred to Epic 1 kickoff.

---

### Stream 2: Decisions Requiring Product-Owner Input
These decisions dictate end-user business semantics and executive reporting metrics:

1. **`OD-003` (Project Progress Calculation — Backlog Inclusion Dilemma):**
   - **The Issue:** Excluding `backlog` from progress calculation falsely reports a project with 2 `done` tasks and 10 unstarted `backlog` tasks as **100% complete**.
   - **Architectural Recommendation:** Include `backlog` in the progress denominator:
     $$\text{progress\_percent} = \frac{N_{\text{done}}}{N_{\text{backlog}} + N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}} \times 100$$
   - **Trade-off:** Teams must consciously move cancelled or obsolete tasks to `cancelled` or `archived` to prevent them from depressing project progress.
   - **Sign-off Needed:** Product Owner confirmation of denominator semantics as detailed in [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md).

2. **`OD-004` (KPI Semantics & Reporting Boundaries):**
   - **The Issue:** Ambiguity in handling tasks with missing `due_at`, zero denominators, and timezone boundaries for operational KPIs.
   - **Architectural Recommendation:** Adopt the rules in [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md):
     - Missing `due_at`: Excluded from overdue task numerator; included in open task count and task completion rate denominator.
     - Zero denominator: Returns `0%` or `0.0` with `freshness: "unavailable"`.
     - Timezone: Evaluated against tenant company's configured IANA timezone converted to UTC.
   - **Sign-off Needed:** Product Owner approval of KPI response shapes and business thresholds.

---

### Stream 3: Decisions Requiring Security, Legal, or Operations Approval

1. **`OD-006` (Company Invitations Security — Security Lead Approval):**
   - **Security Hardening:** Plaintext tokens never stored in database; stored as `SHA-256` hash (`token_hash`).
   - **Lifecycle:** 72-hour hard expiration; single-use token invalidation; revoking previous pending tokens upon resend.
   - **Escalation & Enumeration Defense:** Inviters cannot assign roles higher than their own; rate limiting (10/min per company, 5/hr per email); generic rejection responses to prevent account harvesting.
   - **Transaction Boundary:** Atomic user creation/lookup and membership linking inside `DB::transaction()`.

2. **`OD-008` (Audit Retention & Database Privileges — Security Lead & DBA Approval):**
   - **Least-Privilege Resolution:**
     - `orbis_app` MySQL user: Granted strictly `SELECT, INSERT` on `audit_events` (web traffic cannot update or delete audit logs).
     - `orbis_maintenance` MySQL user: Granted `SELECT, DELETE` on `audit_events`, restricted to scheduled CLI commands (`audit:prune-retention`) running in isolated VPC/cron.
   - **Archival Before Prune:** Pruning requires pre-export to cold compressed JSONL storage.
   - **Engineering Reality:** Formal acknowledgment that relational MySQL tables are not hardware-immutable; security relies on least-privilege role segregation.

3. **`OD-009` (Service Level Objectives & Disaster Recovery — Operations / DevOps Approval):**
   - **Status:** Formally designated as **provisional proposed targets**, NOT verified achievements.
   - **Targets:** Availability 99.5%, p95 response time $\le 400\text{ms}$ across key endpoints (`/dashboard/metrics`, `/projects`, `/world/branches`), RPO 24 hours, RTO 8 hours.
   - **Verification Requirement:** Automated daily backup verification and quarterly restore rehearsals into isolated staging.

4. **`OD-011` (Data Lifecycle & Retention — Legal / Compliance Approval):**
   - **Archival Standard:** Soft-archive by default (`archived_at`); query exclusion via Eloquent global scope.
   - **Cascade Constraints:** Cascades are physical integrity mechanisms, never authorization controls. Foreign key `RESTRICT` on primary relationships prevents orphaned operational records.
   - **Compliance:** GDPR "Right to be Forgotten" satisfied via PII anonymization / tombstoning while preserving historical audit UUIDs and financial aggregates.

5. **`OD-013` (Benchmark & Load Profile — Senior Architect & Operations Approval):**
   - **Clarified Scope:**
     - Data Volume: 20 companies $\times$ 10 branches $\times$ 100,000 tasks = **2,000,000 total seeded tasks** (evaluating index depth and query planner efficiency).
     - Concurrency: **50 concurrent virtual users** executing typical workflows (target $\approx 150\text{ req/sec}$).
   - **Sign-off Needed:** Sizing agreement for staging load testing infrastructure.

---

## 3. Remaining Contradictions & Implementation Blockers

| Blocker ID | Description | Impacted Decisions | Required Action | Status |
|---|---|---|---|:---:|
| **BLK-01** | **Human Approval of Gate 0:** Operating rule requires explicit human approval before any decision moves to `APPROVED` and before Epic 1 begins. | All (OD-001..013) | Human stakeholder review of this report and decision register. | **BLOCKING** |
| **BLK-02** | **Product Owner Sign-off on Progress Formula:** Confirmation of `backlog` inclusion in project progress denominator. | OD-003, OD-004 | Product Owner reviews [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md). | **BLOCKING** |
| **BLK-03** | **Security & DBA Sign-off on Dual DB Users:** Validation of `orbis_app` vs `orbis_maintenance` privilege separation in deployment environment. | OD-008 | Security Lead & DBA approve MySQL grant script. | **BLOCKING** |
| **BLK-04** | **Legal Review on User Anonymization:** Confirmation that user PII scrubbing satisfies GDPR without violating statutory audit retention. | OD-011 | Corporate Legal / DPO review of tombstone model. | **PENDING** |

---

## 4. Proposed Checklist for Closing Gate 0

To formally close Gate 0 and authorize Phase 1 implementation (scaffolding and Epic 1), complete the following checklist:

- [ ] **1. Product Review:** Product Owner signs off on `OD-003` (including `backlog` in progress) and `OD-004` (KPI Semantics Matrix).
- [ ] **2. Security Review:** Application Security Engineer approves `OD-001` Sanctum SPA session flow, `OD-006` invitation token hashing (`SHA-256`), and `OD-008` dual DB user privilege model.
- [ ] **3. Operations Review:** DevOps / DBA signs off on `OD-008` scheduled pruning architecture, `OD-009` provisional SLO/RPO/RTO targets, and `OD-013` load testing profile.
- [ ] **4. Legal Review:** Legal / Compliance officer approves `OD-011` data archival, hard-deletion limits, and user PII tombstoning strategy.
- [ ] **5. Register Transition:** Decision statuses in [DECISION_REGISTER.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md) are formally transitioned from `PROPOSED` to `APPROVED`.
- [ ] **6. Gate 0 Sign-Off:** Human stakeholder issues explicit directive: *"Gate 0 is closed. Proceed to Epic 1 (Laravel 13 Scaffolding & Core Architecture)."*

---

## 5. Artifact Reference Index

All detailed analyses supporting this review are available in the workspace:
1. Master Decision Register: [DECISION_REGISTER.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md)
2. Detailed Hardening Findings (Reviews A–G): [DECISION_REVIEW_FINDINGS.md](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REVIEW_FINDINGS.md)
3. Master KPI & Task Eligibility Matrix: [KPI_SEMANTICS_MATRIX.md](file:///c:/laragon/www/Orbis/artifacts/audit/KPI_SEMANTICS_MATRIX.md)
4. Specification Contradictions Reconciliation: [SPEC_CONTRADICTIONS.md](file:///c:/laragon/www/Orbis/artifacts/audit/SPEC_CONTRADICTIONS.md)
5. Prototype Inventory & Verification: [PROTOTYPE_INVENTORY.md](file:///c:/laragon/www/Orbis/artifacts/audit/PROTOTYPE_INVENTORY.md)
6. Laravel 13 Compatibility Certification: [LARAVEL_13_COMPATIBILITY.md](file:///c:/laragon/www/Orbis/artifacts/audit/LARAVEL_13_COMPATIBILITY.md)
7. Production Baseline Specification: [ORBIS_Production_Baseline/README.md](file:///c:/laragon/www/Orbis/ORBIS_Production_Baseline/README.md)
