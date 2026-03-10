<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_overviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notebook_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('pending');
            $table->string('storage_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('script')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_overviews');
    }
};
