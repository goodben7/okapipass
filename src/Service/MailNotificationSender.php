<?php

namespace App\Service;

use App\Contract\NotificationSenderInterface;
use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

readonly class MailNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerSender,
        private string $mailerSenderName
    )
    {
    }

    public function send(Notification $notification): void
    {
        $template = $notification->getTemplate() ?? 'emails/notification.html.twig';
        $context = [
            'subject' => $notification->getSubject() ?? 'Vous avez une nouvelle notification',
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'context' => $notification->getTemplateContext() ?? $notification->getData() ?? []
        ];
        
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerSender, $this->mailerSenderName))
            ->to(new Address($notification->getTarget()))
            ->subject($notification->getSubject() ?? 'Vous avez une nouvelle notification')
            ->htmlTemplate($template)
            ->context($context);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Email service error : ' . $e->getMessage());
        }
    }

    public function support(string $sentVia): bool 
    {
        return $sentVia === Notification::SENT_VIA_EMAIL;
    }
}
