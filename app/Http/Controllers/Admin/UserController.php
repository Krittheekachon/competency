<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Position;
use App\Models\User;
use App\Models\Workline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\JobFamily;
use App\Models\Division;

class UserController extends Controller
{
    private const ROLE_IDS = [
        'admin' => 0,
        'supervisor' => 1,
        'dept_head' => 2,
        'employee' => 3,
        'hr' => 4,
        'dean' => 5,
        'head' => 2,
        'user' => 3,
        'manager' => 5,
        'manager_dept' => 2,
    ];

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
        DB::transaction(function () use ($user) {
            User::query()->where('supervisor_id_1', $user->id)->update(['supervisor_id_1' => null]);
            User::query()->where('supervisor_id_2', $user->id)->update(['supervisor_id_2' => null]);
            User::query()->where('supervisor', $user->name)->update(['supervisor' => null]);
            User::query()->where('evaluator2', $user->name)->update(['evaluator2' => null]);

            $user->delete();
        });

        return back()->with('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'users' => ['required', 'array', 'min:1'],
            'users.*.id' => ['required', 'numeric'],
            'users.*.firstname' => ['required', 'string', 'max:255'],
            'users.*.lastname' => ['required', 'string', 'max:255'],
            'users.*.workline' => ['required', 'string', 'max:255'],
            'users.*.posi' => ['required', 'string', 'max:255'],
            'users.*.level' => ['required', 'string', 'max:255'],
            'users.*.division' => ['nullable', 'string', 'max:255'],
            'users.*.job' => ['nullable', 'string', 'max:255'],
            'users.*.job_family' => ['nullable', 'string', 'max:255'],
            'users.*.role' => ['nullable', 'string', 'in:admin,hr,dean,head,supervisor,user'],
            'users.*.email' => ['nullable', 'email', 'max:255'],
        ]);

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, &$imported, &$skipped) {
            foreach ($request->input('users', []) as $row) {
                $cleanImportValue = fn (string $key): string => $this->cleanImportValue($row[$key] ?? '');
                $sso = trim($row['id'] ?? '');
                if ($sso === '') {
                    $skipped++;
                    continue;
                }

                $workline = Workline::firstOrCreate([
                    'name' => trim($row['workline'] ?? ''),
                ]);
                $jobFamily = $cleanImportValue('job_family');

                $jobFamilyModel = null;
                if (!empty($jobFamily)) {
                    $jobFamilyModel = JobFamily::firstOrCreate(
                        [
                            'name'        => $jobFamily,
                            'workline_id' => $workline->id,
                        ]
                    );
                }

                $divisionModel = null;
                if (!empty($division)) {
                    $divisionModel = Division::firstOrCreate(
                        [
                            'name'        => $division,
                            'workline_id' => $workline->id,
                        ]
                    );
                }

                $level = Level::firstOrCreate([
                    'name' => trim($row['level'] ?? ''),
                    'workline_id' => $workline->id,
                ]);

                $position = Position::where('name', trim($row['posi'] ?? ''))->first();

                $department = implode(' > ', array_filter(array_map(
                    fn ($value) => trim((string) $value),
                    [
                        $cleanImportValue('faculty'),
                        $cleanImportValue('division'),
                        $cleanImportValue('unit'),
                    ],
                )));
                $division = $cleanImportValue('division');
                $job = $cleanImportValue('job');

                $roleKey = $this->canonicalImportRoleKey(trim($row['role'] ?? ''));

                User::updateOrCreate(
                    ['sso' => $sso],
                    [
                        'name' => trim(($row['firstname'] ?? '').' '.($row['lastname'] ?? '')),
                        'first_name_th' => trim($row['firstname'] ?? ''),
                        'last_name_th' => trim($row['lastname'] ?? ''),
                        'first_name_en' => trim($row['firstname_eng'] ?? ''),
                        'last_name_en' => trim($row['lastname_eng'] ?? ''),
                        'title' => trim($row['title'] ?? ''),
                        'email' => !empty(trim($row['email'] ?? ''))
                            ? trim($row['email'])
                            : $sso . '@kku.ac.th',
                        'password' => bcrypt($sso),
                        'workline' => trim($row['workline'] ?? ''),
                        'department' => $department,
                        'division' => $division,
                        'job' => $job,
                        'job_family' => $jobFamily,
                        'position' => $cleanImportValue('posi'),
                        'level' => trim($row['level'] ?? ''),
                        'level_id' => $level->id,
                        'position_id' => $position?->id,
                        'role_id' => self::ROLE_IDS[$roleKey],
                        'role_key' => $roleKey,
                        'is_active' => true,
                        'division_id' => $divisionModel?->id,
                    ],
                );

                $imported++;
            }
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'นำเข้าข้อมูลสำเร็จ '.$imported.' รายการ'
                .($skipped ? ' (ข้าม '.$skipped.' รายการที่ไม่มี ID)' : ''),
        ]);
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
            'division' => ['nullable', 'string', 'max:255'],
            'job' => ['nullable', 'string', 'max:255'],
            'job_family' => ['nullable', 'string', 'max:255'],
            'p' => ['nullable', 'string', 'max:120'],
            'l' => ['nullable', 'string', 'max:120'],
            'r' => ['required', Rule::in(array_keys(self::ROLE_IDS))],
            'sup' => ['nullable', 'string', 'max:255'],
            'evaluator2' => ['nullable', 'string', 'max:255'],
            'manage_hierarchy' => ['boolean'],
            'act' => ['boolean'],
        ], [
            'ph.regex' => 'กรุณากรอกเบอร์โทรศัพท์ในรูปแบบ 0xx-xxx-xxxx',
        ]);
    }

    private function userAttributes(array $data): array
    {
        $roleKey = match ($data['r']) {
            'head', 'manager_dept' => 'dept_head',
            'user' => 'employee',
            'manager' => 'dean',
            default => $data['r'],
        };
        $name = trim($data['fn'].' '.$data['ln']);

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
            'division' => $data['division'] ?? null,
            'job' => $data['job'] ?? null,
            'job_family' => $data['job_family'] ?? null,
            'position' => $data['p'] ?? null,
            'level' => $data['l'] ?? null,
            'role_id' => self::ROLE_IDS[$roleKey],
            'role_key' => $roleKey,
            'is_active' => $data['act'] ?? true,
        ];

        if ($data['manage_hierarchy'] ?? false) {
            $attributes['supervisor'] = $data['sup'] ?? null;
            $attributes['evaluator2'] = $data['evaluator2'] ?? null;
            $attributes['supervisor_id_1'] = $this->userIdFromDisplayName($data['sup'] ?? null);
            $attributes['supervisor_id_2'] = $this->userIdFromDisplayName($data['evaluator2'] ?? null);
        }

        return $attributes;
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

    private function canonicalImportRoleKey(string $role): string
    {
        return match ($role) {
            'admin' => 'admin',
            'hr' => 'hr',
            'supervisor', 'manager_dept', 'dept_head', 'head' => 'dept_head',
            'manager', 'dean' => 'dean',
            'user', 'employee' => 'employee',
            default => 'employee',
        };
    }

    private function cleanImportValue(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '-'], true) ? '' : $value;
    }

}
