<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Event;
use App\Repository\AdminRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-initial-data', description: 'Seeds initial admin account and demo events (idempotent).')]
class SeedInitialDataCommand extends Command
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly EventRepository $eventRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $adminUsername = $_ENV['ADMIN_SEED_USERNAME'] ?? 'admin';
        $adminPassword = $_ENV['ADMIN_SEED_PASSWORD'] ?? 'admin12345';

        $admin = $this->adminRepository->findByUsername($adminUsername);
        if (!$admin) {
            $admin = new Admin();
            $admin->setUsername($adminUsername);
            $admin->setPasswordHash($this->passwordHasher->hashPassword($admin, $adminPassword));

            $this->entityManager->persist($admin);
            $output->writeln(sprintf('<info>Seeded admin account:</info> %s', $adminUsername));
        } else {
            $output->writeln(sprintf('<comment>Admin already exists:</comment> %s', $adminUsername));
        }

        if ($this->eventRepository->count([]) === 0) {
            foreach ($this->getDemoEvents() as $eventData) {
                $event = new Event();
                $event->setTitle($eventData['title']);
                $event->setDescription($eventData['description']);
                $event->setLocation($eventData['location']);
                $event->setDate($eventData['date']);
                $event->setSeats($eventData['seats']);
                $event->setImage($eventData['image']);
                $event->setCreator($admin);

                $this->entityManager->persist($event);
            }

            $output->writeln('<info>Seeded demo events.</info>');
        } else {
            $output->writeln('<comment>Events already exist, skipping demo events seed.</comment>');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{title: string, description: string, location: string, date: \DateTime, seats: int, image: ?string}>
     */
    private function getDemoEvents(): array
    {
        return [
            [
                'title' => 'Noevent Launch Night',
                'description' => 'A live kickoff with product demos, networking, and announcements.',
                'location' => 'Tunis Tech Arena',
                'date' => new \DateTime('+3 days 18:00'),
                'seats' => 120,
                'image' => null,
            ],
            [
                'title' => 'Design Systems Workshop',
                'description' => 'Hands-on workshop about scalable UI systems and component architecture.',
                'location' => 'Sousse Creative Hub',
                'date' => new \DateTime('+7 days 10:00'),
                'seats' => 60,
                'image' => null,
            ],
            [
                'title' => 'Backend Scalability Meetup',
                'description' => 'Talks on caching, queues, and production-grade API design.',
                'location' => 'Sfax Innovation Center',
                'date' => new \DateTime('+10 days 16:30'),
                'seats' => 90,
                'image' => null,
            ],
        ];
    }
}
