<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('color', 7)->default('#6c757d');  // 十六进制颜色
            $table->unsignedInteger('price')->default(0);    // 0 = 不可购买（仅授予）
            $table->boolean('purchasable')->default(false);
            $table->timestamps();
        });

        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->foreignId('title_id')->constrained('titles')->cascadeOnDelete();
            $table->timestamp('acquired_at')->useCurrent();
            $table->unique(['uid', 'title_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_title_id')->nullable()
                ->after('permission')->constrained('titles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_title_id');
        });
        Schema::dropIfExists('user_titles');
        Schema::dropIfExists('titles');
    }
};
