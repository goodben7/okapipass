<?php

namespace App\Model;

interface UserProxyIntertace
{
    // Utilisateur final qui achète un GoPass
    public const string PERSON_TRAVELER = 'TRAVELER';

    // Partenaire (agence de voyage, transporteur)
    public const string PERSON_PARTNER = 'PARTNER';

    // Agent ONT
    public const string PERSON_ONT_AGENT = 'ONT_AGENT';

    // Administrateur ONT
    public const string PERSON_ONT_ADMIN = 'ONT_ADMIN';

    // Administrateur technique (DIGIS)
    public const string PERSON_SYSTEM_ADMIN = 'SYSTEM_ADMIN';

    // Super administrateur plateforme
    public const string PERSON_SUPER_ADMIN = 'SUPER_ADMIN';
}