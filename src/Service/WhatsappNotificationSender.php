<?php

namespace App\Service;

use App\Contract\NotificationSenderInterface;
use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class WhatsappNotificationSender implements NotificationSenderInterface
{
    private const string ULTRAMSG_API_URL = 'https://api.ultramsg.com';

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface     $logger,
        private string              $whatsappApiKey,
        private string              $whatsappInstanceId
    )
    {
    }

    public function send(Notification $notification): void
    {
        $context = $notification->getTemplateContext() ?? $notification->getData() ?? [];
        $pdfUrl = $context['pdf_url'] ?? null;
        
        $this->logger->info('WhatsappNotificationSender: Starting send process', [
            'has_pdf_url' => isset($pdfUrl),
            'pdf_url' => $pdfUrl,
            'context_keys' => array_keys($context)
        ]);

        $to = $this->formatPhoneNumber($notification->getTarget());
        
        // 1. Envoi du PDF en premier (si présent)
        if (is_string($pdfUrl) && $pdfUrl !== '') {
            $this->logger->info('WhatsappNotificationSender: PDF URL found, attempting to send...', ['url' => $pdfUrl]);
            $this->sendDocument($to, $pdfUrl, 'Ticket-' . ($context['reference'] ?? 'Pass') . '.pdf');
        } else {
            $this->logger->warning('WhatsappNotificationSender: No PDF URL provided in notification context');
        }

        // 2. Envoi du message TEXTE
        $endpoint = self::ULTRAMSG_API_URL . "/{$this->whatsappInstanceId}/messages/chat";
        
        try {
            $response = $this->client->request('POST', $endpoint, [
                'body' => [
                    'token' => $this->whatsappApiKey,
                    'to' => $to,
                    'body' => $this->formatMessage($notification)
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('WhatsappNotificationSender: Text message failed', ['status' => $response->getStatusCode(), 'content' => $response->getContent(false)]);
            }
        } catch (\Exception $e) {
            $this->logger->error('WhatsappNotificationSender: Text message exception', ['message' => $e->getMessage()]);
        }
    }

    private function sendDocument(string $to, string $url, string $filename): void
    {
        $endpoint = self::ULTRAMSG_API_URL . "/{$this->whatsappInstanceId}/messages/document";
        try {
            $response = $this->client->request('POST', $endpoint, [
                'body' => [
                    'token' => $this->whatsappApiKey,
                    'to' => $to,
                    'filename' => $filename,
                    'document' => $url,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('WhatsappNotificationSender: PDF send failed', [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(false),
                    'url' => $url
                ]);
            } else {
                $this->logger->info('WhatsappNotificationSender: PDF sent successfully', [
                    'to' => $to,
                    'response' => $response->toArray(false)
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('WhatsappNotificationSender: PDF exception', ['message' => $e->getMessage()]);
        }
    }
    
    private function formatMessage(Notification $notification): string
    {
        $type = (string) ($notification->getType() ?? '');
        $title = trim((string) ($notification->getTitle() ?? ''));
        $subject = trim((string) ($notification->getSubject() ?? ''));
        $body = trim((string) ($notification->getBody() ?? ''));

        $context = $notification->getTemplateContext() ?? $notification->getData() ?? [];
        if (!is_array($context)) {
            $context = [];
        }

        $actionUrl = null;
        if (isset($context['action_url']) && is_string($context['action_url']) && trim($context['action_url']) !== '') {
            $actionUrl = trim($context['action_url']);
        }
        $actionText = null;
        if (isset($context['action_text']) && is_string($context['action_text']) && trim($context['action_text']) !== '') {
            $actionText = trim($context['action_text']);
        }

        $category = null;
        if (str_starts_with($type, 'tkt_')) {
            $category = 'Ticket';
        } elseif (str_starts_with($type, 'pay_')) {
            $category = 'Paiement';
        } elseif (str_starts_with($type, 'gps_')) {
            $category = 'GoPass';
        } elseif (str_starts_with($type, 'prv_')) {
            $category = 'Province';
        } elseif (str_starts_with($type, 'chk_')) {
            $category = 'Ville';
        } elseif (str_starts_with($type, 'agn_')) {
            $category = 'Agence';
        } elseif (str_starts_with($type, 'doc_')) {
            $category = 'Document';
        } elseif (str_starts_with($type, 'usr_')) {
            $category = 'Compte';
        } elseif (str_starts_with($type, 'sys_')) {
            $category = 'Système';
        }

        $header = $title !== '' ? $title : ($subject !== '' ? $subject : 'OkapiPass');
        if ($category !== null) {
            $header .= " — {$category}";
        }

        $lines = ["*{$header}*"];
        if ($body !== '') {
            $lines[] = $body;
        }

        $details = [];
        foreach ($context as $key => $value) {
            // On masque les clés techniques pour ne pas polluer le message WhatsApp
            if (in_array($key, [
                'action_url', 
                'action_text', 
                'pdf_url', 
                'reference', 
                'whatsapp_paid_notified', 
                'whatsapp_failed_notified'
            ], true)) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'Oui' : 'Non';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $value = (string) $value;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $details[(string) $key] = $value;
        }

        if ($details !== []) {
            $lines[] = '';
            $lines[] = '*Détails*';
            foreach ($details as $key => $value) {
                $lines[] = "- {$key}: *{$value}*";
            }
        }

        if ($actionUrl !== null) {
            $lines[] = '';
            $lines[] = sprintf('%s: %s', $actionText ?? 'Voir les détails', $actionUrl);
        }

        return implode("\n", $lines);
    }

    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        return $cleaned;
    }

    public function support(string $sentVia): bool
    {
        return $sentVia === Notification::SENT_VIA_WHATSAPP;
    }
}
