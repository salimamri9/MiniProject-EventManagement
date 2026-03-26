<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Admin;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

class EventService
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createEvent(array $data, Admin $creator): Event
    {
        $event = new Event();
        $event->setTitle($data['title']);
        $event->setDescription($data['description']);
        $event->setDate(new \DateTime($data['date']));
        $event->setLocation($data['location']);
        $event->setSeats((int)$data['seats']);
        $event->setImage($data['image'] ?? null);
        $event->setCreator($creator);

        $this->eventRepository->save($event, true);
        return $event;
    }

    public function updateEvent(string $id, array $data, Admin $admin): Event
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            throw new \RuntimeException('Event not found');
        }

        if (isset($data['title'])) $event->setTitle($data['title']);
        if (isset($data['description'])) $event->setDescription($data['description']);
        if (isset($data['date'])) $event->setDate(new \DateTime($data['date']));
        if (isset($data['location'])) $event->setLocation($data['location']);
        if (isset($data['seats'])) $event->setSeats((int)$data['seats']);
        if (isset($data['image'])) $event->setImage($data['image']);

        $this->eventRepository->save($event, true);
        return $event;
    }

    public function deleteEvent(string $id, Admin $admin): void
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            throw new \RuntimeException('Event not found');
        }

        $this->eventRepository->remove($event, true);
    }

    public function getAvailableEvents(int $page = 1, int $perPage = 20): array
    {
        return $this->eventRepository->findAvailableEvents($page, $perPage);
    }

    public function getEventById(string $id): ?Event
    {
        return $this->eventRepository->find($id);
    }

    public function checkAvailability(string $eventId): bool
    {
        $event = $this->getEventById($eventId);
        return $event && $event->hasAvailableSeats();
    }
}
