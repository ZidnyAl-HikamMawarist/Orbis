<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    /**
     * List invitations for active company context (Admin/Owner only).
     */
    public function index(Request $request): JsonResponse
    {
        $currentCompanyId = session('current_company_id');
        $user = $request->user();
        $membership = $user->membershipIn($currentCompanyId);

        if (! $membership || ! $membership->canManageMembers()) {
            return response()->json(['message' => 'Unauthorized to view invitations.'], 403);
        }

        $invitations = CompanyInvitation::where('company_id', $currentCompanyId)
            ->with(['inviter.user', 'branch', 'department'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($invitations);
    }

    /**
     * Create and dispatch a new company invitation.
     */
    public function store(Request $request): JsonResponse
    {
        $currentCompanyId = session('current_company_id');
        $user = $request->user();
        $membership = $user->membershipIn($currentCompanyId);

        if (! $membership || ! $membership->canManageMembers()) {
            return response()->json(['message' => 'Unauthorized to invite members.'], 403);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', Rule::in([
                'company_admin',
                'operations_manager',
                'branch_manager',
                'department_lead',
                'staff',
                'auditor'
            ])],
            'branch_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'id')->where('company_id', $currentCompanyId)],
            'department_id' => ['nullable', 'string', 'size:26', Rule::exists('departments', 'id')->where('company_id', $currentCompanyId)],
        ]);

        // Prevent role escalation (e.g. admin cannot invite owner)
        if ($membership->role !== 'owner' && $data['role'] === 'owner') {
            return response()->json(['message' => 'Cannot invite with role higher than your own.'], 403);
        }

        // Check if user is already an active member of this company
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser && $existingUser->membershipIn($currentCompanyId)) {
            return response()->json([
                'message' => 'User is already an active member of this company.',
                'code' => 'ALREADY_MEMBER',
            ], 422);
        }

        // Generate high-entropy 256-bit token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = CompanyInvitation::hashToken($plainToken);

        $invitation = DB::transaction(function () use ($currentCompanyId, $membership, $data, $tokenHash) {
            // Revoke any prior pending invitations for this email in this company
            CompanyInvitation::where('company_id', $currentCompanyId)
                ->where('email', $data['email'])
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            $newInvite = CompanyInvitation::create([
                'id' => (string) Str::ulid(),
                'company_id' => $currentCompanyId,
                'email' => $data['email'],
                'role' => $data['role'],
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'token_hash' => $tokenHash,
                'invited_by_membership_id' => $membership->id,
                'status' => 'pending',
                'expires_at' => now()->addHours(72),
                'created_at' => now(),
            ]);

            AuditEvent::log('invitation.created', 'company_invitation', $newInvite->id, [
                'email' => $data['email'],
                'role' => $data['role'],
            ]);

            return $newInvite;
        });

        return response()->json([
            'message' => 'Invitation dispatched successfully.',
            'invitation' => $invitation,
            'invite_token' => $plainToken, // provided for local testing / direct acceptance
            'invite_url' => url('/invitations/accept?token='.$plainToken),
        ], 201);
    }

    /**
     * Validate an invitation token before user signs up or accepts.
     */
    public function validateToken(string $token): JsonResponse
    {
        $tokenHash = CompanyInvitation::hashToken($token);

        $invitation = CompanyInvitation::where('token_hash', $tokenHash)
            ->with(['company', 'branch', 'department'])
            ->first();

        if (! $invitation || $invitation->status === 'revoked') {
            return response()->json([
                'message' => 'Invitation not found or has been revoked.',
                'code' => 'INVITATION_INVALID',
            ], 404);
        }

        if ($invitation->status === 'accepted') {
            return response()->json([
                'message' => 'This invitation has already been accepted.',
                'code' => 'INVITATION_ALREADY_ACCEPTED',
            ], 410);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired.',
                'code' => 'INVITATION_EXPIRED',
            ], 410);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'company_name' => $invitation->company->name,
            'branch_name' => $invitation->branch?->name,
            'department_name' => $invitation->department?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Accept invitation atomically and link/create user.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $tokenHash = CompanyInvitation::hashToken($token);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $result = DB::transaction(function () use ($tokenHash, $data) {
            $invitation = CompanyInvitation::where('token_hash', $tokenHash)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->isExpired()) {
                throw ValidationException::withMessages([
                    'token' => ['Invitation token is invalid, expired, or already used.'],
                ]);
            }

            // Find or create user
            $user = User::where('email', $invitation->email)->first();

            if (! $user) {
                if (empty($data['password']) || empty($data['name'])) {
                    throw ValidationException::withMessages([
                        'password' => ['Name and password are required for new account registration.'],
                    ]);
                }

                $user = User::create([
                    'id' => (string) Str::ulid(),
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                ]);
            }

            // Verify not already member
            $existingMembership = CompanyUser::where('company_id', $invitation->company_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingMembership) {
                $invitation->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                return ['user' => $user, 'membership' => $existingMembership];
            }

            // Create membership
            $membership = CompanyUser::create([
                'id' => (string) Str::ulid(),
                'company_id' => $invitation->company_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => 'active',
                'invited_by_membership_id' => $invitation->invited_by_membership_id,
                'joined_at' => now(),
                'created_at' => now(),
            ]);

            // Mark invitation accepted
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            AuditEvent::log('invitation.accepted', 'company_invitation', $invitation->id, [
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
                'role' => $invitation->role,
            ]);

            return ['user' => $user, 'membership' => $membership];
        });

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'membership' => [
                'id' => $result['membership']->id,
                'role' => $result['membership']->role,
            ],
        ]);
    }

    /**
     * Revoke an unaccepted invitation.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $currentCompanyId = session('current_company_id');
        $user = $request->user();
        $membership = $user->membershipIn($currentCompanyId);

        if (! $membership || ! $membership->canManageMembers()) {
            return response()->json(['message' => 'Unauthorized to revoke invitations.'], 403);
        }

        $invitation = CompanyInvitation::where('company_id', $currentCompanyId)
            ->where('id', $id)
            ->firstOrFail();

        $invitation->update(['status' => 'revoked']);

        AuditEvent::log('invitation.revoked', 'company_invitation', $invitation->id);

        return response()->json(['message' => 'Invitation revoked successfully.']);
    }
}
