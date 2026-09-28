<?php

namespace App\Providers;

use App\Support\Settings;
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
        // Restaurant name, address and receipt wording come from the database
        // when the owner has set them; otherwise the .env values stand.
        Settings::applyToConfig();
    }
}
