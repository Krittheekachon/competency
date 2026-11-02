<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AssessmentMethodResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentMethodController extends Controller
{
    public function update(Request $request, int $round): RedirectResponse
    {
        $data = $request->validate([
            'assessment_method' => ['required', 'string', Rule::in(AssessmentMethodResolver::allowed())],
        ], [
            'assessment_method.required' => 'กรุณาเลือกวิธีการประเมิน',
            'assessment_method.in' => 'วิธีการประเมินที่เลือกไม่ถูกต้อง',
        ]);

        DB::transaction(function () use ($data, $round): void {
            $assessmentRound = DB::table('assessment_rounds')
                ->where('id', $round)
                ->lockForUpdate()
                ->first(['id', 'assessment_method']);

            abort_unless($assessmentRound, 404);

            if ($assessmentRound->assessment_method === $data['assessment_method']) {
                return;
            }

            $hasSubmittedAssessment = DB::table('assessments')
                ->where('assessment_round_id', $round)
                ->whereNotNull('self_submitted_at')
                ->exists();

            if ($hasSubmittedAssessment) {
                throw ValidationException::withMessages([
                    'assessment_method' => 'ไม่สามารถเปลี่ยนวิธีการประเมินได้ เนื่องจากมีบุคลากรส่งแบบประเมินในรอบนี้แล้ว',
                ]);
            }

            DB::table('assessment_rounds')->where('id', $round)->update([
                'assessment_method' => $data['assessment_method'],
                'assessment_method_version' => AssessmentMethodResolver::CURRENT_VERSIONS[$data['assessment_method']],
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'บันทึกวิธีการประเมินเรียบร้อยแล้ว');
    }
}
