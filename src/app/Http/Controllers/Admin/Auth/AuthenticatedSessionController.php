<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\LoginRequest;

//自作コントローラー。メソッドの中身についてはのちに修正する必要あり。
class AuthenticatedSessionController extends Controller
{
    /* ログインフォーム */
    public function create()
    {
        return view('admin.auth.login');   // 独自 Blade
    }

    /* ログイン実行 */
    public function store(LoginRequest $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ▼ ここだけ guard('admin') に変更
        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // ロール確認（任意。admin ガードなら基本不要）
            if (! Auth::guard('admin')->user()->isAdmin()) {
                Auth::guard('admin')->logout();
                return back()->withErrors(['email' => 'ログイン情報が登録されていません。']);
            }

            return redirect()->intended(route('admin.attendance.index'));
        }

        return back()->withErrors(['email' => 'ログイン情報が登録されていません。']);
    }

    /* ログアウト */
    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();           // ▼ ここだけ変更

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
