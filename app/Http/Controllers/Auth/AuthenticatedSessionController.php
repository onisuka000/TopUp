<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && $user->isAdminStaff()) {
                return redirect()->route('admin.dashboard');
            }

            // If logged in as member, log out so staff can log in without friction
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Enforce admin staff only: members cannot log in to admin site
        if ($user && $user->type === 'member') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'login_name' => 'Access denied: Member accounts cannot access the admin management portal.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $wasMember = $request->user()?->type === 'member';

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $referer = $request->header('referer');
        if ($wasMember || ($referer && ! str_contains($referer, '/admin'))) {
            return redirect('/');
        }

        return redirect('/admin/login');
    }
}
