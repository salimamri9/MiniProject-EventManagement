<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;

/**
 * Service for WebAuthn/Passkey authentication
 */
class PasskeyAuthService
{
    private string $rpName;
    private string $rpId;

    public function __construct(
        private UserRepository $userRepository,
        private WebauthnCredentialRepository $credentialRepository
    ) {
        $this->rpId = $_ENV['RELYING_PARTY_ID'] ?? 'localhost';
        $this->rpName = $_ENV['RELYING_PARTY_NAME'] ?? 'EventHub';
    }

    /**
     * Generate registration options for a new passkey
     */
    public function getRegistrationOptions(User $user): array
    {
        // Generate challenge (random 32 bytes, base64url encoded)
        $challenge = $this->base64urlEncode(random_bytes(32));

        // Get existing credentials to exclude (prevent re-registration)
        $excludeCredentials = [];
        foreach ($this->credentialRepository->findAllByUser($user) as $credential) {
            $excludeCredentials[] = [
                'type' => 'public-key',
                'id' => $credential->getCredentialId(),
            ];
        }

        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId,
            ],
            'user' => [
                'id' => $this->base64urlEncode($user->getId()),
                'name' => $user->getEmail(),
                'displayName' => $user->getUsername(),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                // Don't force platform - let browser decide
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
            'attestation' => 'none',
            'timeout' => 120000, // 2 minutes
            'excludeCredentials' => $excludeCredentials,
        ];
    }

    /**
     * Generate login options for authentication
     */
    public function getLoginOptions(?string $email = null): array
    {
        // Generate challenge
        $challenge = $this->base64urlEncode(random_bytes(32));

        $allowCredentials = [];

        // If email provided, get user's credentials
        if ($email) {
            $user = $this->userRepository->findByEmail($email);
            if ($user) {
                foreach ($this->credentialRepository->findAllByUser($user) as $credential) {
                    $allowCredentials[] = [
                        'type' => 'public-key',
                        'id' => $credential->getCredentialId(),
                    ];
                }
            }
        }

        return [
            'challenge' => $challenge,
            'rpId' => $this->rpId,
            'timeout' => 120000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
        ];
    }

    /**
     * Verify registration response and save credential
     */
    public function verifyRegistration(array $response, User $user): WebauthnCredential
    {
        $credentialId = $response['id'] ?? null;
        $clientDataJSON = $response['response']['clientDataJSON'] ?? null;
        $attestationObject = $response['response']['attestationObject'] ?? null;

        if (!$credentialId || !$clientDataJSON || !$attestationObject) {
            throw new \RuntimeException('Invalid registration response: missing required fields');
        }

        // Decode clientDataJSON to verify type
        $clientData = json_decode($this->base64urlDecode($clientDataJSON), true);
        
        if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException('Invalid client data type');
        }

        // Store the attestation (in production, parse CBOR and extract public key)
        $publicKey = $attestationObject;

        // Save credential
        return $this->credentialRepository->saveCredential(
            $user,
            $credentialId,
            $publicKey,
            0, // Initial counter
            'public-key',
            ['internal'],
            'none',
            '00000000-0000-0000-0000-000000000000'
        );
    }

    /**
     * Verify login response and return user
     */
    public function verifyLogin(array $response, string $email): User
    {
        $credentialId = $response['id'] ?? null;
        $clientDataJSON = $response['response']['clientDataJSON'] ?? null;
        $authenticatorData = $response['response']['authenticatorData'] ?? null;
        $signature = $response['response']['signature'] ?? null;

        if (!$credentialId || !$clientDataJSON || !$authenticatorData || !$signature) {
            throw new \RuntimeException('Invalid login response: missing required fields');
        }

        // Find credential
        $credential = $this->credentialRepository->findOneByCredentialId($credentialId);
        if (!$credential) {
            throw new \RuntimeException('Passkey not found');
        }

        // Get user
        $user = $credential->getUser();
        if (!$user || $user->getEmail() !== $email) {
            throw new \RuntimeException('Email does not match passkey');
        }

        // Decode clientDataJSON to verify type
        $clientData = json_decode($this->base64urlDecode($clientDataJSON), true);
        
        if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \RuntimeException('Invalid client data type');
        }

        // Update counter
        $this->credentialRepository->updateCounter($credential, $credential->getCounter() + 1);

        return $user;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
