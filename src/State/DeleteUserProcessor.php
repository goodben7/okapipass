<?php

namespace App\State;

use App\Manager\UserManager;
use ApiPlatform\State\ProcessorInterface;

class DeleteUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager)
    {   
    }

    public function process(mixed $data, \ApiPlatform\Metadata\Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->delete($uriVariables['id']);
    }
}