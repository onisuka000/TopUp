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
        $rawUser = $request->user();

        // Clearly distinguish between admin staff and storefront member:
        // 1. On /admin/*: active user is strictly management staff
        // 2. On storefront /: active user is STRICTLY a member. Admin users are NEVER treated as logged-in player members!
        if ($request->is('admin*')) {
            $user = ($rawUser && $rawUser->isAdminStaff()) ? $rawUser : null;
        } else {
            $user = ($rawUser && $rawUser->type === 'member') ? $rawUser : null;
        }

        $adminStaff = ($rawUser && $rawUser->isAdminStaff()) ? [
            'id' => $rawUser->id,
            'username' => $rawUser->username,
            'login_name' => $rawUser->login_name,
            'type' => $rawUser->type,
            'is_root' => $rawUser->isRoot(),
        ] : null;

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
                'admin_staff' => $adminStaff,
                'can' => ($rawUser && $rawUser->isAdminStaff()) ? [
                    'view_dashboard' => $rawUser->isRoot() || $rawUser->hasPermissionTo('view::dashboard'),
                    'manage_users' => $rawUser->isRoot() || $rawUser->hasPermissionTo('viewonly::user'),
                    'manage_games' => $rawUser->isRoot() || $rawUser->hasPermissionTo('viewonly::game'),
                    'manage_products' => $rawUser->isRoot() || $rawUser->hasPermissionTo('viewonly::product'),
                    'manage_orders' => $rawUser->isRoot() || $rawUser->hasPermissionTo('viewonly::order'),
                    'create_game' => $rawUser->isRoot() || $rawUser->hasPermissionTo('create::game'),
                    'edit_game' => $rawUser->isRoot() || $rawUser->hasPermissionTo('edit::game'),
                    'delete_game' => $rawUser->isRoot() || $rawUser->hasPermissionTo('delete::game'),
                    'create_product' => $rawUser->isRoot() || $rawUser->hasPermissionTo('create::product'),
                    'edit_product' => $rawUser->isRoot() || $rawUser->hasPermissionTo('edit::product'),
                    'delete_product' => $rawUser->isRoot() || $rawUser->hasPermissionTo('delete::product'),
                    'create_user' => $rawUser->isRoot() || $rawUser->hasPermissionTo('create::user'),
                    'edit_user' => $rawUser->isRoot() || $rawUser->hasPermissionTo('edit::user'),
                    'delete_user' => $rawUser->isRoot() || $rawUser->hasPermissionTo('delete::user'),
                    'edit_order' => $rawUser->isRoot() || $rawUser->hasPermissionTo('edit::order'),
                    'delete_order' => $rawUser->isRoot() || $rawUser->hasPermissionTo('delete::order'),
                ] : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
