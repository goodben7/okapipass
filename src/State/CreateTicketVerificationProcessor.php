<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\VerifyTicketDto;
use App\Manager\TicketVerificationManager;
use App\Model\NewTicketVerificationModel;

final readonly class CreateTicketVerificationProcessor implements ProcessorInterface
{
    public function __construct(
        private TicketVerificationManager $manager,
    ) {
    }

    /**
     * @param VerifyTicketDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $model = new NewTicketVerificationModel(
            uniqueReference: $data->uniqueReference,
            checkpoint: $data->checkpoint,
            comment: $data->comment,
        );

        return $this->manager->createFrom($model);
    }
}
