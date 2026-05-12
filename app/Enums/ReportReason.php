<?php

namespace App\Enums;

enum ReportReason: string
{
    case FraudOrScam = 'fraud_or_scam';
    case FakeListings = 'fake_listings';
    case HarassmentOrAbuse = 'harassment_or_abuse';
    case SpamOrSolicitation = 'spam_or_solicitation';
    case NoShowOrGhosting = 'no_show_or_ghosting';
    case InaccurateInfo = 'inaccurate_info';
    case InappropriateBehavior = 'inappropriate_behavior';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FraudOrScam => 'Fraud or scam',
            self::FakeListings => 'Fake listings',
            self::HarassmentOrAbuse => 'Harassment or abuse',
            self::SpamOrSolicitation => 'Spam or solicitation',
            self::NoShowOrGhosting => 'No-show or ghosting',
            self::InaccurateInfo => 'Inaccurate information',
            self::InappropriateBehavior => 'Inappropriate behavior',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public function availableTo(): array
    {
        return match ($this) {
            self::FakeListings, self::InaccurateInfo => ['customer'],
            default => ['customer', 'vendor'],
        };
    }

    /**
     * @return array<int, self>
     */
    public static function availableFor(string $reporterRole): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $reason): bool => in_array($reporterRole, $reason->availableTo(), true),
        ));
    }
}
