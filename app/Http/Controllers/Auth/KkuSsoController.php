<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KkuSsoController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! config('services.kku_sso.enabled')) {
            return redirect()->route('login');
        }

        $appId = trim((string) config('services.kku_sso.app_id'));
        $webUrl = rtrim((string) config('services.kku_sso.web_url'), '/');

        if ($appId === '' || $webUrl === '') {
            return $this->loginFailure('ยังไม่ได้ตั้งค่า KKU SSO กรุณาติดต่อผู้ดูแลระบบ');
        }

        $request->session()->put('kku_sso_login_attempt', true);

        return redirect()->away($webUrl.'/login?'.http_build_query(['app' => $appId]));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! config('services.kku_sso.enabled')) {
            return redirect()->route('login');
        }

        if (! $request->session()->pull('kku_sso_login_attempt', false)) {
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
                ->post($this->apiUrl('/auth.token'), [
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

        $user = $this->findUser($token, $profile);

        if (! $user) {
            return $this->loginFailure('ไม่พบบัญชีนี้ในระบบ EN-IDP กรุณาติดต่อผู้ดูแลระบบ');
        }

        if (! $user->is_active) {
            return $this->loginFailure('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        $verifiedAttributes = [];
        $ssoId = $this->firstFilled([$token['employeeId'] ?? null, $profile['userId'] ?? null]);
        if (! $user->sso && $ssoId !== null) {
            $verifiedAttributes['sso'] = $ssoId;
        }
        if (! $user->email_verified_at) {
            $verifiedAttributes['email_verified_at'] = now();
        }
        if ($verifiedAttributes !== []) {
            $user->forceFill($verifiedAttributes)->save();
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

    /** @param array<string, mixed> $token @param array<string, mixed> $profile */
    private function findUser(array $token, array $profile): ?User
    {
        $ssoIds = array_values(array_unique(array_filter([
            $this->filledString($token['employeeId'] ?? null),
            $this->filledString($profile['userId'] ?? null),
        ])));

        foreach ($ssoIds as $ssoId) {
            $user = User::query()->where('sso', $ssoId)->first();
            if ($user) {
                return $user;
            }
        }

        $email = $this->firstFilled([$profile['email'] ?? null, $token['email'] ?? null]);

        return $email === null
            ? null
            : User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
    }

    private function hasRequiredConfiguration(): bool
    {
        return collect(['client_id', 'client_secret', 'redirect_url', 'api_url'])
            ->every(fn (string $key): bool => trim((string) config("services.kku_sso.$key")) !== '');
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.kku_sso.api_url'), '/').$path;
    }

    /** @param array<int, mixed> $values */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (($filled = $this->filledString($value)) !== null) {
                return $filled;
            }
        }

        return null;
    }

    private function filledString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function loginFailure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('sso_error', $message);
    }
}
