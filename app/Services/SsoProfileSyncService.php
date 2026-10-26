<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoProfileSyncService
{
    public function __construct(private SsoWorklineNormalizer $worklineNormalizer)
    {
    }

    /** @param array<string, mixed> $token @param array<string, mixed> $profile */
    public function sync(array $token, array $profile): User
    {
        return DB::transaction(function () use ($token, $profile): User {
            $employeeId = $this->firstFilled([$token['employeeId'] ?? null, $profile['employeeId'] ?? null]);
            $ssoUserId = $this->filledString($profile['userId'] ?? null);
            $email = $this->firstFilled([$profile['email'] ?? null, $token['email'] ?? null]);
            $user = $this->findUser($employeeId, $ssoUserId, $email);

            if (! $user && $email === null) {
                throw ValidationException::withMessages([
                    'sso' => 'KKU SSO ไม่ได้ส่งอีเมลสำหรับระบุตัวผู้ใช้',
                ]);
            }

            $isNew = ! $user;

            if ($isNew) {
                $employeeRoleId = DB::table('roles')->where('key', 'employee')->value('id');

                if (! $employeeRoleId) {
                    throw ValidationException::withMessages([
                        'sso' => 'ระบบยังไม่มีบทบาท employee กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }

                $user = new User([
                    'email' => Str::lower((string) $email),
                    'password' => Hash::make(Str::random(64)),
                    'role_id' => (int) $employeeRoleId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            $previousStructuralProfile = $this->structuralProfileFromUser($user);
            $incomingStructuralProfile = $this->structuralProfileFromSso($profile);
            $fingerprint = hash('sha256', json_encode($incomingStructuralProfile, JSON_UNESCAPED_UNICODE));
            $positionTitleId = $this->syncPositionTitle($incomingStructuralProfile);

            $user->forceFill([
                'sso' => $employeeId ?: $user->sso,
                'sso_user_id' => $ssoUserId ?: $user->sso_user_id,
                ...$this->identityAttributes($token, $profile, $user),
                'sso_workline' => $incomingStructuralProfile['workline'],
                'sso_workline_type' => $incomingStructuralProfile['worklineType'],
                'sso_faculty_name' => $incomingStructuralProfile['facultyName'],
                'sso_position_name' => $incomingStructuralProfile['positionName'],
                'sso_position_type_name' => $incomingStructuralProfile['positionTypeName'],
                'sso_level_id' => $incomingStructuralProfile['levelId'],
                'sso_level_name' => $incomingStructuralProfile['levelName'],
                'sso_person_status' => $this->filledString($profile['personStatus'] ?? null),
                'sso_profile_fingerprint' => $fingerprint,
                'sso_profile_synced_at' => now(),
                'position_title_id' => $positionTitleId ?: $user->position_title_id,
            ])->save();

            $this->syncLevel($incomingStructuralProfile);

            if ($isNew || $previousStructuralProfile !== $incomingStructuralProfile) {
                DB::table('sso_profile_changes')->insertOrIgnore([
                    'user_id' => $user->id,
                    'fingerprint' => $fingerprint,
                    'previous_profile' => $isNew ? null : json_encode($previousStructuralProfile, JSON_UNESCAPED_UNICODE),
                    'incoming_profile' => json_encode($incomingStructuralProfile, JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $user->fresh('role');
        });
    }

    private function findUser(?string $employeeId, ?string $ssoUserId, ?string $email): ?User
    {
        if ($employeeId !== null && ($user = User::query()->where('sso', $employeeId)->first())) {
            return $user;
        }

        if ($ssoUserId !== null && ($user = User::query()->where('sso_user_id', $ssoUserId)->first())) {
            return $user;
        }

        return $email === null
            ? null
            : User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
    }

    /** @return array<string, mixed> */
    private function identityAttributes(array $token, array $profile, User $user): array
    {
        $firstNameTh = $this->firstFilled([$profile['firstname'] ?? null, $token['firstName'] ?? null]);
        $lastNameTh = $this->firstFilled([$profile['lastname'] ?? null, $token['lastName'] ?? null]);
        $firstNameEn = $this->filledString($profile['firstnameEng'] ?? null);
        $lastNameEn = $this->filledString($profile['lastnameEng'] ?? null);
        $email = $this->firstFilled([$profile['email'] ?? null, $token['email'] ?? null]) ?: $user->email;
        $name = trim(collect([$firstNameTh, $lastNameTh])->filter()->implode(' '));

        return [
            'name' => $name !== '' ? $name : ($user->name ?: $email),
            'title' => $this->filledString($profile['title'] ?? null) ?: $user->title,
            'first_name_th' => $firstNameTh ?: $user->first_name_th,
            'last_name_th' => $lastNameTh ?: $user->last_name_th,
            'first_name_en' => $firstNameEn ?: $user->first_name_en,
            'last_name_en' => $lastNameEn ?: $user->last_name_en,
            'email' => Str::lower($email),
            'phone' => $this->filledString($profile['phoneNumber'] ?? null) ?: $user->phone,
            'email_verified_at' => $user->email_verified_at ?: now(),
        ];
    }

    /** @return array<string, ?string> */
    private function structuralProfileFromSso(array $profile): array
    {
        $workline = $this->filledString($profile['workline'] ?? null);

        return [
            'workline' => $workline,
            'worklineType' => $this->worklineNormalizer->structureType($workline),
            'facultyName' => $this->filledString($profile['facultyName'] ?? null),
            'positionName' => $this->filledString($profile['positionName'] ?? null),
            'positionTypeName' => $this->filledString($profile['positionTypeName'] ?? null),
            'levelId' => $this->filledString($profile['levelId'] ?? null),
            'levelName' => $this->filledString($profile['levelName'] ?? null),
        ];
    }

    /** @return array<string, ?string> */
    private function structuralProfileFromUser(User $user): array
    {
        return [
            'workline' => $user->sso_workline,
            'worklineType' => $user->sso_workline_type,
            'facultyName' => $user->sso_faculty_name,
            'positionName' => $user->sso_position_name,
            'positionTypeName' => $user->sso_position_type_name,
            'levelId' => $user->sso_level_id,
            'levelName' => $user->sso_level_name,
        ];
    }

    /** @param array<string, ?string> $profile */
    private function syncPositionTitle(array $profile): ?int
    {
        if (! $profile['worklineType'] || ! $profile['positionName']) {
            return null;
        }

        $normalizedName = $this->normalizeName($profile['positionName']);
        $normalizedType = $this->normalizeName($profile['positionTypeName']);
        $existing = DB::table('position_titles')
            ->where('workline_type', $profile['worklineType'])
            ->where('normalized_position_type_name', $normalizedType)
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($existing) {
            DB::table('position_titles')->where('id', $existing->id)->update([
                'name' => $profile['positionName'],
                'position_type_name' => $profile['positionTypeName'],
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('position_titles')->insertGetId([
            'workline_type' => $profile['worklineType'],
            'name' => $profile['positionName'],
            'normalized_name' => $normalizedName,
            'position_type_name' => $profile['positionTypeName'],
            'normalized_position_type_name' => $normalizedType,
            'source' => 'sso',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, ?string> $profile */
    private function syncLevel(array $profile): void
    {
        $worklineName = $this->worklineNormalizer->systemWorkline($profile['workline']);
        if (! $worklineName || ! $profile['levelName']) {
            return;
        }

        $worklineId = DB::table('worklines')->where('name', $worklineName)->value('id');
        if (! $worklineId) {
            DB::table('worklines')->insertOrIgnore([
                'name' => $worklineName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $worklineId = DB::table('worklines')->where('name', $worklineName)->value('id');
        }
        if (! $worklineId) {
            return;
        }

        $level = DB::table('levels')
            ->where('workline_id', $worklineId)
            ->whereNull('job_family_id')
            ->where('name', $profile['levelName'])
            ->first();

        if ($level) {
            if (! $level->sso_level_id && $profile['levelId']) {
                DB::table('levels')->where('id', $level->id)->update([
                    'sso_level_id' => $profile['levelId'],
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => $profile['levelName'],
            'expected_level' => null,
            'source' => 'sso',
            'sso_level_id' => $profile['levelId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeName(?string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', trim((string) $value))));
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
}
