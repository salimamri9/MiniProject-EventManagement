<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Admin;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private Event $event;
    private Admin $admin;

    protected function setUp(): void
    {
        $this->admin = new Admin();
        $this->admin->setUsername('testadmin');
        
        $this->event = new Event();
        $this->event->setTitle('Test Event');
        $this->event->setDescription('Test Description');
        $this->event->setDate(new \DateTime('+1 week'));
        $this->event->setLocation('Test Location');
        $this->event->setSeats(100);
        $this->event->setCreator($this->admin);
    }

    public function testEventCreation(): void
    {
        $this->assertEquals('Test Event', $this->event->getTitle());
        $this->assertEquals(100, $this->event->getSeats());
        $this->assertNotEmpty($this->event->getId());
    }

    public function testAvailableSeatsWithNoReservations(): void
    {
        $this->assertEquals(100, $this->event->getAvailableSeats());
        $this->assertTrue($this->event->hasAvailableSeats());
    }

    public function testAvailableSeatsWithConfirmedReservation(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setUsername('testuser');
        
        $reservation = new Reservation();
        $reservation->setEvent($this->event);
        $reservation->setUser($user);
        $reservation->setFullName('Test User');
        $reservation->setEmail('test@test.com');
        $reservation->setStatus('confirmed');
        
        $this->event->addReservation($reservation);
        
        $this->assertEquals(99, $this->event->getAvailableSeats());
    }

    public function testAvailableSeatsWithPendingReservation(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setUsername('testuser');
        
        $reservation = new Reservation();
        $reservation->setEvent($this->event);
        $reservation->setUser($user);
        $reservation->setFullName('Test User');
        $reservation->setEmail('test@test.com');
        $reservation->setStatus('pending');
        
        $this->event->addReservation($reservation);
        
        // Pending should also count against available seats
        $this->assertEquals(99, $this->event->getAvailableSeats());
    }

    public function testCancelledReservationDoesNotCountAgainstSeats(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setUsername('testuser');
        
        $reservation = new Reservation();
        $reservation->setEvent($this->event);
        $reservation->setUser($user);
        $reservation->setFullName('Test User');
        $reservation->setEmail('test@test.com');
        $reservation->setStatus('cancelled');
        
        $this->event->addReservation($reservation);
        
        // Cancelled should NOT count
        $this->assertEquals(100, $this->event->getAvailableSeats());
    }

    public function testEventFullWhenNoSeatsAvailable(): void
    {
        $this->event->setSeats(1);
        
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setUsername('testuser');
        
        $reservation = new Reservation();
        $reservation->setEvent($this->event);
        $reservation->setUser($user);
        $reservation->setFullName('Test User');
        $reservation->setEmail('test@test.com');
        $reservation->setStatus('confirmed');
        
        $this->event->addReservation($reservation);
        
        $this->assertEquals(0, $this->event->getAvailableSeats());
        $this->assertFalse($this->event->hasAvailableSeats());
    }

    public function testConfirmedReservationsCount(): void
    {
        $user1 = new User();
        $user1->setEmail('user1@test.com');
        $user1->setUsername('user1');
        
        $user2 = new User();
        $user2->setEmail('user2@test.com');
        $user2->setUsername('user2');
        
        $res1 = new Reservation();
        $res1->setEvent($this->event);
        $res1->setUser($user1);
        $res1->setFullName('User 1');
        $res1->setEmail('user1@test.com');
        $res1->setStatus('confirmed');
        
        $res2 = new Reservation();
        $res2->setEvent($this->event);
        $res2->setUser($user2);
        $res2->setFullName('User 2');
        $res2->setEmail('user2@test.com');
        $res2->setStatus('pending');
        
        $this->event->addReservation($res1);
        $this->event->addReservation($res2);
        
        $this->assertEquals(1, $this->event->getConfirmedReservationsCount());
        $this->assertEquals(2, $this->event->getActiveReservationsCount());
    }
}
