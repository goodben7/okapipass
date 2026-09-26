<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\OpenPosSessionDto;
use App\Entity\PosSession;
use App\Manager\PosManager;

/** @implements ProcessorInterface<OpenPosSessionDto, PosSession> */
final class OpenPosSessionProcessor implements ProcessorInterface
{
    public function __construct(private PosManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PosSession
    {
        \assert($data instanceof OpenPosSessionDto);

        return $this->manager->openSession($data);
    }
}
