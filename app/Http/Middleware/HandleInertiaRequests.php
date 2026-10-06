<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'login_name' => $user->login_name,
                    'type' => $user->type,
                    'status' => (bool) $user->status,
                    'is_root' => $user->isRoot(),
                ] : null,
                'can' => $user ? [
                    'view_dashboard' => $user->isRoot() || $user->hasPermissionTo('view::dashboard'),
                    'manage_users' => $user->isRoot() || $user->hasPermissionTo('viewonly::user'),
                    'manage_games' => $user->isRoot() || $user->hasPermissionTo('viewonly::game'),
                    'manage_products' => $user->isRoot() || $user->hasPermissionTo('viewonly::product'),
                    'manage_orders' => $user->isRoot() || $user->hasPermissionTo('viewonly::order'),
                    'create_game' => $user->isRoot() || $user->hasPermissionTo('create::game'),
                    'edit_game' => $user->isRoot() || $user->hasPermissionTo('edit::game'),
                    'delete_game' => $user->isRoot() || $user->hasPermissionTo('delete::game'),
                    'create_product' => $user->isRoot() || $user->hasPermissionTo('create::product'),
                    'edit_product' => $user->isRoot() || $user->hasPermissionTo('edit::product'),
                    'delete_product' => $user->isRoot() || $user->hasPermissionTo('delete::product'),
                    'create_user' => $user->isRoot() || $user->hasPermissionTo('create::user'),
                    'edit_user' => $user->isRoot() || $user->hasPermissionTo('edit::user'),
                    'delete_user' => $user->isRoot() || $user->hasPermissionTo('delete::user'),
                    'edit_order' => $user->isRoot() || $user->hasPermissionTo('edit::order'),
                    'delete_order' => $user->isRoot() || $user->hasPermissionTo('delete::order'),
                ] : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
