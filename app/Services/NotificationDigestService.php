<?php

namespace App\Services;

use App\Mail\DailyIncompleteUserDigestMail;
use App\Mail\NewUserDigestMail;
use App\Mail\PendingAssessmentDigestMail;
use App\Mail\UnmappedPositionDigestMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationDigestService
{
    private const NEW_USERS_KEY = 'digest_new_users';
    private const UNMAPPED_POSITIONS_KEY = 'digest_unmapped_positions';

    public function queueIncompleteUser(User $user): void
    {
        $users = Cache::get(self::NEW_USERS_KEY, []);
        $users[$user->id] = $this->userSummary($user);

        Cache::put(self::NEW_USERS_KEY, $users);
    }

    public function queueUnmappedPosition(string $name): void
    {
        $positions = Cache::get(self::UNMAPPED_POSITIONS_KEY, []);
        $positions[$name] = $name;

        Cache::put(self::UNMAPPED_POSITIONS_KEY, $positions);
    }

    public function sendHourlyDigest(): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $users = array_values(Cache::pull(self::NEW_USERS_KEY, []));
        $positions = array_values(Cache::pull(self::UNMAPPED_POSITIONS_KEY, []));

        if ($users !== []) {
            $this->sendToUsers($this->usersWithRole('admin')->get(), new NewUserDigestMail($users));
        }

        if ($positions !== []) {
            $this->sendToUsers($this->usersWithRole('hr')->get(), new UnmappedPositionDigestMail($positions));
        }
    }

    public function sendDailyDigest(): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $incompleteUsers = $this->incompleteStructureUsers()
            ->get()
            ->map(fn (User $user) => $this->userSummary($user))
            ->all();

        if ($incompleteUsers !== []) {
            $this->sendToUsers(
                $this->usersWithRole('admin')->get(),
                new DailyIncompleteUserDigestMail($incompleteUsers),
            );
        }

        $pendingAssessmentUsers = $this->staffUsers()
            ->whereDoesntHave('assessments', fn (Builder $query) => $query->where('status', '!=', 'draft'))
            ->get()
            ->map(fn (User $user) => $this->userSummary($user))
            ->all();

        if ($pendingAssessmentUsers !== []) {
            $this->sendToUsers(
                $this->usersWithRole('hr')->get(),
                new PendingAssessmentDigestMail($pendingAssessmentUsers),
            );
        }
    }

    private function incompleteStructureUsers(): Builder
    {
        $hasDivision = Schema::hasColumn('users', 'division_id');
        $hasJobFamily = Schema::hasColumn('users', 'job_family_id');
        $hasPosition = Schema::hasColumn('users', 'position_id');

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($hasDivision, $hasJobFamily, $hasPosition): void {
                if (! $hasDivision && ! $hasJobFamily && ! $hasPosition) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                if ($hasDivision) {
                    $query->orWhereNull('division_id');
                }

                if ($hasJobFamily) {
                    $query->orWhereNull('job_family_id');
                } elseif ($hasPosition) {
                    $query->orWhereNull('position_id');
                }
            });
    }

    private function staffUsers(): Builder
    {
        return $this->usersWithAnyRole(['employee', 'hr', 'supervisor', 'dept_head']);
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

    private function sendToUsers($users, $mailable): void
    {
        $users->each(function (User $user) use ($mailable): void {
            if ($user->email) {
                Mail::to($user)->send(clone $mailable);
            }
        });
    }

    private function isNotificationEnabled(): bool
    {
        return ! app()->environment('local') || Cache::get('dev_notifications_enabled', true) !== false;
    }

    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'workline' => $user->workline,
            'department' => $user->department,
            'position' => $user->position,
        ];
    }
}
