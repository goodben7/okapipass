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
                'name' => 'Transco',
                'email' => 'contact@transco.cd',
                'phone' => '+243817121623',
                'address' => 'Boulevard Lumumba, Kinshasa, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Simba Transport',
                'email' => 'contact@simbatransport.cd',
                'phone' => '+243820000001',
                'address' => 'Limete, Kinshasa, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'SOCITRANS',
                'email' => 'contact@socitrans.cd',
                'phone' => '+243822220402',
                'address' => 'Matadi, Kongo Central, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Classic Coach RDC',
                'email' => 'info@classiccoach.cd',
                'phone' => '+243810000003',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'RATCO Express',
                'email' => 'contact@ratcoexpress.cd',
                'phone' => '+243810000004',
                'address' => 'Kinshasa, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'MullyKap Transport',
                'email' => 'info@mullykap.cd',
                'phone' => '+243810000005',
                'address' => 'Goma, Nord-Kivu, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'TMK Transport',
                'email' => 'contact@tmktransport.cd',
                'phone' => '+243810000006',
                'address' => 'Kinshasa, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Lubumbashi Intercity Transport',
                'email' => 'contact@lubumbashi-intercity.cd',
                'phone' => '+243810000007',
                'address' => 'Lubumbashi, Haut-Katanga, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Kivu Express Transport',
                'email' => 'info@kivuexpress.cd',
                'phone' => '+243810000008',
                'address' => 'Goma, Nord-Kivu, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Bukavu Interprovincial Bus',
                'email' => 'contact@bukavubus.cd',
                'phone' => '+243810000009',
                'address' => 'Bukavu, Sud-Kivu, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Katanga Travel Bus',
                'email' => 'info@katangabus.cd',
                'phone' => '+243810000010',
                'address' => 'Kolwezi, Lualaba, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Kasai Road Lines',
                'email' => 'contact@kasairoad.cd',
                'phone' => '+243810000011',
                'address' => 'Kananga, Kasaï-Central, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Congo Intercity Bus',
                'email' => 'info@congobus.cd',
                'phone' => '+243810000012',
                'address' => 'Kisangani, Tshopo, RDC',
                'type' => Agency::TYPE_ROAD,
            ],
            [
                'name' => 'Kwilu Transport Service',
                'email' => 'contact@kwilutransport.cd',
                'phone' => '+243810000013',
                'address' => 'Kikwit, Kwilu, RDC',
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
