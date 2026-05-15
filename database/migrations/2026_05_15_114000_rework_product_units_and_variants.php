<?php

use App\Enums\ProductUnit;
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
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'base_unit') && ! Schema::hasColumn('products', 'conversion_unit')) {
                $table->renameColumn('base_unit', 'conversion_unit');
            }

            if (Schema::hasColumn('products', 'base_unit_quantity') && ! Schema::hasColumn('products', 'conversion_unit_quantity')) {
                $table->renameColumn('base_unit_quantity', 'conversion_unit_quantity');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'canonical_stock_unit')) {
                $table->string('canonical_stock_unit')->nullable()->after('stock_quantity');
            }

            if (! Schema::hasColumn('products', 'canonical_stock_quantity')) {
                $table->decimal('canonical_stock_quantity', 14, 4)->nullable()->after('canonical_stock_unit');
            }
        });

        Schema::create('product_unit_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('unit');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('conversion_unit')->nullable();
            $table->decimal('conversion_unit_quantity', 10, 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'unit']);
            $table->index(['product_id', 'is_default']);
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cart_items', 'product_unit_variant_id')) {
                $table->foreignId('product_unit_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_unit_variants')
                    ->nullOnDelete();

                $table->unique(['cart_id', 'product_id', 'product_unit_variant_id'], 'cart_items_product_variant_unique');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'product_unit_variant_id')) {
                $table->foreignId('product_unit_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_unit_variants')
                    ->nullOnDelete();
            }
        });

        $this->normalizeProductConversions();
        $this->createDefaultVariants();
        $this->backfillItemVariants();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'product_unit_variant_id')) {
                $table->dropConstrainedForeignId('product_unit_variant_id');
            }
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            if (Schema::hasColumn('cart_items', 'product_unit_variant_id')) {
                $table->dropUnique('cart_items_product_variant_unique');
                $table->dropConstrainedForeignId('product_unit_variant_id');
            }
        });

        Schema::dropIfExists('product_unit_variants');

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'canonical_stock_unit')) {
                $table->dropColumn(['canonical_stock_unit', 'canonical_stock_quantity']);
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'conversion_unit') && ! Schema::hasColumn('products', 'base_unit')) {
                $table->renameColumn('conversion_unit', 'base_unit');
            }

            if (Schema::hasColumn('products', 'conversion_unit_quantity') && ! Schema::hasColumn('products', 'base_unit_quantity')) {
                $table->renameColumn('conversion_unit_quantity', 'base_unit_quantity');
            }
        });
    }

    private function normalizeProductConversions(): void
    {
        $validUnits = collect(ProductUnit::cases())->map->value->all();

        DB::table('products')
            ->select(['id', 'conversion_unit', 'conversion_unit_quantity'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $product) use ($validUnits): void {
                $conversionUnit = $this->normalizeUnitValue($product->conversion_unit);
                $conversionQuantity = $product->conversion_unit_quantity === null
                    ? null
                    : (float) $product->conversion_unit_quantity;

                if (! in_array($conversionUnit, $validUnits, true) || $conversionQuantity === null || $conversionQuantity <= 0) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'conversion_unit' => null,
                            'conversion_unit_quantity' => null,
                        ]);

                    return;
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'conversion_unit' => $conversionUnit,
                        'conversion_unit_quantity' => $conversionQuantity,
                    ]);
            });
    }

    private function createDefaultVariants(): void
    {
        DB::table('products')
            ->select(['id', 'unit', 'price', 'stock_quantity', 'conversion_unit', 'conversion_unit_quantity'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $product): void {
                $unit = ProductUnit::tryFrom($this->normalizeUnitValue($product->unit) ?? '') ?? ProductUnit::Piece;
                $canonicalUnit = $unit->baseUnit();
                $canonicalQuantity = $unit->conversionFactor() === null
                    ? null
                    : ((float) $product->stock_quantity * $unit->conversionFactor());

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'unit' => $unit->value,
                        'canonical_stock_unit' => $canonicalUnit?->value,
                        'canonical_stock_quantity' => $canonicalQuantity,
                    ]);

                DB::table('product_unit_variants')->insert([
                    'product_id' => $product->id,
                    'unit' => $unit->value,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'conversion_unit' => $this->normalizeUnitValue($product->conversion_unit),
                    'conversion_unit_quantity' => $product->conversion_unit_quantity,
                    'is_default' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    private function backfillItemVariants(): void
    {
        DB::table('cart_items')
            ->join('product_unit_variants', function ($join): void {
                $join->on('cart_items.product_id', '=', 'product_unit_variants.product_id')
                    ->where('product_unit_variants.is_default', true);
            })
            ->update([
                'cart_items.product_unit_variant_id' => DB::raw('product_unit_variants.id'),
            ]);

        DB::table('order_items')
            ->join('product_unit_variants', function ($join): void {
                $join->on('order_items.product_id', '=', 'product_unit_variants.product_id')
                    ->where(function ($query): void {
                        $query->whereColumn('order_items.unit', 'product_unit_variants.unit')
                            ->orWhere('product_unit_variants.is_default', true);
                    });
            })
            ->update([
                'order_items.product_unit_variant_id' => DB::raw('product_unit_variants.id'),
            ]);
    }

    private function normalizeUnitValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return match (strtolower($normalized)) {
            'kilogram', 'kilograms', 'kilo', 'kilos', 'kg' => ProductUnit::Kilogram->value,
            'gram', 'grams', 'g' => ProductUnit::Gram->value,
            'pound', 'pounds', 'lb', 'lbs' => ProductUnit::Pound->value,
            'ounce', 'ounces', 'oz' => ProductUnit::Ounce->value,
            'liter', 'liters', 'litre', 'litres', 'l' => ProductUnit::Liter->value,
            'milliliter', 'milliliters', 'millilitre', 'millilitres', 'ml' => ProductUnit::Milliliter->value,
            'piece', 'pieces', 'each', 'pc', 'pcs' => ProductUnit::Piece->value,
            'dozen', 'dozens', 'doz' => ProductUnit::Dozen->value,
            'pair', 'pairs' => ProductUnit::Pair->value,
            'bundle', 'bundles' => ProductUnit::Bundle->value,
            'pack', 'packs' => ProductUnit::Pack->value,
            'bag', 'bags' => ProductUnit::Bag->value,
            'tray', 'trays' => ProductUnit::Tray->value,
            'bottle', 'bottles' => ProductUnit::Bottle->value,
            'can', 'cans' => ProductUnit::Can->value,
            'box', 'boxes' => ProductUnit::Box->value,
            'sack', 'sacks' => ProductUnit::Sack->value,
            'bilao', 'bilaos' => ProductUnit::Bilao->value,
            default => ProductUnit::tryFrom($normalized)?->value,
        };
    }
};
