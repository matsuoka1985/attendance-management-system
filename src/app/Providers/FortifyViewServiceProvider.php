<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

//自作クラス
class FortifyViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //自作定義
        Fortify::registerView(fn() => view('auth.register'));


        // 自作定義。ログイン画面
        Fortify::loginView(fn() => view('auth.login'));
    }
}
