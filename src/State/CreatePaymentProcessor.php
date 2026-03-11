<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\PaymentManager;
use App\Model\NewPaymentModel;

class CreatePaymentProcessor implements ProcessorInterface
{
    public function __construct(private PaymentManager $manager)
    {
    }

    /**
     * @param \App\Dto\CreatePaymentDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $amount = null;

        if (null !== $data->amount) {
            $amount = \number_format((float) $data->amount, 2, '.', '');
        }

        $model = new NewPaymentModel(
            $data->reference,
            $data->ticket,
            $amount,
            $data->currency,
            $data->method,
        );

        return $this->manager->createFrom($model);
    }
}
