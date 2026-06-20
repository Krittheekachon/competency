@component('mail::message')
@php
    $statusDetails = match ($status) {
        'self_submitted' => [
            'title' => 'ส่งแบบประเมินแล้ว',
            'body' => 'ผลการประเมินของคุณอยู่ระหว่างรอการอนุมัติ กรุณาติดตามสถานะในระบบ',
            'button' => 'ดูสถานะการประเมิน',
        ],
        'revision_required' => [
            'title' => 'ส่งกลับให้แก้ไข',
            'body' => 'ผลการประเมินของคุณถูกส่งกลับให้แก้ไข กรุณาเข้าสู่ระบบเพื่อตรวจสอบและแก้ไขรายการที่เกี่ยวข้อง',
            'button' => 'แก้ไขแบบประเมิน',
        ],
        default => [
            'title' => 'ผลการประเมินได้รับการอนุมัติแล้ว',
            'body' => 'ผลการประเมินของคุณได้รับการอนุมัติแล้ว สามารถเริ่มทำแผนพัฒนารายบุคคล (IDP) ได้',
            'button' => 'เริ่มทำแผน IDP',
        ],
    };
@endphp

# {{ $statusDetails['title'] }}

เรียน {{ $employee->name }} {{ $statusDetails['body'] }}

@component('mail::button', ['url' => $actionUrl, 'color' => 'success'])
{{ $statusDetails['button'] }}
@endcomponent
@endcomponent
