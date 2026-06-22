@component('mail::message')
# แจ้งเตือนให้ประเมินตนเอง

เรียน {{ $employee->name }} HR แจ้งเตือนให้ดำเนินการประเมินตนเอง กรุณาเข้าสู่ระบบเพื่อทำแบบประเมินตามรายการสมรรถนะที่ได้รับมอบหมาย

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เริ่มประเมินตนเอง
@endcomponent
@endcomponent
