<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rider_id', 'order_id', 'amount', 'earned_at'])]
class RiderEarning extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForRider(Builder $query, int $riderId): Builder
    {
        return $query->where('rider_id', $riderId);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query
            ->whereYear('earned_at', now()->year)
            ->whereMonth('earned_at', now()->month);
    }
}
