<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AgencyNotificationPreviewResource;
use App\Domain\Agency\AgencyNotificationTextBuilder;
use App\Dto\Agency\NotificationPreviewDto;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<NotificationPreviewDto, AgencyNotificationPreviewResource> */
final class NotificationPreviewProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyBookingRepository $bookings,
        private AgencyTicketRepository $tickets,
        private AgencyNotificationTextBuilder $texts,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyNotificationPreviewResource
    {
        \assert($data instanceof NotificationPreviewDto);
        $agency = $this->agencyContext->requireAgency();

        if ('booking' === $data->type) {
            $booking = $this->bookings->find($data->targetId);
            if (null === $booking || $booking->getAgency()?->getId() !== $agency->getId()) {
                throw new UnavailableDataException('Booking not found.');
            }
            $sms = $this->texts->bookingSms($booking);
            $phone = (string) $booking->getPassengerPhone();
        } else {
            $ticket = $this->tickets->find($data->targetId);
            if (null === $ticket || $ticket->getAgency()?->getId() !== $agency->getId()) {
                throw new UnavailableDataException('Ticket not found.');
            }
            $sms = $this->texts->ticketSms($ticket);
            $phone = (string) $ticket->getPassengerPhone();
        }

        return new AgencyNotificationPreviewResource(
            id: 'preview',
            smsText: $sms,
            whatsappUrl: $this->texts->whatsappUrl($phone, $sms),
            whatsappText: $sms,
        );
    }
}
