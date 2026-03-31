# Noevent

A Symfony 7.4 web application to manage events and reservations, with separate user/admin flows, JWT support for APIs, and passkey-ready authentication components.

## Features

- Public event listing and event detail pages
- User registration and login
- Reservation creation and cancellation
- Admin dashboard with event and reservation management
- Reservation status workflow (`pending`, `confirmed`, `cancelled`)
- Automatic email notification when a reservation is confirmed
- API endpoints for reservations/admin actions (JWT-protected)
- Basic unit tests for core entities

## Technologies Used

- **Backend:** PHP 8.2+, Symfony 7.4
- **Database:** PostgreSQL 16 (Docker)
- **ORM:** Doctrine ORM + Doctrine Migrations
- **Auth:** Symfony Security + Lexik JWT + Gesdinet Refresh Token
- **Templates/UI:** Twig + custom CSS/JS
- **Email:** Symfony Mailer (Mailpit SMTP)
- **Testing:** PHPUnit 11
- **Containerization:** Docker Compose

## Project Structure

- [src](src): Controllers, entities, repositories, services
- [templates](templates): Twig templates (web pages + email templates)
- [config](config): Symfony and package configuration
- [migrations](migrations): Doctrine migration files
- [public](public): Web entrypoint and frontend assets
- [tests](tests): Unit tests

## How to Start the Project

The application is fully containerized. You do not need to install PHP or Composer locally.

### 1) Start the Application

```bash
docker compose up --build -d
```

This will automatically:
- Start the application on http://localhost:8088
- Boot the PostgreSQL database
- Run all database migrations
- Seed initial data (Admin user and demo events)
- Start Mailpit for catching local emails on http://localhost:8025

### 2) Default Seeded Credentials

Because new admin accounts cannot be created from the UI, the application automatically seeds a default admin account on the very first startup:

- **Admin Login URL:** http://localhost:8088/admin/login
- **Username:** `admin`
- **Password:** `admin12345`

*(You can change these default seed values in `compose.yaml` under the environment variables `ADMIN_SEED_USERNAME` and `ADMIN_SEED_PASSWORD`)*

## Useful Commands

Run tests (inside the container):

```bash
docker exec app-noevent-1 bin/phpunit
```

Clear cache (inside the container):

```bash
docker exec app-noevent-1 php bin/console cache:clear
```

## Notes

- Confirmation emails are sent when an admin changes a reservation status to `confirmed`.
- For local development, emails are captured by Mailpit (no real email delivery), check http://localhost:8025 .
