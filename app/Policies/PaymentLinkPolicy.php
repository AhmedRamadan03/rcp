<?php

namespace App\Policies;

use App\Models\User;

class PaymentLinkPolicy
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
    public function view(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->payment_link)->create_payment_link == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        //
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->payment_link)->update_payment_link == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        //
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

    public function createInstallment(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->payment_link)->create_installment_payment_link == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function updateInstallment(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->payment_link)->update_installment_payment_link == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function activate(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->payment_link)->activate_payment_link == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
