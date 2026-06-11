<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MockSsoController extends Controller
{
    public function showLogin()
    {
        $users = User::with('role')
            ->orderBy('role_id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id'       => $user->sso ?? (string) $user->id,
                'name'     => ($user->title ?? '') . $user->name,
                'role'     => $user->role?->key ?? 'employee',
                'workline' => $user->workline ?? '',
                'division' => $user->division ?? '',
                'position' => $user->position ?? '',
            ]);

        return view('mock-sso.login', ['mockUsers' => $users]);
    }

    public function login(Request $request)
    {
        $ssoId = $request->input('sso_id');

        $user = User::where('sso', $ssoId)
            ->orWhere('id', $ssoId)
            ->first();

        if (!$user) {
            return back()->withErrors(['sso_id' => 'ไม่พบ User นี้ในระบบ']);
        }

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
