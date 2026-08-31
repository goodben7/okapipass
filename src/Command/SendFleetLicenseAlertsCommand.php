<?php

namespace App\Command;

use App\Repository\AgencyDriverRepository;
use App\Service\Agency\AgencyFleetNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fleet:send-license-alerts',
    description: 'Send WhatsApp alerts for agency drivers with expiring licenses',
)]
final class SendFleetLicenseAlertsCommand extends Command
{
    public function __construct(
        private AgencyDriverRepository $drivers,
        private AgencyFleetNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Alert window in days', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $until = (new \DateTimeImmutable('today'))->modify(sprintf('+%d days', $days));

        $sent = 0;
        foreach ($this->drivers->findAllExpiringUntil($until) as $driver) {
            $agency = $driver->getAgency();
            if (null === $agency) {
                continue;
            }
            $this->notifier->notifyLicenseExpiring($agency, $driver);
            ++$sent;
        }

        $io->success(sprintf('Dispatched %d license alert(s) (window: %d days).', $sent, $days));

        return Command::SUCCESS;
    }
}
