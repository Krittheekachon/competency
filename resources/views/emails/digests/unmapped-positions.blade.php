@component('mail::message')
# ตำแหน่งยังไม่ผูกสมรรถนะ

มีตำแหน่งหรือกลุ่มงานที่ยังไม่ผูกสมรรถนะทั้งหมด {{ count($positions) }} รายการ

@foreach($positions as $position)
- {{ $position }}
@endforeach

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดหน้ากำหนดสมรรถนะ
@endcomponent
@endcomponent
