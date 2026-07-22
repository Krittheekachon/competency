@component('mail::message')
# สรุปประจำวัน: บุคลากรยังไม่ประเมินตนเอง

มีบุคลากรที่ยังไม่ประเมินตนเองทั้งหมด {{ count($users) }} รายการ

@foreach($users as $user)
- {{ $user['name'] ?? 'ไม่ระบุชื่อ' }}@if(!empty($user['email'])) ({{ $user['email'] }})@endif
@endforeach

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
เปิดแดชบอร์ด
@endcomponent
@endcomponent
