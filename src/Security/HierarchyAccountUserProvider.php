<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\DBAL\Connection;
/**
 * @implements UserProviderInterface<UserInterface>
 */
class HierarchyAccountUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private Connection $connection, private UserPasswordHasherInterface $hasher) {
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me. If you're not using these features, you do not
     * need to implement this method.
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $stmt = $this->connection->prepare('SELECT password FROM account WHERE login = :login');
        $stmt->bindValue('login', $identifier);
        $result = $stmt->executeQuery();
        $password = $result->fetchOne();

        if($password) {
            return new HierarchyAccountUser($identifier, $password);
        }

        throw new UserNotFoundException();
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user) : UserInterface
    {
        if (!$user instanceof HierarchyAccountUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $stmt = $this->connection->prepare('SELECT password FROM account WHERE login = :login');

        $stmt->bindValue('login', $user->getUserIdentifier());
        $result = $stmt->executeQuery();
        $password = $result->fetchOne();

        if(!$password) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return HierarchyAccountUser::class === $class || is_subclass_of($class, HierarchyAccountUser::class);
    }

    /**
     * Upgrades the encoded password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newEncodedPassword): void
    {
        // $stmt = $this->connection->prepare('UPDATE account SET password = :password WHERE login = :login');
        // $result = $stmt->executeQuery(['login' => $user->getUserIdentifier(), 'password' => $newEncodedPassword]);
        // $user->setPassword($newEncodedPassword);
    }
}
