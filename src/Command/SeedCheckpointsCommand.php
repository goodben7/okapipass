<?php

namespace App\Command;

use App\Entity\Checkpoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:checkpoints',
    description: 'Seed checkpoints (RDC)',
)]
class SeedCheckpointsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specs = [
            ['label' => 'Kinshasa', 'latitude' => -4.3250, 'longitude' => 15.3222],
            ['label' => 'Lubumbashi', 'latitude' => -11.6609, 'longitude' => 27.4794],
            ['label' => 'Goma', 'latitude' => -1.6790, 'longitude' => 29.2226],
            ['label' => 'Bukavu', 'latitude' => -2.5044, 'longitude' => 28.8618],
            ['label' => 'Kisangani', 'latitude' => 0.5153, 'longitude' => 25.1909],
            ['label' => 'Matadi', 'latitude' => -5.8386, 'longitude' => 13.4631],
            ['label' => 'Mbuji-Mayi', 'latitude' => -6.1360, 'longitude' => 23.5898],
            ['label' => 'Kananga', 'latitude' => -5.8962, 'longitude' => 22.4166],
            ['label' => 'Kolwezi', 'latitude' => -10.7167, 'longitude' => 25.4667],
            ['label' => 'Likasi', 'latitude' => -10.9814, 'longitude' => 26.7384],
            ['label' => 'Kasumbalesa', 'latitude' => -12.2727, 'longitude' => 27.8061],
            ['label' => 'Kamina', 'latitude' => -8.7356, 'longitude' => 24.9981],
            ['label' => 'Tshikapa', 'latitude' => -6.4167, 'longitude' => 20.8000],
            ['label' => 'Uvira', 'latitude' => -3.3953, 'longitude' => 29.1378],
            ['label' => 'Beni', 'latitude' => 0.4911, 'longitude' => 29.4739],
            ['label' => 'Butembo', 'latitude' => 0.1416, 'longitude' => 29.2917],
            ['label' => 'Kindu', 'latitude' => -2.9500, 'longitude' => 25.9500],
            ['label' => 'Boma', 'latitude' => -5.8540, 'longitude' => 13.0536],
            ['label' => 'Kikwit', 'latitude' => -5.0406, 'longitude' => 18.8162],
            ['label' => 'Bandundu', 'latitude' => -3.3167, 'longitude' => 17.3667],
            ['label' => 'Kalemie', 'latitude' => -5.9475, 'longitude' => 29.1947],
            ['label' => 'Bunia', 'latitude' => 1.5656, 'longitude' => 30.2528],
            ['label' => 'Isiro', 'latitude' => 2.7739, 'longitude' => 27.6167],
            ['label' => 'Gemena', 'latitude' => 3.2570, 'longitude' => 19.7723],
            ['label' => 'Gbadolite', 'latitude' => 4.2790, 'longitude' => 21.0023],
        ];

        $repo = $this->em->getRepository(Checkpoint::class);

        foreach ($specs as $spec) {
            $existing = $repo->findOneBy(['label' => $spec['label']]);
            $checkpoint = $existing ?: new Checkpoint();

            $checkpoint->setLabel($spec['label']);
            $checkpoint->setActive(true);
            $checkpoint->setLatitude($spec['latitude']);
            $checkpoint->setLongitude($spec['longitude']);

            if (!$existing) {
                $this->em->persist($checkpoint);
            }
        }

        $this->em->flush();
        $output->writeln('Checkpoints seeded.');

        return Command::SUCCESS;
    }
}
