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
        Schema::create('message_pins', function (Blueprint $table) {
            $table->id();
            $table->morphs('pinnable');
            $table->foreignId('conversation_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('direct_user_one_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('direct_user_two_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('pinned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('pinned_at');
            $table->timestamps();

            $table->index(['conversation_group_id', 'pinned_at'], 'message_pins_group_pinned_idx');
            $table->index(['direct_user_one_id', 'direct_user_two_id', 'pinned_at'], 'message_pins_direct_pinned_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_pins');
    }
};
