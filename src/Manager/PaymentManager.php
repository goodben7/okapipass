<?php

namespace App\Manager;

use App\Entity\Payment;
use App\Entity\User;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewPaymentModel;
use App\Service\ActivityEventDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
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
}
