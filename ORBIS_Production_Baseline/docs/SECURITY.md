# ORBIS Security & Tenant Isolation Specification

**Version:** 1.0.0-reconciled  
**Status:** Reconciled security baseline  
**Lead:** Application Security Engineer

---

## 1. Threat Model & Mitigations

| Threat Vector | Attack Scenario | Implemented Mitigation |
|---|---|---|
| **IDOR / BOLA** | User modifies a URL to access another tenant's task or branch ULID. | All Eloquent queries enforce global tenant scope matching session `current_company_id`. Foreign ULID lookup returns non-disclosing `404 Not Found`. |
| **Cross-Tenant Association Injection** | User crafts a POST request assigning a task to a project or department owned by Company B. | Database composite foreign keys `FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id)` prevent cross-tenant association at the MySQL engine level. |
| **Credential Theft via XSS** | Injected script steals bearer tokens from browser storage. | First-party Laravel session cookies with `HttpOnly`, `Secure`, and `SameSite=Lax`. Tokens are never stored in `localStorage` or `sessionStorage`. |
| **CSRF Attack** | Malicious site triggers state-mutating requests against an active session. | Laravel Sanctum CSRF protection mandates valid `X-XSRF-TOKEN` header on all `POST`, `PATCH`, and `DELETE` endpoints. |
| **Session Fixation** | Attacker presets session ID prior to login. | `$request->session()->regenerate()` is executed upon successful authentication. |
| **Privilege Escalation** | Member updates their own role in a profile or membership request. | Role updates are protected by dedicated Laravel Policy checks (`updateRole`), restricted to Owners and Company Admins. Form Requests explicitly allowlist assignable fields. |
| **Invitation Token Interception** | Compromised email or server logs expose invitation links. | High-entropy 256-bit random tokens are hashed using SHA-256 (`token_hash`) prior to database storage. Tokens expire in 72 hours and are invalidated immediately upon first use. |
| **SQL Injection** | Attacker injects SQL fragments into search or sorting query parameters. | PDO prepared statements used exclusively. Sorting and filtering parameters are validated against strict allowlists. |
| **Audit Log Tampering** | Malicious insider alters historical activity logs. | Application database user is restricted to `SELECT` and `INSERT` on `audit_events` (`UPDATE` and `DELETE` denied). In production, logs are shipped to write-once storage. |

---

## 2. Authentication & Session Architecture

1. **Same-Site Session Authentication:**
   - Session driver: encrypted database or secure file sessions.
   - Cookies emitted: `laravel_session` (`HttpOnly`, `Secure`, `SameSite=Lax`) and `XSRF-TOKEN` (`Secure`, `SameSite=Lax`).
   - Domain binding: Cookies are bound to the parent domain (e.g. `.orbis.id`) ensuring safe communication between `app.orbis.id` and `api.orbis.id`.
2. **Company Switching Authorization:**
   - Invoking `POST /api/v1/companies/{id}/switch` verifies that the authenticated user identity has an `active` row in `company_user` for the target company.
   - Upon verification, `current_company_id` is updated in the session, and all subsequent requests are isolated to that company.

---

## 3. Role-Based Access Control (RBAC) Policy Matrix

| Policy Capability | Owner | Company Admin | Operations Mgr | Branch Mgr | Dept Lead | Staff | Auditor |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `manage-company` | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied |
| `manage-members` | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied |
| `manage-organization` | Allowed | Allowed | Edit Only | Denied | Denied | Denied | Denied |
| `create-task` | Allowed | Allowed | Allowed | Scoped Branch | Scoped Dept | Assigned Only | Denied |
| `transition-task` | Allowed | Allowed | Allowed | Scoped Branch | Scoped Dept | Assigned Only | Denied |
| `reopen-task` | Allowed | Allowed | Allowed | Scoped Branch | Scoped Dept | Denied | Denied |
| `resolve-alert` | Allowed | Allowed | Allowed | Scoped Branch | Scoped Dept | Denied | Denied |
| `view-audit-events` | Allowed | Allowed | Allowed | Denied | Denied | Denied | Allowed |
| `export-reports` | Allowed | Allowed | Allowed | Scoped Branch | Scoped Dept | Denied | Scoped |

---

## 4. Audit Policy & Tamper Evidence

- **Monitored Actions:** Membership invitation, role modification, member deactivation, branch/department archival, task reopening, company switching, and report exports.
- **Payload Sanitization:** Passwords, password hashes, plain-text invitation tokens, session cookies, and private personal identifiers are strictly redacted before writing to `audit_events`.
- **Database Grants:** Dual-identity model: Web application user (`orbis_app`) is restricted to `SELECT` and `INSERT` on `audit_events` (`UPDATE` and `DELETE` denied). Background CLI maintenance user (`orbis_maintenance`) holds `SELECT` and `DELETE` exclusively for scheduled retention pruning after cold-storage archival (`OD-008`).
- **Database Immutability Limits:** Acknowledged that relational MySQL tables are not hardware-immutable; integrity is maintained via least-privilege role segregation and external checksummed archival.

---

## 5. Mandatory Automated Negative Security Tests

**Core Principle:** ULIDs are public opaque identifiers, **never authorization boundaries**. Every request must enforce server-side tenant scoping from authenticated session state (`current_company_id`).

The automated test suite must execute negative tests covering every tenant-owned resource (Branches, Departments, Memberships, Projects, Tasks, Comments, Alerts, KPIs, World Configs, Audit Events):
1. **Cross-Tenant Reads (SEC-ISO-01):** User in Company A cannot view or list records belonging to Company B (returns `404 Not Found`).
2. **Cross-Tenant Mutations (SEC-ISO-02, SEC-ISO-03):** User in Company A cannot update, archive, or delete records belonging to Company B (returns `404 Not Found`).
3. **Relationship Injection (SEC-ISO-04):** Creation/update of tasks with foreign branch/department/project IDs belonging to another tenant is blocked with `422 Unprocessable`.
4. **Context Switching (SEC-ISO-05):** User cannot switch session context to a company where they lack active membership (returns `403 Forbidden`).
5. **Invitation Spoofing (SEC-ISO-07):** Tokens cannot be intercepted or claimed by unauthorized actors; expired or revoked tokens yield non-disclosing rejections.
*(See detailed test specifications in `artifacts/audit/DECISION_REVIEW_FINDINGS.md`)*.
3. User in Company A cannot create an association pointing to an entity ID from Company B.
4. Unauthenticated requests return `401 Unauthorized`.
5. Missing `X-XSRF-TOKEN` on mutating requests returns `419 Page Expired / CSRF Token Mismatch`.
6. Staff member cannot reopen a completed task or escalate their own role.
7. Invitation token cannot be reused after initial acceptance or after 72 hours.
