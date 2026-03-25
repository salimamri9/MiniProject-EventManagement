<?php

namespace App\Repository;

use App\Entity\WebauthnCredential;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 * 
 * Custom repository for WebAuthn credentials.
 * Does not extend WebAuthn bundle class to avoid missing dependency issues.
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function save(WebauthnCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WebauthnCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByCredentialId(string $credentialId): ?WebauthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    /**
     * @return WebauthnCredential[]
     */
    public function findAllByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * @return WebauthnCredential[]
     */
    public function findAllByUserId(string $userId): array
    {
        return $this->createQueryBuilder('wc')
            ->where('wc.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function saveCredential(
        User $user,
        string $credentialId,
        string $publicKey,
        int $counter,
        string $type = 'public-key',
        array $transports = [],
        string $attestationType = 'none',
        string $aaguid = '00000000-0000-0000-0000-000000000000'
    ): WebauthnCredential {
        $credential = new WebauthnCredential();
        $credential->setId(\Symfony\Component\Uid\Uuid::v4()->toRfc4122());
        $credential->setUser($user);
        $credential->setCredentialId($credentialId);
        $credential->setPublicKey($publicKey);
        $credential->setCounter($counter);
        $credential->setType($type);
        $credential->setTransports(json_encode($transports));
        $credential->setAttestationType($attestationType);
        $credential->setTrustPath('[]');
        $credential->setAaguid($aaguid);
        $credential->setCreatedAt(new \DateTimeImmutable());

        $this->save($credential, true);

        return $credential;
    }

    public function updateCounter(WebauthnCredential $credential, int $counter): void
    {
        $credential->setCounter($counter);
        $this->getEntityManager()->flush();
    }
}
