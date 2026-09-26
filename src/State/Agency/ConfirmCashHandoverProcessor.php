<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CashHandover;
use App\Exception\UnavailableDataException;
use App\Manager\PosManager;

/** @implements ProcessorInterface<CashHandover|null, CashHandover> */
final class ConfirmCashHandoverProcessor implements ProcessorInterface
{
    public function __construct(private PosManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CashHandover
    {
        if (!$data instanceof CashHandover) {
            throw new UnavailableDataException('Cash handover not found.');
        }

        return $this->manager->confirmHandover($data);
    }
}
