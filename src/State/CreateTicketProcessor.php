<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreateTicketDto;
use App\Entity\IdempotencyRecord;
use App\Entity\Ticket;
use App\Exception\UnavailableDataException;
use App\Manager\TicketManager;
use App\Model\NewTicketModel;
use App\Service\Agency\IdempotencyService;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<CreateTicketDto, Ticket> */
class CreateTicketProcessor implements ProcessorInterface
{
    public function __construct(
        private TicketManager $manager,
        private IdempotencyService $idempotency,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Ticket
    {
        \assert($data instanceof CreateTicketDto);

        $key = $this->idempotency->readKeyHeader();
        if (null !== $key) {
            $replay = $this->idempotency->findReplay(IdempotencyRecord::SCOPE_TICKET_CREATE, $key);
            if ($replay instanceof IdempotencyRecord) {
                $ticketId = (string) (($replay->getResponseBody() ?? [])['id'] ?? '');
                $ticket = '' !== $ticketId ? $this->em->find(Ticket::class, $ticketId) : null;
                if (!$ticket instanceof Ticket) {
                    throw new UnavailableDataException('Idempotent ticket replay failed: ticket not found.');
                }

                return $ticket;
            }
        }

        $model = new NewTicketModel(
            $data->displayName,
            $data->phone,
            $data->identifier,
            $data->goPass,
            $data->departure,
            $data->arrival,
            $data->method,
        );

        $ticket = $this->manager->createFrom($model);

        if (null !== $key) {
            $this->idempotency->store(
                null,
                IdempotencyRecord::SCOPE_TICKET_CREATE,
                $key,
                201,
                [
                    'id' => (string) $ticket->getId(),
                    'phone' => $ticket->getPhone(),
                    'status' => $ticket->getStatus(),
                ],
            );
        }

        return $ticket;
    }
}
