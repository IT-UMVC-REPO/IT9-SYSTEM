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
        Schema::table('conversation_groups', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('avatar_path');
            $table->unsignedSmallInteger('max_members')->nullable()->after('description');
            $table->string('invite_token', 80)->nullable()->unique()->after('max_members');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->unsignedInteger('invite_usage_limit')->nullable()->after('invite_expires_at');
            $table->unsignedInteger('invite_uses')->default(0)->after('invite_usage_limit');
            $table->boolean('approval_required')->default(false)->after('invite_uses');
            $table->softDeletes();

            $table->index(['owner_id', 'created_at'], 'conversation_groups_owner_created_idx');
            $table->fullText(['name', 'description'], 'conversation_groups_name_description_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_groups', function (Blueprint $table) {
            $table->dropFullText('conversation_groups_name_description_fulltext');
            $table->dropIndex('conversation_groups_owner_created_idx');
            $table->dropForeign(['owner_id']);
            $table->dropUnique(['invite_token']);
            $table->dropColumn([
                'owner_id',
                'description',
                'max_members',
                'invite_token',
                'invite_expires_at',
                'invite_usage_limit',
                'invite_uses',
                'approval_required',
                'deleted_at',
            ]);
        });
    }
};
