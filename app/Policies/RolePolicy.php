<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->role)->show_role == 1);
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
            return custom_policy($user, safe_json_decode($user->permission->first()->role)->show_role == 1);
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
            return custom_policy($user, safe_json_decode($user->permission->first()->role)->create_role == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->role)->update_role == 1);
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
            return custom_policy($user, safe_json_decode($user->permission->first()->role)->update_role == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        //
    }
}
