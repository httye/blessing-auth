<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 接入本站登录的外部应用
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('client_id', 40)->unique();
            $table->string('client_secret', 100);
            $table->text('redirect_uri');            // 多个用换行分隔
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // 已发放的访问令牌
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 80)->unique();
            $table->foreignId('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->string('scope')->default('profile');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 用户对应用的授权记录（跳过重复授权页）
        Schema::create('oauth_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->timestamp('authorized_at')->useCurrent();
            $table->unique(['client_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_authorizations');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_clients');
    }
};
