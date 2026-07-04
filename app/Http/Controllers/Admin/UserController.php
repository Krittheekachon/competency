<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
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
    public function __construct(private NotificationService $notifications)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $user = User::create([
            ...$this->userAttributes($data),
            'password' => Hash::make(Str::password(32)),
        ]);

        $this->notifications->notifyAdminNewUser($user);

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

        if ($request->user()?->is($user) && $data['act'] === false) {
            throw ValidationException::withMessages([
                'act' => 'ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้',
            ]);
        }

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

        $roleKeys = DB::table('roles')->pluck('key')->all();

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
            'supervisor_id_1' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id_2' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id_3' => ['nullable', 'integer', 'exists:users,id'],
            'act' => ['boolean'],
        ], [
            'ph.regex' => 'กรุณากรอกเบอร์โทรศัพท์ในรูปแบบ 0xx-xxx-xxxx',
        ]);

        $this->validateEvaluatorRoles($data);

        return $this->validatedStructureData($data);
    }

    private function userAttributes(array $data): array
    {
        $roleKey = $this->normalizeRoleKey($data['r']);
        $roleKeyColumn = $this->roleKeyColumn();
        $role = DB::table('roles')->where('key', $roleKey)->first(['id', 'key', $roleKeyColumn]);

        if (! $role) {
            throw ValidationException::withMessages([
                'r' => 'กรุณาเลือกบทบาทในระบบที่กำหนดไว้',
            ]);
        }

        $name = trim($data['fn'].' '.$data['ln']);
        $attributes = [
            'sso' => $data['sso'],
            'name' => $name,
            'title' => $data['t'] ?? null,
            'first_name_th' => $data['fn'],
            'last_name_th' => $data['ln'],
            'first_name_en' => $data['fe'] ?? null,
            'last_name_en' => $data['le'] ?? null,
            'email' => $data['em'],
            'phone' => $data['ph'] ?? null,
            'workline' => $data['w'],
            'department' => $data['d'] ?? null,
            'position' => $data['p'] ?? null,
            'level' => $data['l'] ?? null,
            'position_id' => $data['_position_id'],
            'level_id' => $data['_level_id'],
            'role_id' => $role->id,
            'supervisor_id_1' => $data['supervisor_id_1'] ?? null,
            'supervisor_id_2' => $data['supervisor_id_2'] ?? null,
            'supervisor_id_3' => $data['supervisor_id_3'] ?? null,
            'is_active' => $data['act'] ?? true,
        ];

        if (Schema::hasColumn('users', 'role_key')) {
            $attributes['role_key'] = $roleKey;
        }

        return $attributes;
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

    private function validateEvaluatorRoles(array $data): void
    {
        $expectedRoles = [
            'supervisor_id_1' => 'supervisor',
            'supervisor_id_2' => 'dept_head',
            'supervisor_id_3' => 'dean',
        ];

        foreach ($expectedRoles as $field => $roleKey) {
            if (empty($data[$field])) {
                continue;
            }

            $exists = User::query()
                ->whereKey($data[$field])
                ->whereHas('role', fn ($query) => $query->where('key', $roleKey))
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    $field => 'กรุณาเลือกผู้ประเมินให้ตรงกับบทบาทที่กำหนด',
                ]);
            }
        }
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
