<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->decimal('lat', 10, 6)->nullable()->after('vendor_address');
            $table->decimal('lng', 10, 6)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
