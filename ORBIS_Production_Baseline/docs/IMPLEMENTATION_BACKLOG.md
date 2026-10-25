# ORBIS Implementation Backlog (Prioritized)

**Version:** 1.0.0-reconciled  
**Status:** Implementation roadmap aligned with reconciled decisions  
**Backend:** Laravel 13.x | **Frontend:** React + TypeScript + Vite + Three.js

---

## Epic 0 — Specification Freeze & Governance
- [x] Complete prototype inspection and visual/interaction inventory (`PROTOTYPE_INVENTORY.md`).
- [x] Reconcile contradictions and ambiguities across PRD, Schema, and Prototype (`SPEC_CONTRADICTIONS.md`).
- [x] Formulate architecture decisions OD-001 through OD-013 (`DECISION_REGISTER.md`).
- [x] Verify Laravel 13.x environment compatibility (`LARAVEL_13_COMPATIBILITY.md`).
- [ ] Obtain explicit stakeholder approval on `DECISION_REGISTER.md`.

---

## Epic 1 — Secure Multi-Tenant Foundation (Laravel 13.x)
- [ ] Scaffold monorepo structure: `apps/api` (Laravel 13.x) and `apps/web` (React + Vite).
- [ ] Implement canonical database migrations with ULID (`CHAR(26)`) primary and foreign keys:
  - `users`, `companies`, `company_user`, `company_invitations`.
- [ ] Configure Laravel 13 Sanctum SPA stateful session authentication with CSRF protection.
- [ ] Implement `ResolveTenantContext` middleware and global Eloquent tenant scoping.
- [ ] Implement user invitation flow with SHA-256 token hashing, 72-hour expiration, and rate limiting.
- [ ] Build automated cross-tenant negative test suite before developing business modules.

---

## Epic 2 — Organization Structure & Task State Machine
- [ ] Implement branch and department migrations and APIs with soft-archival (`status = 'archived'`).
- [ ] Enforce department scope uniqueness using generated virtual column `scope_key`.
- [ ] Implement projects migration and task-derived progress calculation service:
  - $\text{progress\_percent} = \operatorname{round}((N_{\text{done}} / N_{\text{eligible}}) \times 100)$.
- [ ] Implement tasks migration with priority, due date, and composite foreign keys.
- [ ] Build transactional task status state machine appending atomically to `task_status_history`.
- [ ] Implement task comments with length validation and stored XSS sanitization.
- [ ] Implement `audit_events` listener recording sensitive administrative changes with restricted DB grants.

---

## Epic 3 — Operational Intelligence & Monitoring
- [ ] Implement idempotent alert service evaluating alert rules with deduplication keys.
- [ ] Implement versioned operational KPI calculation service (5 core metrics):
  - `task_completion_rate`, `task_overdue_rate`, `overdue_task_count`, `open_task_count`, `branch_operational_status`.
- [ ] Build high-performance `GET /api/v1/dashboard/summary` endpoint sharing logic with report queries.
- [ ] Implement tabular report filtering and authorized CSV streaming export (`POST /api/v1/reports/exports`).
- [ ] Implement allowlisted search endpoint across authorized branches, departments, projects, and tasks.

---

## Epic 4 — ORBIS 3D World & Responsive Experience
- [ ] Bundle Three.js via npm package `three@^0.165+` inside `apps/web/src/world`.
- [ ] Build React component boundaries (`CampusScene`, `DepartmentInteriorScene`, `ArchipelagoScene`).
- [ ] Implement `WebGLFallback.tsx` gracefully degrading to the 2D Quick Dashboard on WebGL failure or context loss.
- [ ] Integrate floating HTML hex labels and minimap radar synchronized with Three.js camera.
- [ ] Fix prototype usability defects:
  - Eliminate `.joy-active` CSS typo.
  - Implement mobile touch barrier preventing raycast bleed through open overlay cards.
  - Remove `user-scalable=no` viewport restriction.
- [ ] Implement accessible keyboard navigation alternatives for all 3D world landmark actions.

---

## Epic 5 — Production Hardening & Release Gates
- [ ] Configure GitHub Actions CI pipeline executing lint, typecheck, Pest tests, and migration drills.
- [ ] Deploy to staging environment with synthetic multi-tenant test volume (OD-013: 20 companies, 2M tasks).
- [ ] Execute k6 load benchmark verifying p95 latency ≤ 400 ms under 50 concurrent virtual users.
- [ ] Conduct encrypted database backup and isolated restore drill validating RTO ≤ 8 hours.
- [ ] Execute pre-launch security vulnerability audit confirming zero critical/high findings.
- [ ] Final sign-off against release gates prior to production deployment.
