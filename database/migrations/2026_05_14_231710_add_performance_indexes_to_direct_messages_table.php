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
            $table->index(['receiver_id', 'sender_id', 'created_at'], 'messages_reverse_conversation_idx');
            $table->index(['sender_id', 'receiver_id', 'is_read'], 'messages_read_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_reverse_conversation_idx');
            $table->dropIndex('messages_read_lookup_idx');
        });
    }
};
