<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyLoyaltyBenefitsResource;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyLoyaltyBenefitsResource> */
final class AgencyLoyaltyBenefitsProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyTicketRepository $tickets,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyLoyaltyBenefitsResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        $phone = $request?->query->get('phone');
        $phone = null !== $phone ? (string) $phone : null;

        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) ? new \DateTimeImmutable($fromRaw) : null;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw) ? new \DateTimeImmutable($toRaw) : null;

        $agency = $this->agencyContext->requireAgency();
        $rows = $this->tickets->findBenefits($agency, $from, $to, $phone);
        $items = [];
        $total = 0;
        foreach ($rows as $ticket) {
            $discount = $ticket->getDiscountAmount();
            $total += $discount;
            $items[] = [
                'ticketId' => $ticket->getId(),
                'passengerPhone' => $ticket->getPassengerPhone(),
                'promoCode' => $ticket->getPromoCode(),
                'loyaltyRuleId' => $ticket->getLoyaltyRule()?->getId(),
                'loyaltyRuleLabel' => $ticket->getLoyaltyRule()?->getLabel(),
                'discountAmount' => $discount,
                'createdAt' => $ticket->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return new AgencyLoyaltyBenefitsResource(
            id: sprintf('%s_%s_%s', $fromRaw ?: 'all', $toRaw ?: 'all', $phone ?: 'all'),
            from: $from?->format('Y-m-d'),
            to: $to?->format('Y-m-d'),
            phone: $phone,
            items: $items,
            totalDiscount: $total,
        );
    }
}
