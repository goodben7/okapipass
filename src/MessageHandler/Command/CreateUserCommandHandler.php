<?php 

namespace App\MessageHandler\Command;

use App\Entity\User;
use App\Model\NewUserModel;
use App\Manager\UserManager;
use Psr\Log\LoggerInterface;
use App\Message\Command\CreateUserCommand;
use App\Message\Command\CommandHandlerInterface;

class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private UserManager $manager
    ) { 
    }

    /**
     * Summary of __invoke
     * @param \App\Message\Command\CreateUserCommand $command
     * @throws \Exception
     * @return User
     */
    public function __invoke(CreateUserCommand $command): User
    {
        try {
            $model = new NewUserModel(
                $command->email, 
                $command->plainPassword, 
                $command->profile, 
                $command->phone, 
                $command->displayName,
                $command->holderId,
                $command->holderType,
            );

            return $this->manager->createFrom($model);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception('Error in CreateUserCommandHandler: ' . $e->getMessage(), 0, $e);
        }
    }
}