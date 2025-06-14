<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

//自作コントローラー。メソッドの中身についてはのちに修正する必要あり。
class AuthenticatedSessionController extends Controller
{
    /* ログインフォーム */
    public function create()
    {
        return view('admin.auth.login');   // 独自 Blade
    }

    /* ログイン実行 */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // 🚩 管理者ロールかチェック
            if (Auth::user()->role !== 'admin') {
                Auth::logout();                    // 即ログアウト
                return back()->withErrors([
                    'email' => '管理者権限がありません。',
                ]);
            }

            // OK → 管理者ダッシュボード等へ
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => '認証に失敗しました。',
        ]);
    }

    /* ログアウト */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
