# ORBIS — KPI Semantics & Task Eligibility Matrix

**Artifact:** `artifacts/audit/KPI_SEMANTICS_MATRIX.md`  
**Assessment Date:** 2026-10-25  
**Auditor / Analyst:** Senior Software Architect, Product Analyst, Application Security Engineer  
**Status:** **PROPOSED BASELINE FOR STAKEHOLDER REVIEW & SIGN-OFF**

---

## 1. Executive Summary

This document establishes a unified, unambiguous mathematical and operational definition for all task-derived metrics in ORBIS. It resolves the critical architectural flaw where excluding tasks in `backlog` from project progress calculation could falsely produce a **100% complete** status for projects with unstarted work.

It details:
1. The master **Task Eligibility Matrix** across all 6 workflow states.
2. The exact formula, numerator, denominator, missing `due_at` treatment, zero-denominator fallback, rounding, and JSON response shape for each metric.
3. Concrete numerical test scenarios proving consistency between project detail views and dashboard summary widgets.

---

## 2. Master Task Eligibility Matrix

The table below defines how each task workflow status is treated across project progress and operational KPIs:

| Metric / Scope | `backlog` | `todo` | `in_progress` | `blocked` | `done` | `cancelled` | `archived` |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Project Progress (`progress_percent`)** | **Included** (Denominator) | **Included** (Denominator) | **Included** (Denominator) | **Included** (Denominator) | **Included** (Num & Denom) | **Excluded** | **Excluded** |
| **Task Completion Rate (`task_completion_rate`)** | **Included** (Denominator) | **Included** (Denominator) | **Included** (Denominator) | **Included** (Denominator) | **Included** (Num & Denom) | **Excluded** | **Excluded** |
| **Open Task Count (`open_task_count`)** | **Included** | **Included** | **Included** | **Included** | Excluded | Excluded | Excluded |
| **Overdue Task Count (`overdue_task_count`)** | **Included** (if past due) | **Included** (if past due) | **Included** (if past due) | **Included** (if past due) | Excluded | Excluded | Excluded |
| **Task Overdue Rate (`task_overdue_rate`)** | **Included** (if past due) | **Included** (if past due) | **Included** (if past due) | **Included** (if past due) | Excluded | Excluded | Excluded |
| **Branch Operational Status** | Drives Overdue Count | Drives Overdue Count | Drives Overdue Count | Drives Overdue Count | No impact | No impact | Excluded |

---

## 3. The "Backlog Exclusion" Dilemma & Resolution

### 3.1 The Problem
In the initial baseline draft (`OD-003`), project progress was proposed as:
$$\text{progress\_percent} = \frac{N_{\text{done}}}{N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}} \times 100$$
*(Excluding `backlog`)*

**Failure Case Scenario:**
A retail expansion project defines 12 tasks:
- 2 tasks completed (`done`)
- 0 tasks in `todo`, `in_progress`, or `blocked`
- 10 planned tasks remaining in `backlog`

Under the initial rule:
$$\text{progress} = \frac{2}{0 + 0 + 0 + 2} \times 100 = \mathbf{100\%}$$

The dashboard and 3D department view would present this project as **100% Complete (Green)**, despite 10 critical operational work items remaining unstarted. This presents an unacceptable executive visibility hazard.

### 3.2 The Reconciled Rule
For any task assigned to a project (`project_id IS NOT NULL`), placing it in `backlog` represents committed work that has not yet entered active sprint/weekly execution. Therefore, **`backlog` must be included in the project progress denominator**.

$$\text{progress\_percent} = \begin{cases} 0 & \text{if } N_{\text{eligible}} = 0 \\ \operatorname{round}\left(\frac{N_{\text{done}}}{N_{\text{eligible}}} \times 100\right) & \text{if } N_{\text{eligible}} > 0 \end{cases}$$
Where:
$$N_{\text{eligible}} = N_{\text{backlog}} + N_{\text{todo}} + N_{\text{in\_progress}} + N_{\text{blocked}} + N_{\text{done}}$$

Under the revised rule for the same scenario:
$$\text{progress} = \frac{2}{10 + 0 + 0 + 0 + 2} \times 100 = \frac{2}{12} \times 100 = \mathbf{17\%}$$
This accurately reflects actual project status.

---

## 4. Comprehensive Metric Specifications

### 4.1 Project Progress (`projects.progress_percent`)
- **Domain Purpose:** Indicates overall delivery progress of an operational project.
- **Formula:** $\operatorname{round}\left(\frac{\operatorname{count}(\text{tasks WHERE status = 'done'})}{\operatorname{count}(\text{tasks WHERE status IN ('backlog', 'todo', 'in_progress', 'blocked', 'done')})} \times 100\right)$
- **Status Filter:**
  - Numerator: `status = 'done'`
  - Denominator: `status IN ('backlog', 'todo', 'in_progress', 'blocked', 'done')`
- **Exclusions:** `status = 'cancelled'`, `archived_at IS NOT NULL`.
- **Treatment of Missing `due_at`:** Tasks without due dates are fully included; progress is workflow-state dependent, not deadline dependent.
- **Zero-Denominator Rule:** If $N_{\text{eligible}} = 0$, returns integer `0`.
- **Rounding:** Standard nearest integer (`0` to `100`).
- **Storage & Recalculation:** Computed atomically within the database transaction whenever a task's status or project assignment changes.
- **API Response Shape:**
  ```json
  { "id": "01JAX123...", "name": "Surabaya Store Setup", "progress_percent": 17 }
  ```

---

### 4.2 Task Completion Rate (`kpis.task_completion_rate`)
- **Domain Purpose:** Evaluates operational velocity and task execution throughput for a company, branch, or department over a specific time window.
- **Time Window & Timezone:** Defaults to active calendar month or 30-day window, calculated between `window_start` (00:00:00) and `window_end` (23:59:59.999999) in the company's configured IANA timezone (converted to UTC for querying).
- **Formula:** $\operatorname{round}\left(\frac{N_{\text{completed\_in\_window}}}{N_{\text{eligible\_in\_window}}} \times 100, 1\right)$
- **Status Filter:**
  - Numerator: Tasks where `status = 'done'` AND `completed_at BETWEEN window_start AND window_end`.
  - Denominator: Tasks where `created_at <= window_end` AND (`completed_at IS NULL` OR `completed_at >= window_start`) AND `status != 'cancelled'`.
- **Treatment of Missing `due_at`:** Fully included based on `created_at` and `completed_at`.
- **Zero-Denominator Rule:** Returns `0.0` with `freshness: "unavailable"` if no tasks exist in window.
- **Rounding:** 1 decimal place (e.g. `78.4`).
- **Unit:** `percent`.
- **API Response Shape:**
  ```json
  {
    "key": "task_completion_rate",
    "label": "Task Completion Rate",
    "value": 78.4,
    "unit": "percent",
    "window_start": "2026-10-01T00:00:00.000000Z",
    "window_end": "2026-10-25T23:59:59.999999Z",
    "freshness": "fresh"
  }
  ```

---

### 4.3 Overdue Task Count (`kpis.overdue_task_count`)
- **Domain Purpose:** Raw count of operational tasks that have breached their committed SLA.
- **Formula:** $\operatorname{count}(\text{tasks WHERE status IN ('backlog', 'todo', 'in_progress', 'blocked') AND due_at < NOW() AND archived_at IS NULL})$
- **Status Filter:**
  - Included: `backlog`, `todo`, `in_progress`, `blocked`
  - Excluded: `done`, `cancelled`, `archived`
- **Treatment of Missing `due_at`:** Excluded from overdue evaluation. A task without a due date cannot be mathematically overdue.
- **Zero-Denominator Rule:** Not applicable (raw count). Returns integer `0` when no overdue tasks exist.
- **Timezone Behavior:** `NOW()` evaluated in UTC against database `due_at DATETIME(6)` in UTC.
- **Unit:** `count`.
- **API Response Shape:**
  ```json
  {
    "key": "overdue_task_count",
    "label": "Overdue Tasks",
    "value": 7,
    "unit": "count",
    "freshness": "fresh"
  }
  ```

---

### 4.4 Open Task Count (`kpis.open_task_count`)
- **Domain Purpose:** Total active backlog and work-in-progress inventory.
- **Formula:** $\operatorname{count}(\text{tasks WHERE status IN ('backlog', 'todo', 'in_progress', 'blocked') AND archived_at IS NULL})$
- **Status Filter:**
  - Included: `backlog`, `todo`, `in_progress`, `blocked`
  - Excluded: `done`, `cancelled`, `archived`
- **Treatment of Missing `due_at`:** Fully included.
- **Zero-Denominator Rule:** Returns integer `0`.
- **Unit:** `count`.
- **API Response Shape:**
  ```json
  {
    "key": "open_task_count",
    "label": "Open Tasks",
    "value": 24,
    "unit": "count",
    "freshness": "fresh"
  }
  ```

---

### 4.5 Task Overdue Rate (`kpis.task_overdue_rate`)
- **Domain Purpose:** Percentage of active operational work currently failing deadline compliance.
- **Formula:** $\operatorname{round}\left(\frac{\text{overdue\_task\_count}}{\text{open\_task\_count}} \times 100, 1\right)$
- **Status Filter:**
  - Numerator: `overdue_task_count`
  - Denominator: `open_task_count`
- **Treatment of Missing `due_at`:** Tasks without `due_at` remain in the denominator (they represent active open work that could have had a deadline), providing an accurate percentage of total workload compromised. *(Note: If product owner prefers denominator restricted only to open tasks with deadlines, see Trade-off Note below)*.
- **Zero-Denominator Rule:** If `open_task_count = 0`, returns `0.0`.
- **Rounding:** 1 decimal place (e.g. `12.1`).
- **Unit:** `percent`.
- **API Response Shape:**
  ```json
  {
    "key": "task_overdue_rate",
    "label": "Task Overdue Rate",
    "value": 12.1,
    "unit": "percent",
    "freshness": "fresh"
  }
  ```

---

### 4.6 Branch Operational Status (`kpis.branch_operational_status`)
- **Domain Purpose:** High-level spatial health index displayed on 3D island markers and sidebar pills.
- **Evaluation Rules:**
  $$\text{status} = \begin{cases} 
  \text{'critical'} & \text{if } N_{\text{critical\_alerts}} > 0 \text{ OR } \text{overdue\_task\_count} \ge 5 \\
  \text{'warning'} & \text{if } N_{\text{warning\_alerts}} > 0 \text{ OR } \text{overdue\_task\_count} \in [2, 4] \\
  \text{'operational'} & \text{otherwise}
  \end{cases}$$
- **Zero-Denominator Rule:** If 0 tasks exist, returns `'operational'` unless open alerts exist.
- **Unit:** `enum` (`operational`, `warning`, `critical`).
- **API Response Shape:**
  ```json
  {
    "key": "branch_operational_status",
    "label": "Branch Status",
    "value": "warning",
    "unit": "status",
    "freshness": "fresh"
  }
  ```

---

## 5. Verification Test Scenarios (Golden Examples)

| Test Case ID | Project / Task Population | Expected `progress_percent` | Expected `overdue_task_count` | Expected `open_task_count` | Notes / Verified Rule |
|---|---|:---:|:---:|:---:|---|
| **TC-KPI-01** | Empty Project: 0 tasks | `0%` | 0 | 0 | Zero-denominator safe fallback |
| **TC-KPI-02** | 2 done, 10 backlog, 0 todo, 0 in_progress | `17%` | 0 | 10 | **Backlog inclusion prevents false 100%** |
| **TC-KPI-03** | 5 done, 5 in_progress, 2 cancelled | `50%` | 0 | 5 | Cancelled tasks ignored from progress (5/10) |
| **TC-KPI-04** | 1 done, 2 todo (1 past due), 1 cancelled | `33%` | 1 | 2 | Decimal rounded from 33.33% to 33%; Overdue counted |
| **TC-KPI-05** | 3 todo (past due, missing `due_at`, future `due_at`) | `0%` | 1 | 3 | Missing `due_at` excluded from overdue numerator |
| **TC-KPI-06** | 6 overdue tasks, 0 alerts | N/A | 6 | 6 | Branch status evaluates to `'critical'` ($\ge 5$) |
| **TC-KPI-07** | 3 overdue tasks, 0 alerts | N/A | 3 | 3 | Branch status evaluates to `'warning'` ($2..4$) |
| **TC-KPI-08** | Task due at 23:59 Jakarta time evaluated at 00:01 Jakarta time | N/A | Transitions to Overdue | Local company timezone boundary strictly enforced |
