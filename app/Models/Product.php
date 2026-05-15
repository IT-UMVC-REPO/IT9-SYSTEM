<?php

namespace App\Models;

use App\Concerns\HasStorageImage;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Support\UnitConversionResult;
use App\Support\UnitFormatter;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['vendor_id', 'category_id', 'name', 'description', 'price', 'stock_quantity', 'canonical_stock_unit', 'canonical_stock_quantity', 'unit', 'conversion_unit', 'conversion_unit_quantity', 'image', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasStorageImage;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock_quantity' => 0,
        'unit' => ProductUnit::Piece->value,
        'status' => ProductStatus::Inactive->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'int',
            'canonical_stock_unit' => ProductUnit::class,
            'canonical_stock_quantity' => 'float',
            'unit' => ProductUnit::class,
            'conversion_unit' => ProductUnit::class,
            'conversion_unit_quantity' => 'float',
            'status' => ProductStatus::class,
        ];
    }

    public function unitLabel(): string
    {
        return UnitFormatter::format($this->saleUnit(), $this->saleStockQuantity());
    }

    public function priceWithUnit(): string
    {
        return UnitFormatter::pricePerUnit($this->saleUnit(), $this->salePrice());
    }

    public function conversionFor(float $quantity): ?UnitConversionResult
    {
        $variant = $this->purchasableVariant();

        if ($variant !== null) {
            return $variant->conversionFor($quantity);
        }

        if (! $this->hasConversion()) {
            return null;
        }

        return new UnitConversionResult(
            fromUnit: $this->unit,
            fromQuantity: $quantity,
            toUnit: $this->conversion_unit,
            toQuantity: $quantity * (float) $this->conversion_unit_quantity,
            fromUnitPrice: (float) $this->price,
        );
    }

    public function hasConversion(): bool
    {
        return $this->conversion_unit instanceof ProductUnit
            && $this->conversion_unit_quantity !== null
            && (float) $this->conversion_unit_quantity > 0;
    }

    public function canAutoConvert(): bool
    {
        return $this->conversion_unit instanceof ProductUnit
            && $this->unit->isConvertibleTo($this->conversion_unit);
    }

    public function hasVariants(): bool
    {
        if ($this->relationLoaded('unitVariants')) {
            return $this->unitVariants->isNotEmpty();
        }

        return $this->unitVariants()->exists();
    }

    public function purchasableVariant(): ?ProductUnitVariant
    {
        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant !== null) {
            return $this->defaultVariant;
        }

        if ($this->relationLoaded('unitVariants')) {
            return $this->unitVariants
                ->sortBy([
                    ['is_default', 'desc'],
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();
        }

        return $this->defaultVariant()->first()
            ?? $this->unitVariants()->orderBy('sort_order')->orderBy('id')->first();
    }

    public function saleUnit(): ProductUnit
    {
        return $this->purchasableVariant()?->unit ?? $this->unit;
    }

    public function salePrice(): float
    {
        return (float) ($this->purchasableVariant()?->price ?? $this->price);
    }

    public function saleStockQuantity(): int
    {
        return (int) ($this->purchasableVariant()?->stock_quantity ?? $this->stock_quantity);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function unitVariants(): HasMany
    {
        return $this->hasMany(ProductUnitVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductUnitVariant::class)->where('is_default', true)->orderBy('sort_order')->orderBy('id');
    }

    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_items')
            ->withPivot('quantity', 'product_unit_variant_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['quantity', 'unit_price', 'unit', 'product_unit_variant_id']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $searchTerm = trim((string) $term);

        if ($searchTerm === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($searchTerm): void {
            $builder
                ->where('name', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeWithinMaxPrice(Builder $query, ?int $maxPrice): Builder
    {
        if ($maxPrice === null || $maxPrice <= 0) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($maxPrice): void {
            $builder
                ->where('price', '<=', $maxPrice)
                ->orWhereHas('defaultVariant', fn (Builder $variantQuery): Builder => $variantQuery->where('price', '<=', $maxPrice));
        });
    }

    public function scopeSortForStorefront(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('products.created_at'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('products.created_at'),
            'name_asc' => $query->orderBy('name')->orderByDesc('products.created_at'),
            default => $query->latest('products.created_at'),
        };
    }

    public function scopeVisibleToCustomers(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereHas('vendor', fn (Builder $builder): Builder => $builder->approved())
            ->with(['vendor.user', 'category.parent', 'defaultVariant']);
    }

    public function scopeWithDefaultVariant(Builder $query): Builder
    {
        return $query->with('defaultVariant');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->resolvePublicImageUrl(
            $this->getRawOriginal('image'),
            'https://placehold.co/640x640/e7e5e4/9ca3af?text=No+Image',
        ));
    }
}
