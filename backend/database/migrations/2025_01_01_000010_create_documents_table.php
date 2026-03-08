<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('notebook_id')->nullable();
            $table->string('filename');
            $table->string('mime_type');
            $table->enum('status', ['uploaded', 'processing', 'ready', 'failed'])->default('uploaded');
            $table->text('extraction_error')->nullable();
            $table->longText('content')->nullable();
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

