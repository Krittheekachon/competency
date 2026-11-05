<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StructureController extends Controller
{
    public function storePositionTitle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workline_id' => ['required_without:workline_type', 'nullable', 'integer', 'exists:worklines,id'],
            'workline_type' => ['nullable', Rule::in(['academic', 'support', 'management'])],
            'name' => ['required', 'string', 'max:255'],
            'position_type_name' => ['nullable', 'string', 'max:255'],
            'placement_scope' => ['nullable', Rule::in(['workline'])],
        ]);
        $normalizedName = $this->normalizeCatalogName($data['name']);
        $normalizedType = $this->normalizeCatalogName($data['position_type_name'] ?? null);
        $worklineId = $data['workline_id'] ?? null;
        $worklineType = $worklineId
            ? app(\App\Services\SsoWorklineNormalizer::class)->structureType(DB::table('worklines')->where('id', $worklineId)->value('name'))
            : ($data['workline_type'] ?? null);
        if (($data['placement_scope'] ?? null) === 'workline' && (! $worklineId || $worklineType !== 'management')) {
            throw ValidationException::withMessages(['workline_id' => 'เพิ่มตำแหน่งระดับสายงานได้เฉพาะสายบริหารเท่านั้น']);
        }

        $exists = DB::table('position_titles')
            ->where('workline_id', $worklineId)
            ->when(! $worklineId, fn ($q) => $q->where('workline_type', $worklineType))
            ->where('normalized_name', $normalizedName)
            ->where('normalized_position_type_name', $normalizedType)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'มีตำแหน่งนี้ในรายการตำแหน่งทั้งหมดแล้ว',
            ]);
        }

        DB::table('position_titles')->insert([
            'workline_id' => $worklineId,
            'workline_type' => $worklineType,
            'name' => trim($data['name']),
            'normalized_name' => $normalizedName,
            'position_type_name' => filled($data['position_type_name'] ?? null) ? trim($data['position_type_name']) : null,
            'normalized_position_type_name' => $normalizedType,
            'source' => 'manual',
            'is_workline_position' => ($data['placement_scope'] ?? null) === 'workline',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'เพิ่มตำแหน่งในรายการตำแหน่งทั้งหมดแล้ว');
    }

    public function storeWorklinePosition(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workline_id' => ['required', 'integer', 'exists:worklines,id'],
            'position_title_id' => ['required', 'integer', 'exists:position_titles,id'],
        ]);
        DB::transaction(function () use ($data): void {
            $title = DB::table('position_titles')->where('id', $data['position_title_id'])->lockForUpdate()->first();
            abort_unless($title, 404);
            $type = app(\App\Services\SsoWorklineNormalizer::class)->structureType(DB::table('worklines')->where('id', $data['workline_id'])->value('name'));
            if ($type !== 'management' || (int) $title->workline_id !== (int) $data['workline_id']) {
                throw ValidationException::withMessages(['position_title_id' => 'กรุณาเลือกตำแหน่งจากทะเบียนของสายบริหารนี้']);
            }
            if ($title->is_workline_position) {
                throw ValidationException::withMessages(['position_title_id' => 'เพิ่มตำแหน่งนี้ในระดับสายงานแล้ว']);
            }
            DB::table('position_titles')->where('id', $title->id)->update(['is_workline_position' => true, 'updated_at' => now()]);
        });
        return back()->with('success', 'เพิ่มตำแหน่งจากทะเบียนในระดับสายงานแล้ว');
    }

    public function destroyWorklinePosition(Request $request): RedirectResponse
    {
        $data = $request->validate(['position_title_id' => ['required', 'integer', 'exists:position_titles,id']]);
        DB::transaction(function () use ($data): void {
            $title = DB::table('position_titles')->where('id', $data['position_title_id'])->lockForUpdate()->first();
            abort_unless($title && $title->is_workline_position, 404);
            if (DB::table('users')->where('position_title_id', $title->id)->whereNull('position_id')->exists()) {
                throw ValidationException::withMessages(['position_title_id' => 'ยังมีบุคลากรใช้ตำแหน่งระดับสายงานนี้ กรุณาจัดสังกัดก่อนนำออก']);
            }
            DB::table('position_titles')->where('id', $title->id)->update(['is_workline_position' => false, 'updated_at' => now()]);
        });
        return back()->with('success', 'นำตำแหน่งออกจากระดับสายงานแล้ว โดยยังอยู่ในตำแหน่งทั้งหมด');
    }

    public function updatePositionTitle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_title_id' => ['required', 'integer', 'exists:position_titles,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $name = trim($data['name']);
        $normalized = $this->normalizeCatalogName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages(['name' => 'กรุณาระบุชื่อตำแหน่ง']);
        }
        try {
            DB::transaction(function () use ($data, $name, $normalized): void {
                $title = DB::table('position_titles')->where('id', $data['position_title_id'])->lockForUpdate()->first();
                abort_unless($title, 404);
                if (DB::table('position_titles')->where('id', '!=', $title->id)
                    ->where('workline_id', $title->workline_id)
                    ->when(! $title->workline_id, fn ($q) => $q->where('workline_type', $title->workline_type))
                    ->where('normalized_name', $normalized)
                    ->where('normalized_position_type_name', $title->normalized_position_type_name)->exists()) {
                    throw ValidationException::withMessages(['name' => 'มีตำแหน่งชื่อนี้ในสายงานเดียวกันแล้ว']);
                }
                $placements = DB::table('positions')->where('position_title_id', $title->id)->lockForUpdate()->get();
                foreach ($placements as $placement) {
                    if (DB::table('positions')->where('id', '!=', $placement->id)
                        ->where('job_family_id', $placement->job_family_id)
                        ->where('support_unit_id', $placement->support_unit_id)->where('name', $name)->exists()) {
                        throw ValidationException::withMessages(['name' => 'มีตำแหน่งชื่อนี้ในหน่วยงานที่ใช้งานอยู่แล้ว']);
                    }
                }
                DB::table('position_titles')->where('id', $title->id)->update([
                    'name' => $name, 'normalized_name' => $normalized, 'updated_at' => now(),
                ]);
                DB::table('positions')->where('position_title_id', $title->id)->update(['name' => $name, 'updated_at' => now()]);
                DB::table('users')->where(function ($q) use ($title, $placements): void {
                    $q->where('position_title_id', $title->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('position_title_id')->whereIn('position_id', $placements->pluck('id')));
                })->update(['position' => $name, 'updated_at' => now()]);
                // Captured round profiles and SSO incoming profiles retain their original values.
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['name' => 'มีตำแหน่งชื่อนี้อยู่แล้ว กรุณาตรวจสอบรายการอีกครั้ง']);
        }
        return back()->with('success', 'แก้ไขชื่อตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyPositionTitle(Request $request): RedirectResponse
    {
        $data = $request->validate(['position_title_id' => ['required', 'integer', 'exists:position_titles,id']]);
        DB::transaction(function () use ($data): void {
            $title = DB::table('position_titles')->where('id', $data['position_title_id'])->lockForUpdate()->first();
            abort_unless($title, 404);
            foreach ([
                'positions' => 'สังกัดหน่วยงาน', 'users' => 'บัญชีบุคลากร',
                'user_round_profiles' => 'ข้อมูลประจำรอบ', 'position_competencies' => 'การมอบสมรรถนะ',
                'position_fc_selection_rules' => 'จำนวน FC', 'position_fc_unit_rules' => 'เกณฑ์ FC ของหน่วย',
                'fc_topic_selections' => 'รายการเลือก FC',
            ] as $table => $label) {
                if (DB::table($table)->where('position_title_id', $title->id)->exists()) {
                    throw ValidationException::withMessages(['position_title_id' => "ไม่สามารถลบตำแหน่งได้ เนื่องจากยังมี{$label}อ้างอิงอยู่"]);
                }
            }
            DB::table('position_title_aliases')->where('position_title_id', $title->id)->delete();
            DB::table('position_titles')->where('id', $title->id)->delete();
        });
        return back()->with('success', 'ลบตำแหน่งจากรายการตำแหน่งทั้งหมดแล้ว');
    }

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

        return back()->with('success', 'บันทึกสายงานเรียบร้อยแล้ว');
    }

    public function assignPositionTitleWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_title_id' => ['required', 'integer', 'exists:position_titles,id'],
            'workline_id' => ['required', 'integer', 'exists:worklines,id'],
        ]);
        DB::transaction(function () use ($data): void {
            $title = DB::table('position_titles')->where('id', $data['position_title_id'])->lockForUpdate()->first();
            $placements = DB::table('positions as p')->join('job_families as f', 'p.job_family_id', '=', 'f.id')
                ->where('p.position_title_id', $title->id)->pluck('f.workline_id');
            if ($placements->contains(fn ($id) => (int) $id !== (int) $data['workline_id'])) {
                throw ValidationException::withMessages(['workline_id' => 'ตำแหน่งนี้ถูกใช้ในสายงานอื่นอยู่ กรุณาตรวจสังกัดก่อนเปลี่ยนสายงาน']);
            }
            if (DB::table('position_titles')->where('id', '!=', $title->id)->where('workline_id', $data['workline_id'])
                ->where('normalized_name', $title->normalized_name)->where('normalized_position_type_name', $title->normalized_position_type_name)->exists()) {
                throw ValidationException::withMessages(['workline_id' => 'มีตำแหน่งนี้ในสายงานที่เลือกแล้ว']);
            }
            $type = app(\App\Services\SsoWorklineNormalizer::class)->structureType(DB::table('worklines')->where('id', $data['workline_id'])->value('name'));
            DB::table('position_titles')->where('id', $title->id)->update(['workline_id' => $data['workline_id'], 'workline_type' => $type, 'updated_at' => now()]);
        });
        return back()->with('success', 'กำหนดสายงานของตำแหน่งแล้ว');
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

        $this->syncUserWorklineName($data['old_name'], $data['name']);

        return back()->with('success', 'อัปเดตสายงานเรียบร้อยแล้ว');
    }

    public function destroyWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'exists:worklines,name'],
        ]);

        DB::transaction(function () use ($data): void {
            $nodeId = DB::table('worklines')->where('name', $data['name'])->lockForUpdate()->value('id');
            if (DB::table('position_titles')->where('workline_id', $nodeId)->exists()) {
                throw ValidationException::withMessages(['name' => 'สายงานนี้ยังมีตำแหน่งในทะเบียนอ้างอิงอยู่ กรุณาจัดการตำแหน่งก่อนลบ']);
            }
            app(\App\Services\OrganizationChartService::class)->assertDeletable('workline:'.$nodeId);
            DB::table('organization_chart_assignments')->where('node_key', 'workline:'.$nodeId)->delete();

            DB::table('worklines')->where('name', $data['name'])->delete();
        });

        return back()->with('success', 'ลบสายงานเรียบร้อยแล้ว');
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

        return back()->with('success', 'บันทึกกลุ่มงานเรียบร้อยแล้ว');
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

        $this->syncUserDepartmentPrefix($data['workline_name'] ?? null, $data['old_name'], $data['name']);

        return back()->with('success', 'อัปเดตกลุ่มงานเรียบร้อยแล้ว');
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

        DB::transaction(function () use ($data, $worklineId): void {
            $nodeId = DB::table('job_families')->where('workline_id', $worklineId)->where('name', $data['name'])->lockForUpdate()->value('id');
            app(\App\Services\OrganizationChartService::class)->assertDeletable('family:'.$nodeId);
            DB::table('organization_chart_assignments')->where('node_key', 'family:'.$nodeId)->delete();

            DB::table('job_families')
                ->where('workline_id', $worklineId)
                ->where('name', $data['name'])
                ->delete();
        });

        return back()->with('success', 'ลบกลุ่มงานเรียบร้อยแล้ว');
    }

    public function storePosition(Request $request): RedirectResponse
    {
        if ($request->filled('position_title_id')) {
            $request->merge(['name' => DB::table('position_titles')->where('id', $request->input('position_title_id'))->value('name')]);
        }
        if ($request->filled('unit_name')) {
            return $this->storeSupportPosition($request);
        }

        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );

        $worklineName = DB::table('job_families')->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('job_families.id', $jobFamilyId)->value('worklines.name');
        $worklineType = app(\App\Services\SsoWorklineNormalizer::class)->structureType($worklineName);

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
                Rule::unique('positions', 'name')->where(fn ($query) => $query
                    ->where('job_family_id', $jobFamilyId)
                    ->whereNull('support_unit_id')),
            ],
            'position_title_id' => ['nullable', 'integer', Rule::exists('position_titles', 'id')->where(fn ($q) => $q
                ->where('workline_id', DB::table('job_families')->where('id', $jobFamilyId)->value('workline_id'))
                ->orWhere(fn ($legacy) => $legacy->whereNull('workline_id')->where('workline_type', $worklineType)))],
        ]);

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => null,
            'position_title_id' => $data['position_title_id'] ?? null,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกตำแหน่งเรียบร้อยแล้ว');
    }

    public function updatePosition(Request $request): RedirectResponse
    {
        if ($request->filled('unit_name')) {
            return $this->updateSupportPosition($request);
        }

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

        $this->syncUserPositionName(
            $data['workline_name'] ?? null,
            $data['job_family_name'],
            $data['old_name'],
            $data['name']
        );

        return back()->with('success', 'อัปเดตตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyPosition(Request $request): RedirectResponse
    {
        if ($request->filled('unit_name')) {
            return $this->destroySupportPosition($request);
        }

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

        $this->deleteUnusedPositions(DB::table('positions')
            ->where('job_family_id', $jobFamilyId)->whereNull('support_unit_id')->where('name', $data['name']));

        return back()->with('success', 'ลบตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeSupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:support_departments,name'],
        ]);

        DB::table('support_departments')->insert([
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกฝ่ายเรียบร้อยแล้ว');
    }

    public function updateSupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => ['required', 'string', 'max:255', Rule::unique('support_departments', 'name')->ignore($request->old_name, 'name')],
        ]);

        DB::transaction(function () use ($data): void {
            DB::table('support_departments')
                ->where('name', $data['old_name'])
                ->update(['name' => $data['name'], 'updated_at' => now()]);

            $this->syncUserDepartmentPrefix(null, $data['old_name'], $data['name']);
        });

        return back()->with('success', 'อัปเดตฝ่ายเรียบร้อยแล้ว');
    }

    public function destroySupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'exists:support_departments,name'],
        ]);

        DB::transaction(function () use ($data): void {
            $nodeId = DB::table('support_departments')->where('name', $data['name'])->lockForUpdate()->value('id');
            app(\App\Services\OrganizationChartService::class)->assertDeletable('department:'.$nodeId);
            DB::table('organization_chart_assignments')->where('node_key', 'department:'.$nodeId)->delete();

            DB::table('support_departments')->where('name', $data['name'])->delete();
        });

        return back()->with('success', 'ลบฝ่ายเรียบร้อยแล้ว');
    }

    public function storeSupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
        ]);

        DB::table('support_works')->insert([
            'support_department_id' => $supportDepartmentId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกงานเรียบร้อยแล้ว');
    }

    public function updateSupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_works', 'name')
                    ->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId))
                    ->ignore(
                        DB::table('support_works')
                            ->where('support_department_id', $supportDepartmentId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::transaction(function () use ($data, $supportDepartmentId): void {
            DB::table('support_works')
                ->where('support_department_id', $supportDepartmentId)
                ->where('name', $data['old_name'])
                ->update(['name' => $data['name'], 'updated_at' => now()]);

            $this->syncUserDepartmentPrefix(null, $data['division_name'].' > '.$data['old_name'], $data['division_name'].' > '.$data['name']);
        });

        return back()->with('success', 'อัปเดตงานเรียบร้อยแล้ว');
    }

    public function destroySupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => [
                'required',
                'string',
                Rule::exists('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
        ]);

        DB::transaction(function () use ($data, $supportDepartmentId): void {
            $nodeId = DB::table('support_works')->where('support_department_id', $supportDepartmentId)->where('name', $data['name'])->lockForUpdate()->value('id');
            app(\App\Services\OrganizationChartService::class)->assertDeletable('work:'.$nodeId);
            DB::table('organization_chart_assignments')->where('node_key', 'work:'.$nodeId)->delete();

            DB::table('support_works')
                ->where('support_department_id', $supportDepartmentId)
                ->where('name', $data['name'])
                ->delete();
        });

        return back()->with('success', 'ลบงานเรียบร้อยแล้ว');
    }

    public function storeSupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
        ]);

        DB::table('support_units')->insert([
            'support_work_id' => $supportWorkId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกหน่วยงานเรียบร้อยแล้ว');
    }

    public function updateSupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_units', 'name')
                    ->where(fn ($query) => $query->where('support_work_id', $supportWorkId))
                    ->ignore(
                        DB::table('support_units')
                            ->where('support_work_id', $supportWorkId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::transaction(function () use ($data, $supportWorkId): void {
            DB::table('support_units')
                ->where('support_work_id', $supportWorkId)
                ->where('name', $data['old_name'])
                ->update(['name' => $data['name'], 'updated_at' => now()]);

            $this->syncUserDepartmentPrefix(null, $data['division_name'].' > '.$data['work_name'].' > '.$data['old_name'], $data['division_name'].' > '.$data['work_name'].' > '.$data['name']);
        });

        return back()->with('success', 'อัปเดตหน่วยงานเรียบร้อยแล้ว');
    }

    public function destroySupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'name' => [
                'required',
                'string',
                Rule::exists('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
        ]);

        DB::transaction(function () use ($data, $supportWorkId): void {
            $nodeId = DB::table('support_units')->where('support_work_id', $supportWorkId)->where('name', $data['name'])->lockForUpdate()->value('id');
            app(\App\Services\OrganizationChartService::class)->assertDeletable('unit:'.$nodeId);
            DB::table('organization_chart_assignments')->where('node_key', 'unit:'.$nodeId)->delete();

            DB::table('support_units')
                ->where('support_work_id', $supportWorkId)
                ->where('name', $data['name'])
                ->delete();
        });

        return back()->with('success', 'ลบหน่วยงานเรียบร้อยแล้ว');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
            'expected_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        DB::table('levels')->insert([
            'workline_id' => $this->worklineId($data['workline_name'] ?? null),
            'job_family_id' => $jobFamilyId,
            'name' => $data['name'],
            'expected_level' => $data['expected_level'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function updateLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')
                    ->where(fn ($query) => $query
                        ->where('workline_id', $worklineId)
                        ->where('job_family_id', $jobFamilyId))
                    ->ignore($request->old_name, 'name'),
            ],
            'expected_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        DB::table('levels')
            ->where('workline_id', $this->worklineId($data['workline_name'] ?? null))
            ->where('job_family_id', $jobFamilyId)
            ->where('name', $data['old_name'])
            ->update([
                'name' => $data['name'],
                'expected_level' => $data['expected_level'] ?? null,
                'updated_at' => now(),
            ]);

        $this->syncUserLevelName(
            $data['workline_name'] ?? null,
            $data['job_family_name'] ?? null,
            $data['old_name'],
            $data['name']
        );

        return back()->with('success', 'อัปเดตระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
        ]);

        $levelId = DB::table('levels')
            ->where('workline_id', $worklineId)
            ->whereNull('job_family_id')
            ->where('name', $data['name'])
            ->value('id');

        $isInUse = DB::table('users')->where('level_id', $levelId)->exists()
            || (Schema::hasTable('user_round_profiles')
                && DB::table('user_round_profiles')->where('level_id', $levelId)->exists());

        if ($isInUse) {
            throw ValidationException::withMessages([
                'name' => 'ไม่สามารถลบระดับตำแหน่งที่ยังมีผู้ใช้หรือข้อมูลการประเมินอ้างอิงอยู่ได้',
            ]);
        }

        DB::table('levels')
            ->where('id', $levelId)
            ->whereNull('job_family_id')
            ->delete();

        return back()->with('success', 'ลบระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    public function updateLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    public function destroyLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    private function worklineId(?string $name): ?int
    {
        if (!$name) {
            return null;
        }

        return DB::table('worklines')->where('name', $name)->value('id');
    }

    private function syncUserWorklineName(string $oldName, string $newName): void
    {
        DB::table('users')
            ->where('workline', $oldName)
            ->update([
                'workline' => $newName,
                'updated_at' => now(),
            ]);
    }

    private function normalizeCatalogName(?string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', trim((string) $value))));
    }

    private function syncUserDepartmentPrefix(?string $worklineName, string $oldName, string $newName): void
    {
        $query = DB::table('users')
            ->select('id', 'department')
            ->where(function ($query) use ($oldName) {
                $query->where('department', $oldName)
                    ->orWhere('department', 'like', $oldName.' > %');
            });

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        foreach ($query->get() as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'department' => $newName.substr($user->department, strlen($oldName)),
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncUserPositionName(?string $worklineName, string $jobFamilyName, string $oldName, string $newName): void
    {
        $query = DB::table('users')
            ->where('position', $oldName)
            ->where(function ($query) use ($jobFamilyName) {
                $query->where('department', $jobFamilyName)
                    ->orWhere('department', 'like', $jobFamilyName.' > %');
            });

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        $query->update([
            'position' => $newName,
            'updated_at' => now(),
        ]);
    }

    private function syncUserLevelName(?string $worklineName, ?string $jobFamilyName, string $oldName, string $newName): void
    {
        $query = DB::table('users')->where('level', $oldName);

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        if ($jobFamilyName) {
            $query->where(function ($query) use ($jobFamilyName) {
                $query->where('department', $jobFamilyName)
                    ->orWhere('department', 'like', $jobFamilyName.' > %');
            });
        }

        $query->update([
            'level' => $newName,
            'updated_at' => now(),
        ]);
    }

    private function jobFamilyId(string $name, ?string $worklineName = null): int
    {
        $query = DB::table('job_families')->where('name', $name);

        if ($worklineName) {
            $query->where('workline_id', $this->worklineId($worklineName));
        }

        return (int) $query->value('id');
    }

    private function supportWorkId(string $name, string $departmentName): int
    {
        return (int) DB::table('support_works')
            ->where('support_department_id', $this->supportDepartmentId($departmentName))
            ->where('name', $name)
            ->value('id');
    }

    private function supportDepartmentId(string $name): int
    {
        return (int) DB::table('support_departments')->where('name', $name)->value('id');
    }

    private function storeSupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
            'position_title_id' => ['nullable', 'integer', Rule::exists('position_titles', 'id')->where(fn ($q) => $q
                ->where('workline_id', $this->worklineId('สายสนับสนุน'))
                ->orWhere(fn ($legacy) => $legacy->whereNull('workline_id')->where('workline_type', 'support')))],
        ]);

        $supportWorklineId = $this->worklineId('สายสนับสนุน');
        $jobFamilyId = DB::table('job_families')->where('workline_id', $supportWorklineId)->orderBy('id')->value('id');
        if (! $jobFamilyId) {
            $jobFamilyId = DB::table('job_families')->insertGetId([
                'workline_id' => $supportWorklineId,
                'name' => 'ตำแหน่งสายสนับสนุน',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => $unitId,
            'position_title_id' => $data['position_title_id'] ?? null,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกตำแหน่งเรียบร้อยแล้ว');
    }

    private function updateSupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'old_name' => ['required', 'string', Rule::exists('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))->ignore(DB::table('positions')->where('support_unit_id', $unitId)->where('name', $request->old_name)->value('id'))],
        ]);

        DB::table('positions')->where('support_unit_id', $unitId)->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);
        $this->syncUserSupportPositionName(
            $data['division_name'],
            $data['work_name'],
            $data['unit_name'],
            $data['old_name'],
            $data['name'],
        );

        return back()->with('success', 'อัปเดตตำแหน่งเรียบร้อยแล้ว');
    }

    private function syncUserSupportPositionName(
        string $divisionName,
        string $workName,
        string $unitName,
        string $oldName,
        string $newName,
    ): void {
        DB::table('users')
            ->whereIn('workline', ['สายสนับสนุน', 'สายงานสนับสนุน'])
            ->where('department', implode(' > ', [$divisionName, $workName, $unitName]))
            ->where('position', $oldName)
            ->update([
                'position' => $newName,
                'updated_at' => now(),
            ]);
    }

    private function destroySupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'name' => ['required', 'string', Rule::exists('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
        ]);
        $this->deleteUnusedPositions(DB::table('positions')->where('support_unit_id', $unitId)->where('name', $data['name']));

        return back()->with('success', 'ลบตำแหน่งเรียบร้อยแล้ว');
    }

    private function deleteUnusedPositions(\Illuminate\Database\Query\Builder $query): void
    {
        DB::transaction(function () use ($query): void {
            foreach ($query->lockForUpdate()->get() as $position) {
                foreach (['users', 'user_round_profiles', 'position_competencies', 'position_fc_selection_rules', 'fc_topic_selections'] as $table) {
                    if (DB::table($table)->where('position_id', $position->id)->exists()) {
                        throw ValidationException::withMessages(['name' => 'ยังมีบุคลากรหรือข้อมูลประเมินอ้างอิงตำแหน่งนี้ กรุณาจัดการข้อมูลอ้างอิงก่อนนำออก']);
                    }
                }
                DB::table('positions')->where('id', $position->id)->delete();
            }
        });
    }

    private function supportUnitId(Request $request): int
    {
        return (int) DB::table('support_units')
            ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
            ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
            ->where('support_departments.name', $request->string('division_name')->toString())
            ->where('support_works.name', $request->string('work_name')->toString())
            ->where('support_units.name', $request->string('unit_name')->toString())
            ->value('support_units.id');
    }
}
