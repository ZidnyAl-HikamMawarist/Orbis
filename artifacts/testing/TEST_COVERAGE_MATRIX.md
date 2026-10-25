# ORBIS — Requirements to Automated Test Coverage Matrix

**Date:** 2026-10-25  
**Version:** 1.0.0-rc1  
**Framework:** Laravel 13.x (PHP 8.3.26), MySQL 8.4.3 LTS, React 19, Playwright Chromium  
**Status:** FULL PASS  

---

## 1. Traceability Matrix

| Requirement ID | Requirement Description | Backend Implementation | Frontend Implementation | Automated Test Location | Verification Status |
|---|---|---|---|---|:---:|
| **REQ-AUTH-01** | Sanctum SPA Cookie Auth & Session Fixation Protection | `AuthController@login`, `EnsureTenantContext` | `context/AuthContext.tsx`, `LoginView.tsx` | `tests/Feature/AuthTest.php:test_successful_login_initializes_session` | **PASS** |
| **REQ-AUTH-02** | Secure Logout and Guard Invalidation | `AuthController@logout` (`Auth::forgetGuards`) | `Navbar.tsx` (Logout action) | `tests/Feature/AuthTest.php:test_logout_clears_session` | **PASS** |
| **REQ-AUTH-03** | Server-side Company Switching & Membership Verification | `AuthController@switchCompany` | `Navbar.tsx` (Company Switcher) | `tests/Feature/AuthTest.php:test_user_can_switch_company` | **PASS** |
| **REQ-AUTH-04** | Unauthorized Company Switching Rejection | `AuthController@switchCompany` | `Navbar.tsx` | `tests/Feature/AuthTest.php:test_user_cannot_switch_to_company_without_membership` | **PASS** |
| **REQ-INV-01** | SHA-256 Hashed Invitation Token Dispatch & Expiry | `InvitationController@store` | `InvitationController` | `tests/Feature/InvitationTest.php:test_admin_can_dispatch_invitation` | **PASS** |
| **REQ-INV-02** | Invitation Privilege Escalation Rejection | `InvitationController@store` (Admin cannot invite Owner) | `InvitationController` | `tests/Feature/InvitationTest.php:test_admin_cannot_escalate_role_to_owner` | **PASS** |
| **REQ-INV-03** | Invitation Acceptance & Membership Activation | `InvitationController@accept` | `InvitationController` | `tests/Feature/InvitationTest.php:test_invitation_acceptance_creates_user` | **PASS** |
| **REQ-ISO-01** | Tenant Resource Isolation (Foreign ID Rejection) | `TenantScope.php`, `EnsureTenantContext.php` | `api/client.ts` | `tests/Feature/TenantIsolationTest.php:test_user_cannot_access_other_company_invitations` | **PASS** |
| **REQ-ISO-02** | Foreign Relationship Injection Protection | Foreign keys + `Rule::exists()->where('company_id')` | `CreateTaskModal.tsx`, `DepartmentController` | `tests/Feature/TenantIsolationTest.php:test_invite_with_foreign_branch_id_is_rejected` | **PASS** |
| **REQ-ORG-01** | Branch Creation and Company Scoping | `BranchController@store`, `BranchPolicy` | `BranchListView.tsx` | `tests/Feature/OrganizationTest.php:test_admin_can_create_and_list_branches` | **PASS** |
| **REQ-ORG-02** | RBAC Authorization on Org Modification | `BranchPolicy`, `DepartmentPolicy` | `BranchListView.tsx` | `tests/Feature/OrganizationTest.php:test_staff_cannot_create_branch` | **PASS** |
| **REQ-ORG-03** | Generated Column Unique Scope Key for Departments | `DepartmentController@store`, `scope_key` | `BranchListView.tsx` | `tests/Feature/OrganizationTest.php:test_admin_can_create_department_under_branch` | **PASS** |
| **REQ-PRJ-01** | Multi-Tenant Project Management | `ProjectController@store`, `ProjectPolicy` | `DashboardView.tsx`, `CreateTaskModal.tsx` | `tests/Feature/ProjectTaskTest.php:test_admin_can_create_project` | **PASS** |
| **REQ-PRJ-02** | Backlog Inclusion in Project Progress (**OD-003**) | `Project::recalculateProgress()` | `QuickDashboardModal.tsx` | `tests/Feature/ProjectTaskTest.php:test_task_creation_and_recalculation_includes_backlog` | **PASS** |
| **REQ-TSK-01** | Task Creation, Assignment, and Scoping | `TaskController@store`, `TaskPolicy` | `CreateTaskModal.tsx`, `TaskListView.tsx` | `tests/Feature/ProjectTaskTest.php:test_task_creation_and_recalculation_includes_backlog` | **PASS** |
| **REQ-TSK-02** | Atomic Task Status Transition & Timeline | `Task::transitionStatus()`, `TaskController@transition` | `TaskDrawer.tsx` | `tests/Feature/ProjectTaskTest.php:test_task_creation_and_recalculation_includes_backlog` | **PASS** |
| **REQ-TSK-03** | Reopen Completed Task Privilege Restriction (**OD-003**) | `TaskPolicy::transition`, `Task::canReopenTask` | `TaskDrawer.tsx` | `tests/Feature/ProjectTaskTest.php:test_reopen_completed_task_restricted_by_policy` | **PASS** |
| **REQ-TSK-04** | Collaborative Task Comments Timeline | `TaskController@addComment` | `TaskDrawer.tsx` | `tests/Feature/ProjectTaskTest.php:test_task_comment_timeline_and_audit` | **PASS** |
| **REQ-KPI-01** | Zero-Denominator Safe Fallbacks (**TC-KPI-01**) | `KpiCalculationService@calculateDashboardMetrics` | `QuickDashboardModal.tsx` | `tests/Feature/KpiDashboardTest.php:test_tc_kpi_01_empty_dataset_safe_fallbacks` | **PASS** |
| **REQ-KPI-02** | Overdue SLA Calculation & Missing `due_at` Safe Exclusion | `KpiCalculationService@calculateDashboardMetrics` | `QuickDashboardModal.tsx` | `tests/Feature/KpiDashboardTest.php:test_tc_kpi_04_05_overdue_calculation_and_missing_due_at` | **PASS** |
| **REQ-KPI-03** | Dynamic Branch Health Status Index | `KpiCalculationService@calculateBranchOperationalStatus` | `CampusScene.ts`, `QuickDashboardModal.tsx` | `tests/Feature/KpiDashboardTest.php:test_tc_kpi_06_branch_status_evaluates_critical` | **PASS** |
| **REQ-ALR-01** | Alert Lifecycle & Acknowledgment Audit | `AlertController@acknowledge` | `AlertsListView.tsx` | `tests/Feature/KpiDashboardTest.php:test_alert_acknowledgment_and_listing` | **PASS** |
| **REQ-REP-01** | Tenant-Scoped Tasks CSV Export | `ReportController@tasksCsv` | `QuickDashboardModal.tsx` | `tests/Feature/KpiDashboardTest.php:test_tasks_csv_export_enforces_tenant_boundary` | **PASS** |
| **REQ-UI-01** | First-Class Quick Dashboard Decoupled from 3D | `DashboardController@metrics` | `QuickDashboardModal.tsx` | Playwright Chromium: `Journey E` | **PASS** |
| **REQ-UI-02** | Three.js Interactive 3D World & Orbit Camera | Three.js, Raycaster, Procedural meshes | `CampusScene.ts`, `WorldCanvas.tsx` | Playwright Chromium: `Journey H` | **PASS** |
| **REQ-UI-03** | Resilient 2D Topological Fallback | Pure HTML/SVG topological diagram | `WebGLFallback.tsx` | Playwright Chromium: `Journey H` | **PASS** |
| **REQ-UI-04** | Responsive Layouts (Mobile 375px/390px & Desktop 1280px) | CSS Flexbox, Glassmorphism, Auto-fit Grids | `index.css`, `Navbar.tsx`, `App.tsx` | Playwright Chromium: `chromium-mobile` | **PASS** |

---

## 2. Summary Statistics
- Total Tracked Requirements: **27**
- Fully Verified by Reproducible Tests: **27 (100%)**
- Backend PHPUnit Tests: **37 passing (111 assertions)**
- Playwright Browser Journeys: **10 automated journeys across Chromium Desktop & Mobile**
