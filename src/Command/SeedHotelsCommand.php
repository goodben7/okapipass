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
                'name' => 'Karavia Grand Hotel',
                'description' => 'Hotel in Lubumbashi',
                'phone' => '+243815000201',
                'email' => 'reservations@karavia-grand.cd',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'city' => ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
                'latitude' => '-11.7050000',
                'longitude' => '27.4800000',
                'rating' => 5,
                'price' => '160.00',
            ],
            [
                'name' => 'Kin Plaza Hotel',
                'description' => 'Business hotel in Kinshasa',
                'phone' => '+243815000202',
                'email' => 'contact@kin-plaza.cd',
                'address' => 'Kinshasa, RDC',
                'city' => ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
                'latitude' => '-4.3240000',
                'longitude' => '15.3170000',
                'rating' => 4,
                'price' => '95.00',
            ],
            [
                'name' => 'Goma Lake View Hotel',
                'description' => 'Hotel in Goma',
                'phone' => '+243815000203',
                'email' => 'info@goma-lakeview.cd',
                'address' => 'Goma, Nord-Kivu, RDC',
                'city' => ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
                'latitude' => '-1.6775000',
                'longitude' => '29.2240000',
                'rating' => 4,
                'price' => '75.00',
            ],
            [
                'name' => 'Bukavu Panorama Hotel',
                'description' => 'Hotel in Bukavu',
                'phone' => '+243815000204',
                'email' => 'contact@bukavu-panorama.cd',
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'city' => ['label' => 'Bukavu', 'latitude' => -2.5044, 'longitude' => 28.8618],
                'latitude' => '-2.5035000',
                'longitude' => '28.8628000',
                'rating' => 4,
                'price' => '70.00',
            ],
            [
                'name' => 'Kisangani Riverside Hotel',
                'description' => 'Hotel in Kisangani',
                'phone' => '+243815000205',
                'email' => 'hello@kisan-riverside.cd',
                'address' => 'Kisangani, Tshopo, RDC',
                'city' => ['label' => 'Kisangani', 'latitude' => 0.5153, 'longitude' => 25.1909],
                'latitude' => '0.5145000',
                'longitude' => '25.1920000',
                'rating' => 3,
                'price' => '48.00',
            ],
            [
                'name' => 'Matadi City Hotel',
                'description' => 'Hotel in Matadi',
                'phone' => '+243815000206',
                'email' => 'info@matadi-cityhotel.cd',
                'address' => 'Matadi, Kongo Central, RDC',
                'city' => ['label' => 'Matadi', 'latitude' => -5.8386, 'longitude' => 13.4631],
                'latitude' => '-5.8375000',
                'longitude' => '13.4625000',
                'rating' => 3,
                'price' => '42.00',
            ],
            [
                'name' => 'Mbuji-Mayi Central Hotel',
                'description' => 'Hotel in Mbuji-Mayi',
                'phone' => '+243815000207',
                'email' => 'contact@mbuji-central.cd',
                'address' => 'Mbuji-Mayi, Kasaï-Oriental, RDC',
                'city' => ['label' => 'Mbuji-Mayi', 'latitude' => -6.1360, 'longitude' => 23.5898],
                'latitude' => '-6.1350000',
                'longitude' => '23.5905000',
                'rating' => 3,
                'price' => '40.00',
            ],
            [
                'name' => 'Kananga Comfort Hotel',
                'description' => 'Hotel in Kananga',
                'phone' => '+243815000208',
                'email' => 'hello@kananga-comfort.cd',
                'address' => 'Kananga, Kasaï-Central, RDC',
                'city' => ['label' => 'Kananga', 'latitude' => -5.8962, 'longitude' => 22.4166],
                'latitude' => '-5.8950000',
                'longitude' => '22.4172000',
                'rating' => 3,
                'price' => '38.00',
            ],
            [
                'name' => 'Kolwezi Business Hotel',
                'description' => 'Hotel in Kolwezi',
                'phone' => '+243815000209',
                'email' => 'reservations@kolwezi-business.cd',
                'address' => 'Kolwezi, Lualaba, RDC',
                'city' => ['label' => 'Kolwezi', 'latitude' => -10.7167, 'longitude' => 25.4667],
                'latitude' => '-10.7155000',
                'longitude' => '25.4675000',
                'rating' => 4,
                'price' => '68.00',
            ],
            [
                'name' => 'Likasi Garden Hotel',
                'description' => 'Hotel in Likasi',
                'phone' => '+243815000210',
                'email' => 'info@likasi-garden.cd',
                'address' => 'Likasi, Haut-Katanga, RDC',
                'city' => ['label' => 'Likasi', 'latitude' => -10.9814, 'longitude' => 26.7384],
                'latitude' => '-10.9805000',
                'longitude' => '26.7392000',
                'rating' => 3,
                'price' => '44.00',
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
