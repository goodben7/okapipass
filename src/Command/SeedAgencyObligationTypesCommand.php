<?php

namespace App\Command;

use App\Entity\AgencyObligationType;
use App\Repository\AgencyObligationTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-agency-obligation-types',
    description: 'Seed default RDC/ONT agency compliance obligation types',
)]
final class SeedAgencyObligationTypesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyObligationTypeRepository $types,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $specs = [
            [
                'code' => 'AGENCY_LICENSE',
                'label' => 'Agrément / licence ONT',
                'description' => 'Licence d’exploitation d’agence de transport routier.',
                'category' => AgencyObligationType::CATEGORY_LICENSE,
                'months' => 12,
                'reminder' => 45,
                'sort' => 10,
            ],
            [
                'code' => 'FLEET_INSURANCE',
                'label' => 'Assurance flotte',
                'description' => 'Police d’assurance des véhicules de l’agence.',
                'category' => AgencyObligationType::CATEGORY_INSURANCE,
                'months' => 12,
                'reminder' => 30,
                'sort' => 20,
            ],
            [
                'code' => 'TECHNICAL_INSPECTION',
                'label' => 'Visite technique',
                'description' => 'Contrôle technique des véhicules.',
                'category' => AgencyObligationType::CATEGORY_TECHNICAL,
                'months' => 6,
                'reminder' => 21,
                'sort' => 30,
            ],
            [
                'code' => 'FPT_MONTHLY',
                'label' => 'Déclaration FPT mensuelle',
                'description' => 'Déclaration mensuelle du Fond pour la promotion du tourisme.',
                'category' => AgencyObligationType::CATEGORY_FPT,
                'months' => 1,
                'reminder' => 7,
                'sort' => 40,
            ],
            [
                'code' => 'TAX_CLEARANCE',
                'label' => 'Attestation fiscale',
                'description' => 'Attestation de situation fiscale à jour.',
                'category' => AgencyObligationType::CATEGORY_TAX,
                'months' => 12,
                'reminder' => 30,
                'sort' => 50,
            ],
            [
                'code' => 'TRANSPORT_AUTH',
                'label' => 'Autorisation de transport',
                'description' => 'Autorisation d’exploiter des lignes / destinations.',
                'category' => AgencyObligationType::CATEGORY_LICENSE,
                'months' => 12,
                'reminder' => 45,
                'sort' => 60,
            ],
        ];

        $upserted = 0;
        foreach ($specs as $spec) {
            $type = $this->types->findOneByCode($spec['code']) ?? new AgencyObligationType();
            $isNew = null === $type->getId();
            $type->setCode($spec['code']);
            $type->setLabel($spec['label']);
            $type->setDescription($spec['description']);
            $type->setCategory($spec['category']);
            $type->setDefaultValidityMonths($spec['months']);
            $type->setReminderDays($spec['reminder']);
            $type->setSortOrder($spec['sort']);
            $type->setActive(true);
            if ($isNew) {
                $this->em->persist($type);
            }
            ++$upserted;
        }

        $this->em->flush();
        $io->success(sprintf('Seeded/updated %d obligation type(s).', $upserted));

        return Command::SUCCESS;
    }
}
