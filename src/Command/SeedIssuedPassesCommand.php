<?php

namespace App\Command;

use App\Entity\IssuedOkapiPass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-issued-passes', description: 'Seed sample issued OkapiPass OP- references')]
class SeedIssuedPassesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specs = [
            ['ref' => 'OP-MOCK-2044', 'holder' => 'Ilunga Sarah', 'status' => IssuedOkapiPass::STATUS_ACTIVE],
            ['ref' => 'OP-ABC123', 'holder' => 'Kabongo Jean', 'status' => IssuedOkapiPass::STATUS_ACTIVE],
            ['ref' => 'OP-EXPIRED-1', 'holder' => 'Test Expire', 'status' => IssuedOkapiPass::STATUS_EXPIRED],
        ];

        $created = 0;
        foreach ($specs as $spec) {
            $existing = $this->em->getRepository(IssuedOkapiPass::class)->findOneBy(['reference' => $spec['ref']]);
            if (null !== $existing) {
                continue;
            }
            $pass = new IssuedOkapiPass();
            $pass->setReference($spec['ref']);
            $pass->setHolderName($spec['holder']);
            $pass->setStatus($spec['status']);
            $this->em->persist($pass);
            ++$created;
        }
        $this->em->flush();
        $io->success(sprintf('Seeded %d issued pass(es).', $created));

        return Command::SUCCESS;
    }
}
