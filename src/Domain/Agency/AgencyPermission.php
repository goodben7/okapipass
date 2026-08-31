<?php

namespace App\Domain\Agency;

/**
 * Portal permission keys returned by GET /api/agency/me (Sprint A).
 * Centralized here so Sprint A does not invent ad-hoc strings.
 */
final class AgencyPermission
{
    public const string BOOKING_WRITE = 'booking:write';
    public const string TICKET_WRITE = 'ticket:write';
    public const string EMBARKATION_WRITE = 'embarkation:write';
    public const string DECLARATION_WRITE = 'declaration:write';
    public const string PAYMENT_WRITE = 'payment:write';
    public const string REFUND_WRITE = 'refund:write';
    public const string STAFF_WRITE = 'staff:write';
    public const string FLEET_READ = 'fleet:read';
    public const string FLEET_WRITE = 'fleet:write';
    public const string DRIVER_WRITE = 'driver:write';
    public const string MAINTENANCE_WRITE = 'maintenance:write';
    public const string RENTAL_WRITE = 'rental:write';

    /**
     * @return list<string>
     */
    public static function defaultsForPartner(): array
    {
        return [
            self::BOOKING_WRITE,
            self::TICKET_WRITE,
            self::EMBARKATION_WRITE,
            self::DECLARATION_WRITE,
            self::PAYMENT_WRITE,
            self::REFUND_WRITE,
            self::STAFF_WRITE,
            self::FLEET_READ,
            self::FLEET_WRITE,
            self::DRIVER_WRITE,
            self::MAINTENANCE_WRITE,
            self::RENTAL_WRITE,
        ];
    }
}
