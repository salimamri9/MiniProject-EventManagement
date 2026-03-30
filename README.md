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
- **Email:** Symfony Mailer (Mailpit/Mailhog-compatible SMTP)
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

### 1) Install dependencies

```bash
composer install
```

### 2) Start infrastructure (PostgreSQL + Mailpit)

```bash
docker compose up -d
```

Mailpit UI: http://localhost:8025  
SMTP host used by app: `mailer:1025`

### 3) Configure environment

Default values are in `.env`.

Main variables:
- `DATABASE_URL`
- `MAILER_DSN`
- `MAILER_FROM`
- `APP_SECRET`

### 4) Run database migrations

```bash
php bin/console doctrine:migrations:migrate -n
```

### 5) Run the app

Use PHP built-in server:

```bash
php -S 127.0.0.1:8000 -t public
```

Then open: http://127.0.0.1:8000

## Useful Commands

Run tests:

```bash
php bin/phpunit
```

Clear cache:

```bash
php bin/console cache:clear
```

## Notes

- Confirmation emails are sent when an admin changes a reservation status to `confirmed`.
- For local development, emails are captured by Mailpit (no real email delivery).
