<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class InstallationAdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function getRoles() : array {
    	return ['ROLE_SUPERADMIN','ROLE_ADMIN'];
    }

    public function eraseCredentials() {
    	$this->password = '';
    }

    public function getUserIdentifier() : string {
    	return 'admin';
    }

    public function getPassword() : string {
    	return $this->password;
    }

    public function setPassword(string $password) {
    	$this->password = $password;
    }
}