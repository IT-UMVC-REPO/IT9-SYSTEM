<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['sender_id', 'receiver_id', 'created_at'], 'messages_conversation_idx');
            $table->index(['receiver_id', 'is_read'], 'messages_unread_idx');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->index(['group_id', 'created_at'], 'group_messages_thread_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_idx');
            $table->dropIndex('messages_unread_idx');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropIndex('group_messages_thread_idx');
        });
    }
};
