@component('mail::message')
# มีผลการประเมินรอการตรวจสอบ

{{ $employee->name }} ส่งผลการประเมินสมรรถนะ "{{ $competencyName }}" เข้าสู่ขั้นตอนการตรวจสอบแล้ว กรุณาเข้าสู่ระบบเพื่อพิจารณารายการนี้

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดรายการตรวจสอบ
@endcomponent
@endcomponent
