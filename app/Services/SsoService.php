<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SsoService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function syncFromSso(array $payload): User
    {
        $sso = (string) Arr::get($payload, 'sso', Arr::get($payload, 'sso_id', ''));
        $email = Arr::get($payload, 'email');

        $user = null;
        if ($sso !== '') {
            $user = User::where('sso', $sso)->first();
        } elseif ($email) {
            $user = User::where('email', $email)->first();
        }
        $isNewUser = ! $user;

        $attributes = $this->userAttributesFromPayload($payload);
        if ($isNewUser) {
            $attributes['password'] = Str::password(32);
            $user = User::create($attributes);
        } else {
            $user->fill($attributes)->save();
        }

        if ($isNewUser) {
            $this->notifications->notifyAdminNewUser($user);

            if ($this->hasIncompleteStructure($user)) {
                $this->notifications->notifyAdminIncompleteUser($user);
            }
        }

        return $user;
    }

    private function userAttributesFromPayload(array $payload): array
    {
        $attributes = [
            'sso' => Arr::get($payload, 'sso', Arr::get($payload, 'sso_id')),
            'name' => Arr::get($payload, 'name', Arr::get($payload, 'full_name', 'SSO User')),
            'email' => Arr::get($payload, 'email'),
            'title' => Arr::get($payload, 'title'),
            'first_name_th' => Arr::get($payload, 'first_name_th'),
            'last_name_th' => Arr::get($payload, 'last_name_th'),
            'first_name_en' => Arr::get($payload, 'first_name_en'),
            'last_name_en' => Arr::get($payload, 'last_name_en'),
            'phone' => Arr::get($payload, 'phone'),
            'workline' => Arr::get($payload, 'workline'),
            'department' => Arr::get($payload, 'department'),
            'position' => Arr::get($payload, 'position'),
            'level' => Arr::get($payload, 'level'),
            'position_id' => Arr::get($payload, 'position_id'),
            'level_id' => Arr::get($payload, 'level_id'),
        ];

        if (! $attributes['email']) {
            $attributes['email'] = ($attributes['sso'] ?: Str::uuid()).'@sso.local';
        }

        if (Schema::hasColumn('users', 'division_id')) {
            $attributes['division_id'] = Arr::get($payload, 'division_id');
        }

        if (Schema::hasColumn('users', 'job_family_id')) {
            $attributes['job_family_id'] = Arr::get($payload, 'job_family_id');
        }

        if (Schema::hasColumn('users', 'role_key')) {
            $attributes['role_key'] = Arr::get($payload, 'role_key', Arr::get($payload, 'role', 'employee'));
        }

        $roleId = $this->roleIdForKey($attributes['role_key'] ?? Arr::get($payload, 'role_key', 'employee'));
        if ($roleId) {
            $attributes['role_id'] = $roleId;
        }

        return array_filter($attributes, fn ($value) => $value !== null);
    }

    private function hasIncompleteStructure(User $user): bool
    {
        $missingDivision = Schema::hasColumn('users', 'division_id') && blank($user->division_id);
        $missingJobFamily = Schema::hasColumn('users', 'job_family_id')
            ? blank($user->job_family_id)
            : blank($user->position_id);

        return $missingDivision || $missingJobFamily;
    }

    private function roleIdForKey(string $roleKey): ?int
    {
        if (! Schema::hasTable('roles')) {
            return null;
        }

        $query = DB::table('roles');

        if (Schema::hasColumn('roles', 'key')) {
            $query->where('key', $roleKey);
        }

        if (Schema::hasColumn('roles', 'role_key')) {
            $query->orWhere('role_key', $roleKey);
        }

        return $query->value('id');
    }
}
