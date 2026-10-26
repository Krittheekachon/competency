<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::render('Auth/Login', [
            'pageTitle' => 'เข้าสู่ระบบ',
            'status' => session('status'),
            'ssoEnabled' => (bool) config('services.kku_sso.enabled'),
            'passwordLoginEnabled' => (bool) config('services.kku_sso.password_login_enabled'),
            'ssoError' => session('sso_error'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! config('services.kku_sso.password_login_enabled')) {
            if (Auth::guard('web')->check()) {
                return redirect()->intended(route('dashboard', absolute: false));
            }

            return redirect()->route('login')->with('sso_error', 'ระบบนี้อนุญาตให้เข้าสู่ระบบผ่าน KKU SSO เท่านั้น');
        }

        $request->authenticate();

        $request->session()->forget([
            'auth_via_kku_sso',
            'kku_sso_authenticated',
            'kku_sso_login_attempt',
        ]);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $authenticatedViaSso = (bool) $request->session()->get('auth_via_kku_sso', false)
            || (bool) $request->session()->get('kku_sso_authenticated', false);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($authenticatedViaSso && config('services.kku_sso.enabled')) {
            $appId = trim((string) config('services.kku_sso.app_id'));
            $logoutUrl = trim((string) config('services.kku_sso.logout_url'));
            $webUrl = rtrim((string) config('services.kku_sso.web_url'), '/');

            if ($appId !== '' && ($logoutUrl !== '' || $webUrl !== '')) {
                return redirect()->away(($logoutUrl !== '' ? $logoutUrl : $webUrl.'/logout').'?'.http_build_query(['app' => $appId]));
            }
        }

        return redirect('/');
    }
}
