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
    public const string SELLER_BUS = 'SELLER_BUS';
    public const string SELLER_CASH = 'SELLER_CASH';
    public const string SUPERVISOR = 'SUPERVISOR';
    public const string DRIVER = 'DRIVER';
    public const string ACCOUNTANT = 'ACCOUNTANT';
    public const string FLEET_MANAGER = 'FLEET_MANAGER';

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
        return [
            self::ADMIN,
            self::CASHIER,
            self::EMBARKATION,
            self::READONLY,
            self::SELLER_BUS,
            self::SELLER_CASH,
            self::SUPERVISOR,
            self::DRIVER,
            self::ACCOUNTANT,
            self::FLEET_MANAGER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        return match ($role) {
            self::ADMIN => AgencyPermission::defaultsForPartner(),
            self::CASHIER => [
                AgencyPermission::BOOKING_WRITE,
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::REFUND_WRITE,
                AgencyPermission::POS_WRITE,
                AgencyPermission::CASH_COLLECT,
            ],
            self::EMBARKATION => [
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::EMBARKATION_WRITE,
            ],
            self::SELLER_BUS, self::SELLER_CASH => [
                AgencyPermission::BOOKING_WRITE,
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::POS_WRITE,
            ],
            self::SUPERVISOR => [
                AgencyPermission::BOOKING_WRITE,
                AgencyPermission::TICKET_WRITE,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::REFUND_WRITE,
                AgencyPermission::POS_WRITE,
                AgencyPermission::CASH_COLLECT,
                AgencyPermission::EMBARKATION_WRITE,
                AgencyPermission::STAFF_WRITE,
                AgencyPermission::ACCOUNTING_READ,
                AgencyPermission::FLEET_READ,
            ],
            self::READONLY => [],
            self::DRIVER => [
                AgencyPermission::FLEET_READ,
                AgencyPermission::EMBARKATION_WRITE,
            ],
            self::ACCOUNTANT => [
                AgencyPermission::ACCOUNTING_READ,
                AgencyPermission::PAYMENT_WRITE,
                AgencyPermission::REFUND_WRITE,
                AgencyPermission::CASH_COLLECT,
            ],
            self::FLEET_MANAGER => [
                AgencyPermission::FLEET_READ,
                AgencyPermission::FLEET_WRITE,
                AgencyPermission::DRIVER_WRITE,
                AgencyPermission::MAINTENANCE_WRITE,
                AgencyPermission::RENTAL_WRITE,
            ],
            default => AgencyPermission::defaultsForPartner(),
        };
    }
}
