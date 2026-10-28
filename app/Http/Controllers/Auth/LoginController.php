<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\AuditLog;

class LoginController extends Controller
{
    // Show login form
    public function showLoginForm()
    {
        
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'companySettings' => CompanySettings::current(),
        ]);
    }

    // Handle login
    public function login(Request $request)
    {
        // Validate
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Check if user exists and is active
        $credentials = $request->only('email', 'password');
        $settings = CompanySettings::current();
        $rateLimitKey = 'login:' . hash('sha256', Str::lower($credentials['email']) . '|' . ($request->ip() ?? ''));
        $maxAttempts = max(1, (int) $settings->max_failed_login_attempts);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'email' => "Too many failed login attempts. Try again in {$retryAfter} seconds.",
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($rateLimitKey);
            $user = Auth::user();

            // Check if user is active
            if ($user->status !== 'ACTIVE') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Contact admin.',
                ]);
            }

            // Regenerate session
            $request->session()->regenerate();

            // Update last login
            $user->update(['last_login' => now()]);

            // Log the login
            AuditLog::log('LOGGED_IN', 'users', $user->id, "User {$user->name} logged in");

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($rateLimitKey, max(1, (int) $settings->account_lockout_minutes) * 60);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // Handle logout
    public function logout(Request $request)
    {
        $userId = Auth::id();

        if ($userId) {
            AuditLog::log('LOGGED_OUT', 'users', $userId, 'User logged out');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}