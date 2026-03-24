<?php

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity for storing WebAuthn credentials
 * Simplified implementation without external WebAuthn bundle dependencies
 */
#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(name: 'credential_id', length: 255, unique: true)]
    private string $credentialId;

    #[ORM\Column(length: 255)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $transports;

    #[ORM\Column(name: 'attestation_type', length: 255)]
    private string $attestationType;

    #[ORM\Column(name: 'trust_path', type: 'text')]
    private string $trustPath;

    #[ORM\Column(length: 255)]
    private string $aaguid;

    #[ORM\Column(name: 'public_key', type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'integer')]
    private int $counter;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->counter = 0;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTransports(): array
    {
        return json_decode($this->transports, true) ?? [];
    }

    public function setTransports(string $transports): self
    {
        $this->transports = $transports;
        return $this;
    }

    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getTrustPath(): string
    {
        return $this->trustPath;
    }

    public function setTrustPath(string $trustPath): self
    {
        $this->trustPath = $trustPath;
        return $this;
    }

    public function getAaguid(): string
    {
        return $this->aaguid;
    }

    public function setAaguid(string $aaguid): self
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): self
    {
        $this->counter = $counter;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
