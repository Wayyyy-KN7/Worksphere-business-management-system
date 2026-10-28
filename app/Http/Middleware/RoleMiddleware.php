<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $hasAllowedRole = collect($roles)->contains(fn (string $role) => Str::is($role, $user->role));

        if ($user->role === 'ADMIN' || $hasAllowedRole) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this page.');
    }
}