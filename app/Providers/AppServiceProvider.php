<?php

namespace App\Providers;

use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\GamePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
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
        $this->app->bind(
            \App\Contracts\OrderServiceContract::class,
            \App\Services\OrderService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Force HTTPS URLs in production
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Root users can access all (don't check permission)
        Gate::before(function (User $user, string $ability) {
            if ($user->isRoot()) {
                return true;
            }
        });

        // Explicitly register Model Policies
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Game::class, GamePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        // 1. Dashboard permission
        Gate::define('view::dashboard', fn (User $user) => $user->hasPermissionTo('view::dashboard'));

        // 2. User permissions
        Gate::define('viewonly::user', fn (User $user) => $user->hasPermissionTo('viewonly::user'));
        Gate::define('create::user', fn (User $user) => $user->hasPermissionTo('create::user'));
        Gate::define('edit::user', fn (User $user) => $user->hasPermissionTo('edit::user') || $user->hasPermissionTo('Edit::user'));
        Gate::define('delete::user', fn (User $user) => $user->hasPermissionTo('delete::user') || $user->hasPermissionTo('Delete::user'));

        // 3. Game permissions
        Gate::define('viewonly::game', fn (User $user) => $user->hasPermissionTo('viewonly::game'));
        Gate::define('create::game', fn (User $user) => $user->hasPermissionTo('create::game'));
        Gate::define('edit::game', fn (User $user) => $user->hasPermissionTo('edit::game'));
        Gate::define('delete::game', fn (User $user) => $user->hasPermissionTo('delete::game'));

        // 4. Product permissions
        Gate::define('viewonly::product', fn (User $user) => $user->hasPermissionTo('viewonly::product'));
        Gate::define('create::product', fn (User $user) => $user->hasPermissionTo('create::product'));
        Gate::define('edit::product', fn (User $user) => $user->hasPermissionTo('edit::product'));
        Gate::define('delete::product', fn (User $user) => $user->hasPermissionTo('delete::product'));

        // 5. Order permissions
        Gate::define('viewonly::order', fn (User $user) => $user->hasPermissionTo('viewonly::order'));
        Gate::define('create::order', fn (User $user) => $user->hasPermissionTo('create::order'));
        Gate::define('edit::order', fn (User $user) => $user->hasPermissionTo('edit::order'));
        Gate::define('delete::order', fn (User $user) => $user->hasPermissionTo('delete::order'));
    }
}
