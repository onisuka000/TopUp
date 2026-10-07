<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Game;
use App\Models\Product;
use App\Services\TokovoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected TokovoucherService $tokovoucherService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('viewonly::product')) {
            abort(403, 'Unauthorized: You do not have permission to view products.');
        }

        $query = Product::query()->with('game')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('provider_code', 'like', "%{$search}%")
                    ->orWhereHas('game', function ($g) use ($search) {
                        $g->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($gameId = $request->input('game_id')) {
            $query->where('game_id', $gameId);
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->paginate(15)->withQueryString();
        $games = Game::select('id', 'name')->orderBy('name')->get();

        $idrRate = Currency::getRate('IDR', 16000);
        $khrRate = Currency::getRate('KHR', 4000);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'games'    => $games,
            'idrRate'  => $idrRate,
            'khrRate'  => $khrRate,
            'filters'  => [
                'search'  => $request->input('search', ''),
                'game_id' => $request->input('game_id', ''),
                'status'  => $request->input('status', 'all'),
            ],
            'stats' => [
                'total'    => Product::count(),
                'active'   => Product::where('is_active', true)->count(),
                'inactive' => Product::where('is_active', false)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('create::product')) {
            abort(403, 'Unauthorized: You do not have permission to create products.');
        }

        $validated = $request->validate([
            'game_id'           => ['required', 'exists:games,id'],
            'name'              => ['required', 'string', 'max:255'],
            'provider_code'     => ['required', 'string', 'max:255'],
            'tokovoucher_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price'        => ['required', 'numeric', 'min:0'],
            'selling_price'     => ['required', 'numeric', 'min:0'],
            'is_active'         => ['boolean'],
        ]);

        $validated['provider_code'] = strtoupper(trim($validated['provider_code']));
        Product::create($validated);

        return back()->with('success', 'Product package created successfully!');
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::product')) {
            abort(403, 'Unauthorized: You do not have permission to edit products.');
        }

        $validated = $request->validate([
            'game_id'           => ['required', 'exists:games,id'],
            'name'              => ['required', 'string', 'max:255'],
            'provider_code'     => ['required', 'string', 'max:255'],
            'tokovoucher_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price'        => ['required', 'numeric', 'min:0'],
            'selling_price'     => ['required', 'numeric', 'min:0'],
            'is_active'         => ['boolean'],
        ]);

        $validated['provider_code'] = strtoupper(trim($validated['provider_code']));
        $product->update($validated);

        return back()->with('success', 'Product package updated successfully!');
    }

    /**
     * Sync cost prices live from Tokovoucher for all products.
     */
    public function syncTokovoucher(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::product')) {
            abort(403, 'Unauthorized: You do not have permission to sync products.');
        }

        $idrRate = Currency::getRate('IDR', 16000);
        $result  = $this->tokovoucherService->syncPrices('ML', $idrRate);

        if (!empty($result['success'])) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message'] ?? 'Failed to sync with Tokovoucher.');
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::product')) {
            abort(403, 'Unauthorized: You do not have permission to edit products.');
        }

        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        $statusLabel = $product->is_active ? 'Active' : 'Inactive';
        return back()->with('success', "Product status changed to {$statusLabel}.");
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('delete::product')) {
            abort(403, 'Unauthorized: You do not have permission to delete products.');
        }

        $product->delete();

        return back()->with('success', 'Product deleted successfully.');
    }
}
