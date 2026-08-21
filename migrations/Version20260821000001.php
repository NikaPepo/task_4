<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMPORTANT: Adds e-mail verification token column and a supporting
 * index on last_login_at (used by the default ORDER BY on the admin
 * user-list page).
 *
 * NOTE: The UNIQ_IDENTIFIER_EMAIL index from migration
 * {@see Version20260820152947} is intentionally left untouched — it is
 * the source of truth for e-mail uniqueness at the database level.
 */
final class Version20260821000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_verification_token column and last_login_at index to user table.';
    }

    public function up(Schema $schema): void
    {
        // NOTE: additive change — no destructive ops, safe to apply.
        $this->addSql('ALTER TABLE user ADD email_verification_token VARCHAR(128) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_LAST_LOGIN ON user (last_login_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_USER_LAST_LOGIN ON user');
        $this->addSql('ALTER TABLE user DROP email_verification_token');
    }
}