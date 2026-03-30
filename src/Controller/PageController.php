<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Repository\AdminRepository;
use App\Service\ReservationNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PageController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private ReservationRepository $reservationRepository,
        private UserRepository $userRepository,
        private AdminRepository $adminRepository,
        private ReservationNotificationService $reservationNotificationService,
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'event_list', methods: ['GET'])]
    public function listEvents(Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $events = $this->eventRepository->findBySearch($search);
        } else {
            $events = $this->eventRepository->findBy([], ['date' => 'ASC']);
        }

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'search' => $search
        ]);
    }

    #[Route('/event/{id}', name: 'event_detail', methods: ['GET'])]
    public function eventDetail(string $id): Response
    {
        $event = $this->eventRepository->find($id);
        
        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('event_list');
        }

        return $this->render('event/detail.html.twig', [
            'event' => $event
        ]);
    }

    #[Route('/reservation/create/{eventId}', name: 'reservation_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createReservation(Request $request, string $eventId): Response
    {
        if (!$this->isCsrfTokenValid('reservation', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('event_detail', ['id' => $eventId]);
        }

        // Use transaction with locking to prevent race conditions
        $this->em->beginTransaction();
        try {
            // Lock the event row for update to prevent overbooking
            $event = $this->em->createQuery(
                'SELECT e FROM App\Entity\Event e WHERE e.id = :id'
            )
            ->setParameter('id', $eventId)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

            if (!$event) {
                $this->em->rollback();
                $this->addFlash('error', 'Event not found.');
                return $this->redirectToRoute('event_list');
            }

            // Check if event is in the past
            if ($event->getDate() < new \DateTime()) {
                $this->em->rollback();
                $this->addFlash('error', 'Cannot reserve for past events.');
                return $this->redirectToRoute('event_detail', ['id' => $eventId]);
            }

            // Check seat availability - count both pending and confirmed
            $activeCount = $this->reservationRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.event = :event')
                ->andWhere('r.status IN (:statuses)')
                ->setParameter('event', $event)
                ->setParameter('statuses', ['pending', 'confirmed'])
                ->getQuery()
                ->getSingleScalarResult();
            
            if ($event->getSeats() - $activeCount <= 0) {
                $this->em->rollback();
                $this->addFlash('error', 'No seats available for this event.');
                return $this->redirectToRoute('event_detail', ['id' => $eventId]);
            }

            $user = $this->getUser();
            
            // Check for duplicate active reservation (pending or confirmed)
            $existing = $this->reservationRepository->createQueryBuilder('r')
                ->where('r.user = :user')
                ->andWhere('r.event = :event')
                ->andWhere('r.status IN (:statuses)')
                ->setParameter('user', $user)
                ->setParameter('event', $event)
                ->setParameter('statuses', ['pending', 'confirmed'])
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                $this->em->rollback();
                $this->addFlash('error', 'You already have a reservation for this event.');
                return $this->redirectToRoute('event_detail', ['id' => $eventId]);
            }

            // Validate input
            $fullName = trim($request->request->get('fullName', ''));
            $email = trim($request->request->get('email', ''));
            $phone = trim($request->request->get('phone', ''));

            if (empty($fullName) || strlen($fullName) < 2) {
                $this->em->rollback();
                $this->addFlash('error', 'Please enter a valid name.');
                return $this->redirectToRoute('event_detail', ['id' => $eventId]);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->em->rollback();
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->redirectToRoute('event_detail', ['id' => $eventId]);
            }

            $reservation = new \App\Entity\Reservation();
            $reservation->setId(\Symfony\Component\Uid\Uuid::v4()->toRfc4122());
            $reservation->setEvent($event);
            $reservation->setUser($user);
            $reservation->setFullName($fullName);
            $reservation->setEmail($email);
            $reservation->setPhone($phone);
            $reservation->setStatus('pending');
            $reservation->setCreatedAt(new \DateTimeImmutable());
            $reservation->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($reservation);
            $this->em->flush();
            $this->em->commit();

            $this->addFlash('success', 'Reservation created successfully!');
            return $this->redirectToRoute('user_reservations');
        } catch (\Exception $e) {
            $this->em->rollback();
            $this->addFlash('error', 'Failed to create reservation. Please try again.');
            return $this->redirectToRoute('event_detail', ['id' => $eventId]);
        }
    }

    #[Route('/my-reservations', name: 'user_reservations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myReservations(): Response
    {
        $reservations = $this->reservationRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/reservation/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelReservation(Request $request, string $id): Response
    {
        if (!$this->isCsrfTokenValid('reservation_cancel_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('user_reservations');
        }

        $reservation = $this->reservationRepository->find($id);
        
        if (!$reservation || $reservation->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Reservation not found.');
            return $this->redirectToRoute('user_reservations');
        }

        if ($reservation->getStatus() === 'cancelled') {
            $this->addFlash('error', 'Reservation is already cancelled.');
            return $this->redirectToRoute('user_reservations');
        }

        $reservation->setStatus('cancelled');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Reservation cancelled successfully.');
        return $this->redirectToRoute('user_reservations');
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        $stats = [
            'totalUsers' => $this->userRepository->count([]),
            'totalEvents' => $this->eventRepository->count([]),
            'totalReservations' => $this->reservationRepository->count([]),
            'upcomingEvents' => $this->eventRepository->count([
                // Would need custom query for date comparison
            ])
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats
        ]);
    }

    #[Route('/admin/events', name: 'admin_events', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEvents(): Response
    {
        $events = $this->eventRepository->findBy([], ['date' => 'DESC']);

        return $this->render('admin/events.html.twig', [
            'events' => $events
        ]);
    }

    #[Route('/admin/events/create', name: 'admin_event_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createEvent(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('event_create', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_event_create');
            }

            // Validate input
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $dateStr = $request->request->get('date', '');
            $location = trim($request->request->get('location', ''));
            $seats = (int) $request->request->get('seats', 0);
            $image = trim($request->request->get('image', ''));

            $errors = [];

            if (empty($title) || strlen($title) < 3) {
                $errors[] = 'Title must be at least 3 characters.';
            }
            if (empty($description) || strlen($description) < 10) {
                $errors[] = 'Description must be at least 10 characters.';
            }
            if (empty($location)) {
                $errors[] = 'Location is required.';
            }
            if ($seats < 1) {
                $errors[] = 'Seats must be at least 1.';
            }
            if ($seats > 10000) {
                $errors[] = 'Seats cannot exceed 10,000.';
            }

            // Validate date
            try {
                $date = new \DateTime($dateStr);
                if ($date < new \DateTime('today')) {
                    $errors[] = 'Event date must be in the future.';
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid date format.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('admin/event_form.html.twig', [
                    'event' => null,
                    'action' => 'Create'
                ]);
            }

            $event = new Event();
            $event->setTitle($title);
            $event->setDescription($description);
            $event->setDate($date);
            $event->setLocation($location);
            $event->setSeats($seats);
            $event->setImage($image ?: null);
            $event->setCreator($this->getUser());

            $this->em->persist($event);
            $this->em->flush();

            $this->addFlash('success', 'Event created successfully!');
            return $this->redirectToRoute('admin_events');
        }

        return $this->render('admin/event_form.html.twig', [
            'event' => null,
            'action' => 'Create'
        ]);
    }

    #[Route('/admin/events/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editEvent(Request $request, string $id): Response
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('admin_events');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('event_edit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_event_edit', ['id' => $id]);
            }

            // Validate input
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $dateStr = $request->request->get('date', '');
            $location = trim($request->request->get('location', ''));
            $seats = (int) $request->request->get('seats', 0);
            $image = trim($request->request->get('image', ''));

            $errors = [];

            if (empty($title) || strlen($title) < 3) {
                $errors[] = 'Title must be at least 3 characters.';
            }
            if (empty($description) || strlen($description) < 10) {
                $errors[] = 'Description must be at least 10 characters.';
            }
            if (empty($location)) {
                $errors[] = 'Location is required.';
            }
            if ($seats < 1) {
                $errors[] = 'Seats must be at least 1.';
            }

            // Validate date
            try {
                $date = new \DateTime($dateStr);
                // Allow past dates only if event was already in the past
                if ($event->getDate() >= new \DateTime('today') && $date < new \DateTime('today')) {
                    $errors[] = 'Cannot change event date to the past.';
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid date format.';
            }

            // Validate seats against confirmed reservations
            $confirmedCount = $event->getConfirmedReservationsCount();
            if ($seats < $confirmedCount) {
                $errors[] = "Cannot reduce seats below $confirmedCount (current confirmed reservations).";
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('admin/event_form.html.twig', [
                    'event' => $event,
                    'action' => 'Edit'
                ]);
            }

            $event->setTitle($title);
            $event->setDescription($description);
            $event->setDate($date);
            $event->setLocation($location);
            $event->setSeats($seats);
            $event->setImage($image ?: null);

            $this->em->flush();

            $this->addFlash('success', 'Event updated successfully!');
            return $this->redirectToRoute('admin_events');
        }

        return $this->render('admin/event_form.html.twig', [
            'event' => $event,
            'action' => 'Edit'
        ]);
    }

    #[Route('/admin/events/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteEvent(Request $request, string $id): Response
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('admin_events');
        }

        if (!$this->isCsrfTokenValid('event_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_events');
        }

        // Check for confirmed reservations
        $confirmedCount = $event->getConfirmedReservationsCount();
        if ($confirmedCount > 0) {
            $this->addFlash('error', "Cannot delete event with $confirmedCount confirmed reservation(s). Cancel them first.");
            return $this->redirectToRoute('admin_events');
        }

        $this->em->remove($event);
        $this->em->flush();

        $this->addFlash('success', 'Event deleted successfully!');
        return $this->redirectToRoute('admin_events');
    }

    #[Route('/admin/reservations/{id}/status', name: 'admin_reservation_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateReservationStatus(Request $request, string $id): Response
    {
        $reservation = $this->reservationRepository->find($id);
        if (!$reservation) {
            $this->addFlash('error', 'Reservation not found.');
            return $this->redirectToRoute('admin_reservations');
        }

        if (!$this->isCsrfTokenValid('reservation_status_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_reservations');
        }

        $previousStatus = $reservation->getStatus();
        $newStatus = $request->request->get('status');
        if (in_array($newStatus, ['pending', 'confirmed', 'cancelled'])) {
            $reservation->setStatus($newStatus);
            $reservation->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            if ($previousStatus !== 'confirmed' && $newStatus === 'confirmed') {
                $emailSent = $this->reservationNotificationService->sendReservationConfirmedEmail($reservation);

                if ($emailSent) {
                    $this->addFlash('success', 'Reservation confirmed and email sent to the user.');
                } else {
                    $this->addFlash('warning', 'Reservation confirmed, but email could not be sent.');
                }
            } else {
                $this->addFlash('success', 'Reservation status updated!');
            }
        } else {
            $this->addFlash('error', 'Invalid reservation status.');
        }

        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/admin/reservations', name: 'admin_reservations', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminReservations(): Response
    {
        $reservations = $this->reservationRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/logout', name: 'user_logout', methods: ['GET'])]
    public function userLogout(): void
    {
        throw new \LogicException('This method should be intercepted by the logout key on your firewall.');
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function adminLogout(): void
    {
        throw new \LogicException('This method should be intercepted by the logout key on your firewall.');
    }
}
