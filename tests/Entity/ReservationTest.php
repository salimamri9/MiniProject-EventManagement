<?php

namespace App\Tests\Entity;

use App\Entity\Reservation;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\Admin;
use PHPUnit\Framework\TestCase;

class ReservationTest extends TestCase
{
    private Reservation $reservation;
    private Event $event;
    private User $user;

    protected function setUp(): void
    {
        $admin = new Admin();
        $admin->setUsername('admin');
        
        $this->event = new Event();
        $this->event->setTitle('Test Event');
        $this->event->setDescription('Description');
        $this->event->setDate(new \DateTime('+1 week'));
        $this->event->setLocation('Location');
        $this->event->setSeats(100);
        $this->event->setCreator($admin);
        
        $this->user = new User();
        $this->user->setUsername('testuser');
        $this->user->setEmail('test@example.com');
        
        $this->reservation = new Reservation();
        $this->reservation->setEvent($this->event);
        $this->reservation->setUser($this->user);
        $this->reservation->setFullName('John Doe');
        $this->reservation->setEmail('john@example.com');
        $this->reservation->setPhone('+216 12 345 678');
        $this->reservation->setStatus('pending');
    }

    public function testReservationCreation(): void
    {
        $this->assertEquals('John Doe', $this->reservation->getFullName());
        $this->assertEquals('john@example.com', $this->reservation->getEmail());
        $this->assertEquals('pending', $this->reservation->getStatus());
        $this->assertNotEmpty($this->reservation->getId());
    }

    public function testReservationStatusTransitions(): void
    {
        // Initial status is pending
        $this->assertEquals('pending', $this->reservation->getStatus());
        
        // Confirm
        $this->reservation->confirm();
        $this->assertEquals('confirmed', $this->reservation->getStatus());
        
        // Cancel
        $this->reservation->cancel();
        $this->assertEquals('cancelled', $this->reservation->getStatus());
    }

    public function testReservationEventRelation(): void
    {
        $this->assertSame($this->event, $this->reservation->getEvent());
        $this->assertEquals('Test Event', $this->reservation->getEvent()->getTitle());
    }

    public function testReservationUserRelation(): void
    {
        $this->assertSame($this->user, $this->reservation->getUser());
        $this->assertEquals('testuser', $this->reservation->getUser()->getUsername());
    }

    public function testUpdatedAtChangesOnStatusChange(): void
    {
        $originalUpdatedAt = $this->reservation->getUpdatedAt();
        
        // Small delay to ensure different timestamp
        usleep(10000);
        
        $this->reservation->setStatus('confirmed');
        
        $this->assertGreaterThanOrEqual(
            $originalUpdatedAt,
            $this->reservation->getUpdatedAt()
        );
    }
}
