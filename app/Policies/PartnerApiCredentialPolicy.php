<?php

namespace App\Policies;

use App\Models\PartnerApiCredential;
use App\Models\User;

class PartnerApiCredentialPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function showApiCredential(User $user): bool
    {
        //
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->settings)->show_api_credential == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
    
    public function manageApiCredential(User $user): bool
    {
        //
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->settings)->manage_api_credential == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

   
}