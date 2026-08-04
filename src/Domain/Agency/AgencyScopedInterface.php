<?php

namespace App\Domain\Agency;

use App\Entity\Agency;

/**
 * Marker for agency-portal resources that must be scoped by tenant.
 *
 * Existing /api/agencies Agency entity does NOT implement this — on purpose,
 * so ONT/admin listing is unchanged.
 */
interface AgencyScopedInterface
{
    public function getAgency(): ?Agency;
}
