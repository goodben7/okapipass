<?php

namespace App\MessageHandler\Query;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryHandlerInterface;

class GetUserDetailsHandler  implements QueryHandlerInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function __invoke(GetUserDetails $query): ?User
    {
        /** @var User|null $user */
        $user = $this->userRepository->findByEmailOrPhone($query->id);

        if (null === $user) {
            return null;
        }

        return $user;
    }
}
