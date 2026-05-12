<?php

namespace App\Command;

use App\Entity\Province;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:provinces',
    description: 'Seed provinces (RDC)',
)]
final class SeedProvincesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specs = [
            ['code' => 'KIN', 'label' => 'Kinshasa'],
            ['code' => 'KGC', 'label' => 'Kongo-Central'],
            ['code' => 'KWG', 'label' => 'Kwango'],
            ['code' => 'KWL', 'label' => 'Kwilu'],
            ['code' => 'MND', 'label' => 'Mai-Ndombe'],
            ['code' => 'EQU', 'label' => 'Équateur'],
            ['code' => 'MNG', 'label' => 'Mongala'],
            ['code' => 'NUB', 'label' => 'Nord-Ubangi'],
            ['code' => 'SUB', 'label' => 'Sud-Ubangi'],
            ['code' => 'TSH', 'label' => 'Tshuapa'],
            ['code' => 'HLO', 'label' => 'Haut-Lomami'],
            ['code' => 'HKT', 'label' => 'Haut-Katanga'],
            ['code' => 'LUA', 'label' => 'Lualaba'],
            ['code' => 'TAN', 'label' => 'Tanganyika'],
            ['code' => 'ITU', 'label' => 'Ituri'],
            ['code' => 'HUE', 'label' => 'Haut-Uele'],
            ['code' => 'BUE', 'label' => 'Bas-Uele'],
            ['code' => 'TSO', 'label' => 'Tshopo'],
            ['code' => 'NKV', 'label' => 'Nord-Kivu'],
            ['code' => 'SKV', 'label' => 'Sud-Kivu'],
            ['code' => 'MAN', 'label' => 'Maniema'],
            ['code' => 'KAS', 'label' => 'Kasaï'],
            ['code' => 'KAC', 'label' => 'Kasaï-Central'],
            ['code' => 'KAO', 'label' => 'Kasaï-Oriental'],
            ['code' => 'LOM', 'label' => 'Lomami'],
            ['code' => 'SAN', 'label' => 'Sankuru'],
        ];

        $repo = $this->em->getRepository(Province::class);

        foreach ($specs as $spec) {
            $existing = $repo->findOneBy(['code' => $spec['code']]);
            $province = $existing ?: new Province();

            $province->setCode($spec['code']);
            $province->setLabel($spec['label']);
            $province->setActive(true);

            if (!$existing) {
                $this->em->persist($province);
            }
        }

        $this->em->flush();
        $output->writeln('Provinces seeded.');

        return Command::SUCCESS;
    }
}

