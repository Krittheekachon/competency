<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SsoProfileSyncService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KkuSsoController extends Controller
{
    private const ATTEMPT_TTL_SECONDS = 900;

    public function __construct(private SsoProfileSyncService $profileSync)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! config('services.kku_sso.enabled')) {
            return redirect()->route('login');
        }

        $appId = trim((string) config('services.kku_sso.app_id'));
        $loginUrl = trim((string) config('services.kku_sso.login_url'));

        if ($appId === '' || $loginUrl === '') {
            return $this->loginFailure('ยังไม่ได้ตั้งค่า KKU SSO กรุณาติดต่อผู้ดูแลระบบ');
        }

        $request->session()->put('kku_sso_login_attempt', now()->timestamp);

        return redirect()->away($loginUrl.'?'.http_build_query(['app' => $appId]));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! config('services.kku_sso.enabled')) {
            return redirect()->route('login');
        }

        $attemptedAt = (int) $request->session()->pull('kku_sso_login_attempt', 0);
        if ($attemptedAt === 0 || now()->timestamp - $attemptedAt > self::ATTEMPT_TTL_SECONDS) {
            return $this->loginFailure('ไม่พบคำขอเข้าสู่ระบบ SSO กรุณาลองใหม่อีกครั้ง');
        }

        $code = trim((string) $request->query('code'));

        if ($code === '' || Str::length($code) > 255) {
            return $this->loginFailure('KKU SSO ไม่ได้ส่งรหัสเข้าสู่ระบบที่ถูกต้อง');
        }

        if (! $this->hasRequiredConfiguration()) {
            return $this->loginFailure('ยังไม่ได้ตั้งค่า KKU SSO กรุณาติดต่อผู้ดูแลระบบ');
        }

        try {
            $tokenResponse = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.kku_sso.timeout', 10))
                ->post((string) config('services.kku_sso.token_url'), [
                    'code' => $code,
                    'redirectUrl' => config('services.kku_sso.redirect_url'),
                    'clientId' => config('services.kku_sso.client_id'),
                    'clientSecret' => config('services.kku_sso.client_secret'),
                ]);

            $token = $tokenResponse->json();

            if (! $tokenResponse->successful() || ! is_array($token) || ($token['ok'] ?? false) !== true) {
                return $this->loginFailure('ไม่สามารถยืนยันตัวตนกับ KKU SSO ได้ กรุณาลองใหม่อีกครั้ง');
            }

            $accessToken = trim((string) ($token['accessToken'] ?? ''));

            if ($accessToken === '') {
                return $this->loginFailure('KKU SSO ไม่ได้ส่ง Access Token กลับมา');
            }

            $profileResponse = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout((int) config('services.kku_sso.timeout', 10))
                ->post($this->apiUrl('/user.profile'));

            $profilePayload = $profileResponse->json();
            $profile = is_array($profilePayload) ? ($profilePayload['profile'] ?? null) : null;

            if (! $profileResponse->successful()
                || ! is_array($profilePayload)
                || ($profilePayload['ok'] ?? false) !== true
                || ! is_array($profile)) {
                return $this->loginFailure('ไม่สามารถอ่านข้อมูลผู้ใช้จาก KKU SSO ได้ กรุณาลองใหม่อีกครั้ง');
            }
        } catch (ConnectionException) {
            return $this->loginFailure('ไม่สามารถเชื่อมต่อ KKU SSO ได้ กรุณาลองใหม่ภายหลัง');
        }

        try {
            $user = $this->profileSync->sync($token, $profile);
        } catch (ValidationException $exception) {
            return $this->loginFailure(
                collect($exception->errors())->flatten()->first()
                    ?: 'ไม่สามารถบันทึกข้อมูลจาก KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ'
            );
        } catch (UniqueConstraintViolationException) {
            return $this->loginFailure('ข้อมูลบัญชี KKU SSO ขัดแย้งกับบัญชีในระบบ กรุณาติดต่อผู้ดูแลระบบ');
        }

        if (! $user) {
            return $this->loginFailure('ไม่พบบัญชีผู้ใช้นี้ในระบบ EN-IDP กรุณาติดต่อผู้ดูแลระบบ');
        }

        if (! $user->is_active) {
            return $this->loginFailure('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_via_kku_sso', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function logoutCallback(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function hasRequiredConfiguration(): bool
    {
        return collect(['client_id', 'client_secret', 'redirect_url', 'api_url', 'token_url'])
            ->every(fn (string $key): bool => trim((string) config("services.kku_sso.$key")) !== '');
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.kku_sso.api_url'), '/').$path;
    }

    private function loginFailure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('sso_error', $message);
    }
}
