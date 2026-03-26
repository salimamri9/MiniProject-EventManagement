<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\Admin;
use App\Repository\ReservationRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createReservation(string $eventId, array $data, User $user): Reservation
    {
        $event = $this->eventRepository->find($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found');
        }

        // Check if event date has passed
        if ($event->getDate() < new \DateTime()) {
            throw new \RuntimeException('Cannot reserve past events');
        }

        // Check for duplicate reservation
        if ($this->reservationRepository->hasUserReservationForEvent($user, $event)) {
            throw new \RuntimeException('User already has a reservation for this event');
        }

        // Check availability
        if (!$event->hasAvailableSeats()) {
            throw new \RuntimeException('Event is fully booked');
        }

        // Create reservation
        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setUser($user);
        $reservation->setFullName($data['name'] ?? $data['fullName'] ?? '');
        $reservation->setEmail($data['email']);
        $reservation->setPhone($data['phone']);
        $reservation->setStatus('pending');

        $this->reservationRepository->save($reservation, true);
        return $reservation;
    }

    public function cancelReservation(string $id, User $user): Reservation
    {
        $reservation = $this->reservationRepository->find($id);
        if (!$reservation) {
            throw new \RuntimeException('Reservation not found');
        }

        if ($reservation->getUser()->getId() != $user->getId()) {
            throw new \RuntimeException('Unauthorized');
        }

        $reservation->cancel();
        $this->reservationRepository->save($reservation, true);
        return $reservation;
    }

    public function confirmReservation(string $id): Reservation
    {
        $reservation = $this->reservationRepository->find($id);
        if (!$reservation) {
            throw new \RuntimeException('Reservation not found');
        }

        $reservation->confirm();
        $this->reservationRepository->save($reservation, true);
        return $reservation;
    }

    public function getUserReservations(User $user, int $page = 1, int $perPage = 20): array
    {
        return $this->reservationRepository->findUserReservations($user, $page, $perPage);
    }

    public function getEventReservations(string $eventId, Admin $admin, int $page = 1, int $perPage = 20): array
    {
        $event = $this->eventRepository->find($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found');
        }

        return $this->reservationRepository->findEventReservations($event, $page, $perPage);
    }
}
