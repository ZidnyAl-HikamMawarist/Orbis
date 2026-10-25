# ORBIS — Specification Reconciliation & Decision Freeze Report

**Document:** `artifacts/audit/SPECIFICATION_FREEZE_REPORT.md`  
**Date:** 2026-10-25  
**Auditor:** Senior Software Architect, Product Analyst, Application Security Engineer  
**Baseline Status:** **SPECIFICATION RECONCILED — READY FOR STAKEHOLDER SIGN-OFF**

---

## 1. Executive Summary

In accordance with project directives, a comprehensive multi-document reconciliation and architectural freeze has been executed across the entire ORBIS specification baseline. 

All 18 reference sources (including the source HTML prototype, reference screenshots, database DDL, PRD, Architecture, and Security specs) were audited. Twelve fundamental contradictions between the client prototype mockup and backend specifications have been resolved. The thirteen open decisions (`OD-001` through `OD-013`) have been formulated into a concrete, cohesive baseline and recorded in the [Decision Register](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md).

The host environment in `C:\laragon` was directly verified and certified as **100% compatible with Laravel 13.x on PHP 8.3.26 and MySQL 8.4.3 LTS**. No version downgrade is needed or allowed.

Per the strict completion boundary, **no application code or migrations have been scaffolded**. The platform remains in a pre-implementation state awaiting formal human review and sign-off on the proposed decisions.

---

## 2. Documentation Audit & File Inventory

The following 18 reference sources were read, audited, and reconciled:

| # | File Path | Original Status | Action Taken |
|---|---|---|---|
| 1 | `README.md` | Baseline intro | Updated stack to specify **Laravel 13.x**. |
| 2 | `VALIDATION_NOTES.md` | Verification notice | Audited; confirms pre-implementation status. |
| 3 | `docs/DECISIONS.md` | Open questions | Updated with complete details for `OD-001`..`OD-013`. |
| 4 | `docs/PRD.md` | General requirements | Reconciled with RBAC matrix, operational KPIs, and progress formula. |
| 5 | `docs/ARCHITECTURE.md` | System design | Updated with Laravel 13 Sanctum SPA auth, ULID, and monorepo structure. |
| 6 | `docs/API_CONTRACT.md` | Draft API endpoints | Harmonized with session auth, CSRF headers, ULID regex, and invitations. |
| 7 | `docs/SECURITY.md` | Security rules | Updated with threat model, RBAC policies, and audit log tamper evidence. |
| 8 | `docs/DEPLOYMENT.md` | Operations guide | Updated with provisional SLO (99.5%), RPO (24h), RTO (8h), and backups. |
| 9 | `docs/TEST_PLAN.md` | Test strategy | Updated with progress golden tests, invitation tests, and load profile. |
| 10 | `docs/IMPLEMENTATION_BACKLOG.md` | Backlog epics | Updated epics 0–5 aligned with reconciled decisions. |
| 11 | `database/schema.mysql.sql` | Draft SQL DDL | Added `company_invitations` table; updated review notes. |
| 12 | `database/DATA_DICTIONARY.md` | Field definitions | Added `company_invitations`, task-derived progress, and scope uniqueness. |
| 13 | `database/ERD.mmd` | Mermaid ERD | Added `COMPANY_INVITATIONS` entity and relationships. |
| 14 | `traceability/REQUIREMENTS_MATRIX.csv`| Requirements trace | Updated component mappings with Laravel 13, Sanctum, and ULIDs. |
| 15 | `docs/UX_WORLD_SPEC.md` | 3D & UX spec | Audited; verified against live prototype execution. |
| 16 | `reference/prototype/README.md` | Preservation guide | Preserved unchanged. |
| 17 | `reference/prototype/index.html` | Prototype source | Inspected and executed live in browser; preserved unchanged. |
| 18 | `reference/prototype/screenshots/` | Reference images | Verified against live browser rendering; preserved unchanged. |

---

## 3. Decision Status (OD-001 through OD-013)

All decisions have been resolved and formulated with complete architectural specifications in [`artifacts/audit/DECISION_REGISTER.md`](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md):

| Decision ID | Topic | Reconciled Baseline Formulation | Status |
|---|---|---|---|
| **OD-001** | **Authentication** | Laravel 13 Sanctum stateful session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`, CSRF). Bearer tokens in localStorage prohibited. | `PROPOSED` |
| **OD-002** | **Identifier Strategy** | Standardize on ULID (`CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`) across database, models, and API. | `PROPOSED` |
| **OD-003** | **Project Progress** | Task-derived progress exclusively for MVP: $\operatorname{round}(N_{\text{done}} / N_{\text{eligible}} \times 100)$. Manual override disabled. | `PROPOSED` |
| **OD-004** | **KPI Catalogue** | 5 operational KPIs exclusively. Prototype financial/revenue figures classified as mockups and excluded from MVP. | `PROPOSED` |
| **OD-005** | **Timezone Handling** | Store timestamps in UTC; configure IANA timezone per company (default `'Asia/Jakarta'`). | `PROPOSED` |
| **OD-006** | **User Invitations** | Dedicated `company_invitations` table with high-entropy SHA-256 tokens, 72h expiry, and rate limiting. | `PROPOSED` |
| **OD-007** | **File Attachments** | Excluded from MVP scope; zero file upload endpoints or public buckets. | `PROPOSED / DEFERRED` |
| **OD-008** | **Audit Retention** | 12-month retention in `audit_events`; application-level tamper evidence via restricted MySQL grants (`SELECT`, `INSERT`). | `PROPOSED` |
| **OD-009** | **SLO / RPO / RTO** | Provisional operational targets: 99.5% uptime, API p95 ≤ 400ms, RPO 24h, RTO 8h. | `PROPOSED` |
| **OD-010** | **Billing & Plans** | Automated billing and self-service subscriptions excluded from MVP; controlled manual tenant provisioning. | `PROPOSED / DEFERRED` |
| **OD-011** | **Data Lifecycle** | Soft-archival (`status = 'archived'`) for ordinary lifecycle; authorized hard-deletion with tenant cascades. | `PROPOSED` |
| **OD-012** | **Repository Strategy** | Monorepo structure (`apps/web`, `apps/api`, `packages/api-contract`, `docs/`, `database/`). | `PROPOSED` |
| **OD-013** | **Workload Profile** | Staging benchmark profile: 20 companies, 10 branches, 100 users, 50 concurrency, 100k tasks, 30m test duration. | `PROPOSED` |

*Note: In accordance with project instructions, all decisions are classified as `PROPOSED` (or `PROPOSED / DEFERRED`) awaiting explicit human stakeholder review and sign-off.*

---

## 4. Key Contradictions Reconciled

The [Contradictions Report](file:///c:/laragon/www/Orbis/artifacts/audit/SPEC_CONTRADICTIONS.md) details the 12 reconciled discrepancies. The most critical resolutions include:
1. **Identifier Cohesion (OD-002):** Reconciled ambiguous references to UUIDs in `API_CONTRACT.md` and `DECISIONS.md` to standardized ULIDs matching `schema.mysql.sql` and Laravel 13 `HasUlids`.
2. **Project Progress Determinism (OD-003):** Eliminated the dual-mode conflict in `schema.mysql.sql` (`manual` vs `task_derived`) by freezing progress calculation to task-derived for MVP.
3. **Prototype Financial Scope Discrepancy (OD-004):** Reconciled the prototype's simulated revenue/POS figures with the PRD non-goals by formally defining the 5 operational KPIs and excluding financial figures from the MVP database.
4. **Authentication Security (OD-001):** Reconciled API token references with security best practices by mandating first-party Sanctum session cookies and CSRF protection.
5. **Department Scope Leakage:** Documented the MySQL nullable unique index vulnerability and established virtual column `scope_key` generation to enforce company-wide uniqueness.
6. **Task Status vs Health (FR-007):** Separated database workflow statuses (`backlog`, `todo`, `in_progress`, `blocked`, `done`, `cancelled`) from computed schedule health (`overdue`, `due_soon`, `on_track`).
7. **Invitation Foreign Key Integrity (OD-006):** Resolved the schema impossibility of inviting unregistered users by adding the `company_invitations` table.

---

## 5. Laravel 13 Environment & Compatibility Evidence

The local environment was tested directly using official Composer and engine diagnostics:
- **`php -v`:** PHP 8.3.26 (Satisfies Laravel 13 constraint `^8.3`).
- **`php -m`:** Confirmed loaded: `pdo_mysql`, `openssl`, `mbstring`, `session`, `tokenizer`, `ctype`, `filter`, `hash`, `intl`, `bcmath`, `curl`, `json`, `dom`.
- **`composer -V`:** Composer version 2.8.4 (Satisfies Composer runtime API `^2.2`).
- **MySQL Engine:** `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` Ver 8.4.3 LTS (Satisfies MySQL 8.x).
- **Node.js & npm:** Node v24.11.1, npm 11.19.1 (Optimal for Vite 6+ and React 18/19).
- **Packagist Inspection:** Inspected `laravel/framework:v13.38.0`. Zero dependency conflicts or environment blockers identified.

Full diagnostic details are recorded in [`artifacts/audit/LARAVEL_13_COMPATIBILITY.md`](file:///c:/laragon/www/Orbis/artifacts/audit/LARAVEL_13_COMPATIBILITY.md).

---

## 6. Generated Audit Artifacts

| Artifact | Location | Purpose |
|---|---|---|
| **Prototype Inventory** | [`artifacts/audit/PROTOTYPE_INVENTORY.md`](file:///c:/laragon/www/Orbis/artifacts/audit/PROTOTYPE_INVENTORY.md) | Exhaustive visual, interaction, and Three.js scene audit. |
| **Laravel 13 Compatibility** | [`artifacts/audit/LARAVEL_13_COMPATIBILITY.md`](file:///c:/laragon/www/Orbis/artifacts/audit/LARAVEL_13_COMPATIBILITY.md) | Official package requirement verification and environment audit. |
| **Contradictions Report** | [`artifacts/audit/SPEC_CONTRADICTIONS.md`](file:///c:/laragon/www/Orbis/artifacts/audit/SPEC_CONTRADICTIONS.md) | 12 identified contradictions, impact analysis, and resolutions. |
| **Decision Register** | [`artifacts/audit/DECISION_REGISTER.md`](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md) | Formal baseline specifications for OD-001 through OD-013. |
| **Freeze Report** | [`artifacts/audit/SPECIFICATION_FREEZE_REPORT.md`](file:///c:/laragon/www/Orbis/artifacts/audit/SPECIFICATION_FREEZE_REPORT.md) | Process summary and release gate status. |

---

## 7. Recommended Next Implementation Steps

The specification reconciliation is complete. In accordance with the governance protocol, **implementation is halted** until the stakeholder reviews the proposed decision register.

Upon stakeholder approval of [`DECISION_REGISTER.md`](file:///c:/laragon/www/Orbis/artifacts/audit/DECISION_REGISTER.md), proceed with:
1. **Epic 1 (Secure Foundation):** Scaffold the monorepo structure (`apps/api` and `apps/web`), initialize Laravel 13.x, execute the canonical migrations (`users`, `companies`, `company_user`, `company_invitations`), and implement the automated cross-tenant negative test suite.
2. **Epic 2 (Organization & Tasks):** Implement branch/department scoping and the atomic task state machine.
