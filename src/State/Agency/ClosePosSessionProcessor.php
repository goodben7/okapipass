<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PosSession;
use App\Exception\UnavailableDataException;
use App\Manager\PosManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProcessorInterface<PosSession|null, PosSession> */
final class ClosePosSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private PosManager $manager,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PosSession
    {
        if (!$data instanceof PosSession) {
            throw new UnavailableDataException('POS session not found.');
        }

        $deviceId = $this->requestStack->getCurrentRequest()?->query->get('deviceId');
        $deviceId = \is_string($deviceId) ? $deviceId : null;

        return $this->manager->closeSession($data, $deviceId);
    }
}
