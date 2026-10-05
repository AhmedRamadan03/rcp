<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentTransactionPolicy
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
    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        //
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
    public function update(User $user, PaymentTransaction $paymentTransaction): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentTransaction $paymentTransaction): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentTransaction $paymentTransaction): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentTransaction $paymentTransaction): bool
    {
        //
    }

    public function viewAll(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->transactions)->show_all_transactions == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

     public function export(User $user): bool
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->transactions)->show_all_transactions == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

}
