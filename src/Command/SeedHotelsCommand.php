<?php

namespace App\Command;

use App\Entity\Checkpoint;
use App\Entity\Hotel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:hotels',
    description: 'Seed hotels (RDC)',
)]
class SeedHotelsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checkpointRepo = $this->em->getRepository(Checkpoint::class);
        $hotelRepo = $this->em->getRepository(Hotel::class);

        $specs = [
            [
                'name' => 'Pullman Kinshasa Grand Hotel',
                'description' => 'Hôtel 5 étoiles situé au centre de Kinshasa, idéal pour les voyageurs d’affaires et les séjours de luxe.',
                'phone' => '+243817000001',
                'email' => 'reservation@pullmankinshasa.com',
                'address' => 'Boulevard du 30 Juin, Kinshasa, RDC',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'latitude' => '-4.3250',
                'longitude' => '15.3222',
                'rating' => 5,
                'price' => '616000.00',
            ],
            [
                'name' => 'Fleuve Congo Hotel',
                'description' => 'Hôtel moderne avec vue sur le fleuve Congo, offrant des chambres haut de gamme et des services premium.',
                'phone' => '+243817000002',
                'email' => 'info@fleuvecongohotel.com',
                'address' => 'Boulevard Colonel Tshatshi, Kinshasa, RDC',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'latitude' => '-4.3147',
                'longitude' => '15.3119',
                'rating' => 5,
                'price' => '560000.00',
            ],
            [
                'name' => 'Hotel Memling',
                'description' => 'Hôtel historique de Kinshasa proposant des chambres confortables et des installations pour conférences.',
                'phone' => '+243817000003',
                'email' => 'contact@memling.net',
                'address' => 'Avenue du Tchad, Kinshasa, RDC',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'latitude' => '-4.3225',
                'longitude' => '15.3081',
                'rating' => 4,
                'price' => '420000.00',
            ],
            [
                'name' => 'Karavia Grand Hotel',
                'description' => 'Hôtel de luxe situé au bord du lac Kipopo à Lubumbashi, avec piscine et restaurant gastronomique.',
                'phone' => '+243817000004',
                'email' => 'reservations@karavia-grand.com',
                'address' => 'Route du Golf, Lubumbashi, RDC',
                'city' => ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
                'latitude' => '-11.7050',
                'longitude' => '27.4800',
                'rating' => 5,
                'price' => '504000.00',
            ],
            [
                'name' => 'Planet Hotel',
                'description' => 'Hôtel moderne à Lubumbashi offrant des chambres élégantes et des installations pour les voyageurs internationaux.',
                'phone' => '+243817000005',
                'email' => 'info@planethotel.cd',
                'address' => 'Avenue Kilela Balanda, Lubumbashi, RDC',
                'city' => ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
                'latitude' => '-11.6645',
                'longitude' => '27.4790',
                'rating' => 4,
                'price' => '364000.00',
            ],
            [
                'name' => 'Lake Kivu Serena Hotel',
                'description' => 'Hôtel haut de gamme situé au bord du lac Kivu à Goma, avec une vue exceptionnelle sur le lac.',
                'phone' => '+243817000006',
                'email' => 'goma@serenahotels.com',
                'address' => 'Boulevard Kanyamuhanga, Goma, RDC',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'latitude' => '-1.6790',
                'longitude' => '29.2226',
                'rating' => 5,
                'price' => '532000.00',
            ],
            [
                'name' => 'Cap Kivu Hotel',
                'description' => 'Hôtel confortable à Goma situé près du lac Kivu, idéal pour les séjours touristiques.',
                'phone' => '+243817000007',
                'email' => 'contact@capkivu.com',
                'address' => 'Avenue de la Frontière, Goma, RDC',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'latitude' => '-1.6850',
                'longitude' => '29.2310',
                'rating' => 4,
                'price' => '308000.00',
            ],
            [
                'name' => 'Orchid Safari Club',
                'description' => 'Hôtel touristique à Bukavu avec vue sur le lac Kivu et un cadre naturel paisible.',
                'phone' => '+243817000008',
                'email' => 'info@orchidsafariclub.com',
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'city' => ['label' => 'Bukavu', 'latitude' => -2.5044, 'longitude' => 28.8618],
                'latitude' => '-2.5090',
                'longitude' => '28.8625',
                'rating' => 4,
                'price' => '266000.00',
            ],
            [
                'name' => 'Congo Palace Hotel',
                'description' => 'Hôtel situé au cœur de Kisangani offrant des chambres confortables et un service chaleureux.',
                'phone' => '+243817000009',
                'email' => 'contact@congopalace.cd',
                'address' => 'Centre-ville, Kisangani, RDC',
                'city' => ['label' => 'Kisangani', 'latitude' => 0.5153, 'longitude' => 25.1909],
                'latitude' => '0.5153',
                'longitude' => '25.1909',
                'rating' => 3,
                'price' => '196000.00',
            ],
            [
                'name' => 'Ledya Hotel',
                'description' => 'Hôtel moderne à Matadi offrant un hébergement confortable pour les voyageurs.',
                'phone' => '+243817000010',
                'email' => 'info@ledyahotel.com',
                'address' => 'Matadi, Kongo Central, RDC',
                'city' => ['label' => 'Matadi', 'latitude' => -5.8386, 'longitude' => 13.4631],
                'latitude' => '-5.8386',
                'longitude' => '13.4631',
                'rating' => 3,
                'price' => '182000.00',
            ],
        ];

        foreach ($specs as $spec) {
            $existing = $hotelRepo->findOneBy(['email' => $spec['email']]);

            if (!$existing) {
                $existing = $hotelRepo->findOneBy(['name' => $spec['name']]);
            }

            $hotel = $existing ?: new Hotel();

            $hotel->setName($spec['name']);
            $hotel->setDescription($spec['description']);
            $hotel->setPhone($spec['phone']);
            $hotel->setEmail($spec['email']);
            $hotel->setAddress($spec['address']);

            $city = $checkpointRepo->findOneBy(['label' => $spec['city']['label']]);
            if (null === $city) {
                $city = new Checkpoint();
                $city->setLabel($spec['city']['label']);
                $city->setActive(true);
                $city->setLatitude($spec['city']['latitude']);
                $city->setLongitude($spec['city']['longitude']);
                $this->em->persist($city);
            }

            $hotel->setCity($city);
            $hotel->setLatitude($spec['latitude']);
            $hotel->setLongitude($spec['longitude']);
            $hotel->setRating($spec['rating']);
            $hotel->setPrice($spec['price']);
            $hotel->setStatus(Hotel::STATUS_ACTIVE);

            if (!$existing) {
                $this->em->persist($hotel);
            }
        }

        $this->em->flush();
        $output->writeln('Hotels seeded.');

        return Command::SUCCESS;
    }
}
