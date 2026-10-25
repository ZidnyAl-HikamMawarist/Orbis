# ORBIS — Phase 2 Implementation Report
## Organization, Membership, Projects, and Tasks

**Status:** PASS — Gate C Approved  
**Date:** 2026-10-25  
**Framework:** Laravel 13.38.0  
**Test Suite:** 31 tests passed (88 assertions)  
**Database:** MySQL 8.4.3 LTS Community Server  

---

## 1. Scope Implemented

In Phase 2, ORBIS built out the core operational data model, access policies, validation rules, and business logic:

1. **Organization Model:**
   - `branches`: Branches with codes, names, timezones, physical addresses, and unique company-scoped codes.
   - `departments`: Branch-level and global departments with virtual generated column `scope_key` (`CONCAT(company_id, ':', COALESCE(branch_id, '__GLOBAL__'), ':', code)`) preventing multi-NULL uniqueness collisions in MySQL.
2. **Membership & RBAC:**
   - `company_user`: Full membership lifecycle with roles (`owner`, `company_admin`, `operations_manager`, `branch_manager`, `department_lead`, `staff`, `auditor`).
   - `CompanyUserPolicy`: Self-deactivation prevention, owner demotion protection, and admin-only role management.
3. **Projects:**
   - Multi-tenant scoped projects with start and due dates, lead assignment, and automatic progress tracking.
   - Progress formula adheres strictly to **OD-003** and `KPI_SEMANTICS_MATRIX.md`:
     $$\text{Progress \%} = \text{round}\left(\frac{\text{done tasks}}{\text{backlog} + \text{todo} + \text{in\_progress} + \text{blocked} + \text{done}} \times 100\right)$$
     (Cancelled tasks are excluded; backlog is explicitly included in the denominator to avoid premature 100% completion metrics).
4. **Tasks & State Machine:**
   - Atomic `transitionStatus()` method inside database transactions.
   - Append-only timeline in `task_status_history` logging from_status, to_status, actor_membership_id, notes, and timestamp.
   - Strict reopening restriction: Reopening a `done` task to any incomplete status requires `canReopenTask()` privilege (Manager or Admin). Staff cannot reopen completed tasks.
   - Collaborative threaded discussion in `task_comments`.
   - Comprehensive audit logging via `audit_events`.

---

## 2. Key Architecture Files

| File | Purpose |
|------|---------|
| `apps/api/app/Policies/BranchPolicy.php` | Branch view/edit/archive authorization policies |
| `apps/api/app/Policies/DepartmentPolicy.php` | Department scope and management policies |
| `apps/api/app/Policies/ProjectPolicy.php` | Project lifecycle and lead management policies |
| `apps/api/app/Policies/TaskPolicy.php` | Task assignment, transition, and reopening policies |
| `apps/api/app/Policies/CompanyUserPolicy.php` | Membership role update and deactivation policies |
| `apps/api/app/Http/Controllers/Api/BranchController.php` | Branch REST endpoints with department and task counts |
| `apps/api/app/Http/Controllers/Api/DepartmentController.php` | Department REST endpoints with scope validation |
| `apps/api/app/Http/Controllers/Api/MembershipController.php` | Member roster, role assignment, and deactivation |
| `apps/api/app/Http/Controllers/Api/ProjectController.php` | Project management with progress tracking |
| `apps/api/app/Http/Controllers/Api/TaskController.php` | Task lifecycle, atomic transitions, comments, and archives |
| `apps/api/tests/Feature/OrganizationTest.php` | Feature tests for branches, departments, and members |
| `apps/api/tests/Feature/ProjectTaskTest.php` | Feature tests for progress math, transitions, reopening, and comments |

---

## 3. Test Verification & Evidence

All tests executed natively against MySQL 8.4 LTS test database (`orbis_test`):

```
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AuthTest
  ✓ unauthenticated request returns 401
  ✓ successful login initializes session and company context
  ✓ login with wrong password returns 422
  ✓ authenticated user can retrieve me endpoint
  ✓ user can switch company if holding active membership
  ✓ user cannot switch to company without membership
  ✓ logout clears session

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\InvitationTest
  ✓ admin can dispatch invitation with sha256 hashed token
  ✓ staff cannot dispatch invitations
  ✓ admin cannot escalate role to owner
  ✓ valid token can be publicly validated
  ✓ expired token is rejected
  ✓ invitation acceptance creates user and active membership

   PASS  Tests\Feature\OrganizationTest
  ✓ admin can create and list branches
  ✓ staff cannot create branch
  ✓ branch code must be unique within company
  ✓ admin can create department under branch
  ✓ department creation with foreign branch id is rejected
  ✓ admin can update member role

   PASS  Tests\Feature\ProjectTaskTest
  ✓ admin can create project
  ✓ task creation and recalculation includes backlog in denominator
  ✓ reopen completed task restricted by policy
  ✓ task comment timeline and audit
  ✓ task with foreign project id is rejected

   PASS  Tests\Feature\TenantIsolationTest
  ✓ user cannot access other company invitations
  ✓ user cannot revoke other company invitation
  ✓ eloquent tenant scope filters queries to active company
  ✓ user cannot switch to foreign company id
  ✓ invite with foreign branch id is rejected

  Tests:    31 passed (88 assertions)
  Duration: 22.54s
```

---

## 4. Gate Decision

- **Gate C (Operational Functionality): PASSED**
- All organization, member, project, and task endpoints implemented with server-side validation, cross-tenant isolation, audit logging, and automated tests.
