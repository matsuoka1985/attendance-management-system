<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
//自作クラス
class LoginController extends Controller
{
    //
    public function store(LoginRequest $request)
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();

            $intended = session('url.intended');

            if ($intended && str_contains($intended, 'login')) {
                return redirect()->route('attendance.stamp');
            }

            return redirect()->to($intended ?? route('attendance.stamp'));
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput();
    }
}
