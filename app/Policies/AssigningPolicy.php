<?php

namespace App\Policies;

use App\Models\Assigning;
use App\Models\User;

class AssigningPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //

    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assigning $assigning): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
        return $user->role_id == 3 || $user->role_id == 5;

    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assigning $assigning): bool
    {
        //
        return $user->role_id == 3 || $user->role_id == 5;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assigning $assigning): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assigning $assigning): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assigning $assigning): bool
    {
        //
    }
}
