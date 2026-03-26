<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;

class AdminService
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private ReservationRepository $reservationRepository
    ) {
    }

    public function getDashboardStats(): array
    {
        return [
            'total_users' => $this->userRepository->countAll(),
            'total_events' => $this->eventRepository->countAll(),
            'total_reservations' => $this->reservationRepository->countAll(),
            'upcoming_events' => $this->eventRepository->countUpcomingEvents(),
        ];
    }

    public function getUsersList(int $page = 1, int $perPage = 20): array
    {
        return $this->userRepository->findAllPaginated($page, $perPage);
    }

    public function getReservationsList(int $page = 1, int $perPage = 20): array
    {
        return $this->reservationRepository->findAllPaginated($page, $perPage);
    }

    public function deleteUser(string $userId): void
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $this->userRepository->remove($user, true);
    }
}
