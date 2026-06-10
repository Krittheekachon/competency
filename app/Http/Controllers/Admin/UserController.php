<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompetencyAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLE_IDS = [
        'admin' => 0,
        'supervisor' => 1,
        'dept_head' => 2,
        'manager_dept' => 2,
        'employee' => 3,
        'hr' => 4,
        'dean' => 5,
        'manager' => 5,
    ];

    public function __construct(private CompetencyAssessmentSyncService $competencyAssessmentSync)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $user = User::create([
            ...$this->userAttributes($data),
            'password' => Hash::make(Str::password(32)),
        ]);

        $this->competencyAssessmentSync->syncUser($user);

        return back()->with('success', 'บันทึกผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);

        $user->update($this->userAttributes($data));
        $this->competencyAssessmentSync->syncUser($user->fresh());

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
        return $request->validate([
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
            'd' => ['nullable', 'string', 'max:255'],
            'p' => ['nullable', 'string', 'max:120'],
            'l' => ['nullable', 'string', 'max:120'],
            'r' => ['required', Rule::in(array_keys(self::ROLE_IDS))],
            'sup' => ['nullable', 'string', 'max:255'],
            'evaluator2' => ['nullable', 'string', 'max:255'],
            'act' => ['boolean'],
        ], [
            'ph.regex' => 'กรุณากรอกเบอร์โทรศัพท์ในรูปแบบ 0xx-xxx-xxxx',
        ]);
    }

    private function userAttributes(array $data): array
    {
        $roleKey = match ($data['r']) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $data['r'],
        };
        $name = trim($data['fn'].' '.$data['ln']);

        return [
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
            'role_id' => self::ROLE_IDS[$roleKey],
            'role_key' => $roleKey,
            'supervisor' => $data['sup'] ?? null,
            'evaluator2' => $data['evaluator2'] ?? null,
            'supervisor_id_1' => $this->userIdFromDisplayName($data['sup'] ?? null),
            'supervisor_id_2' => $this->userIdFromDisplayName($data['evaluator2'] ?? null),
            'is_active' => $data['act'] ?? true,
        ];
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
}
