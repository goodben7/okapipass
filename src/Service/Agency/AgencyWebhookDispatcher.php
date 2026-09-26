<?php

namespace App\Service\Agency;

use App\Entity\Agency;
use App\Entity\AgencyWebhookSubscription;
use App\Repository\AgencyWebhookSubscriptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AgencyWebhookDispatcher
{
    public function __construct(
        private AgencyWebhookSubscriptionRepository $subscriptions,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(Agency $agency, string $event, array $payload): void
    {
        $subs = $this->subscriptions->findActiveForEvent($agency, $event);
        foreach ($subs as $sub) {
            $this->deliver($sub, $event, $payload);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deliver(AgencyWebhookSubscription $sub, string $event, array $payload): void
    {
        try {
            $body = [
                'event' => $event,
                'agencyId' => $sub->getAgency()?->getId(),
                'payload' => $payload,
                'sentAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
            $json = json_encode($body, \JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', $json, (string) $sub->getSecret());

            $this->httpClient->request('POST', (string) $sub->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-OkapiPass-Event' => $event,
                    'X-OkapiPass-Signature' => $signature,
                ],
                'body' => $json,
                'timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('agency.webhook.dispatch_failed', [
                'subscriptionId' => $sub->getId(),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
