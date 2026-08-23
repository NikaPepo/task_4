<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: user table + messenger_messages table for MySQL.
 *
 * NOTE: user is a reserved keyword in MySQL too, so we use backticks.
 * messenger_messages is auto-created by `messenger:setup-transports`
 * but we include it here so `migrate` creates the whole schema.
 */
final class Version20260823000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: user table with full auth fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (
                id INT AUTO_INCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL,
                previous_status VARCHAR(20) DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                email_verification_token VARCHAR(128) DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON `user` (email)');
        $this->addSql('CREATE INDEX IDX_USER_LAST_LOGIN ON `user` (last_login_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `user`');
    }
}
