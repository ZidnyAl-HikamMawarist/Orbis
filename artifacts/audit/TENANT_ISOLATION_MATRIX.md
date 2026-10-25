# ORBIS — Multi-Tenant Isolation & Authorization Matrix

## 1. Executive Summary
This document establishes the verified isolation boundaries across all system endpoints, resources, database models, and export channels. Each resource has been tested for negative authorization (IDOR / BOLA) using distinct tenant actors (Company A vs. Company B).

---

## 2. Resource & Endpoint Isolation Matrix

| Endpoint Route | HTTP Method | Resource Category | Tenant Ownership Enforcement | Policy / Validation Rule | Negative Attack Test | Test Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/v1/invitations` | `GET` | CompanyInvitation | Server session `company_id` | `where('company_id', $sessionCompanyId)` | `test_user_cannot_access_other_company_invitations` | **PASS (200, filtered)** |
| `/api/v1/invitations/{id}/revoke` | `POST` | CompanyInvitation | Server session `company_id` | `where('company_id', $companyId)->findOrFail()` | `test_user_cannot_revoke_other_company_invitation` | **PASS (404)** |
| `/api/v1/invitations` | `POST` | CompanyInvitation | Server session `company_id` | `Rule::exists('branches')->where('company_id', ...)` | `test_invite_with_foreign_branch_id_is_rejected` | **PASS (422)** |
| `/api/v1/auth/switch-company` | `POST` | Company Context | Server membership lookup | `user->membershipIn($targetCompanyId)` | `test_user_cannot_switch_to_foreign_company_id` | **PASS (403)** |
| `/api/v1/tasks/{id}` | `GET` | Task | Model `#[ScopedBy(TenantScope)]` | Global `TenantScope` + `TaskPolicy::view` | `test_user_cannot_read_task_of_another_company_by_direct_id` | **PASS (404)** |
| `/api/v1/tasks/{id}` | `PUT` | Task | Model `#[ScopedBy(TenantScope)]` | Global `TenantScope` + `TaskPolicy::update` | `test_user_cannot_update_task_of_another_company_by_direct_id` | **PASS (404)** |
| `/api/v1/tasks` | `POST` | Task | Foreign Project Injection | `Rule::exists('projects')->where('company_id', ...)` | `test_user_cannot_assign_task_to_foreign_project_id` | **PASS (422)** |
| `/api/v1/tasks` | `POST` | Task | Foreign Dept Injection | `Rule::exists('departments')->where('company_id', ...)` | `test_user_cannot_assign_task_to_foreign_department_id` | **PASS (422)** |
| `/api/v1/projects/{id}` | `GET` | Project | Model `#[ScopedBy(TenantScope)]` | Global `TenantScope` + `ProjectPolicy::view` | `test_user_cannot_read_project_of_another_company_by_direct_id` | **PASS (404)** |
| `/api/v1/members/{id}/role` | `PUT` | CompanyUser | Server session `company_id` | `where('company_id', $companyId)->findOrFail()` | `test_user_cannot_update_role_of_another_company_member` | **PASS (404)** |
| `/api/v1/tasks?branch_id={id}` | `GET` | Task Filter | Model `#[ScopedBy(TenantScope)]` | Scoped query `where('company_id', ...)` | `test_task_listing_filtered_by_foreign_branch_returns_empty` | **PASS (200, 0 records)** |
| `/api/v1/reports/tasks-csv` | `GET` | CSV Export | Server session `company_id` | `Task::where('company_id', ...)->chunk()` | `tc_kpi_tasks_csv_export_enforces_tenant_boundary` | **PASS (200, filtered)** |
| `/api/v1/dashboard/metrics` | `GET` | Dashboard KPI | Model `#[ScopedBy(TenantScope)]` | Multi-table scoped aggregates | `test_tc_kpi_06_foreign_branch_filter_returns_404` | **PASS (404 on foreign)** |

---

## 3. Defense-in-Depth Mechanisms

1. **Authentication Tier**: Sanctum validates the session cookie against the MySQL session table.
2. **Context Resolution Tier**: `EnsureTenantContext` verifies active tenant membership and populates request attributes.
3. **ORM Scoping Tier**: `TenantScope` automatically injects `company_id` into all Eloquent queries.
4. **Validation Tier**: Form Requests enforce `Rule::unique` and `Rule::exists` with explicit `where('company_id', $companyId)` closures, rejecting cross-tenant foreign key manipulation before business logic executes.
5. **Authorization Policy Tier**: Laravel Policies (`TaskPolicy`, `ProjectPolicy`, `BranchPolicy`) ensure role permissions are verified per tenant.
6. **Information Disclosure Prevention**: Unauthorized requests to foreign resources return `404 Not Found` rather than `403 Forbidden`, concealing the existence and identifier pattern of other companies' data.
