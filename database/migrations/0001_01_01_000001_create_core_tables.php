<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->enum('type', ['skin', 'cape'])->default('skin');
            $table->string('hash', 64)->index();
            $table->unsignedInteger('size'); // KB
            $table->foreignId('uploader')->constrained('users')->cascadeOnDelete();
            $table->boolean('public')->default(true);
            $table->timestamps();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
            $table->string('name', 16)->unique();
            $table->uuid('uuid')->unique();
            $table->foreignId('tid_skin')->nullable()->constrained('textures')->nullOnDelete();
            $table->foreignId('tid_cape')->nullable()->constrained('textures')->nullOnDelete();
            $table->timestamp('last_modified')->useCurrent();
            $table->timestamps();
        });

        Schema::create('ygg_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('access_token', 64)->unique();
            $table->string('client_token', 128);
            $table->foreignId('owner')->constrained('users')->cascadeOnDelete();
            $table->uuid('player_uuid')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ygg_tokens');
        Schema::dropIfExists('players');
        Schema::dropIfExists('textures');
    }
};
