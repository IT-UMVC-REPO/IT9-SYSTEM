<?php

namespace App\Models;

use Database\Factories\RiderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'vehicle_type',
    'plate_number',
    'contact_number',
    'status',
    'is_available',
    'current_lat',
    'current_lng',
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
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasLocation(): bool
    {
        return $this->current_lat !== null && $this->current_lng !== null;
    }
}
