<?php

namespace App\Message;

use App\Entity\Notification;
use App\Event\EventMessageInterface;

readonly class SendNotificationMessage implements EventMessageInterface
{
    public function __construct(
        private Notification $notification
    )
    {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }
}