@component('mail::message')
# มีตำแหน่งที่ยังไม่ผูกสมรรถนะ

ตำแหน่งหรือกลุ่มงาน "{{ $positionName }}" ยังไม่มีการกำหนดสมรรถนะ กรุณาตรวจสอบและผูกสมรรถนะให้ครบถ้วน

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดหน้ากำหนดสมรรถนะ
@endcomponent
@endcomponent
