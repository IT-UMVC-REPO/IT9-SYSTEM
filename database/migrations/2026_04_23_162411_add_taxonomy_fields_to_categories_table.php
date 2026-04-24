<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->foreignId('parent_id')
                ->nullable()
                ->after('slug')
                ->constrained('categories')
                ->nullOnDelete();
        });

        $usedSlugs = [];

        DB::table('categories')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $category) use (&$usedSlugs): void {
                $baseSlug = Str::slug((string) $category->name);
                $slug = $baseSlug !== '' ? $baseSlug : sprintf('category-%d', $category->id);
                $suffix = 2;

                while (in_array($slug, $usedSlugs, true)) {
                    $slug = sprintf(
                        '%s-%d',
                        $baseSlug !== '' ? $baseSlug : sprintf('category-%d', $category->id),
                        $suffix,
                    );
                    $suffix++;
                }

                $usedSlugs[] = $slug;

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'slug']);
        });
    }
};
