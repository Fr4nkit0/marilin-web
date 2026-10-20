<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        // What the apps upload lands here for the admin to look at; drivers must not clear their own records.
        Gate::define('review-inbox', fn (User $user): bool => $user->is_super_user);

        // List prices reach every driver's app on the next pull.
        Gate::define('manage-products', fn (User $user): bool => $user->is_super_user);

        // The truck is loaded at the warehouse, so only the admin records loads.
        Gate::define('load-trucks', fn (User $user): bool => $user->is_super_user);

        Vite::prefetch(concurrency: 3);
    }
}
