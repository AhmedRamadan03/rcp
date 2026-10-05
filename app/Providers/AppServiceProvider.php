<?php

namespace App\Providers;

use App\Database\CustomPostgresConnection;
use App\View\Components\BaseMail;
use Blade;
use Illuminate\Database\Connection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use custom connection that properly handles boolean bindings for PostgreSQL
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            $conn = new CustomPostgresConnection($connection, $database, $prefix, $config);
            $conn->setEventDispatcher($this->app['events']);
            return $conn;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        //
        JsonResource::withoutWrapping();

        Paginator::defaultView('pagination::default');

        Blade::component('mail.base', BaseMail::class);
        \App\Models\Claim::observe(\App\Observers\ClaimRiskObserver::class); // Now uses background job


    }
}