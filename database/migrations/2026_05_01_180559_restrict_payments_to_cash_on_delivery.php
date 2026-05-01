<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_method', '<>', 'cod')
            ->update(['payment_method' => 'cod']);

        DB::table('payments')
            ->where('method', '<>', 'cod')
            ->update(['method' => 'cod']);

        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod') NOT NULL DEFAULT 'cod'");
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cod') NOT NULL DEFAULT 'cod'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod') NOT NULL DEFAULT 'cod'");
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cod') NOT NULL DEFAULT 'cod'");
    }
};
