<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AssessmentLevelEvidenceService;
use App\Services\AssessmentMethodResolver;
use App\Services\AssessmentRoundWindow;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\JsonResponse;
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
        $round = $this->assessmentRoundWindow->assertSelfAssessmentOpen();
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

        $status = DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->value('status') ?? $assessment->status;
        if (! in_array($status, ['draft', 'revision_required'], true)) {
            throw ValidationException::withMessages([
                'evidence' => 'ผลการประเมินนี้ถูกส่งแล้ว ไม่สามารถแก้ไขหลักฐานได้จนกว่าจะถูกส่งกลับมาแก้ไข',
            ]);
        }

        return $assessment;
    }
}
