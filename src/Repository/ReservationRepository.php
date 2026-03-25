<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function save(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find reservations for a specific user
     * @return array<Reservation>
     */
    public function findUserReservations(User $user, int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.event', 'e')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.date', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations for a specific event
     * @return array<Reservation>
     */
    public function findEventReservations(Event $event, int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.event = :event')
            ->setParameter('event', $event)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user already has a reservation for an event
     */
    public function hasUserReservationForEvent(User $user, Event $event): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.event = :event')
            ->andWhere('r.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find all reservations with pagination
     * @return array<Reservation>
     */
    public function findAllPaginated(int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.event', 'e')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count confirmed reservations for an event
     */
    public function countConfirmedForEvent(Event $event): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.event = :event')
            ->andWhere('r.status = :confirmed')
            ->setParameter('event', $event)
            ->setParameter('confirmed', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
