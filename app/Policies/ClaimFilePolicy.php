<?php

namespace App\Policies;

use App\Models\User;

class ClaimFilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        //
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->show_claims_files == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->show_claims_files == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->create_claims_files == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->delete_claims_files == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user): bool
    {
        //
    }
}
