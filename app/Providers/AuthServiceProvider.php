<?php

namespace App\Providers;


use App\Models\Claim;
use App\Models\Dashboard;
use App\Models\Delegation;
use App\Models\StatusChangeRequest;
use App\Policies\ClaimPolicy;
use App\Policies\DelegationPolicy;
use App\Policies\StatusChangeRequestPolicy;
use App\Policies\DashboardPolicy;  
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\PolymorphicSanctumGuard;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
            //
        Claim::class => ClaimPolicy::class,
        Dashboard::class => DashboardPolicy::class,
        Delegation::class => DelegationPolicy::class,
        StatusChangeRequest::class => StatusChangeRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
        $this->registerPolicies();
        Auth::resolved(function ($auth) {
        $auth->extend('polymorphic-sanctum', function ($app, $name, array $config) {
            return new PolymorphicSanctumGuard();
        });
    });
    }
}
