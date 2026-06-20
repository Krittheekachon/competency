<?php

namespace App\Http\Controllers;

use App\Mail\AssessmentStatusUpdateMail;
use App\Mail\AssessmentSubmittedMail;
use App\Mail\ReminderAssessMail;
use App\Mail\RoleNotificationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MockSsoController extends Controller
{
    public function showLogin()
    {
        $users = User::with('role')
            ->orderBy('role_id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->sso ?? (string) $user->id,
                'db_id' => $user->id,
                'name' => ($user->title ?? '') . $user->name,
                'role' => $this->roleKeyForUser($user),
                'workline' => $user->workline ?? '',
                'division' => $user->division ?? '',
                'position' => $user->position ?? '',
            ]);

        return view('mock-sso.login', ['mockUsers' => $users]);
    }

    public function login(Request $request)
    {
        $ssoId = $request->input('sso_id');

        $user = User::where('sso', $ssoId)
            ->orWhere('id', $ssoId)
            ->first();

        if (! $user) {
            return back()->withErrors(['sso_id' => 'ไม่พบ User นี้ในระบบ']);
        }

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function testNotification(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in([
                'assessment_submitted',
                'reminder_assess',
                'admin_new_user',
                'admin_incomplete_user',
                'admin_missing_expectation',
                'hr_unmapped_competency',
                'hr_remind_pending',
                'status_pending',
                'status_revision',
                'status_approved',
            ])],
        ]);

        $user = User::findOrFail((int) $data['user_id']);
        $actionUrl = route('dashboard');
        $recipient = 'krittheekachon.s@kkumail.com';
        $groupName = $user->position ?: 'กลุ่มงานตัวอย่าง';

        $mailable = match ($data['type']) {
            'assessment_submitted' => new AssessmentSubmittedMail($user, 'จืบเก่ง', $actionUrl),
            'reminder_assess', 'hr_remind_pending' => new ReminderAssessMail($user, $actionUrl),
            'status_pending' => new AssessmentStatusUpdateMail($user, 'self_submitted', $actionUrl),
            'status_revision' => new AssessmentStatusUpdateMail($user, 'revision_required', $actionUrl),
            'status_approved' => new AssessmentStatusUpdateMail($user, 'dept_evaluated', $actionUrl),
            'admin_new_user' => new RoleNotificationMail(
                '[A-IDP] มีผู้ใช้งานใหม่ในระบบ',
                'มีผู้ใช้งานใหม่',
                "{$user->name} ถูกเพิ่มเข้าสู่ระบบแล้ว กรุณาตรวจสอบข้อมูลผู้ใช้งานและสิทธิ์การใช้งานให้ถูกต้อง",
                'เปิดหน้าจัดการผู้ใช้งาน',
                $actionUrl,
            ),
            'admin_incomplete_user' => new RoleNotificationMail(
                '[A-IDP] ผู้ใช้งานยังรอตรวจสอบข้อมูล',
                'ผู้ใช้งานยังติดสถานะตรวจสอบข้อมูล',
                "{$user->name} ยังมีข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานไม่ครบ กรุณาตรวจสอบและปรับปรุงข้อมูลผู้ใช้งาน",
                'ตรวจสอบข้อมูลผู้ใช้งาน',
                $actionUrl,
            ),
            'admin_missing_expectation' => new RoleNotificationMail(
                '[A-IDP] มีกลุ่มงานที่ยังไม่ได้กำหนดค่าความคาดหวัง',
                'กลุ่มงานยังไม่ได้กำหนดค่าความคาดหวัง',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดค่าความคาดหวัง กรุณาตรวจสอบการตั้งค่าก่อนเปิดรอบการประเมิน",
                'เปิดหน้าตั้งค่า',
                $actionUrl,
            ),
            'hr_unmapped_competency' => new RoleNotificationMail(
                '[A-IDP] มีกลุ่มงานที่ยังไม่ได้กำหนดสมรรถนะ',
                'กลุ่มงานยังไม่ได้กำหนดสมรรถนะ',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดสมรรถนะ กรุณาตรวจสอบและผูกสมรรถนะให้ครบถ้วน",
                'เปิดหน้ากำหนดสมรรถนะ',
                $actionUrl,
            ),
        };

        Mail::to($recipient)->send($mailable);

        return back()->with('mail_status', "ส่งอีเมลทดสอบไปที่ {$recipient} แล้ว");
    }

    private function roleKeyForUser(User $user): string
    {
        if (Schema::hasColumn('users', 'role_key') && $user->role_key) {
            return $user->role_key;
        }

        return $user->role?->role_key ?? $user->role?->key ?? 'employee';
    }
}
