<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentRoundWindow
{
    public function activeRound(): object
    {
        $round = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first([
                'id',
                'year',
                'self_assess_start',
                'self_assess_end',
                'supervisor_assess_end',
            ]);

        if (! $round) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่มีรอบการประเมินที่เปิดใช้งาน กรุณาติดต่อฝ่ายทรัพยากรบุคคล',
            ]);
        }

        return $round;
    }

    public function assertSelfAssessmentOpen(): object
    {
        $round = $this->activeRound();
        $today = $this->today();

        if ($round->self_assess_start
            && $today->lt($this->date($round->self_assess_start))) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่ถึงวันเปิดให้ประเมินตนเอง',
            ]);
        }

        if ($round->self_assess_end
            && $today->gt($this->date($round->self_assess_end))) {
            throw ValidationException::withMessages([
                'assessment' => 'สิ้นสุดระยะเวลาประเมินตนเองแล้ว ไม่สามารถบันทึกหรือส่งผลการประเมินได้',
            ]);
        }

        return $round;
    }

    public function assertSupervisorAssessmentOpen(): object
    {
        $round = $this->activeRound();

        if ($round->supervisor_assess_end
            && $this->today()->gt($this->date($round->supervisor_assess_end))) {
            throw ValidationException::withMessages([
                'assessment' => 'สิ้นสุดระยะเวลาตรวจผลการประเมินของผู้บังคับบัญชาแล้ว',
            ]);
        }

        return $round;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today(config('app.timezone', 'Asia/Bangkok'));
    }

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('app.timezone', 'Asia/Bangkok'))->startOfDay();
    }
}
