<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('viewonly::order')) {
            abort(403, 'Unauthorized: You do not have permission to view orders.');
        }

        $query = Order::query()->with(['product.game'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('game_user_id', 'like', "%{$search}%")
                    ->orWhere('zone_id', 'like', "%{$search}%")
                    ->orWhere('provider_ref_id', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', strtoupper($status));
            }
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
            'stats' => [
                'total' => Order::count(),
                'completed' => Order::whereIn('status', ['COMPLETED', 'PAID'])->count(),
                'pending' => Order::where('status', 'PENDING')->count(),
                'failed' => Order::where('status', 'FAILED')->count(),
            ],
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::order')) {
            abort(403, 'Unauthorized: You do not have permission to edit orders.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:PENDING,COMPLETED,FAILED,PAID'],
            'error_message' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update($validated);

        return back()->with('success', "Order #{$order->order_number} status updated to {$order->status}.");
    }

    /**
     * Remove the specified order.
     */
    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('delete::order')) {
            abort(403, 'Unauthorized: You do not have permission to delete orders.');
        }

        $order->delete();

        return back()->with('success', 'Order record deleted.');
    }
}
