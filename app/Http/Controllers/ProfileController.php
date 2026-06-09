<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileUser' => $this->profileData($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $firstNameTh = $data['first_name_th'] ?? '';
        $lastNameTh = $data['last_name_th'] ?? '';
        $fullName = trim($firstNameTh.' '.$lastNameTh);

        $user->fill([
            'sso' => $data['sso'] ?? $user->sso,
            'title' => $data['title'] ?? '',
            'name' => $fullName ?: ($data['name'] ?? $user->name),
            'first_name_th' => $firstNameTh,
            'last_name_th' => $lastNameTh,
            'first_name_en' => $data['first_name_en'] ?? '',
            'last_name_en' => $data['last_name_en'] ?? '',
            'gender' => $data['gender'] ?? '',
            'email' => $data['email'] ?: $user->email,
            'phone' => $data['phone'] ?? '',
            'workline' => $data['workline'] ?? '',
            'department' => $data['department'] ?? '',
            'position' => $data['position'] ?? '',
            'level' => $data['level'] ?? '',
            'supervisor' => $data['supervisor'] ?? '',
            'evaluator2' => $data['evaluator2'] ?? '',
            'profile_photo' => $data['profile_photo'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    private function profileData($user): array
    {
        return [
            'db_id' => $user->id,
            'sso' => $user->sso ?: (string) $user->id,
            't' => $user->title ?: '',
            'n' => $user->name,
            'fn' => $user->first_name_th ?: $user->name,
            'ln' => $user->last_name_th ?: '',
            'fe' => $user->first_name_en ?: '',
            'le' => $user->last_name_en ?: '',
            'g' => $user->gender ?: '',
            'em' => $user->email ?: '',
            'ph' => $user->phone ?: '',
            'w' => $user->workline ?: '',
            'd' => $user->department ?: '',
            'p' => $user->position ?: '',
            'l' => $user->level ?: '',
            'r' => $this->normalizeRoleKey($user->role_key ?: $this->roleKeyFromId($user->role_id)),
            'sup' => $user->supervisor ?: '',
            'evaluator2' => $user->evaluator2 ?: '',
            'photo' => $user->profile_photo ?: '',
            'act' => (bool) $user->is_active,
        ];
    }

    private function roleKeyFromId(?int $roleId): string
    {
        return match ($roleId) {
            0 => 'admin',
            1 => 'supervisor',
            2 => 'dept_head',
            3 => 'employee',
            4 => 'hr',
            5 => 'dean',
            default => 'employee',
        };
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'dept_head' => 'manager_dept',
            'dean' => 'manager',
            default => $roleKey,
        };
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
