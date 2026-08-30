<?php

namespace App\Controller\PublicAgency;

use App\Contract\AgencyFlexPayClientInterface;
use App\Entity\AgencyPayment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicAgencyPaymentCardFormController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyFlexPayClientInterface $flexPay,
    ) {
    }

    #[Route(path: '/api/public/agency/payments/{id}/card/form', name: 'public_agency_payment_card_form', methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        /** @var AgencyPayment|null $payment */
        $payment = $this->em->find(AgencyPayment::class, $id);

        if (!$payment instanceof AgencyPayment) {
            return new Response('Payment not found', 404);
        }

        if (AgencyPayment::METHOD_CARD !== $payment->getMethod()) {
            return new Response('Payment method is not CARD', 400);
        }

        if (AgencyPayment::CHANNEL_ONLINE !== $payment->getChannel()) {
            return new Response('Payment is not an online agency payment', 400);
        }

        $booking = $payment->getBooking();
        $ticketRef = (string) ($booking?->getPublicToken() ?: $payment->getReference());

        $form = $this->flexPay->buildAgencyCardPaymentForm($payment, $ticketRef);
        $action = (string) ($form['action'] ?? '');
        $fields = \is_array($form['fields'] ?? null) ? $form['fields'] : [];

        $inputs = '';
        foreach ($fields as $k => $v) {
            $name = \htmlspecialchars((string) $k, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $value = \htmlspecialchars((string) $v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $inputs .= '<input type="hidden" name="' . $name . '" value="' . $value . '">' . "\n";
        }

        $html = '<!DOCTYPE html>' . "\n"
            . '<html lang="fr">' . "\n"
            . '<head><meta charset="UTF-8"><title>Paiement carte</title></head>' . "\n"
            . '<body>' . "\n"
            . '<form id="flexpay-card-form" method="POST" action="' . \htmlspecialchars($action, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n"
            . $inputs
            . '</form>' . "\n"
            . '<script>document.getElementById("flexpay-card-form").submit();</script>' . "\n"
            . '</body></html>' . "\n";

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
