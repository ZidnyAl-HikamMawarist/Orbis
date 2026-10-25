# ORBIS Data Dictionary

**Version:** 1.0.0-reconciled  
**Status:** Reconciled data dictionary baseline  
**Database:** MySQL 8.4 LTS (InnoDB Engine, `utf8mb4_0900_ai_ci`)

---

## 1. Conventions & Standards

- **Primary & Foreign Keys:** Standardized exclusively on ULID (`CHAR(26) CHARACTER SET ascii COLLATE ascii_bin`).
- **Tenant Scoping:** `company_id` is the tenant boundary present on all tenant-owned entities.
- **Composite Integrity:** Relationships between tenant entities enforce composite foreign keys `(company_id, referenced_id)` to guarantee same-company relationships at the storage engine level.
- **Timestamps:** All database timestamps are stored in UTC (`DATETIME(6)`). Rendering and daily window aggregation convert to the company's configured IANA timezone.
- **Lifecycle:** Soft-archival (`status = 'archived'`) is enforced across organization, project, and task entities to preserve reporting consistency.

---

## 2. Table Inventory

| Table | Domain Purpose | Key Constraints & Implementation Rules |
|---|---|---|
| `users` | Global user identity | Unique email; password hash (Argon2id); does not grant company access alone. |
| `companies` | Tenant root boundary | Unique slug; valid IANA timezone (default `'Asia/Jakarta'`). |
| `company_user` | User membership & assigned role | Unique `(company_id, user_id)`; roles: `owner`, `company_admin`, `operations_manager`, `branch_manager`, `department_lead`, `staff`, `auditor`. |
| `company_invitations` | Pending team member invitations | Unique `token_hash` (SHA-256); 72-hour expiration; single-use token; links to `company_user` on acceptance. |
| `branches` | Physical store / campus locations | Code unique per company `(company_id, code)`; soft archival on retirement. |
| `departments` | Functional teams (IT, Operations, etc.) | Optional `branch_id`; enforced unique scope via generated column `scope_key = CONCAT(company_id, ':', COALESCE(branch_id, '__GLOBAL__'), ':', code)`. |
| `projects` | Operational initiatives | Progress calculation strictly task-derived in MVP; `chk_project_progress` (0–100); start/due date validation. |
| `tasks` | Actionable work items | Optional project/branch/department link sharing `company_id`; priority enum (`low`, `normal`, `high`, `urgent`); workflow status enum. |
| `task_status_history` | Append-only transition audit log | Created atomically in the same transaction as task status update; records actor membership ID, from_status, to_status, and note. |
| `task_comments` | Plain-text task discussion | Body length 1–10,000 characters; executable HTML sanitized; plain text only. |
| `alerts` | System & operational exceptions | Unique `(company_id, source, dedupe_key)` for idempotent rule evaluation; status enum (`open`, `acknowledged`, `resolved`). |
| `kpi_definitions` | Operational metric definitions | 5 operational metrics in MVP; unique `(company_id, key, definition_version)`; financial/POS metrics excluded. |
| `kpi_snapshots` | Pre-computed metric aggregations | Unique `(company_id, kpi_definition_id, branch_id, department_id, window_start, window_end)`; freshness tracking. |
| `world_configs` | 3D spatial layout coordinates | Persists position `(x, y, z)`, rotation `y`, scale for campus landmarks; layout versioning. |
| `audit_events` | Tamper-evident administrative audit log | Least-privilege DB grants (`SELECT`, `INSERT` only); 12-month retention policy; excludes passwords and secrets. |

---

## 3. Domain Enumerations

- **Company Status:** `active`, `suspended`, `archived`
- **Membership Role:** `owner`, `company_admin`, `operations_manager`, `branch_manager`, `department_lead`, `staff`, `auditor`
- **Membership Status:** `invited`, `active`, `inactive`
- **Branch / Department Status:** `active`, `inactive`, `archived`
- **Project Status:** `planned`, `active`, `on_hold`, `completed`, `cancelled`, `archived`
- **Project Progress Mode:** `task_derived` (enforced for MVP; `manual` disabled)
- **Task Workflow Status:** `backlog`, `todo`, `in_progress`, `blocked`, `done`, `cancelled`
- **Task Priority:** `low`, `normal`, `high`, `urgent`
- **Alert Severity:** `info`, `warning`, `critical`
- **Alert Status:** `open`, `acknowledged`, `resolved`
- **KPI Snapshot Freshness:** `fresh`, `stale`, `unavailable`
