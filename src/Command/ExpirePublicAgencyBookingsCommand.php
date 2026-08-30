<?php

namespace App\Command;

use App\Manager\PublicAgencyBookingManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:expire-public-agency-bookings',
    description: 'Cancel expired unpaid online agency bookings and free seats',
)]
final class ExpirePublicAgencyBookingsCommand extends Command
{
    public function __construct(private PublicAgencyBookingManager $bookings)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->bookings->expirePendingBookings();
        $io->success(sprintf('Expired %d online booking(s).', $count));

        return Command::SUCCESS;
    }
}
