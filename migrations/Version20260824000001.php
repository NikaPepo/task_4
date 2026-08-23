<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMPORTANT: это миграция-фикс. На Render осталась старая таблица "user"
 * без колонки id (от предыдущих сломанных деплоев). migrations_versions
 * помечает Version20260823000001 как выполненную, но реально таблица
 * битая. Эта миграция дропнет её и пересоздаст с правильной схемой.
 *
 * Безопасно: таблица пустая (регистрация не работала, ничего не
 * записывалось).
 */
final class Version20260824000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop and recreate broken user table (id column missing).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "user" CASCADE');

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
