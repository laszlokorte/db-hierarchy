<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class InstallationAdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private string $password)
    {
    }

    public function getRoles(): array
    {
        return ['ROLE_SUPERADMIN', 'ROLE_ADMIN'];
    }

    public function eraseCredentials(): void
    {
        $this->password = '';
    }

    public function getUserIdentifier(): string
    {
        return 'admin';
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
