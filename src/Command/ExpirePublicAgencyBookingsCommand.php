<?php

namespace App\Command;

use App\Manager\PublicAgencyBookingManager;
use App\Manager\PublicAgencyGroupBookingManager;
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
    public function __construct(private PublicAgencyBookingManager $bookings, private PublicAgencyGroupBookingManager $groups)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $singleCount = $this->bookings->expirePendingBookings();
        $groupCount = $this->groups->expirePendingGroups();
        $io->success(sprintf('Expired %d online booking(s) and %d group booking(s).', $singleCount, $groupCount));

        return Command::SUCCESS;
    }
}
