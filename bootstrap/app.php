<?php

use App\Http\Middleware\CheckAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Illuminate\Support\Facades\Route::prefix('api/yggdrasil')
                ->middleware(App\Http\Middleware\YggdrasilHeaders::class)
                ->group(base_path('routes/yggdrasil.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => CheckAdmin::class,
        ]);
        // Yggdrasil API 为无状态 JSON API，排除 CSRF
        $middleware->validateCsrfTokens(except: [
            'api/yggdrasil/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Yggdrasil API 统一 JSON 错误格式
        $exceptions->render(function (App\Exceptions\YggdrasilException $e, Request $request) {
            return response()->json([
                'error' => $e->getError(),
                'errorMessage' => $e->getMessage(),
            ], $e->getStatusCode());
        });
    })->create();
