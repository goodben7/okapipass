<?php

namespace App\Service;

use App\Contract\NotificationSenderInterface;
use App\Entity\Notification;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service responsible for sending notifications through different channels.
 * 
 * This service uses different notification senders (NotificationSender)
 * registered with the tag 'app.notification_sender' to send notifications
 * according to the specified channel (email, SMS, system, etc.).
 */
final class NotificationService
{
    /** @var NotificationSenderInterface[] */ 
    private array $senders;

    /**
     * Constructor for the notification service.
     * 
     * @param EntityManagerInterface $em The Doctrine entity manager
     * @param iterable $senders Collection of notification senders
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[AutowireIterator('app.notification_sender')] iterable $senders
    )
    {
        $this->senders = iterator_to_array($senders);
    }

    /**
     * Sends a notification through the appropriate channel.
     * 
     * This method iterates through all available notification senders
     * and uses the first one that supports the channel specified in the notification.
     * Once the notification is sent, it is marked as read.
     * 
     * @param Notification $notification The notification to send
     * @throws \RuntimeException If no sender supports the specified channel
     */
    public function send(Notification $notification): void
    {
        // Check if the notification is already read
        if ($notification->getReadAt() !== null) {
            return;
        }

        $sentVia = $notification->getSentVia();
        
        foreach ($this->senders as $sender) {
            if ($sender->support($sentVia)) {
                $sender->send($notification);

                $this->markAsRead($notification);
                
                return;
            }
        }

        throw new \RuntimeException(sprintf(
            'No sender found for notification channel: %s',
            $sentVia
        ));
    }
    
    /**
     * Marks a notification as read.
     * 
     * @param Notification $notification The notification to mark as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTimeImmutable());
        
        $this->em->flush();
    }
}
