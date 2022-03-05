<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class HierarchyAccountUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(string $identifier, string $password) {
        $this->identifier = $identifier;
        $this->password = $password;
    }

    public function getRoles() : array {
    	return ['ROLE_ADMIN'];
    }

    public function eraseCredentials() {
        $this->password = '';
    }

    public function getUserIdentifier() : string {
    	return $this->identifier;
    }

    public function getPassword() : string {
    	return $this->password;
    }

    public function setPassword(string $password) {
        $this->password = $password;
    }
}