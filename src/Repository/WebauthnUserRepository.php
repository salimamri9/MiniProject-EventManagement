<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 * 
 * Repository for WebAuthn user operations.
 * Does not extend WebAuthn bundle class to avoid missing dependency issues.
 */
class WebauthnUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByUserHandle(string $userHandle): ?User
    {
        return $this->find($userHandle);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Create a WebAuthn user entity array for client-side registration
     */
    public function createWebAuthnUserEntity(User $user): array
    {
        return [
            'name' => $user->getUsername(),
            'id' => $user->getId()->toRfc4122(),
            'displayName' => $user->getEmail(),
        ];
    }

    /**
     * Generate a new WebAuthn user entity array for registration
     */
    public function generateWebAuthnUserEntity(string $username, string $email): array
    {
        return [
            'name' => $username,
            'id' => Uuid::v4()->toRfc4122(),
            'displayName' => $email,
        ];
    }
}
