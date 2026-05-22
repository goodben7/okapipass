<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\WhatsappNotificationSender;
use App\Service\WhatsappPassBot;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UltraMsgWebhookController
{
    public function __construct(
        private WhatsappPassBot $bot,
        private WhatsappNotificationSender $sender,
        private LoggerInterface $logger,
        #[Autowire('%env(default::ULTRAMSG_WEBHOOK_KEY)%')]
        private ?string $webhookKey,
    ) {
    }

    #[Route(path: '/api/whatsapp/webhook/ultramsg', name: 'ultramsg_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $webhookKey = (string) ($this->webhookKey ?? '');
        if ('' !== $webhookKey) {
            $key = (string) $request->query->get('key', '');
            if (!hash_equals($webhookKey, $key)) {
                return new Response('Unauthorized', 401);
            }
        }

        $raw = (string) $request->getContent();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return new Response('Bad Request', 400);
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        if ($eventType !== 'message_received') {
            return new Response('OK', 200);
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return new Response('OK', 200);
        }

        if (($data['fromMe'] ?? false) === true) {
            return new Response('OK', 200);
        }

        $messageType = (string) ($data['type'] ?? '');
        if ($messageType !== 'chat') {
            return new Response('OK', 200);
        }

        $fromRaw = (string) ($data['from'] ?? '');
        $body = (string) ($data['body'] ?? '');
        $body = trim($body);

        $from = preg_replace('/@.+$/', '', $fromRaw) ?? $fromRaw;
        $from = preg_replace('/[^\d+]/', '', $from) ?? $from;
        if ($from !== '' && $from[0] !== '+') {
            $from = '+' . $from;
        }

        if ($from === '' || $body === '') {
            return new Response('OK', 200);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $reply = $this->bot->handleIncoming($from, $body, $baseUrl);

        if ($reply !== '') {
            $notification = new Notification();
            $notification->setTarget($from);
            $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
            $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
            $notification->setType('sys_upd');
            $notification->setTitle('OkapiPass');
            $notification->setBody($reply);

            try {
                $this->sender->send($notification);
            } catch (\Throwable $e) {
                $this->logger->error('ultramsg.webhook.reply_failed', [
                    'from' => $from,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return new Response('OK', 200);
    }
}
