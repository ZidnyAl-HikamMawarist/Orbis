# ORBIS — Application Security Audit & Findings Report

## 1. Executive Summary

An exhaustive security review of the ORBIS codebase and configuration was conducted, covering authentication, session state, authorization (BOLA/IDOR), CSV formula injection, mass assignment, cryptographic hashing, and secret exposure.

In RC2, critical security findings were re-audited and hardened, specifically resolving edge-case bypasses in CSV formula sanitization and validating full multi-tenant isolation in browser end-to-end tests.

---

## 2. Security Findings Summary

| ID | Finding Category | Severity | Description & Root Cause | Resolution Status | Verified Test Reference |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | Input Validation / Injection | **P1 (High)** | **CSV Formula Injection (CWE-1236)**: User-controlled fields starting with `=, +, -, @, %, \|`, or with leading whitespace (`"   =SUM(...)"`), tabs (`\t`), or carriage returns (`\r\n`), could execute arbitrary formulas or DDE macros when opened in spreadsheet software (Excel, LibreOffice, Google Sheets). | **HARDENED & VERIFIED**: Trims leading whitespace and detects formula triggers, prepending a single quote `'` across ALL exported columns. Dedicated regression test covers all edge cases. | `CsvSanitizationTest` (2 tests, 27 assertions) & `TenantIsolationTest` |
| **SEC-02** | Session Lifecycle / Auth | **P1 (High)** | **Sanctum Double Session Initialization**: Manual prepend of session middleware caused session clobbering and intermittent `401` on reload. | **FIXED**: Consolidated to canonical `$middleware->statefulApi()` with referer propagation. | `AuthTest::test_logout_clears_session`, Playwright Journey A & B |
| **SEC-03** | Authorization / IDOR | **P1 (High)** | **Cross-Tenant Direct Object Reference**: Risk of accessing foreign tenant tasks, projects, or branches via URL parameter tampering. | **VERIFIED IMMUNE**: Global `TenantScope` and strict `where('company_id', ...)` return 404. Verified in browser E2E. | `TenantIsolationTest`, Playwright Journey I |
| **SEC-04** | Cross-Tenant Relationship Tampering | **P1 (High)** | **Foreign Key Injection**: Risk of associating Company A task with Company B project or branch. | **VERIFIED IMMUNE**: Database constraints & Form Request `Rule::exists(...)->where('company_id', $cid)`. | `TenantIsolationTest::test_user_cannot_assign_task_to_foreign_project_id` |
| **SEC-05** | Cryptographic Storage | **P2 (Medium)** | **Invitation Token Protection**: Plaintext invitation tokens could be exposed if database read replicas or logs leaked. | **VERIFIED IMMUNE**: Raw tokens are hashed with SHA-256 (`hash('sha256', $rawToken)`); only hash is stored. | `InvitationTest::test_admin_can_dispatch_invitation_with_sha256_hashed_token` |
| **SEC-06** | Information Disclosure | **P2 (Medium)** | **Tenant Enumeration**: Differentiating between 403 (unauthorized) and 404 (non-existent) could leak existence of foreign resources. | **VERIFIED IMMUNE**: Unscoped ID lookups throw `ModelNotFoundException` returning uniform 404. | `TenantIsolationTest::test_user_cannot_read_project_of_another_company_by_direct_id` |
| **SEC-07** | UI Responsiveness / Usability | **P3 (Low)** | **Mobile Header Navigation Clipping**: Navigation tabs overflowed horizontally on narrow viewports (<400px), preventing task creation. | **FIXED**: Enabled `overflow-x: auto` and `flex-shrink: 0` on `Navbar.tsx`. | Playwright Journey C & D on Chromium Mobile (390×844) |

---

## 3. Deep-Dive on SEC-01: CSV Formula Injection (CWE-1236)

### 3.1 Vulnerability Mechanism
Spreadsheet calculators (Microsoft Excel, LibreOffice Calc, Google Sheets, Apple Numbers) treat cells starting with formula characters as executable expressions:
- Trigger characters: `=`, `+`, `-`, `@`, `%`, `|`.
- **Evasion & Bypass Vector**: Excel and LibreOffice strip leading spaces and control characters (`\t`, `\r`, `\n`) before parsing formulas. Therefore, a naive regex check like `/^[=+\-@]/` will fail to detect payloads such as:
  - `"   =1+1"` (leading spaces)
  - `"\t=SUM(1,2)"` (leading tab)
  - `"\r\n=cmd|'/C calc'!A0"` (leading CRLF)
  - `"-2+3+cmd|' /C calc'!Z0"`
  - `"@SUM(1+1)"`
  - `"%0A=1+1"`

### 3.2 Implemented Sanitization Strategy
In `apps/api/app/Http/Controllers/Api/ReportController.php`:
```php
public function sanitizeCsvCell(mixed $value): mixed
{
    if (! is_string($value) || $value === '') {
        return $value;
    }

    // Detect leading control characters (tabs, CRLF)
    if (preg_match('/^[\t\r\n]/', $value)) {
        return "'" . $value;
    }

    // Strip leading whitespace and control characters before formula trigger check
    $trimmed = ltrim($value, " \t\n\r\0\x0B");

    if ($trimmed !== '' && preg_match('/^[=\+\-@%|]/', $trimmed)) {
        return "'" . $value;
    }

    return $value;
}
```
All streamed CSV columns (`Task ID`, `Code`, `Title`, `Status`, `Priority`, `Project`, `Branch`, `Department`, `Assignee`, `Due Date`, `Created At`) pass through `array_map([$this, 'sanitizeCsvCell'], $row)`.

### 3.3 Automated Regression Testing (`CsvSanitizationTest.php`)
1. **Unit Edge Cases (`test_sanitize_csv_cell_edge_cases`)**:
   - Asserts `=SUM(1+1)` -> `'=SUM(1+1)`
   - Asserts `"   =SUM(1+1)"` -> `"'   =SUM(1+1)"`
   - Asserts `"\t=1+1"` -> `"\t=1+1"` (prepended with `'`)
   - Asserts `"+1+1"`, `"-1+1"`, `"@SUM"`, `"%SYSTEM%"`, `"|cmd"` are all prepended with `'`
   - Asserts legitimate alphanumeric codes (`TSK-001`, `DEPLOY-PROD`, `2026-10-25`, `42`) remain untouched without quote prepending.
2. **Streamed Response Test (`test_streamed_csv_export_sanitizes_across_all_model_fields`)**:
   - Creates models with adversarial payloads across Task Code, Title, Branch name, Department name, and Project name.
   - Streams CSV via `GET /api/v1/reports/tasks-csv`.
   - Asserts that every single field containing adversarial triggers has the `'` prefix in the emitted CSV byte stream.

### 3.4 Residual Limitations & Trade-offs
1. **Visual Single Quote in Plaintext / ETL**:
   - The prepended single quote (`'`) instructs Excel/Google Sheets to interpret the cell as plaintext string data, preventing execution.
   - However, in raw CSV viewers, text editors, or downstream data pipelines (Python Pandas, R, ETL importers), the leading `'` remains part of the raw string.
   - For internal data processing, consumers should strip leading single quotes if exact string identity is required.
2. **Numeric Hyphen Edge Cases**:
   - Cells beginning with a negative sign (`-500.00` or `-10%`) will receive a `'` prefix (`'-500.00`), treating it as text rather than a number in Excel. In ORBIS task exports, numeric quantities are currently integers/dates; should financial numeric exports be added in future releases, dedicated typed formatting (separate CSV vs XLSX/JSON) is recommended.

---

## 4. Residual Security Risks & Production Recommendations

1. **Production HTTPS Enforcement**:
   - In production, set `SESSION_SECURE_COOKIE=true` and enforce HSTS (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) at the reverse proxy (Nginx/Cloudflare).
2. **Rate Limiting**:
   - Add explicit `throttle:api` limits on `/api/v1/auth/login` (e.g. 5 attempts per minute) and `/api/v1/invitations` to prevent brute force and token exhaustion.
3. **Audit Log External Archival**:
   - Although `AuditEvent` model overrides `update()` and `delete()` to throw exceptions in PHP, database-level replication to an append-only external SIEM (e.g., Datadog, AWS CloudWatch, or OpenSearch) is recommended for SOC 2 Type II compliance.
