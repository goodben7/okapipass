<?php

namespace App\State;

use App\Manager\UserManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

readonly class ToggleLockUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->manager->lockOrUnlockUser($uriVariables['id']);
    }
}
