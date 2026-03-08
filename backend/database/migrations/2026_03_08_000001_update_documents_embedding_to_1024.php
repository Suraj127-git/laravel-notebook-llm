<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop old 1536-dim OpenAI embedding column and recreate as 1024-dim for Voyage AI
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('embedding');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->vector('embedding', dimensions: 1024)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('embedding');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
        });
    }
};
