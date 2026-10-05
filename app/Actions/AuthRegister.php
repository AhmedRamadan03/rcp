<?php

namespace App\Actions;

use App\Models\AuthenticationLog;
use App\Models\User;

class AuthRegister
{
    public function successfullyLogin($user, $personal_access_token_id = null)
    {
        AuthenticationLog::create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'personal_access_token_id' => $personal_access_token_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'login_at' => now(),
            'login_successful' => true,
            'logout_at' => null,
        ]);

         $user->timestamps = false;
        $user->forceFill(['last_active_at' => now()])->save();
    }

    public function failedLogin($user)
    {
        AuthenticationLog::create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'login_at' => now(),
            'login_successful' => false,
            'logout_at' => null,
        ]);
    }

    public function logout($user, $personal_access_token_id = null)
    {
        $query = AuthenticationLog::where('authenticatable_id', $user->id)
            ->where('authenticatable_type', User::class)
            ->where('logout_at', null);

        $userSession = null;
        if($personal_access_token_id){
            $userSession = (clone $query)
                ->where('personal_access_token_id', $personal_access_token_id)
                ->first();
        }

        // fallback to the last open session
        if (! $userSession) {
            $userSession = $query->latest()->first();
        }

        if (! $userSession) {
            return;
        }

        $userSession->logout_at = now();
        $userSession->save();
    }

    public function logoutAllSessions($user)
    {
        AuthenticationLog::where('authenticatable_id', $user->id)
            ->where('authenticatable_type', User::class)
            ->where('logout_at', null)
            ->update(['logout_at' => now()]);
    }

}
