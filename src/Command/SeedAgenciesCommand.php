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
    description: 'Seed agencies (Lubumbashi)',
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
                'name' => 'Agence Lubumbashi Express',
                'email' => 'contact@lubumbashi-express.cd',
                'phone' => '+243815000001',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Katanga Road Transport',
                'email' => 'info@katanga-road-transport.cd',
                'phone' => '+243815000002',
                'address' => 'Avenue Kasa-Vubu, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Congo Shuttle Lushi',
                'email' => 'support@congo-shuttle.cd',
                'phone' => '+243815000003',
                'address' => 'Commune Kampemba, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Lushi Cargo & Passengers',
                'email' => 'hello@lushi-cargo-passengers.cd',
                'phone' => '+243815000004',
                'address' => 'Route Likasi, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Haut-Katanga Voyages',
                'email' => 'reservations@hk-voyages.cd',
                'phone' => '+243815000005',
                'address' => 'Centre-ville, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Copperbelt Transit',
                'email' => 'contact@copperbelt-transit.cd',
                'phone' => '+243815000006',
                'address' => 'Avenue Lumumba, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Kasumbalesa Roadline',
                'email' => 'info@kasumbalesa-roadline.cd',
                'phone' => '+243815000007',
                'address' => 'Corridor Lubumbashi–Kasumbalesa, Haut-Katanga, RDC',
            ],
            [
                'name' => 'Lualaba & Katanga Logistics',
                'email' => 'ops@lualaba-katanga-logistics.cd',
                'phone' => '+243815000008',
                'address' => 'Quartier Industriel, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'KPM Cargo & TLC',
                'email' => 'info@kpmcargo.cd',
                'phone' => '+243843333011',
                'address' => '7876 Av. de Ruwe, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'MULYKAP TERMINAL - Lubumbashi',
                'email' => 'info@mulykap.com',
                'phone' => '+243891148655',
                'address' => '751 Av. Adoula, Lubumbashi, Haut-Katanga, RDC',
            ],
            [
                'name' => 'TransKat',
                'email' => 'info@transkat.cd',
                'phone' => '+243815000099',
                'address' => 'Av. Moero, Lubumbashi, Haut-Katanga, RDC',
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
            $agency->setType(Agency::TYPE_ROAD);
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
