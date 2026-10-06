<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the Vue-powered Admin Dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Check view::dashboard permission (root user automatically passes)
        if (! $user->isRoot() && ! $user->hasPermissionTo('view::dashboard')) {
            abort(403, 'Unauthorized access: You do not have permission to view the admin dashboard.');
        }

        // Financial & Orders Summary
        $completedStatuses = ['COMPLETED', 'PAID'];

        $totalRevenue = (float) Order::whereIn('status', $completedStatuses)->sum('amount');
        $todayRevenue = (float) Order::whereIn('status', $completedStatuses)
            ->whereDate('created_at', today())
            ->sum('amount');
        $monthRevenue = (float) Order::whereIn('status', $completedStatuses)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $totalOrders = Order::count();
        $completedOrders = Order::whereIn('status', $completedStatuses)->count();
        $pendingOrders = Order::where('status', 'PENDING')->count();
        $failedOrders = Order::where('status', 'FAILED')->count();
        $successRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

        // Catalog Statistics
        $totalGames = Game::count();
        $activeGames = Game::where('is_active', true)->count();
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();

        // System Users Breakdown
        $totalUsers = User::count();
        $usersByType = [
            'root' => User::where('type', 'root')->count(),
            'super_senior' => User::whereIn('type', ['super_senior', 'super senior'])->count(),
            'senior' => User::where('type', 'senior')->count(),
            'junior' => User::where('type', 'junior')->count(),
        ];

        // Past 7 Days Revenue & Orders Chart Data
        $weeklyTrends = collect(range(6, 0))->map(function ($daysAgo) use ($completedStatuses) {
            $targetDate = now()->subDays($daysAgo);
            $dateString = $targetDate->toDateString();
            $dayLabel = $targetDate->format('M d');

            $revenue = (float) Order::whereIn('status', $completedStatuses)
                ->whereDate('created_at', $dateString)
                ->sum('amount');

            $orders = Order::whereDate('created_at', $dateString)->count();

            return [
                'day' => $dayLabel,
                'date' => $dateString,
                'revenue' => $revenue,
                'orders' => $orders,
            ];
        });

        // Recent Orders
        $recentOrders = Order::with(['product.game'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'game_user_id' => $order->game_user_id,
                    'zone_id' => $order->zone_id,
                    'amount' => (float) $order->amount,
                    'payment_method' => $order->payment_method ?? 'KHQR',
                    'status' => strtoupper($order->status ?? 'PENDING'),
                    'game_name' => $order->product?->game?->name ?? 'Direct Game',
                    'game_image' => $order->product?->game?->image,
                    'product_name' => $order->product?->name ?? 'Custom Diamond',
                    'created_at_human' => $order->created_at?->diffForHumans() ?? 'Recently',
                    'created_at_formatted' => $order->created_at?->format('M d, Y H:i') ?? '-',
                ];
            });

        // Top Games with Order and Revenue Volume
        $topGames = Game::withCount('products')
            ->with(['products' => function ($q) {
                $q->select('id', 'game_id', 'name', 'selling_price');
            }])
            ->take(6)
            ->get()
            ->map(function ($game) use ($completedStatuses) {
                $productIds = $game->products->pluck('id');
                $ordersCount = Order::whereIn('product_id', $productIds)->count();
                $revenue = (float) Order::whereIn('product_id', $productIds)
                    ->whereIn('status', $completedStatuses)
                    ->sum('amount');

                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'image' => $game->image,
                    'is_active' => (bool) $game->is_active,
                    'products_count' => $game->products_count,
                    'orders_count' => $ordersCount,
                    'revenue' => $revenue,
                ];
            });

        // Top 5 Products by Revenue
        $topProducts = Product::with('game')
            ->where('is_active', true)
            ->take(5)
            ->get()
            ->map(function ($product) use ($completedStatuses) {
                $ordersCount = Order::where('product_id', $product->id)->count();
                $revenue = (float) Order::where('product_id', $product->id)
                    ->whereIn('status', $completedStatuses)
                    ->sum('amount');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->selling_price,
                    'game_name' => $product->game?->name ?? 'Game',
                    'orders_count' => $ordersCount,
                    'revenue' => $revenue,
                ];
            });

        // User Permissions Map
        $permissions = [
            'can_manage_users' => $user->isRoot() || $user->hasPermissionTo('viewonly::user'),
            'can_create_user' => $user->isRoot() || $user->hasPermissionTo('create::user'),
            'can_edit_user' => $user->isRoot() || $user->hasPermissionTo('edit::user'),
            'can_delete_user' => $user->isRoot() || $user->hasPermissionTo('delete::user'),

            'can_manage_games' => $user->isRoot() || $user->hasPermissionTo('viewonly::game'),
            'can_create_game' => $user->isRoot() || $user->hasPermissionTo('create::game'),
            'can_edit_game' => $user->isRoot() || $user->hasPermissionTo('edit::game'),
            'can_delete_game' => $user->isRoot() || $user->hasPermissionTo('delete::game'),

            'can_manage_products' => $user->isRoot() || $user->hasPermissionTo('viewonly::product'),
            'can_create_product' => $user->isRoot() || $user->hasPermissionTo('create::product'),
            'can_edit_product' => $user->isRoot() || $user->hasPermissionTo('edit::product'),
            'can_delete_product' => $user->isRoot() || $user->hasPermissionTo('delete::product'),

            'can_manage_orders' => $user->isRoot() || $user->hasPermissionTo('viewonly::order'),
            'can_create_order' => $user->isRoot() || $user->hasPermissionTo('create::order'),
            'can_edit_order' => $user->isRoot() || $user->hasPermissionTo('edit::order'),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_revenue' => $totalRevenue,
                'today_revenue' => $todayRevenue,
                'month_revenue' => $monthRevenue,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'failed_orders' => $failedOrders,
                'success_rate' => $successRate,
                'total_games' => $totalGames,
                'active_games' => $activeGames,
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'total_users' => $totalUsers,
                'users_by_type' => $usersByType,
            ],
            'weekly_trends' => $weeklyTrends,
            'recent_orders' => $recentOrders,
            'top_games' => $topGames,
            'top_products' => $topProducts,
            'permissions' => $permissions,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'login_name' => $user->login_name,
                'type' => $user->type,
                'is_root' => $user->isRoot(),
                'status' => (bool) $user->status,
                'created_at' => $user->created_at?->format('M Y'),
            ],
        ]);
    }
}
