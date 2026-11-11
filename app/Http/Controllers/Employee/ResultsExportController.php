<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Services\UserOrganizationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printable summary of the signed-in user's assessment results for one round
 * (competency results, development advice, and who passed each review step).
 */
class ResultsExportController extends Controller
{
    public function show(Request $request, UserOrganizationPresenter $organization): Response
    {
        $user = $request->user();
        $roundId = (int) $request->query('round') ?: (int) DB::table('assessment_rounds')
            ->where('is_active', true)->orderByDesc('year')->orderByDesc('id')->value('id');
        $round = $roundId
            ? DB::table('assessment_rounds')->where('id', $roundId)->first(['id', 'name', 'year'])
            : null;
        abort_unless($round, 404);

        // Same rows as the results page, always for the signed-in user.
        $rows = collect(app(DashboardController::class)->evaluationHistory($request, $roundId)->getData(true)['rows']);
        $timeline = collect($rows->first()['workflow']['timeline'] ?? []);
        $selfSubmittedAt = DB::table('assessments')
            ->where('user_id', $user->id)->where('assessment_round_id', $roundId)->max('self_submitted_at');

        return Inertia::render('Employee/ResultsExport', [
            'exportRound' => [
                'id' => (int) $round->id,
                'label' => $round->name ?: 'รอบปี '.$round->year,
                'year' => $round->year ? (int) $round->year : null,
            ],
            'exportUser' => [
                'name' => trim(($user->title ?: '').$user->name),
                'position' => $user->position ?: '',
                'level' => $user->level ?: '',
                'workline' => $user->workline ?: '',
                'organization' => $organization->forUser($user)['display'] ?? '',
                'employeeType' => $user->sso_position_type_name ?: '',
            ],
            'exportRows' => $rows->map(fn (array $row): array => [
                'code' => $row['cd'] ?? '',
                'name' => $row['n'] ?? '',
                'type' => $row['t'] ?? '',
                'expected' => $row['expected'] ?? null,
                'actual' => $row['actual'] ?? null,
                'gap' => $row['gap'] ?? null,
                'status' => $row['status'] ?? '',
                'requiresIdp' => (bool) ($row['requiresIdp'] ?? false),
                'developmentRecommendation' => $row['developmentRecommendation'] ?? null,
                'reviewHistory' => $row['reviewHistory'] ?? [],
            ])->values()->all(),
            'exportChain' => [
                'self' => [
                    'name' => trim(($user->title ?: '').$user->name),
                    'position' => $user->position ?: '',
                    'submittedAt' => $selfSubmittedAt,
                ],
                'steps' => $timeline->where('kind', 'reviewer')->values()->all(),
            ],
            'exportedAt' => now()->toIso8601String(),
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
