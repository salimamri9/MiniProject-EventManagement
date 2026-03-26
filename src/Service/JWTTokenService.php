<?php

namespace App\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTTokenService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Create JWT token for user
     */
    public function createToken(UserInterface $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Decode and validate token
     */
    public function validateToken(string $token): ?array
    {
        try {
            return $this->jwtManager->parse($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract user from token
     */
    public function extractUserFromToken(string $token): ?string
    {
        $payload = $this->validateToken($token);
        return $payload['username'] ?? null;
    }
}
