<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user via session cookie.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Load active memberships with company details
        $memberships = $user->memberships()
            ->where('status', 'active')
            ->with('company')
            ->get();

        if ($memberships->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Account has no active company memberships.',
                'code' => 'NO_ACTIVE_MEMBERSHIPS',
            ], 403);
        }

        // Set default active company
        $primaryMembership = $memberships->first();
        session(['current_company_id' => $primaryMembership->company_id]);

        AuditEvent::log('auth.login', 'user', $user->id, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'current_company' => $primaryMembership->company,
            'current_membership' => [
                'id' => $primaryMembership->id,
                'role' => $primaryMembership->role,
                'status' => $primaryMembership->status,
            ],
            'companies' => $memberships->map(fn ($m) => [
                'id' => $m->company->id,
                'name' => $m->company->name,
                'slug' => $m->company->slug,
                'role' => $m->role,
                'is_current' => $m->company_id === $primaryMembership->company_id,
            ]),
        ]);
    }

    /**
     * Get authenticated user identity and current company context.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $memberships = $user->memberships()
            ->where('status', 'active')
            ->with('company')
            ->get();

        $currentCompanyId = session('current_company_id') ?? $memberships->first()?->company_id;

        if ($currentCompanyId && ! session('current_company_id')) {
            session(['current_company_id' => $currentCompanyId]);
        }

        $currentMembership = $memberships->firstWhere('company_id', $currentCompanyId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'current_company' => $currentMembership?->company,
            'current_membership' => $currentMembership ? [
                'id' => $currentMembership->id,
                'role' => $currentMembership->role,
                'status' => $currentMembership->status,
            ] : null,
            'companies' => $memberships->map(fn ($m) => [
                'id' => $m->company->id,
                'name' => $m->company->name,
                'slug' => $m->company->slug,
                'role' => $m->role,
                'is_current' => $m->company_id === $currentCompanyId,
            ]),
        ]);
    }

    /**
     * Switch active company context with strict server-side membership validation.
     */
    public function switchCompany(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => ['required', 'string', 'size:26'],
        ]);

        $user = $request->user();
        $targetCompanyId = $request->input('company_id');

        $targetMembership = $user->memberships()
            ->where('company_id', $targetCompanyId)
            ->where('status', 'active')
            ->with('company')
            ->first();

        if (! $targetMembership) {
            return response()->json([
                'message' => 'Forbidden. You do not hold an active membership in the requested company.',
                'code' => 'MEMBERSHIP_NOT_FOUND',
            ], 403);
        }

        session(['current_company_id' => $targetCompanyId]);

        AuditEvent::log('auth.switch_company', 'company', $targetCompanyId, [
            'previous_company_id' => session('current_company_id'),
            'target_company_id' => $targetCompanyId,
        ]);

        return response()->json([
            'message' => 'Company context switched successfully.',
            'current_company' => $targetMembership->company,
            'current_membership' => [
                'id' => $targetMembership->id,
                'role' => $targetMembership->role,
                'status' => $targetMembership->status,
            ],
        ]);
    }

    /**
     * Terminate authenticated session and rotate CSRF tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditEvent::log('auth.logout', 'user', $user->id);
        }

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
