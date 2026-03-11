<?php

namespace App\MessageHandler;

use App\Entity\Activity;
use Psr\Log\LoggerInterface;
use App\Repository\RessourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Message\UserActivityLoggedMessage;
use App\Exception\UnknowRessourceException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserActivityLogMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private RessourceRepository    $repository,
        private LoggerInterface        $logger,
    )
    {
    }

    public function __invoke(UserActivityLoggedMessage $command): void
    {
        $ressource = $this->repository->getRessourceByName($command->getRessourceName()); 

        if ($ressource === null) {
            $this->logger->warning(sprintf("ressource %s not found", $command->getRessourceName()), ['exception' => UnknowRessourceException::class]);
            $ressourceName = $command->getRessourceName();
        } else {
            $ressourceName = $ressource->getName();
        }

        $activity = (new Activity())
            ->setActivity($command->getActivity())
            ->setActivityDescription("")
            ->setRessourceName($ressourceName)
            ->setRessourceIdentifier(ressourceIdentifier: $command->getRessourceIdentifier())
            ->setUser($command->getUser())
            ->setActivityDescription( $command->getActivityDescription())
            ->setTriggeredBy($command->getTriggeredBy())
            ->setDate($command->getDate());

        $this->em->persist($activity);
        $this->em->flush();
    }
}
