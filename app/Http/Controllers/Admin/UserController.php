<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of system users.
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        if (! $currentUser->isRoot() && ! $currentUser->hasPermissionTo('viewonly::user')) {
            abort(403, 'Unauthorized: You do not have permission to view users.');
        }

        $query = User::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('login_name', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('status', true);
            } elseif ($status === 'banned') {
                $query->where('status', false);
            }
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', 'all'),
                'status' => $request->input('status', 'all'),
            ],
            'stats' => [
                'total' => User::count(),
                'active' => User::where('status', true)->count(),
                'banned' => User::where('status', false)->count(),
                'root' => User::where('type', 'root')->count(),
                'super_senior' => User::whereIn('type', ['super_senior', 'super senior'])->count(),
                'senior' => User::where('type', 'senior')->count(),
                'junior' => User::where('type', 'junior')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isRoot() && ! $currentUser->hasPermissionTo('create::user')) {
            abort(403, 'Unauthorized: You do not have permission to create users.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'login_name' => ['required', 'string', 'max:255', 'unique:users,login_name'],
            'password' => ['required', 'string', 'min:6'],
            'type' => ['required', 'string', 'in:root,super_senior,senior,junior'],
            'status' => ['boolean'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($plainPassword);
        $validated['encrypted_password'] = Crypt::encryptString($plainPassword);
        $validated['status'] = $validated['status'] ?? true;

        $user = User::create($validated);

        // Assign corresponding role if exists
        $role = Role::where('name', $validated['type'])->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }

        return back()->with('success', "User '{$user->username}' created successfully!");
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isRoot() && ! $currentUser->hasPermissionTo('edit::user')) {
            abort(403, 'Unauthorized: You do not have permission to edit users.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'login_name' => ['required', 'string', 'max:255', 'unique:users,login_name,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'type' => ['required', 'string', 'in:root,super_senior,senior,junior'],
            'status' => ['boolean'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['password'])) {
            $plain = $validated['password'];
            $validated['password'] = Hash::make($plain);
            $validated['encrypted_password'] = Crypt::encryptString($plain);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Update role if changed
        $role = Role::where('name', $validated['type'])->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }

        return back()->with('success', "User '{$user->username}' updated successfully!");
    }

    /**
     * Toggle active/banned status for a user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isRoot() && ! $currentUser->hasPermissionTo('edit::user')) {
            abort(403, 'Unauthorized: You do not have permission to edit users.');
        }

        if ($user->id === $currentUser->id) {
            return back()->with('error', 'You cannot ban or deactivate your own account.');
        }

        $newStatus = ! $user->status;
        $user->update(['status' => $newStatus]);

        $message = $newStatus
            ? "User '{$user->username}' has been activated."
            : "User '{$user->username}' has been banned from logging in.";

        return back()->with('success', $message);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isRoot() && ! $currentUser->hasPermissionTo('delete::user')) {
            abort(403, 'Unauthorized: You do not have permission to delete users.');
        }

        if ($user->id === $currentUser->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isRoot() && User::where('type', 'root')->count() <= 1) {
            return back()->with('error', 'Cannot delete the only root administrator account.');
        }

        $user->roles()->detach();
        $user->permissions()->detach();
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
