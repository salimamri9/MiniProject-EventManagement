<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: '`event`')]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'datetime', name: 'date')]
    private \DateTime $date;

    #[ORM\Column(length: 255)]
    private string $location;

    #[ORM\Column(type: 'integer')]
    private int $seats;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Admin::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', nullable: false)]
    private Admin $creator;

    #[ORM\OneToMany(
        mappedBy: 'event',
        targetEntity: Reservation::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $reservations;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->reservations = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): self
    {
        $this->date = $date;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): self
    {
        $this->seats = $seats;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreator(): Admin
    {
        return $this->creator;
    }

    public function setCreator(Admin $creator): self
    {
        $this->creator = $creator;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setEvent($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getEvent() === $this) {
                $reservation->setEvent(null);
            }
        }
        return $this;
    }

    /**
     * Get the number of confirmed reservations
     */
    public function getConfirmedReservationsCount(): int
    {
        return $this->reservations->filter(
            fn(Reservation $r) => $r->getStatus() === 'confirmed'
        )->count();
    }

    /**
     * Get the number of active reservations (pending + confirmed)
     * Cancelled reservations don't count
     */
    public function getActiveReservationsCount(): int
    {
        return $this->reservations->filter(
            fn(Reservation $r) => in_array($r->getStatus(), ['pending', 'confirmed'])
        )->count();
    }

    /**
     * Get available seats (total seats - active reservations)
     * Both pending and confirmed count to prevent overbooking
     */
    public function getAvailableSeats(): int
    {
        return $this->seats - $this->getActiveReservationsCount();
    }

    /**
     * Check if event has available seats
     */
    public function hasAvailableSeats(): bool
    {
        return $this->getAvailableSeats() > 0;
    }
}
