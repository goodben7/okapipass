<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\UserManager;
use App\Model\UpdateUserModel;

class UpdateUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager)
    {
        
    }

    /**
     * @param \App\Dto\UpdateUserDto $data 
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $model = new UpdateUserModel(
            $data->email,
            $data->phone,
            $data->displayName
        );

        return $this->manager->updateFrom($uriVariables['id'], $model); 
    }
}
