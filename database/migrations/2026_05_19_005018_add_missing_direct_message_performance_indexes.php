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
            $table->index(['sender_id', 'receiver_id', 'created_at', 'id'], 'messages_direct_thread_created_id_idx');
            $table->index(['receiver_id', 'sender_id', 'is_read', 'created_at'], 'messages_direct_read_marking_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_direct_thread_created_id_idx');
            $table->dropIndex('messages_direct_read_marking_idx');
        });
    }
};
