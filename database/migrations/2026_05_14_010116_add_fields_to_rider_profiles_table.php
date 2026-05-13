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
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->default(0)->after('current_lng');
            $table->unsignedInteger('total_ratings')->default(0)->after('rating');
            $table->decimal('total_earnings', 10, 2)->default(0)->after('total_ratings');
            $table->unsignedInteger('average_delivery_minutes')->nullable()->after('total_earnings');
            $table->decimal('acceptance_rate', 5, 2)->default(0)->after('average_delivery_minutes');
            $table->unsignedInteger('total_offers_received')->default(0)->after('acceptance_rate');
            $table->unsignedInteger('total_offers_accepted')->default(0)->after('total_offers_received');
            $table->timestamp('session_started_at')->nullable()->after('total_offers_accepted');
            $table->timestamp('last_seen_at')->nullable()->after('session_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'rating',
                'total_ratings',
                'total_earnings',
                'average_delivery_minutes',
                'acceptance_rate',
                'total_offers_received',
                'total_offers_accepted',
                'session_started_at',
                'last_seen_at',
            ]);
        });
    }
};
