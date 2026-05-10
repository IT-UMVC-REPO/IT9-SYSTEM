<?php

namespace App\Enums;

enum AuditEvent: string
{
    case UserRegistered = 'user.registered';
    case UserLoggedIn = 'user.logged_in';
    case UserLoggedOut = 'user.logged_out';
    case UserEmailVerified = 'user.email_verified';
    case UserPasswordReset = 'user.password_reset';
    case UserTwoFactorEnabled = 'user.two_factor_enabled';
    case UserTwoFactorDisabled = 'user.two_factor_disabled';
    case UserProfileUpdated = 'user.profile_updated';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case UserBrandColorChanged = 'user.brand_color_changed';
    case VendorApplicationSubmitted = 'vendor.application_submitted';
    case VendorApplicationReapplied = 'vendor.application_reapplied';
    case VendorApproved = 'vendor.approved';
    case VendorRejected = 'vendor.rejected';
    case VendorStoreUpdated = 'vendor.store_updated';
    case RiderApplicationSubmitted = 'rider.application_submitted';
    case RiderApproved = 'rider.approved';
    case RiderDeactivated = 'rider.deactivated';
    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';
    case ProductDeleted = 'product.deleted';
    case ProductActivated = 'product.activated';
    case ProductSoldOut = 'product.sold_out';
    case ProductRestocked = 'product.restocked';
    case CartItemAdded = 'cart.item_added';
    case CartItemRemoved = 'cart.item_removed';
    case CartCleared = 'cart.cleared';
    case OrderPlaced = 'order.placed';
    case OrderConfirmed = 'order.confirmed';
    case OrderPreparing = 'order.preparing';
    case OrderReady = 'order.ready';
    case OrderPickedUp = 'order.picked_up';
    case OrderOutForDelivery = 'order.out_for_delivery';
    case OrderDelivered = 'order.delivered';
    case OrderCancelled = 'order.cancelled';
    case MessageSent = 'message.sent';
    case GroupCreated = 'group.created';
    case GroupMessageSent = 'group.message_sent';
    case GroupMemberAdded = 'group.member_added';
    case VendorFollowed = 'vendor.followed';
    case VendorUnfollowed = 'vendor.unfollowed';
    case CustomerStarred = 'customer.starred';
    case CustomerUnstarred = 'customer.unstarred';
    case ReportSubmitted = 'report.submitted';
    case ReportReviewed = 'report.reviewed';
    case ReportDismissed = 'report.dismissed';
    case AdminUserViewed = 'admin.user_viewed';

    public function label(): string
    {
        return match ($this) {
            self::UserRegistered => 'User registered',
            self::UserLoggedIn => 'Logged in',
            self::UserLoggedOut => 'Logged out',
            self::UserEmailVerified => 'Email verified',
            self::UserPasswordReset => 'Password reset',
            self::UserTwoFactorEnabled => '2FA enabled',
            self::UserTwoFactorDisabled => '2FA disabled',
            self::UserProfileUpdated => 'Profile updated',
            self::UserDeactivated => 'Account deactivated',
            self::UserReactivated => 'Account reactivated',
            self::UserBrandColorChanged => 'Brand colour changed',
            self::VendorApplicationSubmitted => 'Vendor application submitted',
            self::VendorApplicationReapplied => 'Vendor application reapplied',
            self::VendorApproved => 'Vendor approved',
            self::VendorRejected => 'Vendor rejected',
            self::VendorStoreUpdated => 'Store profile updated',
            self::RiderApplicationSubmitted => 'Rider application submitted',
            self::RiderApproved => 'Rider approved',
            self::RiderDeactivated => 'Rider deactivated',
            self::ProductCreated => 'Product created',
            self::ProductUpdated => 'Product updated',
            self::ProductDeleted => 'Product deleted',
            self::ProductActivated => 'Product activated',
            self::ProductSoldOut => 'Product sold out',
            self::ProductRestocked => 'Product restocked',
            self::CartItemAdded => 'Item added to cart',
            self::CartItemRemoved => 'Cart item removed',
            self::CartCleared => 'Cart cleared',
            self::OrderPlaced => 'Order placed',
            self::OrderConfirmed => 'Order confirmed',
            self::OrderPreparing => 'Order preparing',
            self::OrderReady => 'Order ready',
            self::OrderPickedUp => 'Order picked up',
            self::OrderOutForDelivery => 'Order out for delivery',
            self::OrderDelivered => 'Order delivered',
            self::OrderCancelled => 'Order cancelled',
            self::MessageSent => 'Message sent',
            self::GroupCreated => 'Group created',
            self::GroupMessageSent => 'Group message sent',
            self::GroupMemberAdded => 'Group member added',
            self::VendorFollowed => 'Vendor followed',
            self::VendorUnfollowed => 'Vendor unfollowed',
            self::CustomerStarred => 'Customer starred',
            self::CustomerUnstarred => 'Customer unstarred',
            self::ReportSubmitted => 'Report submitted',
            self::ReportReviewed => 'Report reviewed',
            self::ReportDismissed => 'Report dismissed',
            self::AdminUserViewed => 'Admin viewed user',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UserRegistered, self::UserLoggedIn, self::UserLoggedOut,
            self::UserEmailVerified, self::UserPasswordReset,
            self::UserTwoFactorEnabled, self::UserTwoFactorDisabled => 'fa-solid fa-user',
            self::UserProfileUpdated, self::UserBrandColorChanged,
            self::UserDeactivated, self::UserReactivated => 'fa-solid fa-user-pen',
            self::VendorApplicationSubmitted, self::VendorApplicationReapplied,
            self::VendorApproved, self::VendorRejected,
            self::VendorStoreUpdated => 'fa-solid fa-store',
            self::RiderApplicationSubmitted, self::RiderApproved,
            self::RiderDeactivated => 'fa-solid fa-motorcycle',
            self::ProductCreated, self::ProductUpdated,
            self::ProductDeleted, self::ProductActivated,
            self::ProductSoldOut, self::ProductRestocked => 'fa-solid fa-tag',
            self::CartItemAdded, self::CartItemRemoved, self::CartCleared => 'fa-solid fa-cart-shopping',
            self::OrderPlaced, self::OrderConfirmed, self::OrderPreparing,
            self::OrderReady, self::OrderPickedUp, self::OrderOutForDelivery,
            self::OrderDelivered, self::OrderCancelled => 'fa-solid fa-bag-shopping',
            self::MessageSent, self::GroupCreated, self::GroupMessageSent,
            self::GroupMemberAdded => 'fa-solid fa-comments',
            self::VendorFollowed, self::VendorUnfollowed => 'fa-solid fa-heart',
            self::CustomerStarred, self::CustomerUnstarred => 'fa-solid fa-star',
            self::ReportSubmitted, self::ReportReviewed,
            self::ReportDismissed => 'fa-solid fa-flag',
            self::AdminUserViewed => 'fa-solid fa-eye',
        };
    }

    public function color(): string
    {
        return match (true) {
            in_array($this, [
                self::UserRegistered,
                self::UserEmailVerified,
                self::VendorApproved,
                self::RiderApproved,
                self::ProductCreated,
                self::ProductActivated,
                self::ProductRestocked,
                self::OrderDelivered,
                self::VendorFollowed,
                self::CustomerStarred,
            ], true) => 'emerald',
            in_array($this, [
                self::UserLoggedIn,
                self::UserLoggedOut,
                self::OrderPlaced,
                self::OrderConfirmed,
                self::MessageSent,
                self::GroupCreated,
                self::GroupMessageSent,
                self::CartItemAdded,
            ], true) => 'blue',
            in_array($this, [
                self::VendorRejected,
                self::UserDeactivated,
                self::RiderDeactivated,
                self::OrderCancelled,
                self::ProductDeleted,
                self::ReportSubmitted,
                self::CartItemRemoved,
            ], true) => 'rose',
            in_array($this, [
                self::OrderPreparing,
                self::OrderReady,
                self::OrderPickedUp,
                self::OrderOutForDelivery,
                self::ProductSoldOut,
                self::VendorApplicationSubmitted,
                self::RiderApplicationSubmitted,
                self::UserProfileUpdated,
            ], true) => 'amber',
            default => 'neutral',
        };
    }

    /**
     * @return list<self>
     */
    public static function orderEvents(): array
    {
        return [self::OrderPlaced, self::OrderConfirmed, self::OrderPreparing, self::OrderReady, self::OrderPickedUp, self::OrderOutForDelivery, self::OrderDelivered, self::OrderCancelled];
    }

    /**
     * @return list<self>
     */
    public static function vendorEvents(): array
    {
        return [self::VendorApplicationSubmitted, self::VendorApplicationReapplied, self::VendorApproved, self::VendorRejected, self::VendorStoreUpdated, self::ProductCreated, self::ProductUpdated, self::ProductDeleted, self::ProductActivated, self::ProductSoldOut, self::ProductRestocked];
    }

    /**
     * @return list<self>
     */
    public static function authEvents(): array
    {
        return [self::UserRegistered, self::UserLoggedIn, self::UserLoggedOut, self::UserEmailVerified, self::UserPasswordReset, self::UserTwoFactorEnabled, self::UserTwoFactorDisabled];
    }
}
