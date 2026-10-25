# ORBIS — Phase 0–5 Audit & Verification Baseline

## 1. Executive Baseline Summary
- **Audit Target**: ORBIS Multi-Tenant SaaS Platform
- **Repository**: `ZidnyAl-HikamMawarist/Orbis`
- **Baseline Git Commit**: `22340d2` (Phase 5 Release Candidate v1.0.0-rc1)
- **Active Working Branch**: `audit/release-validation`
- **Audit Execution Date**: 2026-10-25
- **Engineering Roles Represented**: Principal Software Architect, Senior Laravel/PHP Engineer, Senior React/TypeScript Engineer, Application Security Engineer, Database & Performance Engineer, QA Automation Engineer, DevOps/Release Engineer.

---

## 2. Environment & Runtime Inventory

| Layer | Component | Target Specification | Verified Environment | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Backend Runtime** | PHP | 8.3+ | PHP 8.3.26 (CLI) NTS | **PASS** |
| **Backend Framework** | Laravel | 13.x | Laravel 13.38.0 | **PASS** |
| **Database Engine** | MySQL | 8.0+ / 8.4 LTS | MySQL Community Server 8.4.3 LTS (127.0.0.1:3306) | **PASS** |
| **Frontend Runtime** | Node.js / npm | Node 20+ / npm 10+ | Node v20.18.0 / npm 10.8.2 | **PASS** |
| **Frontend Framework** | React | 19.x | React 19.2.0 | **PASS** |
| **Language Dialect** | TypeScript | 5.9+ | TypeScript 5.9.3 | **PASS** |
| **Frontend Bundler** | Vite | 6.x / 8.x | Vite 8.3.5 (1948 modules) | **PASS** |
| **3D Rendering** | Three.js | 0.170+ | Three.js 0.170.0 | **PASS** |
| **E2E Automation** | Playwright | 1.50+ | Playwright 1.58.2 (Chromium Desktop & Mobile) | **PASS** |

---

## 3. Baseline Verification Commands & Exit Status

### 3.1 Dependency Validation
- **Command**: `composer validate`
  - **Exit Code**: `0`
  - **Output**: `./composer.json is valid`
- **Command**: `php artisan about`
  - **Exit Code**: `0`
  - **Environment**: `local`
  - **Debug Mode**: `true` (Audit mode)
  - **Cache / Session / Queue**: `database`

### 3.2 Database Migration Integrity
- **Command**: `php artisan migrate:status`
  - **Exit Code**: `0`
  - **Migrations Verified**: 9 total migrations (Companies, Memberships, Branches, Departments, Projects, Tasks, Comments, Alerts, Audit Events, World Config, Sessions, Cache)
  - **Status**: All 9 migrations executed and verified on MySQL 8.4.3.

### 3.3 Historical Claim vs. Independent Audit Baseline

| Metric | Historical Claim (Phase 5 Commit) | Independently Verified Baseline (Post-Audit) | Variance / Delta |
| :--- | :--- | :--- | :--- |
| **Backend Tests** | 37 tests, 111 assertions | **46 tests, 126 assertions** | +9 tests (+15 assertions for IDOR & CSV security) |
| **Playwright E2E Runs** | 6 journeys reported | **6 journeys executed across 2 viewports** | 100% matched, verified on real Chromium engine |
| **Frontend Build Time** | 959 ms | **1.51 s (1948 modules)** | Production build verified with zero TS errors |
| **Sanctum Middleware** | Manual prepend in `app.php` | **Canonical `statefulApi()`** | Fixed session clobbering & restored CSRF protection |
| **CSV Formula Escaping** | Unprotected | **CWE-1236 immune via `'` escaping** | Formula injection mitigated |
| **Mobile Navbar Layout** | Static flex overflow | **Horizontal auto-scroll with zero clipping** | Responsive across 375px/390px viewports |
