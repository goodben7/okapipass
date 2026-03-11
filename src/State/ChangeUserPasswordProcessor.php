<?php

namespace App\State;

use App\Manager\UserManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

class ChangeUserPasswordProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager)
    {
        
    }

    /**
     * @param \App\Dto\ChangePasswordDto $data 
     */
    public function process($data, Operation $operation, $uriVariables = [], $context = [])
    {
        return $this->manager->changePassword($uriVariables['id'], $data->actualPassword, $data->newPassword);
    }

}