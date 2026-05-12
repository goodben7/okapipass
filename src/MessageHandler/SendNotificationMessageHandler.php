<?php

namespace App\MessageHandler;

use App\Message\SendNotificationMessage;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
readonly class SendNotificationMessageHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private LoggerInterface     $logger,
    )
    {
    }

    public function __invoke(SendNotificationMessage $event): void
    {
        $notification = $event->getNotification();

        try {
            $this->notificationService->send($notification);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}