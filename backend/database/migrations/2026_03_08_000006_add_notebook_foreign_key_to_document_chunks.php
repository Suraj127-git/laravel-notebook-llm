<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_chunks', function (Blueprint $table): void {
            // Drop the old string notebook_id and replace with FK
            $table->dropColumn('notebook_id');
        });

        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->unsignedBigInteger('notebook_id')->nullable()->after('user_id');
            $table->foreign('notebook_id')->references('id')->on('notebooks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->dropForeign(['notebook_id']);
            $table->dropColumn('notebook_id');
        });

        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->string('notebook_id')->nullable()->after('user_id');
        });
    }
};
