# ORBIS Product Requirements Document (PRD)

**Version:** 1.0.0-reconciled  
**Status:** Reconciled specification baseline awaiting stakeholder decision freeze  
**Product:** ORBIS — Operational Business & Resource Intelligence System  
**Audience:** Retail companies and businesses operating multiple branches  
**Architecture Baseline:** React + TypeScript + Three.js (Frontend) & Laravel 13.x + MySQL 8.x (Backend)

---

## 1. Product Summary

ORBIS is a multi-tenant SaaS operations-monitoring platform. It presents branches, departments, and operational status through a colorful 3D isometric business world, while providing a first-class Quick Dashboard for users who need to act quickly. The 3D world is a spatial visualization interface over business records; it is not a game economy and does not replace standard tables, filters, or operational reports.

---

## 2. Problem Statement

Multi-branch retail operators frequently struggle to unify task status, project progress, team capacity, operational alerts, and KPIs across disparate locations. Conventional business dashboards can be information-dense but lack spatial context. ORBIS provides a memorable spatial overview while retaining a direct, efficient Quick Dashboard for routine daily work.

---

## 3. Product Goals

1. Give authorized users a real-time, cross-branch view of operational health.
2. Allow seamless navigation from company → branch → department → project/task context.
3. Highlight overdue tasks and operational exceptions with high visibility.
4. Enforce deterministic, timezone-aware KPI definitions across overview cards, branch views, and exported reports.
5. Guarantee strict multi-tenant data isolation; no tenant may ever view or mutate another tenant's records.
6. Provide equal desktop and mobile usability; the Quick Dashboard must remain fully functional when 3D is hidden or WebGL is unavailable.

---

## 4. Non-Goals for MVP

- Full ERP, accounting ledger, payroll, inventory valuation, or POS transaction processing.
- Replacing existing third-party HR, POS, or financial accounting software.
- Real-time collaborative 3D multiplayer, multi-user avatar chat, or open-world game physics.
- Automated AI business decision-making or financial forecasting.
- Self-service automated billing, credit card payments, and subscription plan management.
- Native mobile applications (responsive web via mobile browser is the target).
- User-authored scene layout editors or arbitrary 3D asset uploading.
- File attachments and general document storage.

---

## 5. Primary Users & Role-Permission Matrix

A user's effective permission is the intersection of their company membership, assigned role, and organizational resource scope. Role names alone do not grant cross-tenant access.

### 5.1 Persona Definitions
- **Owner:** Ultimate company authority; manages profile, billing/subscription contact, branches, memberships, and company settings.
- **Company Admin:** Manages organization structure, invites members, assigns roles, and configures company-wide defaults.
- **Operations Manager:** Monitors all branches, manages cross-branch projects/tasks, resolves alerts, and exports reports.
- **Branch Manager:** Manages projects, tasks, team progress, and alerts scoped to their assigned branch.
- **Department Lead:** Manages department projects, tasks, and team assignments scoped to their assigned department.
- **Staff Member:** Executes assigned tasks, updates task workflow status, and views relevant branch/department alerts.
- **Read-Only Auditor:** Inspects authorized operational records, reports, and audit logs without mutation rights.

### 5.2 Binding Role-Permission Matrix

| Action / Capability | Owner | Company Admin | Operations Mgr | Branch Mgr | Dept Lead | Staff | Auditor |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Manage Company Settings** | Yes | Yes | No | No | No | No | No |
| **Invite & Deactivate Members** | Yes | Yes | No | No | No | No | No |
| **Manage Branches & Departments** | Yes | Yes | Edit Only | No | No | No | No |
| **Create Projects & Tasks** | Yes | Yes | Yes | Scoped Branch | Scoped Dept | Assigned Only | No |
| **Update Task Workflow Status** | Yes | Yes | Yes | Scoped Branch | Scoped Dept | Assigned Only | No |
| **Reopen Completed Tasks** | Yes | Yes | Yes | Scoped Branch | Scoped Dept | No | No |
| **Acknowledge & Resolve Alerts** | Yes | Yes | Yes | Scoped Branch | Scoped Dept | View Only | View Only |
| **View Audit Trail Events** | Yes | Yes | Yes (Read) | No | No | No | Yes (Read) |
| **Export Operational CSV Reports** | Yes | Yes | Yes | Scoped Branch | Scoped Dept | No | Scoped (Read) |

---

## 6. Core Concepts & Data Model

- **Tenant / Company:** Customer organization and strict data isolation boundary.
- **Branch:** Physical or operational store location belonging to exactly one company.
- **Department:** Functional unit (e.g. IT, Operations, Sales); may be company-wide or scoped to a branch.
- **Membership (`company_user`):** Association linking a user identity to a company with an assigned role.
- **Invitation (`company_invitations`):** Pre-registration token allowing an invited email to join a company with a pre-set role.
- **Project:** Operational initiative with lead, dates, status, and task-derived progress.
- **Task:** Unit of work with priority, status, due date, assignee, and optional project/branch/department scope.
- **Task Status History:** Append-only log recording every status transition, actor, and timestamp.
- **Alert:** Exception notification with severity, deduplication key, status, and optional entity links.
- **KPI Definition:** Versioned metadata and formula for an operational metric.
- **KPI Snapshot:** Pre-aggregated metric value computed for a specific scope and time window.
- **World Configuration:** Spatial coordinates mapping business entities to 3D scene positions.

---

## 7. Functional Requirements

### FR-001: Authentication & Company Context
Users authenticate using email and password via Laravel 13 Sanctum stateful session cookies. Users belonging to multiple companies can switch their active company context via `POST /api/v1/companies/{id}/switch`, which validates active membership.

### FR-002: Tenant Isolation
Every tenant-owned entity includes `company_id`. Server-side query scopes enforce that requests can access only records belonging to the active company context. Cross-tenant access attempts return a non-disclosing `404 Not Found`.

### FR-003: Organization Structure
Authorized administrators can create, update, archive, and view branches and departments. All child references must share the parent's `company_id`. Hard deletion is prohibited; soft archival (`status = 'archived'`) is enforced.

### FR-004: Membership & Secure Invitations
Administrators invite new members via `POST /api/v1/members/invitations`. The system emits an email with a single-use 256-bit cryptographically secure token valid for 72 hours. Tokens are stored as SHA-256 hashes (`token_hash`). Accepting an invitation creates or activates the membership atomically.

### FR-005: Projects & Task-Derived Progress
Authorized users manage projects with start/due dates and assigned leads. Project progress is strictly task-derived (manual percentage override is disabled for MVP):
$$\text{progress\_percent} = \begin{cases} 0 & \text{if } N_{\text{eligible}} = 0 \\ \operatorname{round}\left(\frac{N_{\text{done}}}{N_{\text{eligible}}} \times 100\right) & \text{if } N_{\text{eligible}} > 0 \end{cases}$$
where $N_{\text{eligible}} = \operatorname{count}(\text{tasks WHERE status } \in \{\text{'backlog', 'todo', 'in\_progress', 'blocked', 'done'}\})$. Tasks in `cancelled` and soft-archived tasks are excluded from progress calculations. Tasks in `backlog` are included in the denominator to avoid misleading 100% progress reporting on unstarted projects (see `KPI_SEMANTICS_MATRIX.md` and `OD-003`).

### FR-006: Tasks & Assignment
Tasks record title, description, priority (`low`, `normal`, `high`, `urgent`), due date (`due_at`), and status. Tasks may optionally be scoped to a project, branch, and department sharing the company boundary.

### FR-007: Task Status State Machine & History
Workflow status transitions follow the graph: `backlog → todo → in_progress → blocked → done`. Transitions to/from `cancelled` require manager permissions. Every status mutation atomically appends a record to `task_status_history`. Reopening a `done` task requires manager authority.

### FR-008: Comments & Discussion
Members with access to a task can post plain-text updates. Rich text and executable HTML are rejected. Comments are immutable and limited to 10,000 characters.

### FR-009: Idempotent Alerts
Alerts record severity (`info`, `warning`, `critical`), source, and deduplication key (`dedupe_key`). Repeated evaluation of alert rules within the same window updates existing open alerts rather than creating duplicate notifications.

### FR-010: Operational KPI Engine
The platform calculates 5 core operational KPIs:
1. `task_completion_rate`: Percentage of eligible tasks completed.
2. `task_overdue_rate`: Percentage of open tasks past due date.
3. `overdue_task_count`: Raw count of open tasks where `due_at < NOW()`.
4. `open_task_count`: Count of active tasks in `todo`, `in_progress`, or `blocked`.
5. `branch_operational_status`: Evaluated index based on overdue tasks and active alerts.
All calculations use company timezone boundaries. Mock financial/POS numbers from the prototype are excluded.

### FR-011: Reports & Export
Authorized users filter tasks and operational KPIs by date range, branch, department, and project. Reports export to CSV using the identical calculation and authorization rules as on-screen views.

### FR-012: 3D Isometric Business World
Interactive Three.js scene visualizes campus landmarks, animated vehicles, pedestrians, and floating status hex badges. Clicking any landmark opens its contextual overview.

### FR-013: First-Class Quick Dashboard
Fullscreen glassmorphic dashboard provides immediate access to KPI summary cards, task donut charts, recent alerts, and cross-department tables. The Quick Dashboard functions independently of WebGL.

### FR-014: Search & Filtering
Global search provides allowlisted searching across authorized branches, departments, projects, and tasks, preventing cross-tenant leakage.

### FR-015: Audit Logging
Sensitive changes (invitations, role alterations, branch archiving, company switching) emit an audit event to `audit_events` recording actor, company, action, timestamp, and request ID. Secrets and passwords are never logged.

### FR-016: Responsive & Accessible Interface
The application functions seamlessly from 360px viewport widths to 4K desktops. The HTML viewport tag permits user pinch-zooming. WebGL failures trigger an automatic fallback to the 2D Quick Dashboard.

### FR-017: World Configuration
Spatial coordinates (X, Y, Z, rotation, scale) for branches and departments are persisted in `world_configs`. Scene metadata cannot override server authorization.

### FR-018: Referential & Multi-Tenant Data Integrity
Composite foreign keys `(company_id, id)` prevent assigning tasks or projects to branches or departments belonging to another company.

---

## 8. Non-Functional Requirements & Provisional Targets

- **Availability:** Target 99.5% monthly uptime (excluding planned maintenance).
- **API Performance:** Target p95 latency ≤ 400 ms for core read endpoints (`/dashboard/summary`, `/tasks`, `/branches`) under baseline load.
- **Time to Useful Content:** Initial dashboard content rendered within 2.5 seconds on a standard 4G mobile connection.
- **3D World Frame Rate:** Target stable 30 FPS on average mobile devices and 50–60 FPS on desktop hardware, with reduced quality presets.
- **Recovery Point Objective (RPO):** 24 hours via daily automated encrypted database backups.
- **Recovery Time Objective (RTO):** 8 hours for complete database restoration.
- **Staging Benchmark Profile:** 20 companies, 10 branches/company, 100 users/company, 50 concurrent virtual users, 100,000 tasks/company over a 30-minute sustained test.
