<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $currentCompanyId = session('current_company_id');

        // If not set in session, auto-select the user's first active company membership
        if (! $currentCompanyId) {
            $firstActiveMembership = $user->memberships()
                ->where('status', 'active')
                ->with('company')
                ->first();

            if (! $firstActiveMembership || ! $firstActiveMembership->company) {
                return response()->json([
                    'message' => 'Forbidden. No active company membership found for this user.',
                    'code' => 'NO_ACTIVE_MEMBERSHIP',
                ], 403);
            }

            $currentCompanyId = $firstActiveMembership->company_id;
            session(['current_company_id' => $currentCompanyId]);
        }

        // Verify active membership in the active company
        $membership = $user->membershipIn($currentCompanyId);

        if (! $membership) {
            return response()->json([
                'message' => 'Forbidden. You do not hold an active membership in the selected company context.',
                'code' => 'TENANT_ACCESS_DENIED',
            ], 403);
        }

        $request->attributes->set('current_company_id', $currentCompanyId);
        $request->attributes->set('current_membership', $membership);

        return $next($request);
    }
}
