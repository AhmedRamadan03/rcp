<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->permissions)->show_permissions == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Permission $permission): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->permissions)->show_permissions == 1);
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
            return true;//custom_policy($user, json_decode($user->permission->first()->permissions)->create_permissions == 1);
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
            return custom_policy($user, safe_json_decode($user->permission->first()->permissions)->update_permissions == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function showAccount(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->accounting)->show_accounting == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Permission $permission): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Permission $permission): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Permission $permission): bool
    {
        //
    }
}
