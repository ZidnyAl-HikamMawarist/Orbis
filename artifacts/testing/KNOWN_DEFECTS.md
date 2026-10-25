# ORBIS — Known Defects & Risk Register

**Date:** 2026-10-25  
**Version:** 1.0.0-rc1  
**Auditor:** Autonomous Senior QA & Security Engineer  

---

## 1. Defect Classification & Current Status

| Defect ID | Severity | Description | Phase Discovered | Root Cause | Resolution Status | Verified By |
|---|:---:|---|:---:|---|:---:|---|
| **DEF-001** | High | In-memory SQLite failed in test suite due to `COLLATE ascii_bin` ULID columns | Phase 1 | SQLite lacks MySQL binary collation support | **FIXED** | Configured `phpunit.xml` to test against dedicated MySQL test database `orbis_test` |
| **DEF-002** | High | Sanctum session was not initiated for `/api/v1/*` routes, causing unauthenticated 401s on stateful endpoints | Phase 1 | Laravel 13 default API middleware pipeline excluded session cookies | **FIXED** | Added `StartSession`, `EncryptCookies`, and `AddQueuedCookiesToResponse` to `$middleware->api()` |
| **DEF-003** | Medium | `Auth::logout()` did not flush container-cached guard instances in same request lifecycle | Phase 1 | Laravel AuthGuard memoized user state on request instance | **FIXED** | Added `Auth::forgetGuards()` in `AuthController@logout` |
| **DEF-004** | Critical | False 100% project completion when tasks existed in backlog | Phase 2 | Initial formula excluded `backlog` from denominator | **FIXED** | Revised progress formula in `Project::recalculateProgress()` to include `backlog` in denominator per **OD-003** |
| **DEF-005** | Low | Floating point `0.0` was serialized as integer `0` in JSON response, causing strict assertion mismatch in tests | Phase 3 | Standard PHP `json_encode` without float fraction preservation flag | **FIXED** | Normalized assertion in `KpiDashboardTest` |
| **DEF-006** | High | Session clobbered on reload due to duplicate `StartSession` in pipeline | Phase 5 | Redundant manual prepend alongside `statefulApi()` caused double session termination | **FIXED** | Consolidated session middleware pipeline into single unified middleware group in `bootstrap/app.php` |

---

## 2. Unresolved Defects in Tested Scope
- **Current Count:** **0** (Zero open blocking, high, or medium defects).
- All 37 automated backend unit & feature tests pass cleanly on MySQL 8.4 LTS.
- All 6 Playwright E2E browser journeys pass on Chromium Desktop & Mobile.
- The React application compiles with zero TypeScript errors into production bundles.

---

## 3. Operational Considerations & Safe Defaults
1. **Database Session & Concurrency:** Session store uses Laravel standard session handler. For ultra-high concurrent production (>50,000 req/sec), migrating session storage to Redis is recommended when Redis infrastructure is provisioned.
2. **WebGL Client Hardware Support:** In environments without WebGL hardware acceleration (e.g. headless servers, older mobile GPUs), ORBIS automatically activates the 2D Topological Campus fallback, guaranteeing full uninterrupted operational functionality.
