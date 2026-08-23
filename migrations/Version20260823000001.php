<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMPORTANT: Initial schema for Postgres (Render).
 *
 * Differences from the old MySQL migration that this replaces:
 *   - INT AUTO_INCREMENT        → SERIAL
 *   - DATETIME                  → TIMESTAMP(0) WITHOUT TIME ZONE
 *   - DEFAULT CHARACTER SET …   → убрано (Postgres по умолчанию UTF-8)
 *   - "user" quoted — это зарезервированное слово в Postgres
 *
 * The messenger_messages table is created automatically by
 * `bin/console messenger:setup-transports` (called from CMD in the Dockerfile),
 * so it's intentionally omitted here.
 */
final class Version20260823000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial Postgres schema: user table with all current columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (
                id SERIAL NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL,
                previous_status VARCHAR(20) DEFAULT NULL,
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                email_verification_token VARCHAR(128) DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_USER_LAST_LOGIN ON "user" (last_login_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "user"');
    }
}
