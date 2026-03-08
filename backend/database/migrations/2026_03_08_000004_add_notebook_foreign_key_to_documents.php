<?php

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Add a new integer FK column alongside the old string column
        Schema::table('documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('notebook_id_new')->nullable();
            $table->foreign('notebook_id_new', 'documents_notebook_id_new_fk')
                ->references('id')->on('notebooks')->nullOnDelete();
        });

        // Step 2: For each user, create a "Default" notebook and migrate existing docs
        User::each(function (User $user): void {
            $notebookId = Notebook::firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Default'],
                ['emoji' => '📓'],
            )->id;

            DB::table('documents')
                ->where('user_id', $user->id)
                ->whereNotNull('notebook_id')
                ->update(['notebook_id_new' => $notebookId]);
        });

        // Step 3: Drop old string column and rename new one
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('notebook_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->renameColumn('notebook_id_new', 'notebook_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign('documents_notebook_id_new_fk');
            $table->dropColumn('notebook_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('notebook_id')->nullable();
        });
    }
};
