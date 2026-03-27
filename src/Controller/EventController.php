<?php

namespace App\Controller;

use App\Service\EventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EventService $eventService
    ) {
    }

    #[Route('', name: 'events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 20);

        $events = $this->eventService->getAvailableEvents($page, $perPage);

        return $this->json([
            'events' => array_map(fn($event) => [
                'id' => $event->getId()->toRfc4122(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'date' => $event->getDate()->format('Y-m-d H:i:s'),
                'location' => $event->getLocation(),
                'seats' => $event->getSeats(),
                'available_seats' => $event->getAvailableSeats(),
                'image' => $event->getImage()
            ], $events)
        ]);
    }

    #[Route('/{id}', name: 'events_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $event = $this->eventService->getEventById($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        return $this->json([
            'id' => $event->getId()->toRfc4122(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()->format('Y-m-d H:i:s'),
            'location' => $event->getLocation(),
            'seats' => $event->getSeats(),
            'available_seats' => $event->getAvailableSeats(),
            'image' => $event->getImage(),
            'creator' => [
                'id' => $event->getCreator()->getId()->toRfc4122(),
                'username' => $event->getCreator()->getUsername()
            ]
        ]);
    }

    #[Route('', name: 'events_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $admin = $this->getUser();

        try {
            $event = $this->eventService->createEvent($data, $admin);

            return $this->json([
                'id' => $event->getId()->toRfc4122(),
                'title' => $event->getTitle(),
                'message' => 'Event created successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'events_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $admin = $this->getUser();

        try {
            $event = $this->eventService->updateEvent($id, $data, $admin);

            return $this->json([
                'id' => $event->getId()->toRfc4122(),
                'message' => 'Event updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'events_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $admin = $this->getUser();

        try {
            $this->eventService->deleteEvent($id, $admin);

            return $this->json(['message' => 'Event deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
