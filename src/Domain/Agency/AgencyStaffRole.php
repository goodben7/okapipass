<?php

namespace App\Domain\Agency;

/**
 * Agency portal staff roles (spec §8 RBAC V2) + permission matrix.
 */
final class AgencyStaffRole
{
    public const string ADMIN = 'ADMIN';
    public const string CASHIER = 'CASHIER';
    public const string EMBARKATION = 'EMBARKATION';
    public const string READONLY = 'READONLY';

    /** @deprecated use AgencyPermission::PAYMENT_WRITE */
    public const string PAYMENT_WRITE = AgencyPermission::PAYMENT_WRITE;
    /** @deprecated use AgencyPermission::REFUND_WRITE */
    public const string REFUND_WRITE = AgencyPermission::REFUND_WRITE;
    /** @deprecated use AgencyPermission::STAFF_WRITE */
    public const string STAFF_WRITE = AgencyPermission::STAFF_WRITE;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ADMIN, self::CASHIER, self::EMBARKATION, self::READONLY];
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        return match ($role) {
            self::ADMIN => [
                AgencyPermission::BOOKING_WRITE,
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::EMBARKATION_WRITE,
                AgencyPermission::DECLARATION_WRITE,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::REFUND_WRITE,
                AgencyPermission::STAFF_WRITE,
                AgencyPermission::FLEET_READ,
                AgencyPermission::FLEET_WRITE,
                AgencyPermission::DRIVER_WRITE,
                AgencyPermission::MAINTENANCE_WRITE,
                AgencyPermission::RENTAL_WRITE,
            ],
            self::CASHIER => [
                AgencyPermission::BOOKING_WRITE,
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::REFUND_WRITE,
            ],
            self::EMBARKATION => [
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::EMBARKATION_WRITE,
            ],
            self::READONLY => [],
            default => AgencyPermission::defaultsForPartner(),
        };
    }
}
