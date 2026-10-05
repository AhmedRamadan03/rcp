<?php

namespace App\Policies;

use App\Models\ThirdParty;
use App\Models\User;

class ThirdPartyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->third_parties)->show_third_parties == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ThirdParty $thirdParty): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->third_parties)->create_third_parties == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->third_parties)->update_third_parties == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->third_parties)->delete_third_parties == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ThirdParty $thirdParty): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ThirdParty $thirdParty): bool
    {
        //
    }

    public function activate(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->third_parties)->activate_third_parties == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
