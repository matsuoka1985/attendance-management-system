<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    //自作追加
    ->withProviders([
        Laravel\Fortify\FortifyServiceProvider::class,
        App\Providers\FortifyServiceProvider::class, 

])


    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
        //自作ミドルウェア
        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\AdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
