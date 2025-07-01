<?php

namespace App\Http\Controllers\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\RegisterViewResponse;

//自作クラス
class RegisteredUserController
{
    public function create(): RegisterViewResponse
    {
        return app(RegisterViewResponse::class);
    }

    public function store(Request $request, CreatesNewUsers $creator): RedirectResponse
    {
        $user = $creator->create($request->all());

        event(new Registered($user));

        Auth::login($user);

        // 🔽 強制的に /email/verify に飛ばす
        return redirect()->route('verification.notice');
    }
}
