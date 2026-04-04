<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les directives @role / @endrole / @can sont fournies nativement
        // par spatie/laravel-permission et Laravel — aucune redéfinition nécessaire.
    }
}
