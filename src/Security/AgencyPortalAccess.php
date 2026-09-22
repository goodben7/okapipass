<?php

namespace App\Security;

/**
 * Shared API Platform security expression for the agency portal namespace (/api/agency/*).
 */
final class AgencyPortalAccess
{
    public const string EXPRESSION = 'is_granted("ROLE_PARTNER") or is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_ONT_AGENT") or is_granted("ROLE_SUPER_ADMIN")';
}
