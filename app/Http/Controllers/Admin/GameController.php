<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Display a listing of games.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('viewonly::game')) {
            abort(403, 'Unauthorized: You do not have permission to view games.');
        }

        $query = Game::query()->withCount('products')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $games = $query->paginate(12)->withQueryString();

        return Inertia::render('Admin/Games/Index', [
            'games' => $games,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
            'stats' => [
                'total' => Game::count(),
                'active' => Game::where('is_active', true)->count(),
                'inactive' => Game::where('is_active', false)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created game.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('create::game')) {
            abort(403, 'Unauthorized: You do not have permission to create games.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:games,slug'],
            'image' => ['nullable', 'string', 'max:2048'],
            'has_zone_id' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $count = 1;
            while (Game::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = "{$originalSlug}-{$count}";
                $count++;
            }
        }

        Game::create($validated);

        return back()->with('success', 'Game added successfully!');
    }

    /**
     * Update the specified game.
     */
    public function update(Request $request, Game $game): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::game')) {
            abort(403, 'Unauthorized: You do not have permission to edit games.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:games,slug,' . $game->id],
            'image' => ['nullable', 'string', 'max:2048'],
            'has_zone_id' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $game->update($validated);

        return back()->with('success', 'Game updated successfully!');
    }

    /**
     * Toggle game active status.
     */
    public function toggleStatus(Request $request, Game $game): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('edit::game')) {
            abort(403, 'Unauthorized: You do not have permission to edit games.');
        }

        $game->update([
            'is_active' => ! $game->is_active,
        ]);

        $statusLabel = $game->is_active ? 'Active' : 'Inactive';
        return back()->with('success', "Game status updated to {$statusLabel}.");
    }

    /**
     * Remove the specified game.
     */
    public function destroy(Request $request, Game $game): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRoot() && ! $user->hasPermissionTo('delete::game')) {
            abort(403, 'Unauthorized: You do not have permission to delete games.');
        }

        $game->products()->delete();
        $game->delete();

        return back()->with('success', 'Game and its packages deleted successfully.');
    }
}
