<?php

namespace App\Manager;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewPaymentModel;
use App\Repository\PaymentRepository;
use App\Service\ActivityEventDispatcher;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
        private RequestStack $requestStack,
        private PaymentRepository $paymentRepository,
        private TicketUniqueReferenceGenerator $referenceGenerator,
    ) {
    }

    public function createFrom(NewPaymentModel $model): Payment
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $paidBy = null;

        if (null !== $userId) {
            /** @var User $paidBy */
            $paidBy = $this->queries->ask(new GetUserDetails($userId));
        }

        $payment = new Payment();

        $payment->setReference($model->reference);
        $payment->setTicket($model->ticket);
        $payment->setAmount($model->amount);
        $payment->setCurrency($model->currency);
        $payment->setMethod($model->method);
        $payment->setStatus(Payment::STATUS_PENDING);
        $payment->setPaidBy($paidBy ?: null);

        $this->em->persist($payment);
        $this->em->flush();

        $this->eventDispatcher->dispatch($payment, Payment::EVENT_PAYMENT_CREATED); 



        return $payment;
    }

    public function handleWebhook(): ?Payment
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $payload = \json_decode($request->getContent(), true);

        if (!$payload) {
            return null;
        }

        $transactionId = $payload['transactionId'] ?? null;

        if (!$transactionId) {
            return null;
        }

    
        /** @var Payment $payment */
        $payment = $this->paymentRepository->findOneBy(['providerTransactionId' => $transactionId]);

        if (!$payment) {
            return null;
        }

        if (($payload['status'] ?? null) === 'SUCCESS') {
            $now = new \DateTimeImmutable();

            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_PAID);
            }

            if (null === $payment->getPaidAt()) {
                $payment->setPaidAt($now);
            }

            $ticket = $payment->getTicket();

            if ($ticket instanceof Ticket) {
                if (Ticket::STATUS_VALIDATED !== $ticket->getStatus()) {
                    $ticket->setStatus(Ticket::STATUS_VALIDATED);
                }

                if (null === $ticket->getValidatedAt()) {
                    $ticket->setValidatedAt($now);
                }

                if (null === $ticket->getUniqueReference()) {
                    $ticket->setUniqueReference($this->referenceGenerator->generateFor($ticket));
                }

                if (Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                    $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_PAID);
                }
            }
        } else {
            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }
        }

        $payment->setProviderWebhook($payload);

        $this->em->flush();

        return $payment;
    }
}
