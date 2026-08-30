<?php

namespace App\Controller\PublicAgency;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sprint 1 — public namespace health (verifies security.yaml PUBLIC_ACCESS).
 */
final class PublicAgencyHealthController
{
    #[Route(path: '/api/public/agency/health', name: 'public_agency_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'public-agency',
        ]);
    }
}
