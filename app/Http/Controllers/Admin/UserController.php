<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReviewerTemplateResolver;
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
    public function __construct(
        private NotificationService $notifications,
        private ReviewerTemplateResolver $reviewerTemplateResolver,
    )
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $user = null;

        DB::transaction(function () use ($data, &$user): void {
            $user = User::create([
                ...$this->userAttributes($data),
                'password' => Hash::make(Str::password(32)),
            ]);

            $this->syncReviewerSteps($user, $data['reviewer_ids'] ?? [], 'assessment');
            $this->syncReviewerSteps($user, $data['idp_reviewer_ids'] ?? [], 'idp');
        });

        $this->notifications->notifyAdminNewUser($user);

        return back()->with('success', 'บันทึกผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);

        DB::transaction(function () use ($user, $data): void {
            $user->update($this->userAttributes($data));
            $this->syncReviewerSteps($user, $data['reviewer_ids'] ?? [], 'assessment');
            $this->syncReviewerSteps($user, $data['idp_reviewer_ids'] ?? [], 'idp');
        });

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
            'reviewer_ids' => ['nullable', 'array'],
            'reviewer_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'reviewer_template_id' => ['nullable', 'integer'],
            'idp_reviewer_ids' => ['nullable', 'array'],
            'idp_reviewer_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'idp_reviewer_template_id' => ['nullable', 'integer'],
            'act' => ['boolean'],
        ], [
            'ph.regex' => 'กรุณากรอกเบอร์โทรศัพท์ในรูปแบบ 0xx-xxx-xxxx',
        ]);

        $this->assertReviewerTemplateIsValid($data['reviewer_template_id'] ?? null, 'assessment', 'reviewer_template_id');
        $this->assertReviewerTemplateIsValid($data['idp_reviewer_template_id'] ?? null, 'idp', 'idp_reviewer_template_id');

        $data = $this->validatedStructureData($data);
        $data['reviewer_ids'] = $this->normalizeReviewerIds($data, $user, 'reviewer_ids', 'reviewer_template_id', 'assessment');
        $data['idp_reviewer_ids'] = $this->normalizeReviewerIds($data, $user, 'idp_reviewer_ids', 'idp_reviewer_template_id', 'idp');

        return $data;
    }

    private function assertReviewerTemplateIsValid(mixed $templateId, string $chainType, string $field): void
    {
        if (! filled($templateId)) {
            return;
        }

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                $field => 'กรุณาเลือก template ลำดับที่เปิดใช้งานอยู่',
            ]);
        }

        $query = DB::table('reviewer_chain_templates')
            ->where('id', $templateId)
            ->where('is_active', true);

        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $query->where('chain_type', $chainType);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                $field => 'กรุณาเลือก template ลำดับที่เปิดใช้งานอยู่',
            ]);
        }
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
            'reviewer_template_id' => $data['reviewer_template_id'] ?? null,
            'is_active' => $data['act'] ?? true,
        ];

        if (Schema::hasColumn('users', 'idp_reviewer_template_id')) {
            $attributes['idp_reviewer_template_id'] = $data['idp_reviewer_template_id'] ?? null;
        }

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

        if (
            in_array($data['w'], ['สายสนับสนุน', 'สายงานสนับสนุน'], true)
            && count(array_filter(array_map('trim', explode(' > ', $data['d'])))) === 3
        ) {
            return $this->validatedSupportStructureData($data, (int) $worklineId);
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
            ->whereNull('job_family_id')
            ->value('id');

        if (!$levelId) {
            throw ValidationException::withMessages([
                'l' => 'กรุณาเลือกระดับตำแหน่งที่กำหนดไว้ในสายงานนี้',
            ]);
        }

        $data['_position_id'] = $positionId;
        $data['_level_id'] = $levelId;

        return $data;
    }

    private function validatedSupportStructureData(array $data, int $worklineId): array
    {
        $path = array_values(array_filter(array_map('trim', explode(' > ', $data['d']))));
        if (count($path) !== 3) {
            throw ValidationException::withMessages(['d' => 'กรุณาเลือกฝ่าย งาน และหน่วยให้ครบถ้วน']);
        }

        [$divisionName, $workName, $unitName] = $path;
        $unitId = DB::table('support_units')
            ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
            ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
            ->where('support_departments.name', $divisionName)
            ->where('support_works.name', $workName)
            ->where('support_units.name', $unitName)
            ->value('support_units.id');

        if (! $unitId) {
            throw ValidationException::withMessages(['d' => 'กรุณาเลือกหน่วยที่กำหนดไว้ในฝ่ายและงานนี้']);
        }

        $positionId = DB::table('positions')
            ->where('support_unit_id', $unitId)
            ->where('name', $data['p'] ?? '')
            ->value('id');
        if (! $positionId) {
            throw ValidationException::withMessages(['p' => 'กรุณาเลือกตำแหน่งที่กำหนดไว้ในหน่วยนี้']);
        }

        $levelId = DB::table('levels')
            ->where('workline_id', $worklineId)
            ->whereNull('job_family_id')
            ->where('name', $data['l'])
            ->value('id');
        if (! $levelId) {
            throw ValidationException::withMessages(['l' => 'กรุณาเลือกระดับตำแหน่งที่กำหนดไว้ในสายงานสนับสนุน']);
        }

        $data['_position_id'] = $positionId;
        $data['_level_id'] = $levelId;

        return $data;
    }

    private function normalizeReviewerIds(array $data, ?User $user, string $idsKey, string $templateKey, string $chainType): array
    {
        $rawReviewerIds = $data[$idsKey] ?? [];

        $reviewerIds = collect($rawReviewerIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($reviewerIds->isEmpty() && filled($data[$templateKey] ?? null)) {
            $reviewerIds = collect($this->reviewerTemplateResolver->resolveReviewerIdsForUser((object) [
                'id' => $user?->id,
                'workline' => $data['w'] ?? '',
                'department' => $data['d'] ?? '',
                'position' => $data['p'] ?? '',
            ], (int) $data[$templateKey], $chainType))->values();
        }

        if ($user && $reviewerIds->contains((int) $user->id)) {
            throw ValidationException::withMessages([
                $idsKey => 'ไม่สามารถเลือกผู้ใช้นี้เป็นผู้ประเมินของตัวเองได้',
            ]);
        }

        if ($reviewerIds->isEmpty()) {
            return [];
        }

        $validCount = User::query()
            ->whereIn('id', $reviewerIds)
            ->where('is_active', true)
            ->count();

        if ($validCount !== $reviewerIds->count()) {
            throw ValidationException::withMessages([
                $idsKey => 'กรุณาเลือกผู้ประเมินจากผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        return $reviewerIds->all();
    }

    private function syncReviewerSteps(User $user, array $reviewerIds, string $chainType): void
    {
        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $user->id);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', $chainType);
        }
        $deleteQuery->delete();

        $now = now();
        $rows = collect($reviewerIds)
            ->filter()
            ->values()
            ->map(function (int $reviewerId, int $index) use ($user, $chainType, $now): array {
                $row = [
                    'user_id' => $user->id,
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $row['chain_type'] = $chainType;
                }

                return $row;
            })
            ->all();

        if ($rows !== []) {
            DB::table('user_reviewer_steps')->insert($rows);
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
