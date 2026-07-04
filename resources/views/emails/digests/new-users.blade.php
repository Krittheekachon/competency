@component('mail::message')
# สรุปผู้ใช้งานใหม่

มีผู้ใช้งานใหม่รอตรวจสอบทั้งหมด {{ count($users) }} รายการ

@foreach($users as $user)
- {{ $user['name'] ?? 'ไม่ระบุชื่อ' }}@if(!empty($user['email'])) ({{ $user['email'] }})@endif
@endforeach

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดหน้าจัดการผู้ใช้งาน
@endcomponent
@endcomponent
