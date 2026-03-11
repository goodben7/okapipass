<?php

namespace App\Service;

use App\Entity\User;
use App\Event\ActivityEvent;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Message\UserActivityLoggedMessage;
use Psr\Log\LoggerInterface;
use App\Repository\RessourceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ActivityEventDispatcher 
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private MessageBusInterface      $bus,
        private Security                 $security,
        private UserRepository           $userRepository,
        private ManagerRegistry          $registry,
        private LoggerInterface          $logger,
        private RessourceRepository      $repository, 
    )
    {
    }

    /**
     * @param mixed $ressource
     * @param string $action
     * @param string|null $ressourceClass
     * @param string|null $activityDescription
     * @return ActivityEvent
     */
    public function dispatch(mixed $ressource, string $action, ?string $ressourceClass = null, ?string $activityDescription = null): ActivityEvent
    {
        $eventName = ActivityEvent::getEventName(null === $ressource ? $ressourceClass : get_class($ressource), $action);

        $identifier = $this->security->getUser() ? $this->security->getUser()->getUserIdentifier() : 'system';

        /** @var User|null $user */
        $user = $this->userRepository->findByEmailOrPhone($identifier);
        
        if ($this->security->getUser())
            $this->bus->dispatch(new UserActivityLoggedMessage( 
                $user->getId(),
                new \DateTimeImmutable(),
                $action,
                null === $ressource ? $ressourceClass : get_class($ressource),
                $user,
                $ressource?->getId(), 
                $activityDescription,
            ));

        return $this->dispatcher->dispatch(new ActivityEvent(
            $ressource,
            $action,
            $ressourceClass,
            $activityDescription), 
            $eventName)
        ;
    }

    public function getResourceInstance(string $ressourceName, mixed $ressourceIdentifier): ?object
    {
        $manager = $this->registry->getManagerForClass($ressourceName);

        if (!$manager) {
            throw new \InvalidArgumentException("Impossible de trouver le gestionnaire pour la classe $ressourceName");
        }

        return $manager->getRepository($ressourceName)->find($ressourceIdentifier);
    }
}
