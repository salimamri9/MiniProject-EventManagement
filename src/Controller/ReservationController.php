<?php

namespace App\Controller;

use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations')]
class ReservationController extends AbstractController
{
    public function __construct(
        private ReservationService $reservationService
    ) {
    }

    #[Route('', name: 'reservations_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        $eventId = $data['event_id'] ?? null;

        if (!$eventId) {
            return $this->json(['error' => 'Event ID required'], 400);
        }

        try {
            $reservation = $this->reservationService->createReservation($eventId, $data, $user);

            return $this->json([
                'id' => $reservation->getId()->toRfc4122(),
                'event_id' => $reservation->getEvent()->getId()->toRfc4122(),
                'status' => $reservation->getStatus(),
                'message' => 'Reservation created successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('', name: 'reservations_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $page = (int) $request->query->get('page', 1);

        $reservations = $this->reservationService->getUserReservations($user, $page);

        return $this->json([
            'reservations' => array_map(fn($r) => [
                'id' => $r->getId()->toRfc4122(),
                'event' => [
                    'id' => $r->getEvent()->getId()->toRfc4122(),
                    'title' => $r->getEvent()->getTitle(),
                    'date' => $r->getEvent()->getDate()->format('Y-m-d H:i:s')
                ],
                'name' => $r->getFullName(),
                'email' => $r->getEmail(),
                'phone' => $r->getPhone(),
                'status' => $r->getStatus(),
                'created_at' => $r->getCreatedAt()->format('Y-m-d H:i:s')
            ], $reservations)
        ]);
    }

    #[Route('/{id}/cancel', name: 'reservations_cancel', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(string $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $reservation = $this->reservationService->cancelReservation($id, $user);

            return $this->json([
                'id' => $reservation->getId()->toRfc4122(),
                'status' => $reservation->getStatus(),
                'message' => 'Reservation cancelled'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/confirm', name: 'reservations_confirm', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirm(string $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->confirmReservation($id);

            return $this->json([
                'id' => $reservation->getId()->toRfc4122(),
                'status' => $reservation->getStatus(),
                'message' => 'Reservation confirmed'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
