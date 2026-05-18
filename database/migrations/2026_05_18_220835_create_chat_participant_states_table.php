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
        Schema::create('chat_participant_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chat_type', 16);
            $table->foreignId('direct_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('conversation_groups')->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('marked_unread_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'chat_type', 'pinned_at'], 'chat_states_user_type_pinned_idx');
            $table->index(['user_id', 'chat_type', 'archived_at'], 'chat_states_user_type_archived_idx');
            $table->index(['user_id', 'label'], 'chat_states_user_label_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_participant_states');
    }
};
