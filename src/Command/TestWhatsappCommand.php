<?php

namespace App\Command;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Message\SendNotificationMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'okapi:test-whatsapp',
    description: 'Test sending WhatsApp notification via NotificationService',
)]
class TestWhatsappCommand extends Command
{
    public function __construct(private MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number (E.164). Example: +2438xxxxxxx')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Message title', 'OkapiPass')
            ->addOption('body', null, InputOption::VALUE_OPTIONAL, 'Message body', 'Ceci est un message WhatsApp de test envoyé depuis okapi:test-whatsapp.')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Notification type', NotificationType::SYSTEM_UPDATE)
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'JSON context to enrich the WhatsApp message (supports action_url/action_text). Example: {"Ticket":"TKT-001","Montant":"10 USD","action_url":"https://...","action_text":"Payer"}')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $phone = (string) $input->getArgument('phone');
        $title = (string) $input->getOption('title');
        $body = (string) $input->getOption('body');
        $type = (string) $input->getOption('type');
        $contextJson = $input->getOption('context');

        $io->note(sprintf('Preparing to send test WhatsApp message to: %s', $phone));

        try {
            $notification = new Notification();
            $notification->setTarget($phone);
            $notification->setTitle($title);
            $notification->setBody($body);
            $notification->setType($type);
            $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
            $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);

            if (is_string($contextJson) && trim($contextJson) !== '') {
                $context = json_decode($contextJson, true);
                if (!is_array($context)) {
                    throw new \InvalidArgumentException('Invalid --context JSON. Expected an object JSON like {"key":"value"}.');
                }
                $notification->setTemplateContext($context);
            }

            $message = new SendNotificationMessage($notification);
            $this->messageBus->dispatch($message);
            
            $io->success('Test WhatsApp notification dispatched successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error dispatching test WhatsApp notification: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
