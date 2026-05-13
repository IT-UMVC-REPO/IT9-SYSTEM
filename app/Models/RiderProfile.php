<?php

namespace App\Models;

use App\Enums\RiderOfferStatus;
use Database\Factories\RiderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'vehicle_type',
    'plate_number',
    'contact_number',
    'bio',
    'status',
    'is_available',
    'current_lat',
    'current_lng',
    'rating',
    'total_ratings',
    'total_earnings',
    'average_delivery_minutes',
    'acceptance_rate',
    'total_offers_received',
    'total_offers_accepted',
    'session_started_at',
    'last_seen_at',
    'approved_at',
])]
class RiderProfile extends Model
{
    /** @use HasFactory<RiderProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'current_lat' => 'float',
            'current_lng' => 'float',
            'rating' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'acceptance_rate' => 'decimal:2',
            'session_started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class, 'rider_id', 'user_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(RiderRating::class, 'rider_id', 'user_id');
    }

    public function hasLocation(): bool
    {
        return $this->current_lat !== null && $this->current_lng !== null;
    }

    public function formattedRating(): string
    {
        return number_format((float) $this->rating, 1);
    }

    public function formattedEarnings(): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $this->total_earnings, 2));
    }

    public function recalculateStats(): void
    {
        $totalRatings = $this->ratings()->count();
        $rating = $totalRatings > 0 ? (float) $this->ratings()->avg('rating') : 0.0;
        $offers = RiderDeliveryOffer::query()
            ->where('rider_id', $this->user_id);
        $totalOffersReceived = (int) (clone $offers)->count();
        $totalOffersAccepted = (int) (clone $offers)
            ->where('status', RiderOfferStatus::Accepted)
            ->count();

        $this->forceFill([
            'rating' => round($rating, 2),
            'total_ratings' => $totalRatings,
            'total_offers_received' => $totalOffersReceived,
            'total_offers_accepted' => $totalOffersAccepted,
            'acceptance_rate' => $totalOffersReceived > 0
                ? round(($totalOffersAccepted / $totalOffersReceived) * 100, 2)
                : 0,
        ])->save();
    }
}
