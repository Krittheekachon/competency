<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        User::create([
            ...$this->userAttributes($data),
            'password' => Hash::make(Str::password(32)),
        ]);

        return back()->with('success', 'บันทึกผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);

        $user->update($this->userAttributes($data));

        return back()->with('success', 'อัปเดตผู้ใช้เรียบร้อยแล้ว');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'act' => ['required', 'boolean'],
        ]);

        $user->update([
            'is_active' => $data['act'],
        ]);

        return back()->with('success', 'อัปเดตสถานะผู้ใช้เรียบร้อยแล้ว');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $request->merge([
            'r' => $this->normalizeRoleKey((string) $request->input('r', '')),
        ]);

        $roleKeys = DB::table('roles')->pluck($this->roleKeyColumn())->all();

        $data = $request->validate([
            'sso' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'sso')->ignore($user?->id),
            ],
            't' => ['nullable', 'string', 'max:30'],
            'fn' => ['required', 'string', 'max:120'],
            'ln' => ['required', 'string', 'max:120'],
            'fe' => ['nullable', 'string', 'max:120'],
            'le' => ['nullable', 'string', 'max:120'],
            'g' => ['nullable', 'string', 'max:30'],
            'em' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'ph' => ['nullable', 'regex:/^0\d{2}-\d{3}-\d{4}$/'],
            'w' => ['required', 'string', 'max:120'],
            'd' => ['required', 'string', 'max:255'],
            'p' => [Rule::requiredIf(fn () => $request->input('r') !== 'dean'), 'nullable', 'string', 'max:120'],
            'l' => ['required', 'string', 'max:120'],
            'r' => ['required', Rule::in($roleKeys)],
            'sup' => ['nullable', 'string', 'max:255'],
            'evaluator2' => ['nullable', 'string', 'max:255'],
            'supervisor_id_1' => ['nullable', 'integer', 'exists:users,id', 'different:supervisor_id_2'],
            'supervisor_id_2' => ['nullable', 'integer', 'exists:users,id'],
            'evaluator3' => ['nullable', 'string', 'max:255'],
            'supervisor_id_3' => ['nullable', 'integer', 'exists:users,id'],
            'act' => ['boolean'],
        ], [
            'ph.regex' => 'กรุณากรอกเบอร์โทรศัพท์ในรูปแบบ 0xx-xxx-xxxx',
        ]);

        return $this->validatedStructureData($data);
    }

    private function userAttributes(array $data): array
    {
        $roleKey = $this->normalizeRoleKey($data['r']);
        $role = DB::table('roles')->where($this->roleKeyColumn(), $roleKey)->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'r' => 'กรุณาเลือกบทบาทในระบบที่กำหนดไว้',
            ]);
        }

        $name = trim($data['fn'].' '.$data['ln']);
        $supervisorId1 = $data['supervisor_id_1'] ?? null;
        $supervisorId2 = $data['supervisor_id_2'] ?? null;
        $supervisorId3 = $data['supervisor_id_3'] ?? null;

        $attributes = [
            'sso' => $data['sso'],
            'name' => $name,
            'title' => $data['t'] ?? null,
            'first_name_th' => $data['fn'],
            'last_name_th' => $data['ln'],
            'first_name_en' => $data['fe'] ?? null,
            'last_name_en' => $data['le'] ?? null,
            'gender' => $data['g'] ?? null,
            'email' => $data['em'],
            'phone' => $data['ph'] ?? null,
            'workline' => $data['w'],
            'department' => $data['d'] ?? null,
            'position' => $data['p'] ?? null,
            'level' => $data['l'] ?? null,
            'position_id' => $data['_position_id'],
            'level_id' => $data['_level_id'],
            'role_id' => $role->{$this->roleIdColumn()},
            'supervisor' => $this->userNameFromId($supervisorId1) ?? ($data['sup'] ?? null),
            'evaluator2' => $this->userNameFromId($supervisorId2) ?? ($data['evaluator2'] ?? null),
            'evaluator3' => $this->userNameFromId($supervisorId3) ?? ($data['evaluator3'] ?? null),
            'supervisor_id_1' => $supervisorId1 ?: $this->userIdFromDisplayName($data['sup'] ?? null),
            'supervisor_id_2' => $supervisorId2 ?: $this->userIdFromDisplayName($data['evaluator2'] ?? null),
            'supervisor_id_3' => $supervisorId3 ?: $this->userIdFromDisplayName($data['evaluator3'] ?? null),
            'is_active' => $data['act'] ?? true,
        ];

        if (Schema::hasColumn('users', 'role_key')) {
            $attributes['role_key'] = $role->{$this->roleKeyColumn()};
        }

        return $attributes;
    }

    private function userNameFromId(?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        return User::query()->whereKey($id)->value('name');
    }

    private function userIdFromDisplayName(?string $displayName): ?int
    {
        $displayName = trim((string) $displayName);

        if ($displayName === '') {
            return null;
        }

        return User::query()
            ->get(['id', 'title', 'name'])
            ->first(function (User $user) use ($displayName) {
                $name = trim($user->name);
                $nameWithTitle = trim(($user->title ?? '').$user->name);

                return $displayName === $name || $displayName === $nameWithTitle;
            })
            ?->id;
    }

    private function validatedStructureData(array $data): array
    {
        $worklineId = DB::table('worklines')->where('name', $data['w'])->value('id');

        if (!$worklineId) {
            throw ValidationException::withMessages([
                'w' => 'กรุณาเลือกสายงานที่กำหนดไว้ในระบบ',
            ]);
        }

        $jobFamilyName = $this->jobFamilyNameFromDepartment($data['d']);
        $jobFamily = $jobFamilyName
            ? DB::table('job_families')
                ->where('workline_id', $worklineId)
                ->where('name', $jobFamilyName)
                ->first(['id'])
            : null;

        if (!$jobFamily) {
            throw ValidationException::withMessages([
                'd' => 'กรุณาเลือกกลุ่มงานที่กำหนดไว้ในสายงานนี้',
            ]);
        }

        if ($this->normalizeRoleKey($data['r']) === 'dean' && trim((string) ($data['p'] ?? '')) === '') {
            $data['p'] = $jobFamilyName;
        }

        $positionId = DB::table('positions')
            ->where('job_family_id', $jobFamily->id)
            ->where('name', $data['p'] ?? '')
            ->value('id');
        $usesJobFamilyAsPosition = $this->normalizeRoleKey($data['r']) === 'dean'
            && trim((string) ($data['p'] ?? '')) === $jobFamilyName;

        if (!$positionId && !$usesJobFamilyAsPosition) {
            throw ValidationException::withMessages([
                'p' => 'กรุณาเลือกตำแหน่งที่กำหนดไว้ในกลุ่มงานนี้',
            ]);
        }

        $levelId = DB::table('levels')
            ->where('workline_id', $worklineId)
            ->where('name', $data['l'])
            ->where(function ($query) use ($jobFamily) {
                $query->whereNull('job_family_id')
                    ->orWhere('job_family_id', $jobFamily->id);
            })
            ->value('id');

        if (!$levelId) {
            throw ValidationException::withMessages([
                'l' => 'กรุณาเลือกระดับตำแหน่งที่กำหนดไว้ในสายงานหรือกลุ่มงานนี้',
            ]);
        }

        $data['_position_id'] = $positionId;
        $data['_level_id'] = $levelId;

        return $data;
    }

    private function jobFamilyNameFromDepartment(string $department): string
    {
        return trim(explode(' > ', $department)[0] ?? '');
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function roleKeyColumn(): string
    {
        return Schema::hasColumn('roles', 'role_key') ? 'role_key' : 'key';
    }

    private function roleIdColumn(): string
    {
        return Schema::hasColumn('roles', 'role_id') ? 'role_id' : 'id';
    }
}
