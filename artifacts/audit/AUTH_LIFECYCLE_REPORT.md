# ORBIS — Sanctum Session Lifecycle & Authentication Audit Report

## 1. Investigation of High-Priority Inquest A

### 1.1 Context & Background
In Phase 1, the implementation cited Laravel Sanctum's `statefulApi()` middleware. In Phase 5 diffs, `statefulApi()` was replaced with a manual prepend array:
```php
$middleware->api(prepend: [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
]);
```
The user raised concerns about whether this change introduced session clobbering, lost CSRF protection, or unauthenticated redirects on subsequent browser requests.

### 1.2 Root Cause Analysis
1. **Double Session Initialization**:
   When `statefulApi()` was combined with manual prepends, `StartSession::class` was executed twice in the request pipeline. The secondary execution created a blank session store, clobbering session cookies and causing subsequent requests to fail with `401 Unauthenticated`.
2. **PHPUnit Stateful Detection**:
   In Laravel Sanctum, `EnsureFrontendRequestsAreStateful::fromFrontend()` checks the `Referer` or `Origin` header against `config('sanctum.stateful')`. In PHPUnit test cases, `$this->postJson()` does not automatically send a `Referer` header. Consequently, `fromFrontend()` returned `false`, and Sanctum skipped the session pipeline during backend test runs, leading past developers to mistakenly add redundant global session middleware.

---

## 2. Definitive Architecture & Configuration Hardening

### 2.1 Final Pipeline in `bootstrap/app.php`
The manual prepend has been eliminated in favor of Laravel's canonical Sanctum configuration:
```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ...
    })->create();
```

### 2.2 PHPUnit Test Suite Foundation in `TestCase.php`
To ensure all automated backend tests exercise the exact stateful Sanctum pipeline used by browsers in production, `apps/api/tests/TestCase.php` now injects the canonical local referer:
```php
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Referer' => 'http://localhost',
        ]);
    }
}
```

### 2.3 Environment & Session Cookie Configuration (`.env`)
- `SESSION_DRIVER=database`: Persistent session records in MySQL `sessions` table.
- `SESSION_DOMAIN=`: Empty string to avoid parsing string `'null'` as literal domain on localhost.
- `SESSION_SECURE_COOKIE=false`: Configured for local HTTP; will be set to `true` in production with HTTPS.
- `SANCTUM_STATEFUL_DOMAINS="localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,127.0.0.1:8000"`: Exhaustive domain allowlist covering Vite and direct API origins.

---

## 3. End-to-End Browser Authentication Lifecycle Verification

The full lifecycle was executed and verified using Playwright against real Chromium browsers:

| Step | Action | Expected Behavior | Observed Result | Status |
| :--- | :--- | :--- | :--- | :--- |
| **1** | Anonymous Visit (`/`) | Displays Login View; protected data blocked | Unauthenticated login screen displayed | **PASS** |
| **2** | Login Attempt | Submits `admin@nusantara.local` / `password` | Returns 200, sets `orbis-session` and `XSRF-TOKEN` cookies | **PASS** |
| **3** | Session Hydration | `/api/v1/auth/me` retrieves user identity | User displayed as `Budi Santoso (Owner)` | **PASS** |
| **4** | Page Reload | Browser reloads page with existing cookies | User remains authenticated without prompt | **PASS** |
| **5** | Context Switch | Switches company tenant | Updates `session('current_company_id')` cleanly | **PASS** |
| **6** | Logout | Clicks `Log out` button | Invalidate session, clear cookies, renders login | **PASS** |
| **7** | Post-Logout Verification | Protected API calls return 401 | Verified unauthenticated; Back button safe | **PASS** |

### Evidence
- **Desktop Chromium**: Journey A & B passed in 22.6s.
- **Mobile Chromium (390×844)**: Journey A & B passed in 25.8s.
- **Trace Output**: Sanitized HTTP archive confirmed proper Set-Cookie headers and 200 OK responses on reload and logout.
