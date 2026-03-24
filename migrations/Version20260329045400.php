<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329045400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema: User, Admin, Event, Reservation, WebauthnCredential, RefreshToken';
    }

    public function up(Schema $schema): void
    {
        // Admin table
        $this->addSql('CREATE TABLE admin (
            id CHAR(36) NOT NULL PRIMARY KEY,
            username VARCHAR(180) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ADMIN_USERNAME ON admin (username)');

        // User table
        $this->addSql('CREATE TABLE user (
            id CHAR(36) NOT NULL PRIMARY KEY,
            username VARCHAR(180) NOT NULL UNIQUE,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) DEFAULT NULL,
            roles TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_USERNAME ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');

        // Event table
        $this->addSql('CREATE TABLE event (
            id CHAR(36) NOT NULL PRIMARY KEY,
            creator_id CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            date TIMESTAMP NOT NULL,
            location VARCHAR(255) NOT NULL,
            seats INTEGER NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            FOREIGN KEY (creator_id) REFERENCES admin(id)
        )');
        $this->addSql('CREATE INDEX IDX_EVENT_DATE ON event (date)');
        $this->addSql('CREATE INDEX IDX_EVENT_CREATOR ON event (creator_id)');

        // Reservation table
        $this->addSql('CREATE TABLE reservation (
            id CHAR(36) NOT NULL PRIMARY KEY,
            event_id CHAR(36) NOT NULL,
            user_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NOT NULL,
            confirmed_at TIMESTAMP DEFAULT NULL,
            FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IDX_RESERVATION_EVENT ON reservation (event_id)');
        $this->addSql('CREATE INDEX IDX_RESERVATION_USER ON reservation (user_id)');
        $this->addSql('CREATE INDEX IDX_RESERVATION_STATUS ON reservation (status)');

        // WebauthnCredential table
        $this->addSql('CREATE TABLE webauthn_credential (
            id CHAR(36) NOT NULL PRIMARY KEY,
            user_id CHAR(36) NOT NULL,
            public_key_credential_id VARCHAR(255) NOT NULL UNIQUE,
            credential_data TEXT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL,
            last_used_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CREDENTIAL_ID ON webauthn_credential (public_key_credential_id)');
        $this->addSql('CREATE INDEX IDX_WEBAUTHN_USER ON webauthn_credential (user_id)');

        // RefreshToken table (already exists in entity but adding for completeness)
        $this->addSql('CREATE TABLE IF NOT EXISTS refresh_token (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            refresh_token VARCHAR(128) NOT NULL UNIQUE,
            username VARCHAR(255) NOT NULL,
            valid TIMESTAMP NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REFRESH_TOKEN ON refresh_token (refresh_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS webauthn_credential');
        $this->addSql('DROP TABLE IF EXISTS reservation');
        $this->addSql('DROP TABLE IF EXISTS event');
        $this->addSql('DROP TABLE IF EXISTS user');
        $this->addSql('DROP TABLE IF EXISTS admin');
        $this->addSql('DROP TABLE IF EXISTS refresh_token');
    }
}
