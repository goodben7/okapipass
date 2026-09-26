<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateCashHandoverDto;
use App\Entity\CashHandover;
use App\Manager\PosManager;

/** @implements ProcessorInterface<CreateCashHandoverDto, CashHandover> */
final class CreateCashHandoverProcessor implements ProcessorInterface
{
    public function __construct(private PosManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CashHandover
    {
        \assert($data instanceof CreateCashHandoverDto);

        return $this->manager->createHandover($data);
    }
}
