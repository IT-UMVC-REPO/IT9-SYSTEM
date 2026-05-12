<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY order_status ENUM('pending', 'confirmed', 'preparing', 'ready', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT '".OrderStatus::Pending->value."'");

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('rider_id')->nullable()->after('vendor_id')->constrained('users')->nullOnDelete();
            $table->decimal('rider_lat', 10, 7)->nullable()->after('delivery_lng');
            $table->decimal('rider_lng', 10, 7)->nullable()->after('rider_lat');
            $table->timestamp('picked_up_at')->nullable()->after('delay_note');
            $table->timestamp('out_for_delivery_at')->nullable()->after('picked_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->whereIn('order_status', [OrderStatus::PickedUp->value, OrderStatus::OutForDelivery->value])
            ->update(['order_status' => OrderStatus::Ready->value]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rider_id');
            $table->dropColumn(['rider_lat', 'rider_lng', 'picked_up_at', 'out_for_delivery_at']);
        });

        DB::statement("ALTER TABLE orders MODIFY order_status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT '".OrderStatus::Pending->value."'");
    }
};
