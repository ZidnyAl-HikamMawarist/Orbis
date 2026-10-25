# ORBIS API Contract — Reconciled Baseline

**Base path:** `/api/v1`  
**Format:** JSON; UTF-8  
**Authentication:** First-party stateful cookie session via Laravel 13 Sanctum SPA mode  
**CSRF Protection:** Required on mutating requests (`POST`, `PATCH`, `DELETE`) via `X-XSRF-TOKEN` header  
**Identifier Standard:** ULID (26-character Crockford Base32 matching `^[0-7][0-9A-HJKMNP-TV-Z]{25}$`)

---

## 1. Conventions & Standards

### 1.1 Response Envelope
All API responses use consistent envelopes:

**Successful Single Resource:**
```json
{
  "data": { ... },
  "meta": { "request_id": "01JAX1234567890ABCDEFGHJKM" }
}
```

**Successful Paginated Collection:**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 142,
    "last_page": 6,
    "request_id": "01JAX1234567890ABCDEFGHJKM"
  }
}
```

**Standard Error Envelope (4xx / 5xx):**
```json
{
  "code": "VALIDATION_FAILED",
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  },
  "request_id": "01JAX1234567890ABCDEFGHJKM"
}
```

### 1.2 Timestamps & Timezones
- All timestamps in API responses are serialized in UTC ISO-8601 format (e.g. `2026-10-25T14:30:00.000000Z`).
- The client application converts and displays timestamps in the active company's configured IANA timezone (default `'Asia/Jakarta'`).

---

## 2. Endpoint Inventory

### 2.1 Authentication & Company Context
- `GET /sanctum/csrf-cookie` — Initializes CSRF cookie for same-site session.
- `POST /session/login` — Authenticates user credentials, regenerates session ID, and sets session cookie.
- `POST /session/logout` — Invalidates authenticated session and clears session cookie.
- `GET /me` — Returns current authenticated user and list of active memberships.
- `POST /companies/{company_ulid}/switch` — Validates active membership and sets active tenant context in session.

### 2.2 Organization (Branches & Departments)
- `GET /branches` — Lists branches for active company (paginated, filterable by `status`).
- `POST /branches` — Creates branch (requires Owner/Admin role).
- `GET /branches/{branch_ulid}` — Retrieves single branch details and department count.
- `PATCH /branches/{branch_ulid}` — Updates branch information.
- `POST /branches/{branch_ulid}/archive` — Soft-archives branch (`status = 'archived'`).
- `GET /departments` — Lists departments (filterable by `branch_id`, `status`).
- `POST /departments` — Creates department (company-wide or branch-scoped).
- `GET /departments/{dept_ulid}` — Retrieves department details.
- `PATCH /departments/{dept_ulid}` — Updates department information.
- `POST /departments/{dept_ulid}/archive` — Soft-archives department.

### 2.3 Memberships & Invitations
- `GET /members` — Lists active and inactive company members with roles.
- `PATCH /members/{membership_ulid}` — Updates member role or status (requires Owner/Admin).
- `POST /members/invitations` — Dispatches invitation email with 72-hour high-entropy token.
- `GET /invitations/{token}` — Public lookup validating token validity and returning invitation details.
- `POST /invitations/{token}/accept` — Accepts invitation, creating/linking user and activating membership.
- `DELETE /members/invitations/{invite_ulid}` — Revokes pending invitation.

### 2.4 Projects & Tasks
- `GET /projects` — Lists projects (filterable by `branch_id`, `department_id`, `status`).
- `POST /projects` — Creates project (enforcing task-derived progress).
- `GET /projects/{project_ulid}` — Retrieves project details with calculated progress.
- `PATCH /projects/{project_ulid}` — Updates project details.
- `POST /projects/{project_ulid}/archive` — Soft-archives project.
- `GET /tasks` — Lists tasks (filterable by `status`, `priority`, `project_id`, `branch_id`, `department_id`, `assignee_id`).
- `POST /tasks` — Creates new task.
- `GET /tasks/{task_ulid}` — Retrieves task details, calculated SLA health, and assignees.
- `PATCH /tasks/{task_ulid}` — Updates editable task fields.
- `POST /tasks/{task_ulid}/transitions` — Atomically executes status transition:
  ```json
  { "to_status": "in_progress", "note": "Started implementation" }
  ```
- `GET /tasks/{task_ulid}/history` — Returns complete append-only status transition log.
- `GET /tasks/{task_ulid}/comments` — Returns task discussion comments.
- `POST /tasks/{task_ulid}/comments` — Posts sanitized plain-text comment.

### 2.5 Alerts & Operational KPIs
- `GET /alerts` — Lists alerts (filterable by `severity`, `status`, `branch_id`).
- `POST /alerts/{alert_ulid}/acknowledge` — Acknowledges open alert (idempotent).
- `POST /alerts/{alert_ulid}/resolve` — Resolves alert with optional resolution note (idempotent).
- `GET /kpis` — Returns all 5 operational KPIs for current company/branch.
- `GET /kpis/{key}` — Returns historical snapshot trend for a specific operational metric.
- `GET /dashboard/summary` — High-performance aggregated summary for the Quick Dashboard:
  ```json
  {
    "data": {
      "kpis": {
        "task_completion_rate": 78.4,
        "task_overdue_rate": 12.1,
        "overdue_task_count": 7,
        "open_task_count": 24,
        "branch_operational_status": "operational"
      },
      "branch_count": 4,
      "recent_alerts": [ ... ]
    }
  }
  ```
- `GET /world/overview` — Lightweight summaries optimized for 3D world landmark badges.
- `GET /reports/tasks` — Tabular report data with date filtering.
- `POST /reports/exports` — Generates authorized CSV export matching filter criteria.

---

## 3. Query Pagination & Filtering

- **Pagination:** Collection endpoints support `page` (default: 1) and `per_page` (default: 25, maximum allowed: 100). Unbounded `limit=ALL` queries are rejected.
- **Allowlisted Sorting:** Sorting parameters accept only explicit allowlisted columns (e.g. `sort=due_at` or `sort=-created_at`). Arbitrary SQL fragments are rejected with `422 Unprocessable Entity`.
