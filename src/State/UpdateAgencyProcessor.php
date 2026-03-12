<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\AgencyManager;
use App\Model\UpdateAgencyModel;

class UpdateAgencyProcessor implements ProcessorInterface
{
    public function __construct(private AgencyManager $manager) 
    {
    }

    /**
     * @param \App\Dto\UpdateAgencyDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $model = new UpdateAgencyModel(
            $data->name,
            $data->email,
            $data->phone,
            $data->address,
            $data->type,
            $data->status,
        );

        return $this->manager->updateFrom($uriVariables['id'], $model);
    }
}
