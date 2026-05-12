<?php

namespace App\Command;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Message\SendNotificationMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'okapi:test-email',
    description: 'Test sending email notification via NotificationService',
)]
class TestEmailCommand extends Command
{
    public function __construct(private MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send the test notification to')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Email subject', 'Test notification from OkapiPass')
            ->addOption('body', 'b', InputOption::VALUE_OPTIONAL, 'Email body content', '<h2>Test Email</h2><p>Ceci est un email de test envoyé depuis la commande go:test-email.</p>')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Notification type', NotificationType::TICKET_CREATED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $subject = $input->getOption('subject');
        $body = $input->getOption('body');
        $type = $input->getOption('type');

        $io->note(sprintf('Preparing to send test email to: %s', $email));

        try {
            // Create notification entity
            $notification = new Notification();
            $notification->setTarget($email);
            $notification->setSubject($subject);
            $notification->setBody($body);
            $notification->setType($type);
            $notification->setTargetType(Notification::TARGET_TYPE_EMAIL);
            $notification->setSentVia(Notification::SENT_VIA_EMAIL);

            // Create and dispatch SendNotificationMessage
            $message = new SendNotificationMessage($notification); 
            $this->messageBus->dispatch($message);
            
            $io->success('Test email notification dispatched successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error dispatching test email notification: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
