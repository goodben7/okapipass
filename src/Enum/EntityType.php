<?php

namespace App\Enum;

class EntityType
{
    // === ENTITÉS PRINCIPALES ===
    public const string USER = 'USER'; // App\Entity\User
    public const string PROFILE = 'PROFILE'; // App\Entity\Profile
    public const string ACTIVITY = 'ACTIVITY'; // App\Entity\Activity
    public const string CHECKPOINT = 'CHECKPOINT';
    public const string AGENCY = 'AGENCY';
    public const string GOPASS = 'GOPASS';
    public const string TICKET = 'TICKET';
    public const string PAYMENT = 'PAYMENT';
    public const string TRIP = 'TRIP';
    public const string HOTEL = 'HOTEL';
    public const string TOURIST_SITE = 'TOURIST_SITE';
    public const string PROVINCE = 'PROVINCE';
    public const string NOTIFICATION = 'NOTIFICATION';
    public const string TICKET_VERIFICATION = 'TICKET_VERIFICATION';
    public const string AGENCY_TRANSPORT = 'AGENCY_TRANSPORT';
    public const string AGENCY_OFFER = 'AGENCY_OFFER';
    public const string AGENCY_BOOKING = 'AGENCY_BOOKING';
    public const string AGENCY_TICKET = 'AGENCY_TICKET';
    public const string AGENCY_EMBARKATION = 'AGENCY_EMBARKATION';
    public const string PASS_DECLARATION = 'PASS_DECLARATION';
    public const string AGENCY_STAFF_MEMBER = 'AGENCY_STAFF_MEMBER';
    public const string AGENCY_PAYMENT = 'AGENCY_PAYMENT';


    public static function getAll(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }

    public static function getGrouped(): array
    {
        return [
            'entities' => [
                self::USER,
                self::PROFILE,
                self::ACTIVITY,
                self::CHECKPOINT,
                self::AGENCY,
                self::GOPASS,
                self::TICKET,
                self::PAYMENT,
                self::TRIP,
                self::HOTEL,
                self::TOURIST_SITE,
                self::PROVINCE,
                self::NOTIFICATION,
                self::TICKET_VERIFICATION,
                self::AGENCY_TRANSPORT,
                self::AGENCY_OFFER,
                self::AGENCY_BOOKING,
                self::AGENCY_TICKET,
                self::AGENCY_EMBARKATION,
                self::PASS_DECLARATION,
                self::AGENCY_STAFF_MEMBER,
                self::AGENCY_PAYMENT,
            ],
        ];
    }
}
