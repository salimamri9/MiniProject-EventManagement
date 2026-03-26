<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Repository\AdminRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminRepository $adminRepository
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === 'App\Entity\User' || $class === 'App\Entity\Admin';
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try email first (for users)
        $user = $this->userRepository->findByEmail($identifier);
        if ($user) {
            return $user;
        }

        // Try username (for both users and admins)
        $user = $this->userRepository->findByUsername($identifier);
        if ($user) {
            return $user;
        }

        $admin = $this->adminRepository->findByUsername($identifier);
        if ($admin) {
            return $admin;
        }

        throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
    }
}
