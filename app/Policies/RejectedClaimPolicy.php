<?php

namespace App\Policies;

use App\Models\RejectedClaim;
use App\Models\User;

class RejectedClaimPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->reject_claims)->create_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RejectedClaim $rejectedClaim): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->reject_claims)->create_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->reject_claims)->create_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RejectedClaim $rejectedClaim): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RejectedClaim $rejectedClaim): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RejectedClaim $rejectedClaim): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RejectedClaim $rejectedClaim): bool
    {
        //
    }

    public function approve(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->reject_claims)->approve_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function reject(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->reject_claims)->reject_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
