<?php

use App\Enums\UserRole;
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
        DB::statement("ALTER TABLE users MODIFY role ENUM('customer', 'vendor', 'rider', 'admin') NOT NULL DEFAULT '".UserRole::Customer->value."'");

        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_type')->default('motorcycle');
            $table->string('plate_number')->nullable();
            $table->string('contact_number')->nullable();
            $table->enum('status', ['pending', 'approved', 'inactive'])->default('pending');
            $table->boolean('is_available')->default(false);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');

        DB::table('users')
            ->where('role', UserRole::Rider->value)
            ->update(['role' => UserRole::Customer->value]);

        DB::statement("ALTER TABLE users MODIFY role ENUM('customer', 'vendor', 'admin') NOT NULL DEFAULT '".UserRole::Customer->value."'");
    }
};
