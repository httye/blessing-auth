<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->integer('delta');           // 正为收入，负为支出
            $table->integer('balance_after');   // 变动后余额
            $table->string('reason', 100);      // 变动原因
            $table->timestamp('created_at')->useCurrent();
            $table->index(['uid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_logs');
    }
};
