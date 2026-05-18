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
        Schema::table('conversation_group_members', function (Blueprint $table) {
            $table->string('nickname', 80)->nullable()->after('role');
            $table->timestamp('muted_until')->nullable()->after('last_read_at');
            $table->timestamp('archived_at')->nullable()->after('muted_until');
            $table->timestamp('pinned_at')->nullable()->after('archived_at');
            $table->timestamp('marked_unread_at')->nullable()->after('pinned_at');
            $table->softDeletes();

            $table->index(['group_id', 'role', 'joined_at'], 'group_members_group_role_joined_idx');
            $table->index(['user_id', 'pinned_at', 'archived_at'], 'group_members_user_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_group_members', function (Blueprint $table) {
            $table->dropIndex('group_members_group_role_joined_idx');
            $table->dropIndex('group_members_user_state_idx');
            $table->dropColumn([
                'nickname',
                'muted_until',
                'archived_at',
                'pinned_at',
                'marked_unread_at',
                'deleted_at',
            ]);
        });
    }
};
