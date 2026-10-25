# ORBIS — Application Security Audit & Findings Report

## 1. Executive Summary
An exhaustive security review of the ORBIS codebase and configuration was conducted, covering authentication, session state, authorization (BOLA/IDOR), CSV formula injection, mass assignment, cryptographic hashing, and secret exposure.

---

## 2. Security Findings Summary

| ID | Finding Category | Severity | Description & Root Cause | Resolution Status | Verified Test Reference |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | Input Validation / Injection | **P1 (High)** | **CSV Formula Injection (CWE-1236)**: User-controlled task titles starting with `=, +, -, @` could execute arbitrary formulas when opened in spreadsheet software. | **FIXED**: Escaped with `'` prefix via `sanitizeCsvCell()` in `ReportController.php`. | `TenantIsolationTest::test_csv_export_sanitizes_potential_formula_injection` |
| **SEC-02** | Session Lifecycle / Auth | **P1 (High)** | **Sanctum Double Session Initialization**: Manual prepend of session middleware caused session clobbering and intermittent `401` on reload. | **FIXED**: Consolidated to canonical `$middleware->statefulApi()` with referer propagation. | `AuthTest::test_logout_clears_session`, Playwright Journey A & B |
| **SEC-03** | Authorization / IDOR | **P1 (High)** | **Cross-Tenant Direct Object Reference**: Risk of accessing foreign tenant tasks, projects, or branches via URL parameter tampering. | **VERIFIED IMMUNE**: Global `TenantScope` and strict `where('company_id', ...)` return 404. | `TenantIsolationTest::test_user_cannot_read_task_of_another_company_by_direct_id` |
| **SEC-04** | Cross-Tenant Relationship Tampering | **P1 (High)** | **Foreign Key Injection**: Risk of associating Company A task with Company B project or branch. | **VERIFIED IMMUNE**: Database constraints & Form Request `Rule::exists(...)->where('company_id', $cid)`. | `TenantIsolationTest::test_user_cannot_assign_task_to_foreign_project_id` |
| **SEC-05** | Cryptographic Storage | **P2 (Medium)** | **Invitation Token Protection**: Plaintext invitation tokens could be exposed if database read replicas or logs leaked. | **VERIFIED IMMUNE**: Raw tokens are hashed with SHA-256 (`hash('sha256', $rawToken)`); only hash is stored. | `InvitationTest::test_admin_can_dispatch_invitation_with_sha256_hashed_token` |
| **SEC-06** | Information Disclosure | **P2 (Medium)** | **Tenant Enumeration**: Differentiating between 403 (unauthorized) and 404 (non-existent) could leak existence of foreign resources. | **VERIFIED IMMUNE**: Unscoped ID lookups throw `ModelNotFoundException` returning uniform 404. | `TenantIsolationTest::test_user_cannot_read_project_of_another_company_by_direct_id` |
| **SEC-07** | UI Responsiveness / Usability | **P3 (Low)** | **Mobile Header Navigation Clipping**: Navigation tabs overflowed horizontally on narrow viewports (<400px), preventing task creation. | **FIXED**: Enabled `overflow-x: auto` and `flex-shrink: 0` on `Navbar.tsx`. | Playwright Journey C & D on Chromium Mobile (390×844) |

---

## 3. Residual Security Risks & Recommendations
1. **Production HTTPS Enforcement**:
   In production, set `SESSION_SECURE_COOKIE=true` and enforce HSTS (`Strict-Transport-Security`) at the reverse proxy (Nginx/Cloudflare).
2. **Rate Limiting**:
   Add explicit `throttle:api` limits on `/api/v1/auth/login` (e.g. 5 attempts per minute) and `/api/v1/invitations` to prevent brute force and token exhaustion.
3. **Audit Log External Archival**:
   Although `AuditEvent` model overrides `update()` and `delete()` to throw exceptions in PHP, database-level replication to an append-only external SIEM (e.g., Datadog, AWS CloudWatch, or OpenSearch) is recommended for SOC 2 Type II compliance.
