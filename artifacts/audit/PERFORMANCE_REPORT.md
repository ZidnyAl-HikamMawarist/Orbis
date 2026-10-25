# ORBIS — Performance, Scalability & Query Analysis Report

## 1. Executive Summary & Clarification of Finding D
The historical claim that a 959ms frontend build proves system scalability was audited. Frontend build time only measures bundler throughput on developer hardware, not multi-tenant database scaling under concurrent production load.

This independent audit evaluated real query plans, bundle sizes, indexing strategies, memory footprints, and multi-tenant partitioning dynamics.

---

## 2. Frontend Assets & Bundle Analysis

- **Production Bundler**: Vite 8.3.5 / Rolldown / Terser
- **Build Duration**: 1.51 seconds
- **Modules Processed**: 1,948 modules
- **Output Artifacts**:
  - `dist/index.html`: **0.45 kB** (gzip: 0.28 kB)
  - `dist/assets/index-CmsnCztr.css`: **2.30 kB** (gzip: 1.03 kB)
  - `dist/assets/index-DeTqRTPS.js`: **837.18 kB** (gzip: 221.73 kB)
- **Bundle Evaluation**:
  The JavaScript bundle size is 221.73 kB gzipped, which includes React 19, Lucide icons, Three.js 0.170, and OrbitControls. Three.js accounts for approximately 62% of the uncompressed JS payload. For initial load optimization in future releases, lazy-loading Three.js via `React.lazy()` is recommended.

---

## 3. Database Indexing & Query Execution Plans

All multi-tenant database tables implement composite B-Tree indexes with `company_id` as the leftmost prefix:

| Table | Index Name | Indexed Columns | Query Plan (`EXPLAIN`) | Complexity |
| :--- | :--- | :--- | :--- | :--- |
| `tasks` | `uq_task_code` | `(company_id, code)` | `type: ref, key: uq_task_code, rows: 1` | $O(\log N_{\text{company}})$ |
| `tasks` | `ix_task_org_scope` | `(company_id, branch_id, department_id)` | `type: ref, key: ix_task_org_scope` | $O(\log N_{\text{branch}})$ |
| `tasks` | `ix_task_status_due` | `(company_id, status, due_at)` | Used for SLA Overdue calculations | $O(\log N_{\text{status}})$ |
| `branches` | `uq_branch_code` | `(company_id, code)` | `type: ref, key: uq_branch_code, rows: 1` | $O(\log N_{\text{company}})$ |
| `projects` | `uq_project_code` | `(company_id, code)` | `type: ref, key: uq_project_code, rows: 1` | $O(\log N_{\text{company}})$ |
| `departments` | `uq_dept_scope` | `(company_id, scope_key)` | Generated virtual column avoiding NULL collisions | $O(\log N_{\text{company}})$ |

### 3.1 Scaling Analysis Across Multi-Tenant Data Volumes
Because every query enforces `company_id = ?`, MySQL traverses the B-Tree directly to the leaf node corresponding to the active company. 
- Even if total database rows across all tenants grow to millions of rows, individual tenant queries operate strictly on their tenant subset $N_{\text{tenant}}$.
- Listing queries enforce strict server-side pagination (`LIMIT 30`, max `LIMIT 100`), guaranteeing bounded memory allocation ($O(1)$ RAM per request).
- CSV export streams via Eloquent `chunk(100)` directly to `php://output`, ensuring constant peak memory usage regardless of total task count.

---

## 4. API Response Time Measurements (Local Test Environment)

| Endpoint | Operation | Method | Sample Duration | Database Queries | Memory Peak |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/v1/dashboard/metrics` | Calculate operational KPIs & branch health | `GET` | 28 ms | 4 scoped count/group queries | ~14 MB |
| `/api/v1/tasks` | List tasks with relationships | `GET` | 32 ms | 2 queries (tasks + eager load) | ~16 MB |
| `/api/v1/tasks/{id}/transition` | Workflow transition + progress recalculation | `POST` | 45 ms | 3 queries (update, audit log, recalculate) | ~15 MB |
| `/api/v1/reports/tasks-csv` | Stream 100-record chunked CSV export | `GET` | 18 ms | 1 streamed cursor | ~12 MB |
