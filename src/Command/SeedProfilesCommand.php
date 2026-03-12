<?php

namespace App\Command;

use App\Entity\Profile;
use App\Manager\PermissionManager;
use App\Model\UserProxyIntertace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ar:seed:profiles',
    description: 'Seed profiles',
)]
class SeedProfilesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pm = PermissionManager::getInstance();
        $all = array_map(fn($p) => $p->getPermissionId(), $pm->getPermissions());

        $userSelf = [
            'ROLE_USER_CHANGE_PWD',
            'ROLE_USER_DETAILS',
        ];

        $userAdmin = [
            ...$userSelf,
            'ROLE_USER_CREATE',
            'ROLE_USER_LOCK',
            'ROLE_USER_LIST',
            'ROLE_USER_EDIT',
            'ROLE_USER_DELETE',
            'ROLE_USER_SET_PROFILE',
        ];

        $profileAdmin = [
            'ROLE_PROFILE_CREATE',
            'ROLE_PROFILE_LIST',
            'ROLE_PROFILE_UPDATE',
            'ROLE_PROFILE_DETAILS',
        ];

        $activityRead = [
            'ROLE_ACTIVITY_LIST',
            'ROLE_ACTIVITY_VIEW',
        ];

        $checkpointRead = [
            'ROLE_CHECKPOINT_LIST',
            'ROLE_CHECKPOINT_DETAILS',
        ];
        $checkpointCrud = [
            ...$checkpointRead,
            'ROLE_CHECKPOINT_CREATE',
            'ROLE_CHECKPOINT_UPDATE',
        ];

        $goPassRead = [
            'ROLE_GOPASS_LIST',
            'ROLE_GOPASS_DETAILS',
        ];
        $goPassCrud = [
            ...$goPassRead,
            'ROLE_GOPASS_CREATE',
            'ROLE_GOPASS_UPDATE',
        ];

        $ticketCrud = [
            'ROLE_TICKET_CREATE',
            'ROLE_TICKET_LIST',
            'ROLE_TICKET_DETAILS',
        ];

        $paymentRead = [
            'ROLE_PAYMENT_LIST',
            'ROLE_PAYMENT_DETAILS',
        ];
        $paymentCrud = [
            ...$paymentRead,
            'ROLE_PAYMENT_CREATE',
            'ROLE_PAYMENT_UPDATE',
        ];

        $agencyRead = [
            'ROLE_AGENCY_LIST',
            'ROLE_AGENCY_DETAILS',
        ];
        $agencyCrud = [
            ...$agencyRead,
            'ROLE_AGENCY_CREATE',
            'ROLE_AGENCY_UPDATE',
        ];

        $specs = [
            [
                'label' => 'Super Administrateur Plateforme',
                'personType' => UserProxyIntertace::PERSON_SUPER_ADMIN,
                'permissions' => $all,
            ],
            [
                'label' => 'Administrateur Technique',
                'personType' => UserProxyIntertace::PERSON_SYSTEM_ADMIN,
                'permissions' => array_values(array_unique(array_merge(
                    $userAdmin,
                    $profileAdmin,
                    $activityRead,
                    $checkpointCrud,
                    $goPassCrud,
                    $ticketCrud,
                    $paymentCrud,
                    $agencyCrud,
                ))),
            ],
            [
                'label' => 'Administrateur ONT',
                'personType' => UserProxyIntertace::PERSON_ONT_ADMIN,
                'permissions' => array_values(array_unique(array_merge(
                    $activityRead,
                    $checkpointCrud,
                    $goPassCrud,
                    $ticketCrud,
                    $paymentCrud,
                    $agencyCrud,
                    ['ROLE_USER_LIST', 'ROLE_USER_DETAILS'],
                ))),
            ],
            [
                'label' => 'Agent ONT',
                'personType' => UserProxyIntertace::PERSON_ONT_AGENT,
                'permissions' => array_values(array_unique(array_merge(
                    $userSelf,
                    $activityRead,
                    $checkpointRead,
                    $goPassRead,
                    $ticketCrud,
                    [...$paymentRead, 'ROLE_PAYMENT_CREATE'],
                    $agencyRead,
                ))),
            ],
            [
                'label' => 'Partenaire',
                'personType' => UserProxyIntertace::PERSON_PARTNER,
                'permissions' => array_values(array_unique(array_merge(
                    $activityRead,
                    $goPassRead,
                    $ticketCrud,
                    [...$paymentRead, 'ROLE_PAYMENT_CREATE'],
                    $agencyCrud,
                    $checkpointRead,
                    $userSelf,
                ))),
            ],
            [
                'label' => 'Voyageur',
                'personType' => UserProxyIntertace::PERSON_TRAVELER,
                'permissions' => array_values(array_unique(array_merge(
                    $userSelf,
                    $activityRead,
                    $goPassRead,
                    $ticketCrud,
                    [...$paymentRead, 'ROLE_PAYMENT_CREATE'],
                    $checkpointRead,
                    $agencyRead,
                ))),
            ],
        ];

        foreach ($specs as $spec) {
            $repo = $this->em->getRepository(Profile::class);
            $existing = $repo->findOneBy(['personType' => $spec['personType']]);
            $perms = array_values(array_intersect($all, $spec['permissions']));
            if ($existing) {
                $existing->setLabel($spec['label']);
                $existing->setPermission($perms);
                $existing->setActive(true);
            } else {
                $p = new Profile();
                $p->setLabel($spec['label']);
                $p->setPersonType($spec['personType']);
                $p->setPermission($perms);
                $p->setActive(true);
                $this->em->persist($p);
            }
        }

        $this->em->flush();
        $output->writeln('Profiles seeded.');
        return Command::SUCCESS;
    }
}
