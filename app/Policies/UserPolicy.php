<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->show_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->show_users == 1);
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
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->create_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $model): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->update_users == 1) || $model == $user->id;
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
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->delete_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->users)->activate_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        //
    }

    public function activate(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->users)->activate_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function deActivate(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->users)->deactivate_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function autoAssign(User $user, $model)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->users)->auto_assign_users == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
