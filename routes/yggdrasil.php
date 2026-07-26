<?php

use App\Http\Controllers\Yggdrasil\AuthController;
use App\Http\Controllers\Yggdrasil\MetaController;
use App\Http\Controllers\Yggdrasil\ProfileController;
use App\Http\Controllers\Yggdrasil\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Yggdrasil API（authlib-injector 兼容）
| 前缀 /api/yggdrasil 已在 bootstrap/app.php 中设置
|--------------------------------------------------------------------------
*/

// API 元数据
Route::get('/', [MetaController::class, 'index']);

// 认证服务器
Route::prefix('authserver')->group(function () {
    Route::post('authenticate', [AuthController::class, 'authenticate']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('validate', [AuthController::class, 'validateToken']);
    Route::post('invalidate', [AuthController::class, 'invalidate']);
    Route::post('signout', [AuthController::class, 'signout']);
});

// 会话服务器
Route::prefix('sessionserver/session/minecraft')->group(function () {
    Route::post('join', [SessionController::class, 'join']);
    Route::get('hasJoined', [SessionController::class, 'hasJoined']);
    Route::get('profile/{uuid}', [SessionController::class, 'profile']);
});

// 角色批量查询
Route::post('api/profiles/minecraft', [ProfileController::class, 'search']);
