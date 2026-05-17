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
        Schema::table('conversation_group_members', function (Blueprint $table): void {
            $table->index(['user_id', 'group_id', 'last_read_at'], 'conversation_group_members_user_group_read_idx');
        });

        Schema::table('group_messages', function (Blueprint $table): void {
            $table->index(['group_id', 'created_at', 'id'], 'group_messages_group_created_id_idx');
            $table->index(['group_id', 'sender_id', 'created_at'], 'group_messages_group_sender_created_idx');
        });

        Schema::table('video_calls', function (Blueprint $table): void {
            $table->index(['group_id', 'is_group_call', 'status', 'ended_at', 'created_at'], 'video_calls_group_status_created_idx');
            $table->index(['receiver_id', 'caller_id', 'is_group_call', 'status', 'created_at'], 'video_calls_direct_pending_idx');
        });

        Schema::table('video_call_participants', function (Blueprint $table): void {
            $table->index(['video_call_id', 'left_at', 'user_id'], 'video_call_participants_call_left_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_group_members', function (Blueprint $table): void {
            $table->dropIndex('conversation_group_members_user_group_read_idx');
        });

        Schema::table('group_messages', function (Blueprint $table): void {
            $table->dropIndex('group_messages_group_created_id_idx');
            $table->dropIndex('group_messages_group_sender_created_idx');
        });

        Schema::table('video_calls', function (Blueprint $table): void {
            $table->dropIndex('video_calls_group_status_created_idx');
            $table->dropIndex('video_calls_direct_pending_idx');
        });

        Schema::table('video_call_participants', function (Blueprint $table): void {
            $table->dropIndex('video_call_participants_call_left_user_idx');
        });
    }
};
