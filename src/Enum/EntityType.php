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
            ],  
        ];
    }
}
