<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\TicketManager;
use App\Model\NewTicketModel;

class CreateTicketProcessor implements ProcessorInterface
{
    public function __construct(private TicketManager $manager)
    {
    }

    /**
     * @param \App\Dto\CreateTicketDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $model = new NewTicketModel(
            $data->displayName,
            $data->phone,
            $data->identifier,
            $data->goPass,
            $data->departure,
            $data->arrival,
            $data->method,
        );

        return $this->manager->createFrom($model);
    }
}
