<?php

namespace App\Command;

use App\Entity\Checkpoint;
use App\Entity\TouristSite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:tourist-sites',
    description: 'Seed tourist sites (RDC)',
)]
class SeedTouristSitesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checkpointRepo = $this->em->getRepository(Checkpoint::class);
        $siteRepo = $this->em->getRepository(TouristSite::class);

        $specs = [
            [
                'name' => 'Jardin Botanique de Kisantu',
                'description' => 'Botanical garden',
                'city' => ['label' => 'Matadi', 'latitude' => -5.8386, 'longitude' => 13.4631],
                'address' => 'Kongo Central, RDC',
                'latitude' => '-5.8200000',
                'longitude' => '13.4500000',
                'entryPrice' => '5.00',
                'openingHours' => 'Mon-Sun 08:00-17:00',
            ],
            [
                'name' => 'Chutes de la Lofoi',
                'description' => 'Waterfall',
                'city' => ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
                'address' => 'Haut-Katanga, RDC',
                'latitude' => '-11.7500000',
                'longitude' => '27.5500000',
                'entryPrice' => '10.00',
                'openingHours' => 'Mon-Sun 07:00-18:00',
            ],
            [
                'name' => 'Parc de la Vallée de la Nsele',
                'description' => 'Natural park',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'address' => 'Kinshasa, RDC',
                'latitude' => '-4.3000000',
                'longitude' => '15.4500000',
                'entryPrice' => '15.00',
                'openingHours' => 'Mon-Sun 08:00-18:00',
            ],
            [
                'name' => 'Lac Kivu - Point de Vue',
                'description' => 'Lake viewpoint',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'address' => 'Goma, Nord-Kivu, RDC',
                'latitude' => '-1.6800000',
                'longitude' => '29.2300000',
                'entryPrice' => '3.00',
                'openingHours' => 'Mon-Sun 06:00-19:00',
            ],
            [
                'name' => 'Belvédère de Bukavu',
                'description' => 'Viewpoint',
                'city' => ['label' => 'Bukavu', 'latitude' => -2.5044, 'longitude' => 28.8618],
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'latitude' => '-2.5000000',
                'longitude' => '28.8700000',
                'entryPrice' => '2.00',
                'openingHours' => 'Mon-Sun 07:00-18:30',
            ],
            [
                'name' => 'Rives du Fleuve Congo (Kisangani)',
                'description' => 'River walk',
                'city' => ['label' => 'Kisangani', 'latitude' => 0.5153, 'longitude' => 25.1909],
                'address' => 'Kisangani, Tshopo, RDC',
                'latitude' => '0.5160000',
                'longitude' => '25.2000000',
                'entryPrice' => '0.00',
                'openingHours' => 'Mon-Sun 00:00-23:59',
            ],
            [
                'name' => 'Marché Central (Mbuji-Mayi)',
                'description' => 'Local market',
                'city' => ['label' => 'Mbuji-Mayi', 'latitude' => -6.1360, 'longitude' => 23.5898],
                'address' => 'Mbuji-Mayi, Kasaï-Oriental, RDC',
                'latitude' => '-6.1400000',
                'longitude' => '23.5900000',
                'entryPrice' => '0.00',
                'openingHours' => 'Mon-Sat 06:00-18:00',
            ],
            [
                'name' => 'Centre Culturel (Kananga)',
                'description' => 'Cultural center',
                'city' => ['label' => 'Kananga', 'latitude' => -5.8962, 'longitude' => 22.4166],
                'address' => 'Kananga, Kasaï-Central, RDC',
                'latitude' => '-5.8950000',
                'longitude' => '22.4200000',
                'entryPrice' => '1.50',
                'openingHours' => 'Tue-Sun 09:00-17:00',
            ],
            [
                'name' => 'Esplanade Centrale (Kolwezi)',
                'description' => 'City square',
                'city' => ['label' => 'Kolwezi', 'latitude' => -10.7167, 'longitude' => 25.4667],
                'address' => 'Kolwezi, Lualaba, RDC',
                'latitude' => '-10.7170000',
                'longitude' => '25.4700000',
                'entryPrice' => '0.00',
                'openingHours' => 'Mon-Sun 00:00-23:59',
            ],
            [
                'name' => 'Parc Urbain (Likasi)',
                'description' => 'Urban park',
                'city' => ['label' => 'Likasi', 'latitude' => -10.9814, 'longitude' => 26.7384],
                'address' => 'Likasi, Haut-Katanga, RDC',
                'latitude' => '-10.9800000',
                'longitude' => '26.7400000',
                'entryPrice' => '1.00',
                'openingHours' => 'Mon-Sun 08:00-18:00',
            ],
        ];

        foreach ($specs as $spec) {
            $existing = $siteRepo->findOneBy(['name' => $spec['name']]);
            $site = $existing ?: new TouristSite();

            $city = $checkpointRepo->findOneBy(['label' => $spec['city']['label']]);
            if (null === $city) {
                $city = new Checkpoint();
                $city->setLabel($spec['city']['label']);
                $city->setActive(true);
                $city->setLatitude($spec['city']['latitude']);
                $city->setLongitude($spec['city']['longitude']);
                $this->em->persist($city);
            }

            $site->setName($spec['name']);
            $site->setDescription($spec['description']);
            $site->setCity($city);
            $site->setAddress($spec['address']);
            $site->setLatitude($spec['latitude']);
            $site->setLongitude($spec['longitude']);
            $site->setEntryPrice($spec['entryPrice']);
            $site->setOpeningHours($spec['openingHours']);
            $site->setStatus(TouristSite::STATUS_ACTIVE);

            if (!$existing) {
                $this->em->persist($site);
            }
        }

        $this->em->flush();
        $output->writeln('Tourist sites seeded.');

        return Command::SUCCESS;
    }
}
