<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MemberAuthController extends Controller
{
    /**
     * Handle incoming member login request on storefront (http://127.0.0.1:8000/).
     * Only users of type 'member' are permitted to log in.
     * Different user types (root, super_senior, senior, junior) are rejected.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login_name' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        $identifier = trim($request->input('login_name'));
        $password = (string) $request->input('password');

        $user = User::where('login_name', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        // 1. Check credentials
        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login_name' => trans('auth.failed'),
            ]);
        }

        // 2. Check if user is banned
        if (! (bool) $user->status) {
            throw ValidationException::withMessages([
                'login_name' => 'User is ban',
            ]);
        }

        // 3. Check user type: ONLY type 'member' is allowed to log into the storefront!
        if ($user->type !== 'member') {
            throw ValidationException::withMessages([
                'login_name' => 'Access denied: Non-member accounts cannot log in to the member site. Please use the Admin Portal for management accounts.',
            ]);
        }

        Auth::login($user, $request->boolean('remember', true));
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', "Welcome back, {$user->username}!");
    }
}
