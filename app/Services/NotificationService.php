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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationService
{
    private const DEFAULT_TEST_RECIPIENT = 'krittheekachon.s@kkumail.com';
    private const REVIEWER_1_RECIPIENT = 'krittheekachon.s@kkumail.com';
    private const REVIEWER_2_3_RECIPIENT = 'chin172755@gmail.com';
    private const EMPLOYEE_REVISION_RECIPIENT = 'polysaccc1351@gmail.com';

    public function __construct(
        private NotificationDigestService $digest,
        private ReviewerChainResolver $reviewerChainResolver,
    )
    {
    }

    public function notifyFirstReviewerOnSubmit(User $employee, string $competencyName): void
    {
        $reviewer = $this->reviewerForAssessmentStep($employee, 1);

        $this->sendToUser(
            $reviewer,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
            $this->recipientForReviewer($reviewer),
        );
    }

    public function notifyNextReviewerForAssessment(User $employee, string $competencyName, string $pendingStatus): void
    {
        $reviewer = $this->reviewerForAssessmentStatus($employee, $pendingStatus);

        $this->sendToUser(
            $reviewer,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
            $this->recipientForReviewer($reviewer),
        );
    }

    private function reviewerForAssessmentStatus(User $employee, string $pendingStatus): ?User
    {
        $step = match (true) {
            $pendingStatus === 'self_submitted' => 1,
            $pendingStatus === 'unit_evaluated' => 2,
            $pendingStatus === 'dept_evaluated' => 3,
            (bool) preg_match('/^review_step_(\d+)$/', $pendingStatus, $matches) => (int) $matches[1],
            default => null,
        };

        return $step ? $this->reviewerForAssessmentStep($employee, $step) : null;
    }

    private function reviewerForAssessmentStep(User $employee, int $step): ?User
    {
        $reviewerId = collect($this->reviewerChainResolver->stepsForUser($employee))
            ->first(fn (array $item): bool => (int) $item['step'] === $step)['reviewer_id'] ?? null;

        return $reviewerId ? User::find((int) $reviewerId) : null;
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
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueIncompleteUser($user);

        return;

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

    public function notifyEmployeeStatusUpdate(User $employee, string $status, string $rejectComment = ''): void
    {
        $this->sendToUser(
            $employee,
            new AssessmentStatusUpdateMail($employee, $status, $this->dashboardUrl(), $rejectComment),
            $this->recipientForEmployeeStatus($status),
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

    private function sendToUser(?User $user, $mailable, ?string $recipient = null): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        if (! $user?->email) {
            return;
        }

        try {
            Mail::to($user)->send($mailable);
        } catch (Throwable $exception) {
            Log::warning('Unable to send notification email.', [
                'user_id' => $user->id,
                'recipient' => $user->email,
                'mail' => $mailable::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function recipientForReviewer(?User $reviewer): string
    {
        $roleKey = $this->normalizeRoleKey($reviewer?->role?->key ?? $reviewer?->role_key ?? '');

        return match ($roleKey) {
            'supervisor' => self::REVIEWER_1_RECIPIENT,
            'dept_head', 'dean' => self::REVIEWER_2_3_RECIPIENT,
            default => self::DEFAULT_TEST_RECIPIENT,
        };
    }

    private function recipientForEmployeeStatus(string $status): string
    {
        return $status === 'revision_required'
            ? self::EMPLOYEE_REVISION_RECIPIENT
            : self::DEFAULT_TEST_RECIPIENT;
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }
}
