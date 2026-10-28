<?php

namespace App\Http\Middleware;

use App\Models\CompanySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    private const LAST_ACTIVITY_KEY = 'security_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }

        $timeoutSeconds = max(5, (int) CompanySettings::current()->session_timeout_minutes) * 60;
        $now = now()->timestamp;
        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) >= $timeoutSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your session expired due to inactivity. Please sign in again.',
            ]);
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }
}