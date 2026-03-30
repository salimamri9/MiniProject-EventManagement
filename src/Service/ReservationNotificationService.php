<?php

namespace App\Service;

use App\Entity\Reservation;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReservationNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $fromAddress,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function sendReservationConfirmedEmail(Reservation $reservation): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromAddress, 'Events Management'))
                ->to($reservation->getEmail())
                ->subject('Your reservation is confirmed')
                ->htmlTemplate('emails/reservation_confirmed.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'user' => $reservation->getUser(),
                    'event' => $reservation->getEvent(),
                ]);

            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to send reservation confirmation email', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
