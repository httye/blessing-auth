<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);          // microsoft / github / gitee / ...
            $table->string('provider_user_id');      // 提供商侧的用户唯一 ID
            $table->string('provider_email')->nullable();
            $table->string('provider_nickname')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['uid', 'provider']);     // 每用户每提供商只绑一个
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_identities');
    }
};
