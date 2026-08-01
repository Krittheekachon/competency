<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReviewerTemplateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReviewerChainTemplateController extends Controller
{
    public function __construct(
        private ReviewerTemplateResolver $reviewerTemplateResolver,
    )
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'chain_type' => ['nullable', 'in:assessment'],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['required', 'integer', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $reviewerIds = collect($data['reviewer_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $assignmentUserIds = collect($data['assignment_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($reviewerIds->count() !== count($data['reviewer_ids'])) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'ไม่สามารถเลือกผู้ประเมินซ้ำในลำดับเดียวกันได้',
            ]);
        }

        $activeReviewerCount = User::query()
            ->whereIn('id', $reviewerIds)
            ->where('is_active', true)
            ->count();
        $activeAssignmentCount = User::query()
            ->whereIn('id', $assignmentUserIds)
            ->where('is_active', true)
            ->count();

        if ($activeReviewerCount !== $reviewerIds->count() || $activeAssignmentCount !== $assignmentUserIds->count()) {
            throw ValidationException::withMessages([
                'users' => 'กรุณาเลือกเฉพาะผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        foreach ($assignmentUserIds as $userId) {
            if ($reviewerIds->contains((int) $userId)) {
                throw ValidationException::withMessages([
                    'assignment_user_ids' => 'ผู้ใช้ที่ถูกประเมินต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง',
                ]);
            }
        }

        DB::transaction(function () use ($data, $reviewerIds, $assignmentUserIds): void {
            $now = now();
            $templatePayload = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
                $templatePayload['chain_type'] = 'assessment';
            }

            $templateId = (int) DB::table('reviewer_chain_templates')->insertGetId($templatePayload);

            DB::table('reviewer_chain_template_steps')->insert($reviewerIds
                ->map(fn (int $reviewerId, int $index): array => [
                    'template_id' => $templateId,
                    'step_order' => $index + 1,
                    'resolver_type' => 'fixed_user',
                    'role_key' => null,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());

            if ($assignmentUserIds->isNotEmpty()) {
                DB::table('reviewer_chain_template_assignments')->insert($assignmentUserIds
                    ->map(fn (int $userId): array => [
                        'template_id' => $templateId,
                        'scope_type' => 'user',
                        'scope_value' => null,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());
            }

            foreach ($assignmentUserIds as $userId) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'reviewer_template_id' => $templateId,
                        'supervisor_id_1' => $reviewerIds->get(0),
                        'supervisor_id_2' => $reviewerIds->get(1),
                        'supervisor_id_3' => $reviewerIds->get(2),
                        'updated_at' => $now,
                    ]);

                $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $userId);
                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $deleteQuery->where('chain_type', 'assessment');
                }
                $deleteQuery->delete();

                DB::table('user_reviewer_steps')->insert($reviewerIds
                    ->map(function (int $reviewerId, int $index) use ($userId, $now): array {
                        $row = [
                            'user_id' => $userId,
                            'step_order' => $index + 1,
                            'reviewer_id' => $reviewerId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                            $row['chain_type'] = 'assessment';
                        }

                        return $row;
                    })
                    ->all());
            }
        });

        return back()->with('success', 'บันทึกลำดับในการประเมินเรียบร้อยแล้ว');
    }

    public function addUsers(Request $request, int $template): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateQuery = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true);
        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $templateQuery->where('chain_type', 'assessment');
        }

        if (! $templateQuery->exists()) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }

        $userIds = collect($data['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($users->count() !== $userIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'กรุณาเลือกเฉพาะผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        DB::transaction(function () use ($template, $userIds, $users): void {
            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                $reviewerIds = collect($this->reviewerTemplateResolver->resolveReviewerIdsForUser($user, $template, 'assessment'))
                    ->filter()
                    ->unique()
                    ->values();

                if ($reviewerIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'user_ids' => "ไม่พบผู้ประเมินสำหรับ {$user->name}",
                    ]);
                }

                if ($reviewerIds->contains((int) $user->id)) {
                    throw ValidationException::withMessages([
                        'user_ids' => "ผู้ใช้ {$user->name} ต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง",
                    ]);
                }

                $this->removeExistingAssessmentAssignments((int) $user->id);
                $this->insertTemplateAssignment($template, (int) $user->id);
                $this->applyReviewerStepsToUser($user, $template, $reviewerIds->all());
            }
        });

        return back()->with('success', 'เพิ่มผู้ใช้ในลำดับการประเมินเรียบร้อยแล้ว');
    }

    public function removeUser(int $template, User $user): RedirectResponse
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateQuery = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true);
        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $templateQuery->where('chain_type', 'assessment');
        }

        if (! $templateQuery->exists()) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }

        DB::transaction(function () use ($template, $user): void {
            DB::table('reviewer_chain_template_assignments')
                ->where('template_id', $template)
                ->where('scope_type', 'user')
                ->where('user_id', $user->id)
                ->delete();

            if ((int) ($user->reviewer_template_id ?? 0) === $template) {
                $this->clearUserAssessmentChain((int) $user->id, $template);
            }
        });

        return back()->with('success', 'ลบผู้ใช้ออกจากลำดับการประเมินเรียบร้อยแล้ว');
    }

    public function destroy(int $template): RedirectResponse
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateQuery = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true);
        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $templateQuery->where('chain_type', 'assessment');
        }

        if (! $templateQuery->exists()) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }

        DB::transaction(function () use ($template): void {
            $affectedUserIds = DB::table('reviewer_chain_template_assignments')
                ->where('template_id', $template)
                ->where('scope_type', 'user')
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->merge(DB::table('users')
                    ->where('reviewer_template_id', $template)
                    ->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($affectedUserIds as $userId) {
                $this->clearUserAssessmentChain($userId, $template);
            }

            DB::table('reviewer_chain_templates')
                ->where('id', $template)
                ->delete();
        });

        return back()->with('success', 'ลบลำดับการประเมินเรียบร้อยแล้ว');
    }

    private function removeExistingAssessmentAssignments(int $userId): void
    {
        $query = DB::table('reviewer_chain_template_assignments')
            ->where('scope_type', 'user')
            ->where('user_id', $userId);

        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $assessmentTemplateIds = DB::table('reviewer_chain_templates')
                ->where('chain_type', 'assessment')
                ->pluck('id');
            $query->whereIn('template_id', $assessmentTemplateIds);
        }

        $query->delete();
    }

    private function clearUserAssessmentChain(int $userId, int $templateId): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->where('reviewer_template_id', $templateId)
            ->update([
                'reviewer_template_id' => null,
                'supervisor_id_1' => null,
                'supervisor_id_2' => null,
                'supervisor_id_3' => null,
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $userId);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', 'assessment');
        }
        $deleteQuery->delete();
    }

    private function insertTemplateAssignment(int $templateId, int $userId): void
    {
        $now = now();

        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'user',
            'scope_value' => null,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function applyReviewerStepsToUser(User $user, int $templateId, array $reviewerIds): void
    {
        $now = now();

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'reviewer_template_id' => $templateId,
                'supervisor_id_1' => $reviewerIds[0] ?? null,
                'supervisor_id_2' => $reviewerIds[1] ?? null,
                'supervisor_id_3' => $reviewerIds[2] ?? null,
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $user->id);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', 'assessment');
        }
        $deleteQuery->delete();

        DB::table('user_reviewer_steps')->insert(collect($reviewerIds)
            ->values()
            ->map(function (int $reviewerId, int $index) use ($user, $now): array {
                $row = [
                    'user_id' => $user->id,
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $row['chain_type'] = 'assessment';
                }

                return $row;
            })
            ->all());
    }
}
