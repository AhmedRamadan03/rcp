<?php

namespace App\Policies;

use App\Models\User;


class DashboardPolicy
{
    public function show_all_executive(User $user)
    {
        try {
            $dashboard = safe_json_decode($user->permission->first()->dashboard);
            return custom_policy($user, ($dashboard->show_all_executive ?? 0) == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
