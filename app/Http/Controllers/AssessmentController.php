<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function save(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'assessment_round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
            'checked_indicators' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);

        $userId = auth()->id();

        DB::transaction(function () use ($request, $userId): void {
            $assessment = Assessment::updateOrCreate(
                [
                    'user_id' => $userId,
                    'competency_id' => $request->competency_id,
                    'assessment_round_id' => $request->assessment_round_id,
                ],
                [
                    'score' => $request->score,
                    'note' => $request->note ?? '',
                    'status' => 'draft',
                    'last_draft_saved_at' => now(),
                ]
            );

            DB::table('assessment_evidences')
                ->where('assessment_id', $assessment->id)
                ->delete();

            foreach ($request->checked_indicators as $key => $checked) {
                if (! $checked) {
                    continue;
                }

                DB::table('assessment_evidences')->insert([
                    'assessment_id' => $assessment->id,
                    'competency_id' => $request->competency_id,
                    'uploaded_by' => $userId,
                    'indicator_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'บันทึกการประเมินเรียบร้อยแล้ว',
        ]);
    }

    public function load(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
        ]);

        $assessment = Assessment::where('user_id', auth()->id())
            ->where('competency_id', $request->query('competency_id'))
            ->where('assessment_round_id', $request->query('round_id'))
            ->first();

        if (! $assessment) {
            return response()->json(['checked' => [], 'note' => '', 'score' => 0]);
        }

        $indicators = DB::table('assessment_evidences')
            ->where('assessment_id', $assessment->id)
            ->pluck('indicator_key')
            ->mapWithKeys(fn ($key) => [$key => true]);

        return response()->json([
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
        ]);
    }
}
