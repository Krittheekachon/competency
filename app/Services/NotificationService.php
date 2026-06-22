<?php

namespace App\Services;

use App\Mail\AssessmentStatusUpdateMail;
use App\Mail\AssessmentSubmittedMail;
use App\Mail\ReminderAssessMail;
use App\Mail\RoleNotificationMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationService
{
    public function notifyFirstReviewerOnSubmit(User $employee, string $competencyName): void
    {
        $employee->loadMissing('evaluatorLevel1', 'evaluatorLevel2', 'evaluatorLevel3');

        $this->sendToUser(
            collect([
                $employee->evaluatorLevel1,
                $employee->evaluatorLevel2,
                $employee->evaluatorLevel3,
            ])->first(),
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
        );
    }

    public function notifyNextReviewerForAssessment(User $employee, string $competencyName, string $pendingStatus): void
    {
        $employee->loadMissing('evaluatorLevel1', 'evaluatorLevel2', 'evaluatorLevel3');

        $reviewer = match ($pendingStatus) {
            'self_submitted' => $employee->evaluatorLevel1,
            'unit_evaluated' => $employee->evaluatorLevel2,
            'dept_evaluated' => $employee->evaluatorLevel3,
            default => null,
        };

        $this->sendToUser(
            $reviewer,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
        );
    }

    public function notifyAdminIncompleteUser(User $user): void
    {
        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                '[A-IDP] ผู้ใช้งานยังรอตรวจสอบข้อมูล',
                'ผู้ใช้งานยังติดสถานะตรวจสอบข้อมูล',
                "{$user->name} ยังมีข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานไม่ครบ กรุณาตรวจสอบและปรับปรุงข้อมูลผู้ใช้งาน",
                'ตรวจสอบข้อมูลผู้ใช้งาน',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyAdminNewUser(User $user): void
    {
        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                '[A-IDP] มีผู้ใช้งานใหม่ในระบบ',
                'มีผู้ใช้งานใหม่',
                "{$user->name} ถูกเพิ่มเข้าสู่ระบบแล้ว กรุณาตรวจสอบข้อมูลผู้ใช้งานและสิทธิ์การใช้งานให้ถูกต้อง",
                'เปิดหน้าจัดการผู้ใช้งาน',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyAdminMissingExpectation(string $groupName): void
    {
        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                '[A-IDP] มีกลุ่มงานที่ยังไม่ได้กำหนดค่าความคาดหวัง',
                'กลุ่มงานยังไม่ได้กำหนดค่าความคาดหวัง',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดค่าความคาดหวัง กรุณาตรวจสอบการตั้งค่าก่อนเปิดรอบการประเมิน",
                'เปิดหน้าตั้งค่า',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyHrUnmappedPosition(string $positionName): void
    {
        $this->sendToUsers(
            $this->usersWithRole('hr')->get(),
            fn () => new RoleNotificationMail(
                '[A-IDP] มีกลุ่มงานที่ยังไม่ได้กำหนดสมรรถนะ',
                'กลุ่มงานยังไม่ได้กำหนดสมรรถนะ',
                "กลุ่มงาน {$positionName} ยังไม่มีการกำหนดสมรรถนะ กรุณาตรวจสอบและผูกสมรรถนะให้ครบถ้วน",
                'เปิดหน้ากำหนดสมรรถนะ',
                $this->dashboardUrl(),
            ),
        );
    }

    public function remindPendingEmployees(): void
    {
        $employees = $this->usersWithAnyRole(['employee', 'hr', 'supervisor', 'dept_head'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('assessments')
                    ->whereColumn('assessments.user_id', 'users.id')
                    ->where('assessments.status', '!=', 'draft');
            })
            ->get();

        $this->sendToUsers(
            $employees,
            fn (User $employee) => new ReminderAssessMail($employee, $this->dashboardUrl()),
        );
    }

    public function notifyEmployeeStatusUpdate(User $employee, string $status): void
    {
        $this->sendToUser(
            $employee,
            new AssessmentStatusUpdateMail($employee, $status, $this->dashboardUrl()),
        );
    }

    private function dashboardUrl(): string
    {
        return route('dashboard');
    }

    private function usersWithRole(string $roleKey): Builder
    {
        return User::query()->where(function (Builder $query) use ($roleKey): void {
            if (Schema::hasColumn('users', 'role_key')) {
                $query->where('role_key', $roleKey);
            }

            $query->orWhereHas('role', function (Builder $roleQuery) use ($roleKey): void {
                $roleQuery->where(function (Builder $columnQuery) use ($roleKey): void {
                    if (Schema::hasColumn('roles', 'key')) {
                        $columnQuery->orWhere('key', $roleKey);
                    }

                    if (Schema::hasColumn('roles', 'role_key')) {
                        $columnQuery->orWhere('role_key', $roleKey);
                    }
                });
            });
        });
    }

    private function usersWithAnyRole(array $roleKeys): Builder
    {
        return User::query()->where(function (Builder $query) use ($roleKeys): void {
            if (Schema::hasColumn('users', 'role_key')) {
                $query->whereIn('role_key', $roleKeys);
            }

            $query->orWhereHas('role', function (Builder $roleQuery) use ($roleKeys): void {
                $roleQuery->where(function (Builder $columnQuery) use ($roleKeys): void {
                    if (Schema::hasColumn('roles', 'key')) {
                        $columnQuery->orWhereIn('key', $roleKeys);
                    }

                    if (Schema::hasColumn('roles', 'role_key')) {
                        $columnQuery->orWhereIn('role_key', $roleKeys);
                    }
                });
            });
        });
    }

    private function sendToUsers(Collection $users, callable $mailableFactory): void
    {
        $users->each(function (User $user) use ($mailableFactory): void {
            $this->sendToUser($user, $mailableFactory($user));
        });
    }

    private function sendToUser(?User $user, $mailable): void
    {
        if (! $user?->email) {
            return;
        }

        try {
            Mail::to($user)->send($mailable);
        } catch (Throwable $exception) {
            Log::warning('Unable to send notification email.', [
                'user_id' => $user->id,
                'mail' => $mailable::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
