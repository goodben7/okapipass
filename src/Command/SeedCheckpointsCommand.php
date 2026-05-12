<?php

namespace App\Command;

use App\Entity\Checkpoint;
use App\Entity\Province;
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
            ['label' => 'Kinshasa', 'provinceCode' => 'KIN', 'latitude' => -4.3250, 'longitude' => 15.3222],
            ['label' => 'Lubumbashi', 'provinceCode' => 'HKT', 'latitude' => -11.6609, 'longitude' => 27.4794],
            ['label' => 'Goma', 'provinceCode' => 'NKV', 'latitude' => -1.6790, 'longitude' => 29.2226],
            ['label' => 'Bukavu', 'provinceCode' => 'SKV', 'latitude' => -2.5044, 'longitude' => 28.8618],
            ['label' => 'Kisangani', 'provinceCode' => 'TSO', 'latitude' => 0.5153, 'longitude' => 25.1909],
            ['label' => 'Matadi', 'provinceCode' => 'KGC', 'latitude' => -5.8386, 'longitude' => 13.4631],
            ['label' => 'Mbuji-Mayi', 'provinceCode' => 'KAO', 'latitude' => -6.1360, 'longitude' => 23.5898],
            ['label' => 'Kananga', 'provinceCode' => 'KAC', 'latitude' => -5.8962, 'longitude' => 22.4166],
            ['label' => 'Kolwezi', 'provinceCode' => 'LUA', 'latitude' => -10.7167, 'longitude' => 25.4667],
            ['label' => 'Likasi', 'provinceCode' => 'HKT', 'latitude' => -10.9814, 'longitude' => 26.7384],
            ['label' => 'Kasumbalesa', 'provinceCode' => 'HKT', 'latitude' => -12.2727, 'longitude' => 27.8061],
            ['label' => 'Kamina', 'provinceCode' => 'HLO', 'latitude' => -8.7356, 'longitude' => 24.9981],
            ['label' => 'Tshikapa', 'provinceCode' => 'KAS', 'latitude' => -6.4167, 'longitude' => 20.8000],
            ['label' => 'Uvira', 'provinceCode' => 'SKV', 'latitude' => -3.3953, 'longitude' => 29.1378],
            ['label' => 'Beni', 'provinceCode' => 'NKV', 'latitude' => 0.4911, 'longitude' => 29.4739],
            ['label' => 'Butembo', 'provinceCode' => 'NKV', 'latitude' => 0.1416, 'longitude' => 29.2917],
            ['label' => 'Kindu', 'provinceCode' => 'MAN', 'latitude' => -2.9500, 'longitude' => 25.9500],
            ['label' => 'Boma', 'provinceCode' => 'KGC', 'latitude' => -5.8540, 'longitude' => 13.0536],
            ['label' => 'Kikwit', 'provinceCode' => 'KWL', 'latitude' => -5.0406, 'longitude' => 18.8162],
            ['label' => 'Bandundu', 'provinceCode' => 'KWL', 'latitude' => -3.3167, 'longitude' => 17.3667],
            ['label' => 'Kalemie', 'provinceCode' => 'TAN', 'latitude' => -5.9475, 'longitude' => 29.1947],
            ['label' => 'Bunia', 'provinceCode' => 'ITU', 'latitude' => 1.5656, 'longitude' => 30.2528],
            ['label' => 'Isiro', 'provinceCode' => 'HUE', 'latitude' => 2.7739, 'longitude' => 27.6167],
            ['label' => 'Gemena', 'provinceCode' => 'SUB', 'latitude' => 3.2570, 'longitude' => 19.7723],
            ['label' => 'Gbadolite', 'provinceCode' => 'NUB', 'latitude' => 4.2790, 'longitude' => 21.0023],
        ];

        $checkpointRepo = $this->em->getRepository(Checkpoint::class);
        $provinceRepo = $this->em->getRepository(Province::class);

        foreach ($specs as $spec) {
            $existing = $checkpointRepo->findOneBy(['label' => $spec['label']]);
            $checkpoint = $existing ?: new Checkpoint();

            $province = $provinceRepo->findOneBy(['code' => $spec['provinceCode']]);
            if (!$province) {
                $output->writeln(sprintf('Province not found for checkpoint "%s" (provinceCode=%s). Run ar:seed:provinces first.', $spec['label'], $spec['provinceCode']));
            }

            $checkpoint->setLabel($spec['label']);
            $checkpoint->setActive(true);
            $checkpoint->setLatitude($spec['latitude']);
            $checkpoint->setLongitude($spec['longitude']);
            $checkpoint->setProvince($province);

            if (!$existing) {
                $this->em->persist($checkpoint);
            }
        }

        $this->em->flush();
        $output->writeln('Checkpoints seeded.');

        return Command::SUCCESS;
    }
}
