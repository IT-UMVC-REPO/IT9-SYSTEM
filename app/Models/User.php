<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Mail\EmailVerification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'address', 'profile_image', 'is_active', 'brand_color', 'email_verification_code', 'email_verification_code_expires_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        ];
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

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function callsMade(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'caller_id');
    }

    public function callsReceived(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'receiver_id');
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

        if (
            $this->role === UserRole::Vendor
            && $this->hasApprovedVendorProfile()
            && (! app()->runningInConsole() || app()->runningUnitTests())
            && request()->hasSession()
            && session('marketplace_mode') === 'customer'
        ) {
            return UserRole::Customer;
        }

        if ($this->role === UserRole::Vendor || $this->vendorProfile !== null) {
            return $this->hasApprovedVendorProfile()
                ? UserRole::Vendor
                : UserRole::Customer;
        }

        return UserRole::Customer;
    }

    public function hasApprovedVendorProfile(): bool
    {
        return $this->vendorProfile?->status === VendorStatus::Approved;
    }

    public function homeRoute(): string
    {
        return match ($this->effectiveMarketplaceRole()) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Vendor => 'vendor.dashboard',
            UserRole::Customer => 'customer.dashboard',
        };
    }

    public function brandColorCssVars(): string
    {
        $hex = $this->brand_color ?? '#059669';

        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;

        $lin = fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $rl = $lin($r);
        $gl = $lin($g);
        $bl = $lin($b);

        $X = 0.4122214708 * $rl + 0.5363325363 * $gl + 0.0514459929 * $bl;
        $Y = 0.2119034982 * $rl + 0.6806995451 * $gl + 0.1073969566 * $bl;
        $Z = 0.0883024619 * $rl + 0.2817188376 * $gl + 0.6299787005 * $bl;

        $cbrt = fn ($v) => $v >= 0 ? $v ** (1 / 3) : -((-$v) ** (1 / 3));
        $l_ = $cbrt($X);
        $m_ = $cbrt($Y);
        $s_ = $cbrt($Z);

        $L = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
        $bv = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;

        $C = sqrt($a ** 2 + $bv ** 2);
        $H = fmod(rad2deg(atan2($bv, $a)) + 360, 360);

        $cl = fn ($v) => max(0.05, min(0.98, $v));
        $cc = fn ($v) => max(0.0, min(0.37, $v));
        $f = fn ($l, $c) => sprintf('oklch(%.4f %.4f %.2f)', $cl($l), $cc($c), $H);

        $stops = [
            50 => $f(0.97, $C * 0.25),
            100 => $f(0.93, $C * 0.35),
            200 => $f(0.87, $C * 0.45),
            300 => $f(0.79, $C * 0.60),
            400 => $f(0.70, $C * 0.75),
            500 => $f($L, $C),
            600 => $f($L * 0.82, $C * 1.05),
            700 => $f($L * 0.68, $C * 1.08),
            800 => $f($L * 0.52, $C * 0.95),
            900 => $f($L * 0.36, $C * 0.80),
            950 => $f($L * 0.22, $C * 0.60),
        ];

        $vars = [];

        foreach ($stops as $stop => $value) {
            $vars[] = "--brand-{$stop}:{$value}";
        }

        $vars[] = '--color-accent:var(--brand-600)';
        $vars[] = '--color-accent-content:var(--brand-700)';
        $vars[] = '--color-accent-foreground:#ffffff';

        return implode(';', $vars);
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
