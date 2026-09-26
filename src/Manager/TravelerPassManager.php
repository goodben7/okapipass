<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Domain\Agency\SeatOccupancyService;
use App\Dto\Agency\CreateAgencyPassProductDto;
use App\Dto\Agency\UpdateAgencyPassProductDto;
use App\Dto\Traveler\ConvertTravelerPreorderDto;
use App\Dto\Traveler\CreateTravelerPassPurchaseDto;
use App\Dto\Traveler\CreateTravelerPreorderDto;
use App\Entity\Agency;
use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Entity\TravelerPass;
use App\Entity\TravelerPassProduct;
use App\Entity\TravelerPreorder;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\TravelerPassProductRepository;
use App\Repository\TravelerPassRepository;
use App\Repository\TravelerPreorderRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Auth\TravelerOtpService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TravelerPassManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private TravelerPassProductRepository $products,
        private TravelerPassRepository $passes,
        private TravelerPreorderRepository $preorders,
        private AgencyOfferRepository $offers,
        private TravelerWalletManager $walletManager,
        private TravelerOtpService $otpService,
        private SeatOccupancyService $occupancy,
        private AgencyTicketIssuanceService $ticketIssuance,
        private AgencyTransportAvailabilityService $transportAvailability,
    ) {
    }

    public function createProduct(CreateAgencyPassProductDto $dto): TravelerPassProduct
    {
        $this->agencyContext->requirePermission(AgencyPermission::TICKET_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $existing = $this->products->findOneBy([
            'agency' => $agency,
            'code' => strtoupper(trim((string) $dto->code)),
        ]);
        if ($existing instanceof TravelerPassProduct) {
            throw new UnprocessableEntityException('A pass product with this code already exists.');
        }

        $product = new TravelerPassProduct();
        $product->setAgency($agency);
        $product->setCode((string) $dto->code);
        $product->setLabel((string) $dto->label);
        $product->setOrigin($dto->origin);
        $product->setDestination($dto->destination);
        $product->setTripsAllowed($dto->tripsAllowed);
        $product->setValidityDays((int) $dto->validityDays);
        $product->setPrice((int) $dto->price);
        $product->setCurrency($dto->currency ?? Agency::DEFAULT_CURRENCY);
        $product->setActive(false !== $dto->active);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function updateProduct(TravelerPassProduct $product, UpdateAgencyPassProductDto $dto): TravelerPassProduct
    {
        $this->agencyContext->requirePermission(AgencyPermission::TICKET_WRITE);
        $this->agencyContext->assertOwns($product->getAgency());

        if (null !== $dto->label) {
            $product->setLabel($dto->label);
        }
        if (null !== $dto->origin) {
            $product->setOrigin('' === $dto->origin ? null : $dto->origin);
        }
        if (null !== $dto->destination) {
            $product->setDestination('' === $dto->destination ? null : $dto->destination);
        }
        if (null !== $dto->tripsAllowed) {
            $product->setTripsAllowed($dto->tripsAllowed);
        }
        if (null !== $dto->validityDays) {
            $product->setValidityDays($dto->validityDays);
        }
        if (null !== $dto->price) {
            $product->setPrice($dto->price);
        }
        if (null !== $dto->currency) {
            $product->setCurrency($dto->currency);
        }
        if (null !== $dto->active) {
            $product->setActive($dto->active);
        }

        $this->em->flush();

        return $product;
    }

    /**
     * @return list<TravelerPassProduct>
     */
    public function listActiveProducts(): array
    {
        return $this->products->findAllActive();
    }

    public function purchasePass(User $user, CreateTravelerPassPurchaseDto $dto): TravelerPass
    {
        if (false === $dto->payWithWallet) {
            throw new UnprocessableEntityException('Wallet only in v1 of this endpoint.');
        }

        $product = $this->products->find($this->extractId((string) $dto->productId));
        if (!$product instanceof TravelerPassProduct || !$product->isActive()) {
            throw new UnavailableDataException('Pass product not found.');
        }

        $this->walletManager->debit(
            $user,
            $product->getPrice(),
            'pass:' . $product->getId(),
            'Pass purchase ' . $product->getLabel(),
            ['productId' => $product->getId()],
        );

        $now = new \DateTimeImmutable();
        $validFrom = $now->setTime(0, 0);
        $validTo = $validFrom->modify(sprintf('+%d days', max(1, $product->getValidityDays()) - 1));

        $pass = new TravelerPass();
        $pass->setUser($user);
        $pass->setProduct($product);
        $pass->setAgency($product->getAgency());
        $pass->setStatus(TravelerPass::STATUS_ACTIVE);
        $pass->setTripsRemaining($product->getTripsAllowed());
        $pass->setValidFrom($validFrom);
        $pass->setValidTo($validTo);
        $pass->setPurchasePrice($product->getPrice());
        $pass->setCurrency($product->getCurrency());

        $this->em->persist($pass);
        $this->em->flush();

        return $pass;
    }

    /**
     * @return list<TravelerPass>
     */
    public function listForUser(User $user): array
    {
        return $this->passes->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function consumeTrip(TravelerPass $pass): TravelerPass
    {
        if (TravelerPass::STATUS_ACTIVE !== $pass->getStatus()) {
            throw new ConflictException('Pass is not active.');
        }

        $remaining = $pass->getTripsRemaining();
        if (null === $remaining) {
            return $pass;
        }
        if ($remaining <= 0) {
            $pass->setStatus(TravelerPass::STATUS_EXHAUSTED);
            $this->em->flush();

            throw new ConflictException('Pass has no trips remaining.');
        }

        $pass->setTripsRemaining($remaining - 1);
        if ($pass->getTripsRemaining() <= 0) {
            $pass->setTripsRemaining(0);
            $pass->setStatus(TravelerPass::STATUS_EXHAUSTED);
        }
        $this->em->flush();

        return $pass;
    }

    /**
     * Consume linked traveler pass on successful QR board (best-effort).
     */
    public function consumeTripForTicket(\App\Entity\AgencyTicket $ticket): void
    {
        $pass = $ticket->getTravelerPass();
        if (!$pass instanceof TravelerPass) {
            return;
        }
        if (TravelerPass::STATUS_ACTIVE !== $pass->getStatus()) {
            return;
        }
        $remaining = $pass->getTripsRemaining();
        if (null === $remaining || $remaining <= 0) {
            $pass->setStatus(TravelerPass::STATUS_EXHAUSTED);
            $pass->setTripsRemaining(0);

            return;
        }
        $pass->setTripsRemaining($remaining - 1);
        if ($pass->getTripsRemaining() <= 0) {
            $pass->setTripsRemaining(0);
            $pass->setStatus(TravelerPass::STATUS_EXHAUSTED);
        }
    }

    public function createPreorder(User $user, CreateTravelerPreorderDto $dto): TravelerPreorder
    {
        $offer = $this->offers->find($this->extractId((string) $dto->offerId));
        if (!$offer instanceof AgencyOffer) {
            throw new UnavailableDataException('Offer not found.');
        }

        $travelDate = $this->parseDate((string) $dto->travelDate);
        $holdHours = max(1, $offer->getPreorderHoldHours());
        $holdUntil = new \DateTimeImmutable(sprintf('+%d hours', $holdHours));

        $phone = $user->getPhone() ?? '';
        $normalizedPhone = '' !== trim($phone) ? $this->otpService->normalizePhone($phone) : '+243000000000';
        $passengerName = $dto->passengerName ?? $user->getDisplayName() ?? 'Traveler';
        $passengerId = 'TRV-' . substr((string) $user->getId(), -8);

        if (null !== $dto->beneficiaryId && '' !== trim($dto->beneficiaryId)) {
            $beneficiary = $this->em->getRepository(\App\Entity\TravelerBeneficiary::class)->find($dto->beneficiaryId);
            if ($beneficiary instanceof \App\Entity\TravelerBeneficiary
                && $beneficiary->getOwner()?->getId() === $user->getId()) {
                $passengerName = (string) $beneficiary->getFullName();
                $normalizedPhone = (string) $beneficiary->getPhone();
                if (null !== $beneficiary->getIdDocument() && '' !== $beneficiary->getIdDocument()) {
                    $passengerId = (string) $beneficiary->getIdDocument();
                }
            }
        }

        $preorder = new TravelerPreorder();
        $preorder->setUser($user);
        $preorder->setAgency($offer->getAgency());
        $preorder->setOffer($offer);
        $preorder->setStatus(TravelerPreorder::STATUS_HELD);
        $preorder->setPassengerName($passengerName);
        $preorder->setPassengerId($passengerId);
        $preorder->setPassengerPhone($normalizedPhone);
        $preorder->setTravelDatePreferred($travelDate);
        $preorder->setHoldUntil($holdUntil);

        $this->em->persist($preorder);
        $this->em->flush();

        return $preorder;
    }

    public function confirmPreorder(TravelerPreorder $preorder): TravelerPreorder
    {
        $this->agencyContext->assertOwns($preorder->getAgency());

        return $this->convertPreorderInternal($preorder, new ConvertTravelerPreorderDto());
    }

    public function convertPreorder(
        User $user,
        TravelerPreorder $preorder,
        ConvertTravelerPreorderDto $dto,
    ): TravelerPreorder {
        if ($preorder->getUser()?->getId() !== $user->getId()) {
            throw new UnavailableDataException('Preorder not found.');
        }

        return $this->convertPreorderInternal($preorder, $dto);
    }

    public function cancelPreorder(TravelerPreorder $preorder): TravelerPreorder
    {
        $this->agencyContext->assertOwns($preorder->getAgency());

        if (TravelerPreorder::STATUS_HELD !== $preorder->getStatus()) {
            throw new ConflictException('Only held preorders can be cancelled.');
        }

        $preorder->setStatus(TravelerPreorder::STATUS_CANCELLED);
        $this->em->flush();

        return $preorder;
    }

    private function convertPreorderInternal(
        TravelerPreorder $preorder,
        ConvertTravelerPreorderDto $dto,
    ): TravelerPreorder {
        if (TravelerPreorder::STATUS_HELD !== $preorder->getStatus()) {
            throw new ConflictException('Only held preorders can be converted.');
        }

        $holdUntil = $preorder->getHoldUntil();
        if ($holdUntil instanceof \DateTimeImmutable && $holdUntil < new \DateTimeImmutable()) {
            throw new ConflictException('Preorder hold has expired.');
        }

        if ($preorder->getBooking() instanceof AgencyBooking) {
            $preorder->setStatus(TravelerPreorder::STATUS_CONVERTED);
            $this->em->flush();

            return $preorder;
        }

        $offer = $preorder->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Preorder has no offer.');
        }

        $this->ticketIssuance->assertOfferSellable($offer);

        $travelDate = null !== $dto->travelDate && '' !== trim($dto->travelDate)
            ? $this->parseDate($dto->travelDate)
            : $preorder->getTravelDatePreferred();
        if (!$travelDate instanceof \DateTimeImmutable) {
            throw new UnprocessableEntityException('travelDate is required to convert this preorder.');
        }

        $transport = $offer->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportAvailability->assertAvailableForTravelDate($transport, $travelDate);
        }

        $seat = $this->resolveSeat($offer, $travelDate, $dto->seatNumber, $preorder->getSeatNumber());

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable($offer, $travelDate, $seat);

            $booking = new AgencyBooking();
            $booking->setAgency($preorder->getAgency());
            $booking->setOffer($offer);
            $booking->setPassengerName((string) $preorder->getPassengerName());
            $booking->setPassengerId((string) $preorder->getPassengerId());
            $booking->setPassengerPhone((string) $preorder->getPassengerPhone());
            $booking->setSeatNumber($seat);
            $booking->setTravelDate($travelDate);
            $booking->setStatus(AgencyBooking::STATUS_CONFIRMED);
            $booking->setChannel(AgencyBooking::CHANNEL_ONLINE);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_UNPAID);

            $this->em->persist($booking);
            $this->em->flush();

            $this->ticketIssuance->issueFromBooking($booking, sendSms: true);

            $preorder->setBooking($booking);
            $preorder->setSeatNumber($seat);
            $preorder->setTravelDatePreferred($travelDate);
            $preorder->setStatus(TravelerPreorder::STATUS_CONVERTED);

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $preorder;
    }

    private function resolveSeat(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        ?string $requestedSeat,
        ?string $preorderSeat,
    ): string {
        if (null !== $requestedSeat && '' !== trim($requestedSeat)) {
            return $this->occupancy->normalizeSeat($requestedSeat);
        }

        if (null !== $preorderSeat && '' !== trim($preorderSeat)) {
            return $this->occupancy->normalizeSeat($preorderSeat);
        }

        $availability = $this->occupancy->availability($offer, $travelDate);
        foreach ($availability['layout']['seatIds'] as $seatId) {
            if (!\in_array($seatId, $availability['occupiedSeats'], true)) {
                return $seatId;
            }
        }

        throw new ConflictException('Bus complet — aucune place disponible.');
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid travelDate, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
    }
}
