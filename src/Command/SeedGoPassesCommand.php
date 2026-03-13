<?php

namespace App\Command;

use App\Entity\GoPass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:gopasses',
    description: 'Seed GoPass (CDF)',
)]
class SeedGoPassesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specs = [
            [
                'code' => 'CDF_ROUTIER',
                'label' => 'Pass Routier',
                'transportType' => GoPass::TRANSPORT_ROUTIER,
                'price' => 10000.0,
                'currency' => 'CDF',
            ],
            [
                'code' => 'CDF_FLUVIAL',
                'label' => 'Pass Fluvial',
                'transportType' => GoPass::TRANSPORT_FLUVIAL,
                'price' => 20000.0,
                'currency' => 'CDF',
            ],
            [
                'code' => 'CDF_LACUSTRE',
                'label' => 'Pass Lacustre',
                'transportType' => GoPass::TRANSPORT_LACUSTRE,
                'price' => 25000.0,
                'currency' => 'CDF',
            ],
        ];

        $repo = $this->em->getRepository(GoPass::class);

        foreach ($specs as $spec) {
            $existing = $repo->findOneBy(['code' => $spec['code']]);
            $goPass = $existing ?: new GoPass();

            $goPass->setCode($spec['code']);
            $goPass->setLabel($spec['label']);
            $goPass->setTransportType($spec['transportType']);
            $goPass->setPrice($spec['price']);
            $goPass->setCurrency($spec['currency']);
            $goPass->setActive(true);

            if (!$existing) {
                $this->em->persist($goPass);
            }
        }

        $this->em->flush();
        $output->writeln('GoPass seeded.');

        return Command::SUCCESS;
    }
}
