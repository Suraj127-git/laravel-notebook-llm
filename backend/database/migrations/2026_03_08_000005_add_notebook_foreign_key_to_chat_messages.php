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
        // Step 1: Add new integer FK column alongside old string column
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('notebook_id_new')->nullable();
            $table->foreign('notebook_id_new', 'chat_messages_notebook_id_new_fk')
                ->references('id')->on('notebooks')->nullOnDelete();
        });

        // Step 2: Migrate each user's messages to the "Default" notebook
        User::each(function (User $user): void {
            $notebookId = Notebook::firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Default'],
                ['emoji' => '📓'],
            )->id;

            DB::table('chat_messages')
                ->where('user_id', $user->id)
                ->update(['notebook_id_new' => $notebookId]);
        });

        // Step 3: Drop old string column and rename new one
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('notebook_id');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->renameColumn('notebook_id_new', 'notebook_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropForeign('chat_messages_notebook_id_new_fk');
            $table->dropColumn('notebook_id');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('notebook_id');
        });
    }
};
