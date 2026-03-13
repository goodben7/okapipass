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
                'description' => 'Grand jardin botanique historique créé à l’époque coloniale, célèbre pour sa collection de plantes tropicales.',
                'city' => ['label' => 'Matadi', 'latitude' => -5.8386, 'longitude' => 13.4631],
                'address' => 'Kisantu, Kongo Central, RDC',
                'latitude' => '-5.1320',
                'longitude' => '15.1000',
                'entryPrice' => '14000.00',
                'openingHours' => 'Mon-Sun 08:00-17:00',
            ],
            [
                'name' => 'Chutes de la Lofoi',
                'description' => 'L’une des plus hautes chutes d’eau d’Afrique situées dans le Parc National de Kundelungu.',
                'city' => ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
                'address' => 'Parc National de Kundelungu, Haut-Katanga, RDC',
                'latitude' => '-10.7000',
                'longitude' => '28.0500',
                'entryPrice' => '28000.00',
                'openingHours' => 'Mon-Sun 07:00-18:00',
            ],
            [
                'name' => 'Parc de la Vallée de la Nsele',
                'description' => 'Grand parc naturel près de Kinshasa offrant des safaris, des animaux sauvages et des activités écotouristiques.',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'address' => 'Nsele, Kinshasa, RDC',
                'latitude' => '-4.3000',
                'longitude' => '15.4500',
                'entryPrice' => '56000.00',
                'openingHours' => 'Mon-Sun 08:00-18:00',
            ],
            [
                'name' => 'Mont Nyiragongo',
                'description' => 'Volcan actif célèbre pour son lac de lave spectaculaire situé dans le Parc National des Virunga.',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'address' => 'Parc National des Virunga, Nord-Kivu, RDC',
                'latitude' => '-1.5200',
                'longitude' => '29.2500',
                'entryPrice' => '840000.00',
                'openingHours' => 'Mon-Sun 06:00-17:00',
            ],
            [
                'name' => 'Lac Kivu',
                'description' => 'Magnifique lac volcanique partagé entre la RDC et le Rwanda, très populaire pour le tourisme et les paysages.',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'address' => 'Goma, Nord-Kivu, RDC',
                'latitude' => '-1.6800',
                'longitude' => '29.2300',
                'entryPrice' => '0.00',
                'openingHours' => 'Mon-Sun 00:00-23:59',
            ],
            [
                'name' => 'Parc National des Virunga',
                'description' => 'Le plus ancien parc national d’Afrique, célèbre pour ses gorilles de montagne et sa biodiversité exceptionnelle.',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'address' => 'Nord-Kivu, RDC',
                'latitude' => '-0.9000',
                'longitude' => '29.1000',
                'entryPrice' => '560000.00',
                'openingHours' => 'Mon-Sun 06:00-18:00',
            ],
            [
                'name' => 'Orchid Safari Club - Point de vue',
                'description' => 'Point panoramique offrant une vue spectaculaire sur le lac Kivu et la ville de Bukavu.',
                'city' => ['label' => 'Bukavu', 'latitude' => -2.5044, 'longitude' => 28.8618],
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'latitude' => '-2.5100',
                'longitude' => '28.8600',
                'entryPrice' => '8400.00',
                'openingHours' => 'Mon-Sun 07:00-18:30',
            ],
            [
                'name' => 'Chutes Wagenia',
                'description' => 'Spectaculaires rapides du fleuve Congo célèbres pour la pêche traditionnelle Wagenia.',
                'city' => ['label' => 'Kisangani', 'latitude' => 0.5153, 'longitude' => 25.1909],
                'address' => 'Kisangani, Tshopo, RDC',
                'latitude' => '0.5200',
                'longitude' => '25.1900',
                'entryPrice' => '5600.00',
                'openingHours' => 'Mon-Sun 06:00-18:00',
            ],
            [
                'name' => 'Marché Central de Mbuji-Mayi',
                'description' => 'Grand marché populaire où l’on découvre l’artisanat et les produits locaux de la région du Kasaï.',
                'city' => ['label' => 'Mbuji-Mayi', 'latitude' => -6.1360, 'longitude' => 23.5898],
                'address' => 'Mbuji-Mayi, Kasaï-Oriental, RDC',
                'latitude' => '-6.1400',
                'longitude' => '23.5900',
                'entryPrice' => '0.00',
                'openingHours' => 'Mon-Sat 06:00-18:00',
            ],
            [
                'name' => 'Chutes Katende',
                'description' => 'Site naturel impressionnant situé près de Kananga avec un grand potentiel hydroélectrique et touristique.',
                'city' => ['label' => 'Kananga', 'latitude' => -5.8962, 'longitude' => 22.4166],
                'address' => 'Kananga, Kasaï-Central, RDC',
                'latitude' => '-6.1000',
                'longitude' => '22.5000',
                'entryPrice' => '8400.00',
                'openingHours' => 'Mon-Sun 07:00-18:00',
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
