<?php

namespace App\Controller;

use App\Service\AdminService;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private AdminService $adminService,
        private ReservationService $reservationService
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $stats = $this->adminService->getDashboardStats();
        return $this->json($stats);
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $users = $this->adminService->getUsersList($page);

        return $this->json([
            'users' => array_map(fn($u) => [
                'id' => $u->getId()->toRfc4122(),
                'username' => $u->getUsername(),
                'email' => $u->getEmail(),
                'created_at' => $u->getCreatedAt()->format('Y-m-d H:i:s')
            ], $users)
        ]);
    }

    #[Route('/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function reservations(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $reservations = $this->adminService->getReservationsList($page);

        return $this->json([
            'reservations' => array_map(fn($r) => [
                'id' => $r->getId()->toRfc4122(),
                'event' => [
                    'id' => $r->getEvent()->getId()->toRfc4122(),
                    'title' => $r->getEvent()->getTitle()
                ],
                'user' => [
                    'id' => $r->getUser()->getId()->toRfc4122(),
                    'username' => $r->getUser()->getUsername()
                ],
                'name' => $r->getFullName(),
                'email' => $r->getEmail(),
                'status' => $r->getStatus(),
                'created_at' => $r->getCreatedAt()->format('Y-m-d H:i:s')
            ], $reservations)
        ]);
    }

    #[Route('/events/{id}/reservations', name: 'admin_event_reservations', methods: ['GET'])]
    public function eventReservations(string $id, Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $admin = $this->getUser();

        try {
            $reservations = $this->reservationService->getEventReservations($id, $admin, $page);

            return $this->json([
                'reservations' => array_map(fn($r) => [
                    'id' => $r->getId()->toRfc4122(),
                    'user' => [
                        'username' => $r->getUser()->getUsername(),
                        'email' => $r->getUser()->getEmail()
                    ],
                    'name' => $r->getFullName(),
                    'email' => $r->getEmail(),
                    'phone' => $r->getPhone(),
                    'status' => $r->getStatus(),
                    'created_at' => $r->getCreatedAt()->format('Y-m-d H:i:s')
                ], $reservations)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/users/{id}', name: 'admin_delete_user', methods: ['DELETE'])]
    public function deleteUser(string $id): JsonResponse
    {
        try {
            $this->adminService->deleteUser($id);
            return $this->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
