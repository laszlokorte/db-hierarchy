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

class InstallationAdminProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(Connection $connection, UserPasswordHasherInterface $hasher) {
        $this->connection = $connection;
        $this->hasher = $hasher;
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
        if($identifier === 'admin') {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM account');
            $result = $stmt->execute();
            $numberOfAccounts = $result->fetchOne();
            if($numberOfAccounts == 0) {
                $admin = new InstallationAdminUser($identifier);
                $hashed = $this->hasher->hashPassword($admin, 'admin');
                $admin->setPassword($hashed);
                return $admin;
            }
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
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof InstallationAdminUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM account');
        $result = $stmt->execute();
        $numberOfAccounts = $result->fetchOne();

        if($numberOfAccounts != 0) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class)
    {
        return InstallationAdminUser::class === $class || is_subclass_of($class, InstallationAdminUser::class);
    }

    /**
     * Upgrades the encoded password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newEncodedPassword): void
    {
        $user->setPassword($newEncodedPassword);
    }
}