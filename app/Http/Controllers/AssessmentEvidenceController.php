<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AssessmentLevelEvidenceService;
use App\Services\AssessmentMethodResolver;
use App\Services\AssessmentRoundWindow;
use App\Services\FacultyAssessmentPermissionResolver;
use App\Services\NotificationService;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentEvidenceController extends Controller
{
    private const MAX_EVIDENCE_PER_COMPETENCY = 10;

    public function __construct(
        private AssessmentRoundWindow $assessmentRoundWindow,
        private AssessmentMethodResolver $assessmentMethods,
        private AssessmentLevelEvidenceService $evidences,
        private ReviewerChainResolver $reviewerChainResolver,
        private FacultyAssessmentPermissionResolver $facultyPermissions,
        private NotificationService $notifications,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'evidenceLinks' => ['nullable', 'array', 'max:10'],
            'evidenceLinks.*.url' => ['required_with:evidenceLinks', 'url:http,https', 'max:2048'],
            'evidenceLinks.*.description' => ['nullable', 'string', 'max:255'],
            'evidenceFiles' => ['nullable', 'array', 'max:10'],
            'evidenceFiles.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ], [
            'evidenceLinks.*.url.url' => 'กรุณากรอกลิงก์ที่ขึ้นต้นด้วย http:// หรือ https://',
            'evidenceFiles.*.max' => 'ไฟล์หลักฐานต้องมีขนาดไม่เกิน 10 MB',
            'evidenceFiles.*.mimes' => 'รองรับเฉพาะรูปภาพ PDF เอกสาร Office และไฟล์ข้อความ',
        ]);

        $links = $data['evidenceLinks'] ?? [];
        $files = $request->file('evidenceFiles', []);
        if ($links === [] && $files === []) {
            throw ValidationException::withMessages(['evidence' => 'กรุณาแนบไฟล์หรือลิงก์หลักฐานอย่างน้อย 1 รายการ']);
        }

        $assessment = $this->editableAssessment($request, (int) $data['competency_id']);
        $competencyId = (int) $data['competency_id'];
        $existingCount = DB::table('assessment_level_evidences')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->count();
        if ($existingCount + count($links) + count($files) > self::MAX_EVIDENCE_PER_COMPETENCY) {
            throw ValidationException::withMessages([
                'evidence' => 'แนบหลักฐานได้ไม่เกิน '.self::MAX_EVIDENCE_PER_COMPETENCY.' รายการต่อสมรรถนะ',
            ]);
        }

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $assessment, $competencyId, $links, $files, &$storedPaths): void {
                $now = now();

                foreach ($links as $link) {
                    DB::table('assessment_level_evidences')->insert([
                        'public_id' => (string) Str::uuid(),
                        'assessment_id' => $assessment->id,
                        'competency_id' => $competencyId,
                        'kind' => 'link',
                        'url' => $link['url'],
                        'description' => filled($link['description'] ?? null) ? trim($link['description']) : null,
                        'uploaded_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($files as $file) {
                    $path = $file->store('assessment-evidence/'.$request->user()->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['evidenceFiles' => 'ไม่สามารถบันทึกไฟล์หลักฐานได้ กรุณาลองใหม่']);
                    }
                    $storedPaths[] = $path;
                    DB::table('assessment_level_evidences')->insert([
                        'public_id' => (string) Str::uuid(),
                        'assessment_id' => $assessment->id,
                        'competency_id' => $competencyId,
                        'kind' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
                        'storage_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json([
            'evidences' => $this->evidences->payloadsFor((int) $assessment->id, $competencyId),
            'message' => 'แนบหลักฐานเรียบร้อยแล้ว',
        ]);
    }

    public function destroy(Request $request, string $evidence): JsonResponse
    {
        $record = DB::table('assessment_level_evidences')->where('public_id', $evidence)->firstOrFail();
        $assessment = $this->editableAssessment($request, (int) $record->competency_id);
        abort_unless((int) $record->assessment_id === (int) $assessment->id, 403);

        DB::table('assessment_level_evidences')->where('id', $record->id)->delete();
        if ($record->storage_path) {
            Storage::disk('local')->delete($record->storage_path);
        }

        return response()->json([
            'evidences' => $this->evidences->payloadsFor((int) $assessment->id, (int) $record->competency_id),
            'message' => 'ลบหลักฐานเรียบร้อยแล้ว',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
        ]);
        $competencyId = (int) $data['competency_id'];
        $assessment = $this->editableAssessment($request, $competencyId);
        $status = $this->assessmentStatus($assessment, $competencyId);

        if ($status !== 'evidence_required') {
            throw ValidationException::withMessages(['evidence' => 'ผลการประเมินนี้ไม่ได้อยู่ระหว่างรอหลักฐาน']);
        }
        if (! $this->evidences->existsFor((int) $assessment->id, $competencyId)) {
            throw ValidationException::withMessages(['evidence' => 'กรุณาแนบหลักฐานอย่างน้อย 1 รายการก่อนส่ง']);
        }

        $employee = $request->user();
        $employee->setAttribute('assessment_round_id', (int) $assessment->assessment_round_id);
        $competency = DB::table('competencies')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('competencies.id', $competencyId)
            ->first(['competencies.name', 'competency_types.code']);
        // Return to the scorer who asked for the evidence.
        $requestedBy = (int) DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->value('rejected_by');
        $steps = $this->reviewerChainResolver->stepsForUser($employee);
        $scorerStep = ($requestedBy ? $this->reviewerChainResolver->stepForReviewer($employee, $requestedBy) : null)
            ?? $this->facultyPermissions->latestScorerStepBefore($employee, (string) ($competency->code ?? ''), $steps, PHP_INT_MAX)
            ?? 1;
        $nextStatus = $this->reviewerChainResolver->pendingStatusForStep($scorerStep);

        DB::transaction(function () use ($assessment, $competencyId, $nextStatus): void {
            $now = now();
            Assessment::where('id', $assessment->id)
                ->where('status', 'evidence_required')
                ->update(['status' => $nextStatus, 'updated_at' => $now]);
            DB::table('competency_gaps')
                ->where('assessment_id', $assessment->id)
                ->where('competency_id', $competencyId)
                ->where('status', 'evidence_required')
                ->update([
                    'status' => $nextStatus,
                    'rejected_by' => null,
                    'reject_comment' => null,
                    'updated_at' => $now,
                ]);
        });

        $this->notifications->notifyNextReviewerForAssessment($employee, (string) ($competency->name ?? ''), $nextStatus);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'ส่งหลักฐานให้ผู้ประเมินตรวจสอบแล้ว',
        ]);
    }

    public function show(Request $request, string $evidence): BinaryFileResponse
    {
        $record = DB::table('assessment_level_evidences')
            ->join('assessments', 'assessment_level_evidences.assessment_id', '=', 'assessments.id')
            ->where('assessment_level_evidences.public_id', $evidence)
            ->select(
                'assessment_level_evidences.storage_path',
                'assessment_level_evidences.original_name',
                'assessments.user_id',
                'assessments.assessment_round_id',
            )
            ->firstOrFail();

        $viewer = $request->user();
        $isOwner = (int) $record->user_id === (int) $viewer->id;
        $isAssessmentReviewer = $this->reviewerChainResolver->stepForReviewer(
            (object) ['user_id' => (int) $record->user_id, 'assessment_round_id' => (int) $record->assessment_round_id],
            (int) $viewer->id,
        ) !== null;
        abort_unless($isOwner || $isAssessmentReviewer || $viewer->hasRole(['hr', 'admin', 'dean']), 403);
        abort_unless($record->storage_path && Storage::disk('local')->exists($record->storage_path), 404);

        return response()->download(
            Storage::disk('local')->path($record->storage_path),
            $record->original_name ?: 'evidence',
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    private function editableAssessment(Request $request, int $competencyId): Assessment
    {
        $round = $this->assessmentRoundWindow->activeRound();
        if (! $this->assessmentMethods->isFaculty((int) $round->id)) {
            throw ValidationException::withMessages(['evidence' => 'รอบประเมินนี้ไม่ได้ใช้การแนบหลักฐานระดับ 5']);
        }

        $assessment = Assessment::where('user_id', $request->user()->id)
            ->where('competency_id', $competencyId)
            ->where('assessment_round_id', $round->id)
            ->first();
        if (! $assessment) {
            throw ValidationException::withMessages(['evidence' => 'กรุณาบันทึกผลการประเมินก่อนแนบหลักฐาน']);
        }

        $status = $this->assessmentStatus($assessment, $competencyId);
        if (! in_array($status, ['draft', 'revision_required', 'evidence_required'], true)) {
            throw ValidationException::withMessages([
                'evidence' => 'ผลการประเมินนี้ถูกส่งแล้ว ไม่สามารถแก้ไขหลักฐานได้จนกว่าจะถูกส่งกลับมาแก้ไข',
            ]);
        }

        // Evidence requested by a reviewer is collected during the reviewer window.
        $status === 'evidence_required'
            ? $this->assessmentRoundWindow->assertSupervisorAssessmentOpen()
            : $this->assessmentRoundWindow->assertSelfAssessmentOpen();

        return $assessment;
    }

    private function assessmentStatus(Assessment $assessment, int $competencyId): string
    {
        return DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->value('status') ?? $assessment->status;
    }
}
