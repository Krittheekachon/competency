<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function storeWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:worklines,name'],
        ]);

        DB::table('worklines')->insert([
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return redirect()->route('admin.dashboard')->with('success', 'บันทึกสายงานเรียบร้อยแล้ว');
    }

    public function updateWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_name' => ['required', 'string', 'exists:worklines,name'],
            'name' => ['required', 'string', 'max:255', Rule::unique('worklines', 'name')->ignore($request->old_name, 'name')],
        ]);

        DB::table('worklines')
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.dashboard')->with('success', 'อัปเดตสายงานเรียบร้อยแล้ว');
    }

    public function destroyWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'exists:worklines,name'],
        ]);

        DB::table('worklines')->where('name', $data['name'])->delete();

        return redirect()->route('admin.dashboard')->with('success', 'ลบสายงานเรียบร้อยแล้ว');
    }

    public function storeJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('job_families')->insert([
            'workline_id' => $worklineId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'บันทึกกลุ่มงานเรียบร้อยแล้ว');
    }

    public function updateJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('job_families', 'name')
                    ->where(fn ($query) => $query->where('workline_id', $worklineId))
                    ->ignore(
                        DB::table('job_families')
                            ->where('workline_id', $worklineId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::table('job_families')
            ->where('workline_id', $worklineId)
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.dashboard')->with('success', 'อัปเดตกลุ่มงานเรียบร้อยแล้ว');
    }

    public function destroyJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('job_families')
            ->where('workline_id', $worklineId)
            ->where('name', $data['name'])
            ->delete();

        return redirect()->route('admin.dashboard')->with('success', 'ลบกลุ่มงานเรียบร้อยแล้ว');
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')->where(fn ($query) => $query->where('job_family_id', $jobFamilyId)),
            ],
        ]);

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'บันทึกตำแหน่งเรียบร้อยแล้ว');
    }

    public function updatePosition(Request $request): RedirectResponse
    {
        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'old_name' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        DB::table('positions')
            ->where('job_family_id', $jobFamilyId)
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.dashboard')->with('success', 'อัปเดตตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyPosition(Request $request): RedirectResponse
    {
        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'name' => ['required', 'string'],
        ]);

        DB::table('positions')
            ->where('job_family_id', $jobFamilyId)
            ->where('name', $data['name'])
            ->delete();

        return redirect()->route('admin.dashboard')->with('success', 'ลบตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('levels')->insert([
            'workline_id' => $this->worklineId($data['workline_name'] ?? null),
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'บันทึกระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function updateLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')
                    ->where(fn ($query) => $query->where('workline_id', $worklineId))
                    ->ignore($request->old_name, 'name'),
            ],
        ]);

        DB::table('levels')
            ->where('workline_id', $this->worklineId($data['workline_name'] ?? null))
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.dashboard')->with('success', 'อัปเดตระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('levels')
            ->where('workline_id', $this->worklineId($data['workline_name'] ?? null))
            ->where('name', $data['name'])
            ->delete();

        return redirect()->route('admin.dashboard')->with('success', 'ลบระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeLearningMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:learning_method_types,key'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::table('learning_method_types')->insert([
            'key' => $data['key'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'sort_order' => DB::table('learning_method_types')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'บันทึกประเภทการเรียนรู้เรียบร้อยแล้ว');
    }

    public function updateLearningMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_key' => ['required', 'string', 'exists:learning_method_types,key'],
            'key' => ['required', 'string', 'max:255', Rule::unique('learning_method_types', 'key')->ignore($request->old_key, 'key')],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::table('learning_method_types')
            ->where('key', $data['old_key'])
            ->update([
                'key' => $data['key'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()->route('admin.dashboard')->with('success', 'อัปเดตประเภทการเรียนรู้เรียบร้อยแล้ว');
    }

    public function destroyLearningMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'exists:learning_method_types,key'],
        ]);

        DB::table('learning_method_types')->where('key', $data['key'])->delete();

        return redirect()->route('admin.dashboard')->with('success', 'ลบประเภทการเรียนรู้เรียบร้อยแล้ว');
    }

    private function worklineId(?string $name): ?int
    {
        if (!$name) {
            return null;
        }

        return DB::table('worklines')->where('name', $name)->value('id');
    }

    private function jobFamilyId(string $name, ?string $worklineName = null): int
    {
        $query = DB::table('job_families')->where('name', $name);

        if ($worklineName) {
            $query->where('workline_id', $this->worklineId($worklineName));
        }

        return (int) $query->value('id');
    }

    private function redirectToAdminDashboard(Request $request, string $message): RedirectResponse
    {
        $adminPage = $request->string('admin_page')->toString();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $message)
            ->with('adminPage', $adminPage ?: 'admin-org-structure');
    }

    public function storeSupportDept(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:support_departments,name'],
        ]);

        DB::table('support_departments')->insert([
            'name'       => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectToAdminDashboard($request, 'บันทึกฝ่ายเรียบร้อยแล้ว');
    }

    public function storeSupportWork(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dept_name' => ['required', 'string', 'exists:support_departments,name'],
            'work_name' => ['required', 'string', 'max:255'],
        ]);

        $deptId = DB::table('support_departments')
            ->where('name', $data['dept_name'])
            ->value('id');

        if (DB::table('support_works')
            ->where('support_department_id', $deptId)
            ->where('name', $data['work_name'])
            ->exists()) {
            return back()->withErrors(['work_name' => "มีงาน \"{$data['work_name']}\" ในฝ่ายนี้แล้ว"]);
        }

        DB::table('support_works')->insert([
            'support_department_id' => $deptId,
            'name'                  => $data['work_name'],
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return $this->redirectToAdminDashboard($request, 'บันทึกงานเรียบร้อยแล้ว');
    }

    public function storeSupportUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dept_name' => ['required', 'string', 'exists:support_departments,name'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string', 'max:255'],
        ]);

        $deptId = DB::table('support_departments')
            ->where('name', $data['dept_name'])
            ->value('id');

        $workId = DB::table('support_works')
            ->where('support_department_id', $deptId)
            ->where('name', $data['work_name'])
            ->value('id');

        if (!$workId) {
            return back()->withErrors(['work_name' => 'ไม่พบงานที่ระบุ']);
        }

        if (DB::table('support_units')
            ->where('support_work_id', $workId)
            ->where('name', $data['unit_name'])
            ->exists()) {
            return back()->withErrors(['unit_name' => "มีหน่วย \"{$data['unit_name']}\" ในงานนี้แล้ว"]);
        }

        DB::table('support_units')->insert([
            'support_work_id' => $workId,
            'name'            => $data['unit_name'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $this->redirectToAdminDashboard($request, 'บันทึกหน่วยเรียบร้อยแล้ว');
    }
}
