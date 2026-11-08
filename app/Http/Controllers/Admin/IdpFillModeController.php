<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IdpFillMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdpFillModeController extends Controller
{
    public function update(Request $request, IdpFillMode $settings): RedirectResponse
    {
        $data = $request->validate(['idp_fill_mode' => ['required', Rule::in(['quick', 'form'])]]);
        $settings->save($data['idp_fill_mode']);
        return back()->with('success', 'บันทึกรูปแบบการกรอกแผน IDP แล้ว');
    }
}
