<?php

namespace App\Contract;

use App\Entity\Notification;

interface NotificationSenderInterface
{
    public function send(Notification $notification): void;
    public function support(string $sentVia): bool;
}