<?php

namespace App\Services;

use App\Mail\AssessmentStatusUpdateMail;
use App\Mail\AssessmentSubmittedMail;
use App\Mail\ReminderAssessMail;
use App\Mail\RoleNotificationMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public function __construct(private NotificationDigestService $digest)
    {
    }

    public function notifyDeptHeadOnSubmit(User $employee, string $competencyName): void
    {
        $employee->loadMissing('evaluatorLevel2');

        $this->sendToUser(
            $employee->evaluatorLevel2,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
        );
    }

    public function notifySupervisorOnDeptApproval(User $employee, string $competencyName): void
    {
        $employee->loadMissing('evaluatorLevel1');

        $this->sendToUser(
            $employee->evaluatorLevel1,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
        );
    }

    public function notifyDeanOnSupervisorApproval(User $employee, string $competencyName): void
    {
        $dean = $this->usersWithRole('dean')->first();

        $this->sendToUser(
            $dean,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
        );
    }

    public function notifyAdminIncompleteUser(User $user): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueIncompleteUser($user);
    }

    public function notifyAdminNewUser(User $user): void
    {
        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                'มีผู้ใช้งานใหม่ในระบบ',
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
                'มีกลุ่มงานที่ยังไม่ได้กำหนดค่าความคาดหวัง',
                'กลุ่มงานยังไม่ได้กำหนดค่าความคาดหวัง',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดค่าความคาดหวัง กรุณาตรวจสอบการตั้งค่าก่อนเปิดรอบการประเมิน",
                'เปิดหน้าตั้งค่า',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyHrUnmappedPosition(string $positionName): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueUnmappedPosition($positionName);
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

    public function isNotificationEnabled(): bool
    {
        return ! app()->environment('local') || Cache::get('dev_notifications_enabled', true) !== false;
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
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $users->each(function (User $user) use ($mailableFactory): void {
            $this->sendToUser($user, $mailableFactory($user));
        });
    }

    private function sendToUser(?User $user, $mailable): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        if (! $user?->email) {
            return;
        }

        Mail::to($user)->send($mailable);
    }
}
