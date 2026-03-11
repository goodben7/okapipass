<?php

namespace App\Provider;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * User provider that allows authentication via email or phone number.
 */
class MultiFieldUserProvider implements UserProviderInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Loads the user for the given identifier (email or phone number).
     *
     * This method attempts to find a user by email first, then by phone number.
     * 
     * @param string $identifier The user identifier (email or phone number)
     * @return UserInterface The loaded user
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try to find by email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);

        if ($user) {
            return $user;
        }

        // Try to find by phone number
        // Make sure your phone number is stored in a unique format without spaces/special characters.
        // You might need to normalize the number here before searching.
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['phone' => $identifier]);

        if ($user) {
            return $user;
        }

        // If user is not found by email or phone number
        throw new UserNotFoundException(sprintf('User with "%s" not found.', $identifier));
    }

    /**
     * @deprecated since Symfony 5.3, use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * @param UserInterface $user The user to refresh
     * @return UserInterface The refreshed user
     * @throws UnsupportedUserException if the user is not supported
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // Reload the user to ensure its data is up-to-date
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Checks if this provider supports the given user class.
     *
     * @param string $class The class name
     * @return bool True if this provider supports the given user class
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}