<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminStaff
{
    /**
     * Handle an incoming request.
     * Ensure only management staff can access admin routes.
     * Member accounts are strictly denied.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Root administrator always has access
        if ($user->isRoot()) {
            return $next($request);
        }

        if ($user->type === 'member' || ! $user->isAdminStaff()) {
            abort(403, 'Access denied: Member accounts cannot access the management site.');
        }

        return $next($request);
    }
}
