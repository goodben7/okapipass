<?php

namespace App\Command;

use App\Entity\Agency;
use App\Entity\Checkpoint;
use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:trips',
    description: 'Seed trips (CDF)',
)]
class SeedTripsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agencyRepo = $this->em->getRepository(Agency::class);
        $checkpointRepo = $this->em->getRepository(Checkpoint::class);
        $tripRepo = $this->em->getRepository(Trip::class);

        $agencyByName = [];
        foreach ([
            'Transco',
            'Simba Transport',
            'SOCITRANS',
            'Classic Coach RDC',
            'RATCO Express',
            'MullyKap Transport',
            'TMK Transport',
            'Lubumbashi Intercity Transport',
            'Kivu Express Transport',
            'Bukavu Interprovincial Bus',
            'Katanga Travel Bus',
            'Kasai Road Lines',
            'Congo Intercity Bus',
            'Kwilu Transport Service',
        ] as $name) {
            $agency = $agencyRepo->findOneBy(['name' => $name]);
            if ($agency instanceof Agency) {
                $agencyByName[$name] = $agency;
            }
        }

        $routes = [
            ['from' => 'Kinshasa', 'to' => 'Matadi', 'distance' => 350],
            ['from' => 'Kinshasa', 'to' => 'Kikwit', 'distance' => 520],
            ['from' => 'Kinshasa', 'to' => 'Tshikapa', 'distance' => 820],
            ['from' => 'Lubumbashi', 'to' => 'Kolwezi', 'distance' => 320],
            ['from' => 'Lubumbashi', 'to' => 'Likasi', 'distance' => 120],
            ['from' => 'Goma', 'to' => 'Bukavu', 'distance' => 200],
            ['from' => 'Goma', 'to' => 'Beni', 'distance' => 250],
            ['from' => 'Bukavu', 'to' => 'Uvira', 'distance' => 130],
            ['from' => 'Kananga', 'to' => 'Mbuji-Mayi', 'distance' => 180],
        ];

        $agencyFallback = \array_values($agencyByName);
        $agencyFallbackIndex = 0;

        $agencyPreferencesByDeparture = [
            'Kinshasa' => ['Transco', 'Simba Transport', 'RATCO Express', 'TMK Transport'],
            'Lubumbashi' => ['Classic Coach RDC', 'Lubumbashi Intercity Transport'],
            'Goma' => ['MullyKap Transport', 'Kivu Express Transport'],
            'Bukavu' => ['Bukavu Interprovincial Bus'],
            'Kananga' => ['Kasai Road Lines'],
        ];

        $baseDay = (new \DateTimeImmutable('tomorrow'))->setTime(6, 0);

        foreach (\array_values($routes) as $i => $route) {
            $departure = $checkpointRepo->findOneBy(['label' => $route['from']]);
            if (!$departure instanceof Checkpoint) {
                $departure = new Checkpoint();
                $departure->setLabel($route['from']);
                $departure->setActive(true);
                $this->em->persist($departure);
            }

            $arrival = $checkpointRepo->findOneBy(['label' => $route['to']]);
            if (!$arrival instanceof Checkpoint) {
                $arrival = new Checkpoint();
                $arrival->setLabel($route['to']);
                $arrival->setActive(true);
                $this->em->persist($arrival);
            }

            $agency = null;
            foreach (($agencyPreferencesByDeparture[$route['from']] ?? []) as $preferredName) {
                $agency = $agencyByName[$preferredName] ?? null;
                if ($agency instanceof Agency) {
                    break;
                }
            }
            if (!$agency instanceof Agency) {
                $agency = $agencyFallback[$agencyFallbackIndex] ?? null;
                $agencyFallbackIndex = \count($agencyFallback) > 0 ? (($agencyFallbackIndex + 1) % \count($agencyFallback)) : 0;
            }
            if (!$agency instanceof Agency) {
                continue;
            }

            $label = \sprintf('%s - %s', $route['from'], $route['to']);

            $departureTime = $baseDay->modify('+' . $i . ' hours');
            $durationHours = (int) \max(1, (int) \ceil(((int) $route['distance']) / 60));
            $arrivalTime = $departureTime->modify('+' . $durationHours . ' hours');

            $price = \number_format(((int) $route['distance']) * 100, 2, '.', '');

            $existing = $tripRepo->findOneBy([
                'agency' => $agency,
                'departure' => $departure,
                'arrival' => $arrival,
            ]);

            $trip = $existing ?: new Trip();
            $trip->setAgency($agency);
            $trip->setDeparture($departure);
            $trip->setArrival($arrival);
            $trip->setLabel($label);
            $trip->setDepartureTime($departureTime);
            $trip->setArrivalTime($arrivalTime);
            $trip->setPrice($price);
            $trip->setStatus(Trip::STATUS_ACTIVE);

            if (!$existing) {
                $this->em->persist($trip);
            }
        }

        $this->em->flush();
        $output->writeln('Trips seeded.');

        return Command::SUCCESS;
    }
}
