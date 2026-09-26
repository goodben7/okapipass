<?php

namespace App\Command;

use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Repository\AgencyTicketRepository;
use App\Service\Traveler\TravelerNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'okapi:notify-departures',
    description: 'Send SMS departure reminders for tickets travelling tomorrow',
)]
final class NotifyDeparturesCommand extends Command
{
    public function __construct(
        private AgencyTicketRepository $tickets,
        private TravelerNotificationService $notifications,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            null,
            InputOption::VALUE_OPTIONAL,
            'Travel date to remind (YYYY-MM-DD). Defaults to tomorrow.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dateRaw = $input->getOption('date');
        $travelDate = \is_string($dateRaw) && '' !== trim($dateRaw)
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw)
            : (new \DateTimeImmutable('tomorrow'))->setTime(0, 0);

        if (false === $travelDate) {
            $io->error('Invalid --date, expected YYYY-MM-DD.');

            return Command::FAILURE;
        }

        $tickets = $this->tickets->findIssuedForTravelDate($travelDate);
        $sent = 0;
        $skipped = 0;

        foreach ($tickets as $ticket) {
            $phone = trim((string) $ticket->getPassengerPhone());
            if ('' === $phone) {
                ++$skipped;
                continue;
            }

            $offer = $ticket->getOffer();
            if (!$offer instanceof AgencyOffer) {
                ++$skipped;
                continue;
            }

            try {
                $this->notifications->notifyDepartureReminder(
                    $phone,
                    (string) $offer->getOrigin(),
                    (string) $offer->getDestination(),
                    $travelDate->format('Y-m-d'),
                    (string) ($offer->getDepartureTime() ?? '06:00'),
                );
                ++$sent;
            } catch (\Throwable $e) {
                $io->warning(sprintf('Failed for ticket %s: %s', $ticket->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Departure reminders for %s: %d sent, %d skipped (of %d ISSUED tickets).',
            $travelDate->format('Y-m-d'),
            $sent,
            $skipped,
            \count($tickets),
        ));

        return Command::SUCCESS;
    }
}
