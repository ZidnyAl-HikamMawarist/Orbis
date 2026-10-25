# ORBIS Test & Verification Plan

**Version:** 1.0.0-reconciled  
**Status:** Reconciled test plan baseline  
**Test Runners:** Pest 3.x / PHPUnit 11+, Playwright for E2E, k6 for Load Testing

---

## 1. Test Layers & Verification Scope

### 1.1 Unit Testing
- **Task State Machine:** Verify valid transitions (`backlog → todo → in_progress → blocked → done`) and illegal transitions (e.g. `backlog → done` rejected).
- **Project Progress Engine:**
  - 0 eligible tasks $\rightarrow$ `0%`.
  - 1 done out of 2 active $\rightarrow$ `50%`.
  - Cancelled tasks excluded: 1 done, 1 cancelled out of 2 total $\rightarrow$ `100%`.
  - Backlog tasks excluded: 1 done, 1 backlog out of 2 total $\rightarrow$ `100%`.
  - Decimal rounding: 1 done out of 3 active $\rightarrow$ `33%` (not `33.333%`).
- **KPI Formula Functions:** Zero denominator handling (returns `0`), date window cutoff, and company timezone conversions.
- **Invitation Token Security:** High-entropy token generation and SHA-256 hash calculation.

### 1.2 Feature & API Testing
- **Sanctum SPA Session Authentication:** Verify login, session rotation, logout invalidation, and missing CSRF header rejection (`419`).
- **Tenant Context Scoping:** Verify that every query is strictly filtered by the session `current_company_id`.
- **Cross-Tenant Negative Matrix:** Verify that attempting to read, update, archive, or associate an entity belonging to Company B returns `404 Not Found`.
- **Role-Based Authorization:** Verify that staff cannot reopen completed tasks or invite members; verify that managers are scoped to their assigned branch.
- **Invitation Lifecycle:** Verify token validation, 72-hour expiration boundary, single-use invalidation, and rate limiting (max 10 invites/admin/hour).
- **Task Status Mutation Atomicity:** Verify that updating task status and writing to `task_status_history` occurs in a single atomic database transaction.

### 1.3 Integration & Schema Testing
- **Migration Drills:** Apply all Laravel migrations from an empty MySQL 8.4 database; rollback all migrations; reapply cleanly.
- **Composite Foreign Key Constraints:** Verify that MySQL rejects inserting a task referencing `(company_A, project_B)`.
- **Department Scope Uniqueness:** Verify that inserting duplicate department codes within the same branch or company-wide scope is blocked.

### 1.4 Frontend & End-to-End Testing (Playwright)
- **Quick Dashboard Navigation:** Direct navigation to Quick Dashboard without WebGL rendering.
- **WebGL Context Loss Fallback:** Simulate `webglcontextlost` on Three.js canvas; verify that `WebGLFallback.tsx` displays the 2D dashboard without crashing.
- **Responsive Layout Verification:** Verify rendering and interaction at 360×800, 390×844, 768×1024, and 1440×900 viewports. Ensure no horizontal overflow.
- **Mobile Touch Barrier:** Verify that dragging on mobile overlay cards does not steer the Three.js character underneath.

### 1.5 Load & Performance Testing (k6 / Staging)
- **Workload Benchmark Profile (OD-013):**
  - Database seeded with 20 companies, 10 branches/company, 100 users/company, and 100,000 tasks/company (total 2,000,000 tasks).
  - 50 concurrent virtual users executing realistic operational workflows over 30 minutes.
  - Acceptance threshold: p95 latency ≤ 400 ms for `/dashboard/summary` and `/tasks`; 0% HTTP 5xx errors.

---

## 2. Release Acceptance Gate Checklist

- [ ] All migrations apply from empty database and rollback cleanly on MySQL 8.4.
- [ ] 100% of cross-tenant negative tests pass across all domain resources.
- [ ] Role-permission matrix verified with positive and negative test cases.
- [ ] Golden KPI test suite matches operational definitions across timezones.
- [ ] Project progress calculation matches task-derived mathematical specifications.
- [ ] Invitations expire after 72 hours and enforce SHA-256 hash verification.
- [ ] WebGL context loss gracefully displays the 2D Quick Dashboard.
- [ ] Responsive UI verified on mobile viewports (360px and 390px) without horizontal overflow.
- [ ] Staging load test achieves p95 latency ≤ 400 ms under 50-concurrency profile.
- [ ] Backup restore drill executed successfully within 8 hours.
