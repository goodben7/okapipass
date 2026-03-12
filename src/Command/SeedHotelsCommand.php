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
    description: 'Seed hotels (Lubumbashi)',
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

        $lubumbashi = $checkpointRepo->findOneBy(['label' => 'Lubumbashi']);
        if (null === $lubumbashi) {
            $lubumbashi = new Checkpoint();
            $lubumbashi->setLabel('Lubumbashi');
            $lubumbashi->setActive(true);
            $lubumbashi->setLatitude(-11.6600);
            $lubumbashi->setLongitude(27.4700);
            $this->em->persist($lubumbashi);
        }

        $specs = [
            [
                'name' => 'Pullman Lubumbashi Grand Karavia',
                'description' => 'Hotel & resort in Lubumbashi',
                'phone' => '+243815000201',
                'email' => 'info@grandkaravia.cd',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.7050000',
                'longitude' => '27.4800000',
                'rating' => 5,
                'price' => '180.00',
            ],
            [
                'name' => 'Hotel Lubumbashi',
                'description' => 'Business hotel in Lubumbashi',
                'phone' => '+243815000202',
                'email' => 'contact@hotel-lubumbashi.cd',
                'address' => 'Centre-ville, Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.6650000',
                'longitude' => '27.4800000',
                'rating' => 4,
                'price' => '85.00',
            ],
            [
                'name' => 'Park Hotel Lubumbashi',
                'description' => 'Hotel with restaurant in Lubumbashi',
                'phone' => '+243815000203',
                'email' => 'reservations@parkhotel-lushi.cd',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.6670000',
                'longitude' => '27.4790000',
                'rating' => 4,
                'price' => '95.00',
            ],
            [
                'name' => 'Hotel Karavia Lodge',
                'description' => 'Lodge in Lubumbashi',
                'phone' => '+243815000204',
                'email' => 'hello@karavialodge.cd',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.7020000',
                'longitude' => '27.4820000',
                'rating' => 4,
                'price' => '120.00',
            ],
            [
                'name' => 'Hotel du Centre Lubumbashi',
                'description' => 'City hotel in Lubumbashi',
                'phone' => '+243815000205',
                'email' => 'contact@hotelducentre.cd',
                'address' => 'Centre-ville, Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.6665000',
                'longitude' => '27.4795000',
                'rating' => 3,
                'price' => '55.00',
            ],
            [
                'name' => 'Copperbelt Inn',
                'description' => 'Affordable hotel in Lubumbashi',
                'phone' => '+243815000206',
                'email' => 'info@copperbelt-inn.cd',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'latitude' => '-11.6610000',
                'longitude' => '27.4740000',
                'rating' => 3,
                'price' => '45.00',
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
            $hotel->setCity($lubumbashi);
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
