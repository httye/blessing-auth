<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OAuthServerController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TextureController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 首页
Route::get('/', function () {
    return view('home', [
        'announcements' => App\Models\Announcement::query()->visible()->limit(3)->get(),
    ]);
})->name('home');

// 公告（公开）
Route::get('news', [AnnouncementController::class, 'index'])->name('news.index');
Route::get('news/{announcement}', [AnnouncementController::class, 'show'])->name('news.show');

// 材质文件（公开，供游戏客户端下载）
Route::get('textures/{hash}', [TextureController::class, 'raw'])->name('texture.raw');

// OAuth（redirect/callback 游客与已登录都可访问：游客=登录，已登录=绑定）
Route::get('auth/oauth/{provider}', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('auth/oauth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

// OAuth 服务器（本站作为身份提供方）
Route::middleware('auth')->group(function () {
    Route::get('oauth/authorize', [OAuthServerController::class, 'authorize'])->name('oauth.server.authorize');
    Route::post('oauth/authorize', [OAuthServerController::class, 'approve'])->name('oauth.server.approve');
});
Route::post('oauth/token', [OAuthServerController::class, 'token'])
    ->middleware('throttle:30,1')->withoutMiddleware([Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('oauth.server.token');
Route::get('oauth/userinfo', [OAuthServerController::class, 'userinfo'])
    ->middleware('throttle:60,1')->name('oauth.server.userinfo');

// 游客
Route::middleware('guest')->group(function () {
    Route::get('auth/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('auth/login', [LoginController::class, 'login']);
    Route::get('auth/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('auth/register', [RegisterController::class, 'register']);
    Route::post('auth/register/code', [RegisterController::class, 'sendCode'])
        ->middleware('throttle:10,60')->name('register.code');

    Route::get('auth/forgot', [ForgotPasswordController::class, 'showForgotForm'])->name('password.forgot');
    Route::post('auth/forgot/code', [ForgotPasswordController::class, 'sendCode'])
        ->middleware('throttle:10,60')->name('password.code');
    Route::post('auth/forgot/reset', [ForgotPasswordController::class, 'reset'])
        ->name('password.reset');
});

// 已登录用户
Route::middleware('auth')->group(function () {
    Route::post('auth/logout', [LoginController::class, 'logout'])->name('logout');

    // 用户中心
    Route::get('user', [UserController::class, 'home'])->name('user.home');
    Route::get('user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('user/sign', [UserController::class, 'sign'])->name('user.sign');
    Route::get('user/score', [UserController::class, 'scoreLogs'])->name('user.score');

    // 称号
    Route::get('titles', [TitleController::class, 'index'])->name('title.index');
    Route::post('titles/{title}/buy', [TitleController::class, 'buy'])->name('title.buy');
    Route::post('titles/wear', [TitleController::class, 'wear'])->name('title.wear');
    Route::post('user/nickname', [UserController::class, 'updateNickname'])->name('user.nickname');
    Route::post('user/password', [UserController::class, 'updatePassword'])->name('user.password');
    Route::post('user/email', [UserController::class, 'updateEmail'])->name('user.email');
    Route::post('auth/oauth/{provider}/unbind', [OAuthController::class, 'unbind'])->name('oauth.unbind');

    // 角色管理
    Route::get('player', [PlayerController::class, 'index'])->name('player.index');
    Route::post('player', [PlayerController::class, 'store'])->name('player.store');
    Route::post('player/{player}/rename', [PlayerController::class, 'rename'])->name('player.rename');
    Route::post('player/{player}/texture', [PlayerController::class, 'setTexture'])->name('player.texture');
    Route::post('player/{player}/texture/clear', [PlayerController::class, 'clearTexture'])->name('player.texture.clear');
    Route::delete('player/{player}', [PlayerController::class, 'destroy'])->name('player.destroy');

    // 皮肤库
    Route::get('skinlib', [TextureController::class, 'index'])->name('texture.index');
    Route::get('skinlib/upload', [TextureController::class, 'showUploadForm'])->name('texture.upload');
    Route::post('skinlib/upload', [TextureController::class, 'upload']);
    Route::delete('skinlib/{texture}', [TextureController::class, 'destroy'])->name('texture.destroy');
});

// 管理面板
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');

    Route::get('settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('settings', [AdminController::class, 'updateSettings']);

    Route::get('plugins', [AdminController::class, 'plugins'])->name('plugins');
    Route::post('plugins', [AdminController::class, 'togglePlugin']);

    Route::get('announcements', [AdminController::class, 'announcements'])->name('announcements');
    Route::post('announcements', [AdminController::class, 'storeAnnouncement']);
    Route::post('announcements/{announcement}', [AdminController::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AdminController::class, 'destroyAnnouncement'])->name('announcements.destroy');

    Route::get('backups', [AdminController::class, 'backups'])->name('backups');
    Route::post('backups', [AdminController::class, 'createBackup']);
    Route::get('backups/{name}/download', [AdminController::class, 'downloadBackup'])->name('backups.download');
    Route::delete('backups/{name}', [AdminController::class, 'destroyBackup'])->name('backups.destroy');

    Route::get('audit', [AdminController::class, 'auditLogs'])->name('audit');

    Route::get('titles', [AdminController::class, 'titles'])->name('titles');
    Route::post('titles', [AdminController::class, 'storeTitle']);
    Route::post('titles/{title}/grant', [AdminController::class, 'grantTitle'])->name('titles.grant');
    Route::delete('titles/{title}', [AdminController::class, 'destroyTitle'])->name('titles.destroy');

    Route::get('oauth-clients', [AdminController::class, 'oauthClients'])->name('oauth-clients');
    Route::post('oauth-clients', [AdminController::class, 'storeOAuthClient']);
    Route::post('oauth-clients/{client}/toggle', [AdminController::class, 'toggleOAuthClient'])->name('oauth-clients.toggle');
    Route::delete('oauth-clients/{client}', [AdminController::class, 'destroyOAuthClient'])->name('oauth-clients.destroy');

    Route::get('users', [AdminController::class, 'users'])->name('users');
    Route::post('users/{user}/permission', [AdminController::class, 'updatePermission'])->name('users.permission');
    Route::post('users/{user}/score', [AdminController::class, 'updateScore'])->name('users.score');
    Route::post('users/{user}/password', [AdminController::class, 'resetPassword'])->name('users.password');
    Route::delete('users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');

    Route::get('players', [AdminController::class, 'players'])->name('players');
    Route::delete('players/{player}', [AdminController::class, 'destroyPlayer'])->name('players.destroy');

    Route::get('textures', [AdminController::class, 'textures'])->name('textures');
    Route::delete('textures/{texture}', [AdminController::class, 'destroyTexture'])->name('textures.destroy');
});
