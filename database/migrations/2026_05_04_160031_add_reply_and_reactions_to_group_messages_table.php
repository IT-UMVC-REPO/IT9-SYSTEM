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
        Schema::table('group_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')
                ->nullable()
                ->after('content')
                ->constrained('group_messages')
                ->nullOnDelete();
            $table->boolean('is_system_message')->default(false)->after('reply_to_id');
            $table->string('system_event', 64)->nullable()->after('is_system_message');
            $table->foreignId('system_actor_id')
                ->nullable()
                ->after('system_event')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('group_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 10);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['group_message_id', 'user_id', 'emoji']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_message_reactions');

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropForeign(['system_actor_id']);
            $table->dropColumn([
                'reply_to_id',
                'is_system_message',
                'system_event',
                'system_actor_id',
            ]);
        });
    }
};
