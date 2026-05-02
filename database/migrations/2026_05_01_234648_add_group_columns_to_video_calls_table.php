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
        Schema::table('video_calls', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('receiver_id')->constrained('conversation_groups')->nullOnDelete();
            $table->boolean('is_group_call')->default(false)->after('group_id');
            $table->unsignedBigInteger('receiver_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('is_group_call');
            $table->unsignedBigInteger('receiver_id')->nullable(false)->change();
        });
    }
};
