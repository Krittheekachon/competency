@component('mail::message')
# สรุปประจำวัน: ผู้ใช้งานข้อมูลไม่ครบ

มีผู้ใช้งานข้อมูลโครงสร้างไม่ครบทั้งหมด {{ count($users) }} รายการ

@foreach($users as $user)
- {{ $user['name'] ?? 'ไม่ระบุชื่อ' }}@if(!empty($user['email'])) ({{ $user['email'] }})@endif
@endforeach

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดหน้าจัดการผู้ใช้งาน
@endcomponent
@endcomponent
