<?php

namespace App\Models;

use App\Concerns\HasStorageImage;
use App\Enums\VendorStatus;
use Database\Factories\VendorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'store_name', 'store_description', 'vendor_address', 'lat', 'lng', 'store_image', 'status', 'rejection_reason', 'approved_at', 'created_at'])]
class VendorProfile extends Model
{
    /** @use HasFactory<VendorProfileFactory> */
    use HasFactory, HasStorageImage;

    public const UPDATED_AT = null;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => VendorStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'approved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
        ];
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'vendor_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'vendor_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Approved);
    }

    protected function storeImageUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->resolvePublicImageUrl(
            $this->getRawOriginal('store_image'),
            'https://placehold.co/640x640/e7e5e4/9ca3af?text=Store',
        ));
    }
}
