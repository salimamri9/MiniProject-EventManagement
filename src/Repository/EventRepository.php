<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function save(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find upcoming events (from now forward)
     * @return array<Event>
     */
    public function findUpcomingEvents(int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.date', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events with available seats
     * @return array<Event>
     */
    public function findAvailableEvents(int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r', 'WITH', 'r.status = :confirmed')
            ->where('e.date >= :now')
            ->groupBy('e.id')
            ->having('COUNT(r.id) < e.seats')
            ->setParameter('now', new \DateTime())
            ->setParameter('confirmed', 'confirmed')
            ->orderBy('e.date', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all events with pagination
     * @return array<Event>
     */
    public function findAllPaginated(int $page = 1, int $perPage = 20): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.date', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count upcoming events
     */
    public function countUpcomingEvents(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.date >= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search events by title, description, or location
     * @return array<Event>
     */
    public function findBySearch(string $search): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.title LIKE :search')
            ->orWhere('e.description LIKE :search')
            ->orWhere('e.location LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
