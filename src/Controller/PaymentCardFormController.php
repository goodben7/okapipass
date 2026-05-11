<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Service\FlexPayGateway;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentCardFormController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FlexPayGateway $gateway,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/payments/{id}/card/form', name: 'payment_card_form', methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {

        /** @var Payment|null $payment */
        $payment = $this->em->find(Payment::class, $id);

        if (!$payment) {
            return new Response('Payment not found', 404);
        }

        if (Payment::METHOD_CARD !== $payment->getMethod()) {
            return new Response('Payment method is not CARD', 400);
        }

        $amount = (string) $payment->getAmount();
        $currency = (string) $payment->getCurrency();
        $paymentReference = (string) $payment->getReference();
        $ticketRef = (string) ($payment->getTicket()?->getUniqueReference() ?: $payment->getTicket()?->getId() ?: $paymentReference);

        $form = $this->gateway->buildCardPaymentForm(
            paymentId: (string) $payment->getId(),
            paymentReference: $paymentReference,
            ticketRef: $ticketRef,
            amount: $amount,
            currency: $currency,
        );

        $action = (string) ($form['action'] ?? '');
        $fields = \is_array($form['fields'] ?? null) ? $form['fields'] : [];

        $authorization = (string) ($fields['authorization'] ?? '');
        $tokenNoBearer = \preg_replace('/^\s*Bearer\s+/i', '', $authorization) ?? $authorization;

        $this->logger->info(
            'flexpay.card.form.render paymentId={paymentId} amount={amount} currency={currency} merchant={merchant} cardReference={cardReference} callbackUrl={callbackUrl} approveUrl={approveUrl} cancelUrl={cancelUrl} declineUrl={declineUrl} authLen={authLen} tokenHash={tokenHash} userIdentifier={userIdentifier} ip={ip} userAgent={userAgent}',
            [
                'paymentId' => (string) $payment->getId(),
                'amount' => $amount,
                'currency' => $currency,
                'merchant' => (string) ($fields['merchant'] ?? ''),
                'cardReference' => (string) ($fields['reference'] ?? ''),
                'callbackUrl' => (string) ($fields['callback_url'] ?? ''),
                'approveUrl' => (string) ($fields['approve_url'] ?? ''),
                'cancelUrl' => (string) ($fields['cancel_url'] ?? ''),
                'declineUrl' => (string) ($fields['decline_url'] ?? ''),
                'authLen' => (string) \strlen($authorization),
                'tokenHash' => \hash('sha256', (string) $tokenNoBearer),
                'userIdentifier' => (string) ($this->security->getUser()?->getUserIdentifier() ?? ''),
                'ip' => (string) ($request->getClientIp() ?? ''),
                'userAgent' => (string) ($request->headers->get('user-agent') ?? ''),
            ]
        );

        $inputs = '';
        foreach ($fields as $k => $v) {
            $name = \htmlspecialchars((string) $k, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $value = \htmlspecialchars((string) $v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $inputs .= '<input type="hidden" name="' . $name . '" value="' . $value . '">' . "\n";
        }

        $html = '<!DOCTYPE html>' . "\n"
            . '<html lang="fr">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="UTF-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
            . '<title>Paiement carte</title>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '<form id="flexpay-card-form" method="POST" action="' . \htmlspecialchars($action, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n"
            . $inputs
            . '</form>' . "\n"
            . '<script>document.getElementById("flexpay-card-form").submit();</script>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
