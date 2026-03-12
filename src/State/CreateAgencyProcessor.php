<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\AgencyManager;
use App\Model\NewAgencyModel;

class CreateAgencyProcessor implements ProcessorInterface
{
    public function __construct(private AgencyManager $manager)
    {
    }

    /**
     * @param \App\Dto\CreateAgencyDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $model = new NewAgencyModel(
            $data->name,
            $data->email,
            $data->phone,
            $data->address,
            $data->type,
        );

        return $this->manager->createFrom($model);
    }
}
