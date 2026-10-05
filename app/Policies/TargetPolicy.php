<?php

namespace App\Policies;

use App\Enums\Roles;
use App\Models\Target;
use App\Models\User;
use App\Services\UserService;

class TargetPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {

    }

    /**
     * Determine whether the user can view the model.
     */
    public function viewUserTargets(User $user, $target_user_id): bool
    {

        try {
            if (in_array($user->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value])) {
                return true;
            }
            if($user->role_id == Roles::SENIOR_COLLECTOR->value) {
                if((new UserService())->belongToSupervisor($target_user_id, $user->id)){
                    return true;
                }
            }

            if (safe_json_decode($user->permission->first()->reports)->show_reports == 0 && $user->id != $target_user_id) {
                return false;
            }

            return true;

        } catch (\Exception $exception) {
            return false;
        }
    }


    public function viewUserTargetData(User $user, $target_id): bool
    {
        try {
            $target = Target::withTrashed()->findOrFail($target_id);
            if (in_array($user->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value])) {
                return true;
            }

            if($user->role_id == Roles::SENIOR_COLLECTOR->value) {

                if((new UserService())->belongToSupervisor($target->user_id, $user->id)){
                    return true;
                }
            }

            if (safe_json_decode($user->permission->first()->reports)->show_reports == 0 && $user->id != $target->user_id) {
                return false;
            }

            return true;

        } catch (\Exception $exception) {
            return false;
        }
    }



    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        //
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
