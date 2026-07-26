<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('content');
            $table->foreignId('author')->constrained('users')->cascadeOnDelete();
            $table->boolean('pinned')->default(false);   // 置顶
            $table->boolean('published')->default(true); // 草稿/发布
            $table->timestamps();
            $table->index(['published', 'pinned', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
