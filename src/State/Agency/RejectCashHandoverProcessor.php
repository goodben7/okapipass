<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RejectCashHandoverDto;
use App\Entity\CashHandover;
use App\Exception\UnavailableDataException;
use App\Manager\PosManager;

/** @implements ProcessorInterface<RejectCashHandoverDto|CashHandover|null, CashHandover> */
final class RejectCashHandoverProcessor implements ProcessorInterface
{
    public function __construct(private PosManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CashHandover
    {
        $previous = $context['previous_data'] ?? null;
        if (!$previous instanceof CashHandover) {
            if ($data instanceof CashHandover) {
                $previous = $data;
            } else {
                throw new UnavailableDataException('Cash handover not found.');
            }
        }

        $reason = $data instanceof RejectCashHandoverDto ? $data->reason : null;

        return $this->manager->rejectHandover($previous, $reason);
    }
}
