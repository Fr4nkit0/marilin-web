<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Stored morph types are aliases, so moving a model to its module never orphans rows (ADR 0003).
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);

        // A module model keeps its factory next to it: App\<Module>\[<Subtopic>\]Models\X -> ...\Database\Factories\XFactory.
        Factory::guessFactoryNamesUsing(fn (string $model): string => str_starts_with($model, 'App\\Models\\')
            ? 'Database\\Factories\\'.class_basename($model).'Factory'
            : Str::replaceLast('\\Models\\', '\\Database\\Factories\\', $model).'Factory');

        // What the apps upload lands here for the admin to look at; drivers must not clear their own records.
        Gate::define('review-inbox', fn (User $user): bool => $user->is_super_user);

        // List prices reach every driver's app on the next pull.
        Gate::define('manage-products', fn (User $user): bool => $user->is_super_user);

        // An agreement overrides the list price for one customer, so it is the admin's call too.
        Gate::define('manage-agreements', fn (User $user): bool => $user->is_super_user);

        // The truck is loaded at the warehouse, so only the admin records loads.
        Gate::define('load-trucks', fn (User $user): bool => $user->is_super_user);

        // Driver accounts are created and switched on or off by the admin only.
        Gate::define('manage-users', fn (User $user): bool => $user->is_super_user);

        Vite::prefetch(concurrency: 3);
    }
}
