<?php

namespace App\Models;

use App\Concerns\HasStorageImage;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vendor_id', 'category_id', 'name', 'description', 'price', 'stock_quantity', 'unit', 'image', 'status'])]
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
            'unit' => ProductUnit::class,
            'status' => ProductStatus::class,
        ];
    }

    public function unitLabel(): string
    {
        return $this->unit->stockLabel($this->stock_quantity);
    }

    public function priceWithUnit(): string
    {
        return $this->unit->priceLabel($this->price);
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

    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_items')
            ->withPivot('quantity');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['quantity', 'unit_price', 'unit']);
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

        return $query->where('price', '<=', $maxPrice);
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
            ->with(['vendor.user', 'category.parent']);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->resolvePublicImageUrl(
            $this->getRawOriginal('image'),
            'https://placehold.co/640x640/e7e5e4/9ca3af?text=No+Image',
        ));
    }
}
