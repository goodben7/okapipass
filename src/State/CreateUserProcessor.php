<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\UserManager;
use App\Model\NewUserModel;

class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager)
    {
        
    }

    /**
     * @param \App\Dto\CreateUserDto $data 
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        $model = new NewUserModel(
            $data->email, 
            $data->plainPassword, 
            $data->profile, 
            $data->phone, 
            $data->displayName
        );

        return $this->manager->createFrom($model); 
    }
}
