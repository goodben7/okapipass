<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerTicketShareResource;
use App\Dto\Traveler\ShareTravelerTicketDto;
use App\Manager\TravelerTicketManager;

/** @implements ProcessorInterface<ShareTravelerTicketDto, TravelerTicketShareResource> */
final class ShareTravelerTicketProcessor implements ProcessorInterface
{
    public function __construct(private TravelerTicketManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerTicketShareResource
    {
        \assert($data instanceof ShareTravelerTicketDto);
        $result = $this->manager->share((string) ($uriVariables['id'] ?? ''), (string) $data->toPhone);

        return new TravelerTicketShareResource(
            id: 'share',
            ticketId: $result['ticketId'],
            toPhone: $result['toPhone'],
            smsMessageId: $result['smsMessageId'],
            shareUrl: $result['shareUrl'],
            whatsappUrl: $result['whatsappUrl'],
            shareToken: $result['shareToken'],
        );
    }
}
