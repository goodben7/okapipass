<?php

namespace App\Command;

use App\Entity\Agency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:agencies',
    description: 'Seed agencies (RDC)',
)]
class SeedAgenciesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specs = [
            [
                'name' => 'Agence Kinshasa Express',
                'email' => 'contact@kinshasa-express.cd',
                'phone' => '+243815000001',
                'address' => 'Kinshasa, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Lubumbashi Road Transport',
                'email' => 'info@lubumbashi-road-transport.cd',
                'phone' => '+243815000002',
                'address' => 'Avenue Kasa-Vubu, Lubumbashi, Haut-Katanga, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Goma Shuttle',
                'email' => 'support@goma-shuttle.cd',
                'phone' => '+243815000003',
                'address' => 'Goma, Nord-Kivu, RDC',
                'type' => Agency::TYPE_LAKE,
            ],
            [
                'name' => 'Bukavu Lake Transport',
                'email' => 'hello@bukavu-lake-transport.cd',
                'phone' => '+243815000004',
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'type' => Agency::TYPE_LAKE,
            ],
            [
                'name' => 'Kisangani Riverline',
                'email' => 'reservations@kisangani-riverline.cd',
                'phone' => '+243815000005',
                'address' => 'Kisangani, Tshopo, RDC',
                'type' => Agency::TYPE_RIVER,
            ],
            [
                'name' => 'Matadi River Cargo',
                'email' => 'contact@matadi-river-cargo.cd',
                'phone' => '+243815000006',
                'address' => 'Matadi, Kongo Central, RDC',
                'type' => Agency::TYPE_RIVER,
            ],
            [
                'name' => 'Mbuji-Mayi Roadline',
                'email' => 'info@mbuji-mayi-roadline.cd',
                'phone' => '+243815000007',
                'address' => 'Mbuji-Mayi, Kasaï-Oriental, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Kananga Transit',
                'email' => 'ops@kananga-transit.cd',
                'phone' => '+243815000008',
                'address' => 'Kananga, Kasaï-Central, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Kolwezi Logistics',
                'email' => 'info@kolwezi-logistics.cd',
                'phone' => '+243843333011',
                'address' => 'Kolwezi, Lualaba, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Likasi Express',
                'email' => 'info@likasi-express.cd',
                'phone' => '+243891148655',
                'address' => 'Likasi, Haut-Katanga, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
        ];

        $repo = $this->em->getRepository(Agency::class);

        foreach ($specs as $spec) {
            $existing = $repo->findOneBy(['email' => $spec['email']]);

            if (!$existing) {
                $existing = $repo->findOneBy(['name' => $spec['name']]);
            }

            $agency = $existing ?: new Agency();

            $agency->setName($spec['name']);
            $agency->setEmail($spec['email']);
            $agency->setPhone($spec['phone']);
            $agency->setAddress($spec['address']);
            $agency->setType($spec['type']);
            $agency->setStatus(Agency::STATUS_ACTIVE);

            if (!$existing) {
                $agency->setCreatedAt(new \DateTimeImmutable());
                $this->em->persist($agency);
            }
        }

        $this->em->flush();
        $output->writeln('Agencies seeded.');

        return Command::SUCCESS;
    }
}
