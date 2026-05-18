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
            $table->timestamp('edited_at')->nullable()->after('is_read');
            $table->timestamp('deleted_for_everyone_at')->nullable()->after('edited_at');
            $table->timestamp('delivered_at')->nullable()->after('deleted_for_everyone_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->string('forwarded_from_type')->nullable()->after('read_at');
            $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('forwarded_from_type');
            $table->softDeletes();

            $table->index(['sender_id', 'created_at'], 'messages_sender_created_idx');
            $table->index(['receiver_id', 'is_read', 'created_at'], 'messages_receiver_read_created_idx');
            $table->fullText('content', 'messages_content_fulltext');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('system_actor_id');
            $table->timestamp('deleted_for_everyone_at')->nullable()->after('edited_at');
            $table->string('forwarded_from_type')->nullable()->after('deleted_for_everyone_at');
            $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('forwarded_from_type');
            $table->softDeletes();

            $table->index(['sender_id', 'created_at'], 'group_messages_sender_created_idx');
            $table->fullText('content', 'group_messages_content_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropFullText('group_messages_content_fulltext');
            $table->dropIndex('group_messages_sender_created_idx');
            $table->dropColumn([
                'edited_at',
                'deleted_for_everyone_at',
                'forwarded_from_type',
                'forwarded_from_id',
                'deleted_at',
            ]);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropFullText('messages_content_fulltext');
            $table->dropIndex('messages_sender_created_idx');
            $table->dropIndex('messages_receiver_read_created_idx');
            $table->dropColumn([
                'edited_at',
                'deleted_for_everyone_at',
                'delivered_at',
                'read_at',
                'forwarded_from_type',
                'forwarded_from_id',
                'deleted_at',
            ]);
        });
    }
};
