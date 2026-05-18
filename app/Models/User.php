<?php

namespace App\Models;

use App\Concerns\HasStorageImage;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Mail\EmailVerification;
use App\Support\BrandColorConverter;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'address', 'lat', 'lng', 'profile_image', 'is_active', 'brand_color', 'email_verified_at', 'email_verification_code', 'email_verification_code_expires_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasStorageImage, Notifiable, TwoFactorAuthenticatable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Customer->value,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'immutable_datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'bool',
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
        ];
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    protected function profileImageUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->resolvePublicImageUrl(
            $this->profile_image,
            'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=random&color=fff&size=200',
        ));
    }

    public function profileImageUrlFor(?string $path): ?string
    {
        return $this->resolveNullablePublicImageUrl($path);
    }

    public function sendEmailVerificationCode(): void
    {
        $this->sendEmailVerificationNotification();
    }

    public function sendEmailVerificationNotification(): void
    {
        $verificationCode = $this->refreshEmailVerificationCode();

        Mail::to($this->email, $this->name)->queue(new EmailVerification(
            user: $this,
            verificationUrl: $this->emailVerificationUrl(),
            verificationCode: $verificationCode,
        ));
    }

    public function emailVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );
    }

    private function refreshEmailVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->fill([
            'email_verification_code' => $code,
            'email_verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        return $code;
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function riderProfile(): HasOne
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function riderOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function riderDeliveryOffers(): HasMany
    {
        return $this->hasMany(RiderDeliveryOffer::class, 'rider_id');
    }

    public function riderRatings(): HasMany
    {
        return $this->hasMany(RiderRating::class, 'rider_id');
    }

    public function riderEarnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class, 'rider_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function nicknamesGiven(): HasMany
    {
        return $this->hasMany(UserNickname::class, 'owner_id');
    }

    public function nicknamesReceived(): HasMany
    {
        return $this->hasMany(UserNickname::class, 'target_id');
    }

    public function conversationGroupMemberships(): HasMany
    {
        return $this->hasMany(ConversationGroupMember::class);
    }

    public function conversationGroups(): BelongsToMany
    {
        return $this->belongsToMany(ConversationGroup::class, 'conversation_group_members', 'user_id', 'group_id')
            ->withPivot(['role', 'joined_at', 'last_read_at']);
    }

    public function callsMade(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'caller_id');
    }

    public function callsReceived(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'receiver_id');
    }

    public function nicknameFor(int $viewerId): ?string
    {
        return UserNickname::query()
            ->where('owner_id', $viewerId)
            ->where('target_id', $this->getKey())
            ->value('nickname');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'customer_id');
    }

    public function customerStarsGiven(): HasMany
    {
        return $this->hasMany(VendorCustomerStar::class, 'vendor_user_id');
    }

    public function customerStarsReceived(): HasMany
    {
        return $this->hasMany(VendorCustomerStar::class, 'customer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function hasMarketplaceRole(UserRole|string $role): bool
    {
        $expectedRole = $role instanceof UserRole ? $role : UserRole::from($role);

        return $this->role === $expectedRole;
    }

    public function canAccessMarketplaceRole(UserRole|string $role): bool
    {
        $expectedRole = $role instanceof UserRole ? $role : UserRole::from($role);

        return $this->effectiveMarketplaceRole() === $expectedRole;
    }

    public function effectiveMarketplaceRole(): UserRole
    {
        if ($this->role === UserRole::Admin) {
            return UserRole::Admin;
        }

        if ($this->role === UserRole::Vendor) {
            return $this->hasApprovedVendorProfile()
                ? UserRole::Vendor
                : UserRole::Customer;
        }

        if ($this->role === UserRole::Rider) {
            return $this->hasApprovedRiderProfile()
                ? UserRole::Rider
                : UserRole::Customer;
        }

        if ($this->vendorProfile !== null) {
            return $this->hasApprovedVendorProfile()
                ? UserRole::Vendor
                : UserRole::Customer;
        }

        if ($this->riderProfile !== null) {
            return $this->hasApprovedRiderProfile()
                ? UserRole::Rider
                : UserRole::Customer;
        }

        return UserRole::Customer;
    }

    public function hasApprovedVendorProfile(): bool
    {
        return $this->vendorProfile?->status === VendorStatus::Approved;
    }

    public function hasApprovedRiderProfile(): bool
    {
        return $this->riderProfile?->status === 'approved';
    }

    public function homeRoute(): string
    {
        return match ($this->effectiveMarketplaceRole()) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Vendor => 'vendor.dashboard',
            UserRole::Rider => 'rider.dashboard',
            UserRole::Customer => 'customer.dashboard',
        };
    }

    public function brandColorCssVars(): string
    {
        return BrandColorConverter::toOklchCssVars($this->brand_color ?? '#059669');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
