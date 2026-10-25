<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        try {
            return DB::transaction(function () use ($token, $profile): User {
                $this->validateStringFields($token, [
                    'employeeId',
                    'userId',
                    'email',
                    'firstName',
                    'lastName',
                ]);
                $this->validateStringFields($profile, [
                    'employeeId',
                    'userId',
                    'email',
                    'title',
                    'firstname',
                    'lastname',
                    'firstnameEng',
                    'lastnameEng',
                    'phoneNumber',
                    'workline',
                    'facultyName',
                    'positionName',
                    'positionTypeName',
                    'levelId',
                    'levelName',
                    'personStatus',
                ]);

                $tokenEmployeeId = $this->filledIdentityString($token['employeeId'] ?? null);
                $profileEmployeeId = $this->filledIdentityString($profile['employeeId'] ?? null);

                if ($tokenEmployeeId !== null && $profileEmployeeId !== null && $tokenEmployeeId !== $profileEmployeeId) {
                    throw ValidationException::withMessages([
                        'sso' => 'รหัสบุคลากรจาก KKU SSO ไม่ตรงกัน กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }

                $employeeId = $tokenEmployeeId ?? $profileEmployeeId;
                $ssoUserId = $this->consistentIdentityValue($token, 'userId', $profile, 'userId');
                $tokenEmail = $this->filledIdentityString($token['email'] ?? null);
                $profileEmail = $this->filledIdentityString($profile['email'] ?? null);
                if ($tokenEmail !== null && $profileEmail !== null && Str::lower($tokenEmail) !== Str::lower($profileEmail)) {
                    throw ValidationException::withMessages([
                        'sso' => 'ไม่สามารถยืนยันข้อมูล KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }
                $emailCandidates = collect([
                    $profileEmail,
                    $tokenEmail,
                ])->filter()->unique(fn (string $email): string => Str::lower($email))->values();
                $user = $this->findUser($employeeId, $ssoUserId, $emailCandidates->all());

                if (! $user) {
                    $user = $this->provisionUser($token, $profile, $employeeId, $ssoUserId);
                }

                if (! $user) {
                    throw ValidationException::withMessages([
                        'sso' => 'ไม่พบบัญชีผู้ใช้นี้ในระบบ EN-IDP กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }

                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        'sso' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }

                $previousStructuralProfile = $this->structuralProfileFromUser($user);
                $incomingStructuralProfile = $this->structuralProfileFromSso($profile);
                $hasIncomingWorkline = $incomingStructuralProfile['workline'] !== null;
                foreach (['workline', 'facultyName', 'positionName', 'positionTypeName', 'levelId', 'levelName'] as $field) {
                    $incomingStructuralProfile[$field] ??= $previousStructuralProfile[$field];
                }
                if (! $hasIncomingWorkline) {
                    $incomingStructuralProfile['worklineType'] = $previousStructuralProfile['worklineType'];
                }
                $fingerprint = hash('sha256', json_encode($incomingStructuralProfile, JSON_UNESCAPED_UNICODE));

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
                    'sso_person_status' => $this->filledString($profile['personStatus'] ?? null) ?? $user->sso_person_status,
                    'sso_profile_fingerprint' => $fingerprint,
                    'sso_profile_synced_at' => now(),
                ])->save();

                $this->syncPositionTitle($incomingStructuralProfile);
                $this->syncLevel($incomingStructuralProfile);

                if ($previousStructuralProfile !== $incomingStructuralProfile) {
                    DB::table('sso_profile_changes')->insertOrIgnore([
                        'user_id' => $user->id,
                        'fingerprint' => $fingerprint,
                        'previous_profile' => json_encode($previousStructuralProfile, JSON_UNESCAPED_UNICODE),
                        'incoming_profile' => json_encode($incomingStructuralProfile, JSON_UNESCAPED_UNICODE),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $user->fresh('role');
            });
        } catch (QueryException $exception) {
            if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['sso' => 'ไม่สามารถยืนยันข้อมูล KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ']);
        }
    }

    private function provisionUser(array $token, array $profile, ?string $employeeId, ?string $ssoUserId): User
    {
        if (! $employeeId && ! $ssoUserId) {
            throw ValidationException::withMessages(['sso' => 'ไม่สามารถยืนยันข้อมูล KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ']);
        }
        $email = $this->firstIdentityString([$profile['email'] ?? null, $token['email'] ?? null]);
        if (! $email || User::query()->whereRaw('LOWER(TRIM(email)) = ?', [Str::lower($email)])->exists()) {
            throw ValidationException::withMessages(['sso' => 'ไม่สามารถยืนยันข้อมูล KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ']);
        }
        $roleId = DB::table('roles')->where('key', 'employee')->value('id');
        if (! $roleId) {
            throw ValidationException::withMessages(['sso' => 'ไม่สามารถสร้างบัญชีผู้ใช้ได้ กรุณาติดต่อผู้ดูแลระบบ']);
        }

        return User::query()->create([
            'name' => trim(collect([$profile['firstname'] ?? null, $profile['lastname'] ?? null])->filter()->implode(' ')) ?: $email,
            'email' => $email,
            'password' => Str::random(64),
            'role_id' => $roleId,
            'is_active' => true,
            'email_verified_at' => now(),
            'sso' => $employeeId,
            'sso_user_id' => $ssoUserId,
        ]);
    }

    /** @param array<int, string> $emailCandidates */
    private function findUser(?string $employeeId, ?string $ssoUserId, array $emailCandidates): ?User
    {
        $matches = collect();

        foreach ([['sso', $employeeId], ['sso_user_id', $ssoUserId]] as [$column, $identifier]) {
            if ($identifier === null) {
                continue;
            }

            $identifierMatches = User::query()
                ->whereRaw('TRIM('.$column.') = ?', [$identifier])
                ->limit(2)
                ->get();

            if ($identifierMatches->count() > 1) {
                throw ValidationException::withMessages([
                    'sso' => 'ข้อมูลระบุตัวตน KKU SSO ตรงกับหลายบัญชี กรุณาติดต่อผู้ดูแลระบบ',
                ]);
            }

            $matches = $matches->concat($identifierMatches);
        }

        $matches = $matches->unique('id')->values();

        $emailMatches = collect();
        foreach ($emailCandidates as $email) {
            $emailMatches = $emailMatches->concat(
                User::query()->whereRaw('LOWER(TRIM(email)) = ?', [Str::lower($email)])->get()
            );
        }

        $emailMatches = $emailMatches->unique('id')->values();

        if ($emailMatches->count() > 1
            || ($matches->isNotEmpty()
                && $emailMatches->isNotEmpty()
                && $emailMatches->contains(fn (User $emailMatch): bool => $emailMatch->id !== $matches->first()->id))) {
            throw ValidationException::withMessages([
                'sso' => 'ข้อมูล KKU SSO ไม่ตรงกับบัญชี EN-IDP กรุณาติดต่อผู้ดูแลระบบ',
            ]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'sso' => 'ข้อมูล KKU SSO ไม่ตรงกับบัญชี EN-IDP กรุณาติดต่อผู้ดูแลระบบ',
            ]);
        }

        return $matches->first();
    }

    private function consistentIdentityValue(array $first, string $key, array $second, string $secondKey): ?string
    {
        $firstValue = $this->filledIdentityString($first[$key] ?? null);
        $secondValue = $this->filledIdentityString($second[$secondKey] ?? null);

        if ($firstValue !== null && $secondValue !== null && $firstValue !== $secondValue) {
            throw ValidationException::withMessages([
                'sso' => 'ข้อมูลระบุตัวตนจาก token และ profile ของ KKU SSO ไม่ตรงกัน กรุณาติดต่อผู้ดูแลระบบ',
            ]);
        }

        return $firstValue ?? $secondValue;
    }

    /** @return array<string, mixed> */
    private function identityAttributes(array $token, array $profile, User $user): array
    {
        $firstNameTh = $this->firstFilled([$profile['firstname'] ?? null, $token['firstName'] ?? null]);
        $lastNameTh = $this->firstFilled([$profile['lastname'] ?? null, $token['lastName'] ?? null]);
        $firstNameEn = $this->filledString($profile['firstnameEng'] ?? null);
        $lastNameEn = $this->filledString($profile['lastnameEng'] ?? null);
        $name = trim(collect([$firstNameTh, $lastNameTh])->filter()->implode(' '));

        return [
            'name' => $name !== '' ? $name : ($user->name ?: $user->email),
            'title' => $this->filledString($profile['title'] ?? null) ?: $user->title,
            'first_name_th' => $firstNameTh ?: $user->first_name_th,
            'last_name_th' => $lastNameTh ?: $user->last_name_th,
            'first_name_en' => $firstNameEn ?: $user->first_name_en,
            'last_name_en' => $lastNameEn ?: $user->last_name_en,
            // Email is not changed until KKU documents a trusted verification claim.
            'email' => $user->email,
            'phone' => $this->filledString($profile['phoneNumber'] ?? null) ?: $user->phone,
            'email_verified_at' => $user->email_verified_at,
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
    private function syncPositionTitle(array $profile): void
    {
        if (! $profile['worklineType'] || ! $profile['positionName']) {
            return;
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

            return;
        }

        DB::table('position_titles')->insert([
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
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * PostgreSQL TRIM(value) removes ordinary spaces by default. Keep identity
     * normalization identical so PHP does not merge tab/newline identifiers
     * that the database indexes and lookup expressions consider distinct.
     */
    private function filledIdentityString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value, ' ');

        return $value === '' ? null : $value;
    }

    /** @param array<int, mixed> $values */
    private function firstIdentityString(array $values): ?string
    {
        foreach ($values as $value) {
            if (($filled = $this->filledIdentityString($value)) !== null) {
                return $filled;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @param array<int, string> $keys */
    private function validateStringFields(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                throw ValidationException::withMessages([
                    'sso' => 'ข้อมูลโปรไฟล์จาก KKU SSO มีรูปแบบไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ',
                ]);
            }

            if (in_array($key, ['employeeId', 'userId', 'email'], true) && $value !== null) {
                $stringValue = trim((string) $value, ' ');
                if (Str::length($stringValue) > 255
                    || ($key === 'email' && $stringValue !== '' && filter_var($stringValue, FILTER_VALIDATE_EMAIL) === false)) {
                    throw ValidationException::withMessages([
                        'sso' => 'ไม่สามารถยืนยันข้อมูล KKU SSO ได้ กรุณาติดต่อผู้ดูแลระบบ',
                    ]);
                }
            }
        }
    }
}
