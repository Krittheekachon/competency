@component('mail::message')
# มีผู้ใช้งานใหม่ข้อมูลไม่ครบ

{{ $user->name }} ถูกเพิ่มเข้าสู่ระบบแล้ว แต่ยังมีข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานไม่ครบ กรุณาตรวจสอบและปรับปรุงข้อมูลผู้ใช้งาน

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
ตรวจสอบข้อมูลผู้ใช้งาน
@endcomponent
@endcomponent
