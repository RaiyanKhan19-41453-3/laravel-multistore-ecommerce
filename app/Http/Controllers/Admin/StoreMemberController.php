<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Support\AdminStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreMemberController extends Controller
{
    public function __construct(
        protected AdminStoreContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store = $this->context->selected($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Select a store first.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $store->users()->orderBy('name')->get()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->context->selected($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Select a store first.',
            ], 422);
        }

        if (! $this->context->isOwner($request->user(), $store)) {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can manage members.',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255|exists:users,email',
            'role' => ['required', 'string', Rule::in(['owner', 'staff'])],
        ]);

        $member = User::where('email', $validated['email'])->firstOrFail();

        if ($store->users()->whereKey($member->id)->exists()) {
            $store->users()->updateExistingPivot($member->id, ['role' => $validated['role']]);
        } else {
            $store->users()->attach($member->id, ['role' => $validated['role']]);
        }

        $this->ensureBaselineRole($member, $validated['role']);

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully.',
        ]);
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $store = $this->context->selected($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Select a store first.',
            ], 422);
        }

        if (! $this->context->isOwner($request->user(), $store)) {
            return response()->json([
                'success' => false,
                'message' => 'Only store owners can manage members.',
            ], 403);
        }

        $member = User::findOrFail($user);

        if (! $store->users()->whereKey($member->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this store.',
            ], 404);
        }

        $isOwner = $store->users()->whereKey($member->id)->first()?->pivot->role === 'owner';

        if ($isOwner && $store->users()->wherePivot('role', 'owner')->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'A store must keep at least one owner.',
            ], 422);
        }

        $store->users()->detach($member->id);

        $this->stripOrphanedMerchantRole($member);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully.',
        ]);
    }

    /**
     * Give a new member the minimum Spatie role for their membership so
     * they can actually open the admin. Additive only: members who
     * already hold staff roles keep them untouched.
     */
    private function ensureBaselineRole(User $member, string $membershipRole): void
    {
        try {
            if ($member->hasRole('super-admin')) {
                return;
            }

            if ($membershipRole === 'owner') {
                if (Role::where('name', 'store-owner')->exists()) {
                    $member->assignRole('store-owner');
                }

                return;
            }

            if ($member->roles()->count() === 0 && Role::where('name', 'support')->exists()) {
                $member->assignRole('support');
            }
        } catch (\Throwable) {
            // Membership must never fail because of role seeding state.
        }
    }

    /**
     * A removed member with no stores left must not keep merchant access:
     * without memberships they would fall back to the platform-wide view.
     * store-owner is always membership-bound, so it goes. The auto-granted
     * support baseline goes too, but only when nothing else remains —
     * holders of real staff roles (catalog-manager, …) keep them.
     */
    private function stripOrphanedMerchantRole(User $member): void
    {
        try {
            if ($member->hasRole('super-admin')) {
                return;
            }

            $remaining = $member->belongsToMany(Store::class, 'store_user')->count();

            if ($remaining > 0) {
                return;
            }

            if ($member->hasRole('store-owner')) {
                $member->removeRole('store-owner');
            }

            $leftover = $member->roles()->pluck('name')->all();

            if ($leftover !== [] && array_diff($leftover, ['support']) === []) {
                $member->removeRole('support');
            }
        } catch (\Throwable) {
            // Membership removal already succeeded; never fail on cleanup.
        }
    }
}
